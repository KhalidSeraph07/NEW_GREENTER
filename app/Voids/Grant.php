<?php
declare(strict_types=1);
namespace App\Voids;

use App\Notes\Original;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Grant
{
    public function __construct(private Source $source) {}

    // Se invoca bajo el lock del original, después del replay idempotente de NC/ND.
    public function assertNoteAllowed(string $kind, string $id): void
    {
        $this->source->unreserved($kind,$id);
        if (DB::table('h5c1_grants')->where('kind',$kind)->where('document_id',$id)->orderByDesc('sequence')->value('state')==='not_granted') {
            throw new ConflictHttpException('El original tiene no otorgamiento acreditado; registrar otorgamiento antes de crear una nota.');
        }
    }

    public function record(int $user, array $input): object
    {
        if (config('h5c1.mode')!=='fictitious' || DB::transactionLevel()!==0) { throw new \LogicException('Solo afirmaciones ficticias sin transaccion externa.'); }
        if (array_diff(array_keys($input),['idempotency_key','kind','id','state','provenance'])) { throw ValidationException::withMessages(['grant'=>'Campos de otorgamiento no permitidos.']); }
        $v=Validator::make($input,['idempotency_key'=>'required|uuid','kind'=>'required|in:invoice,invoice_note,boleta,boleta_note',
            'id'=>'required|uuid','state'=>'required|in:granted,not_granted','provenance'=>['required','string','min:15','max:500','not_regex:/[\x00-\x1F]/u']])->validate();
        $v['id']=strtolower($v['id']); $v['idempotency_key']=strtolower($v['idempotency_key']);
        $hash=Original::hash(array_diff_key($v,['idempotency_key'=>true]));
        return DB::transaction(function () use ($user,$v,$hash) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h5c1-user:'.$user]);
            $this->source->document($user,$v['kind'],$v['id'],true);
            $prior=DB::table('h5c1_grants')->where('user_id',$user)->where('idempotency_key',$v['idempotency_key'])->first();
            if ($prior) {
                if ($prior->payload_hash!==$hash) { throw new ConflictHttpException('Clave de otorgamiento reutilizada con otros hechos.'); }
                return $prior;
            }
            $this->source->unreserved($v['kind'],$v['id']);
            $proof=$this->source->capture($user,$v['kind'],$v['id']);
            if ($v['state']==='not_granted') {
                $this->source->noDependants($v['kind'],$v['id']);
                if (DB::table('h5c1_grants')->where('kind',$v['kind'])->where('document_id',$v['id'])->where('state','granted')->exists()) {
                    throw new ConflictHttpException('Un otorgamiento acreditado no se revierte mediante una declaracion posterior.');
                }
            }
            $id=(string)Str::uuid();
            DB::table('h5c1_grants')->insert(['id'=>$id,'user_id'=>$user,'kind'=>$v['kind'],'document_id'=>$v['id'],
                'idempotency_key'=>$v['idempotency_key'],'payload_hash'=>$hash,'state'=>$v['state'],'provenance'=>$v['provenance'],
                'proof'=>json_encode($proof,JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
            return DB::table('h5c1_grants')->where('id',$id)->sole();
        });
    }
}
