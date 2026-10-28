<?php
declare(strict_types=1);
namespace App\Voids;

use App\Models\VoidBatch;
use App\Notes\Original;
use Illuminate\Support\Facades\{DB, Event, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class Preparation
{
    public function __construct(private Source $source, private Window $window, private Xml $xml, private Evidence $files) {}

    public function freeze(int $user, array $input): VoidBatch
    {
        if (config('h5c1.mode')!=='fictitious' || DB::transactionLevel()!==0) { throw new \LogicException('Solo baja ficticia sin transaccion externa.'); }
        if (array_diff(array_keys($input),['idempotency_key','members','reason'])) { throw ValidationException::withMessages(['batch'=>'Solo identidad, motivo y miembros explicitos.']); }
        $v=Validator::make($input,['idempotency_key'=>'required|uuid','members'=>'required|array|min:1|max:100',
            'members.*'=>'required|array:kind,id','members.*.kind'=>'required|in:invoice,invoice_note,boleta,boleta_note',
            'members.*.id'=>'required|uuid','reason'=>['required','string','min:3','max:100','not_regex:/[\x00-\x1F]|\]\]>/u']])->validate();
        $v['idempotency_key']=strtolower($v['idempotency_key']);
        $v['members']=array_map(fn($m)=>['kind'=>$m['kind'],'id'=>strtolower($m['id'])],$v['members']);
        usort($v['members'],fn($a,$b)=>strcmp($a['kind'].':'.$a['id'],$b['kind'].':'.$b['id']));
        if (count(array_unique(array_map(fn($m)=>$m['kind'].':'.$m['id'],$v['members'])))!==count($v['members'])) {
            throw ValidationException::withMessages(['members'=>'Documento repetido.']);
        }
        $hash=Original::hash(['members'=>$v['members'],'reason'=>$v['reason']]); $id=null; $written=false; $committing=false;
        try {
            return DB::transaction(function() use($user,$v,$hash,&$id,&$written,&$committing){
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h5c1-user:'.$user]);
                $prior=VoidBatch::query()->where('user_id',$user)->where('idempotency_key',$v['idempotency_key'])->first();
                if ($prior) {
                    if ($prior->payload_hash!==$hash) { throw new ConflictHttpException('Clave de baja reutilizada con otro contenido.'); }
                    $this->verify($prior); return $prior;
                }
                $members=[];
                foreach($v['members'] as $key){
                    $this->source->document($user,$key['kind'],$key['id'],true);
                    $this->source->unreserved($key['kind'],$key['id']); $this->source->noDependants($key['kind'],$key['id']);
                    $grant=DB::table('h5c1_grants')->where('kind',$key['kind'])->where('document_id',$key['id'])->orderByDesc('sequence')->first();
                    if (!$grant || $grant->state!=='not_granted' || $grant->user_id!==$user) { throw new ConflictHttpException('No otorgamiento desconocido o incompatible; baja bloqueada.'); }
                    $member=$this->source->capture($user,$key['kind'],$key['id']);
                    if (Original::hash($member)!==Original::hash(json_decode($grant->proof,true,flags:JSON_THROW_ON_ERROR))) { throw new ConflictHttpException('Prueba de no otorgamiento ya no corresponde al documento aceptado.'); }
                    $this->window->check($member);
                    $members[]=$member+['grant_id'=>$grant->id,'grant_hash'=>Original::hash((array)$grant)];
                }
                $routes=array_unique(array_map(fn($m)=>str_starts_with($m['series'],'F')?'RA':'RC',$members));
                if (count($routes)!==1 || count(array_unique(array_column($members,'emission_date')))!==1) { throw ValidationException::withMessages(['members'=>'Misma ruta y fecha de emision requeridas.']); }
                $route=reset($routes); $date=$members[0]['emission_date'];
                if ($route==='RA' && array_filter($members,fn($m)=>$m['generation_date']!==$date)) { throw ValidationException::withMessages(['members'=>'RA conservadora exige generacion y emision coincidentes acreditadas.']); }
                $table=$route==='RC'?'h4_counters':'h5c1_counters'; $column=$route==='RC'?'series':'route';
                $counter=DB::table($table)->where($column,$route)->lockForUpdate()->sole();
                if ($counter->next_number>99999) { throw new ConflictHttpException('Identidad de comunicacion agotada; revision requerida.'); }
                $today=now('America/Lima')->toDateString(); $id=(string)Str::uuid();
                $snapshot=['mode'=>'fictitious','route'=>$route,'operation'=>$route==='RC'?'3':null,'reference_date'=>$date,
                    'issue_date'=>$today,'number'=>(int)$counter->next_number,'name'=>'00000000000-'.$route.'-'.str_replace('-','',$today).'-'.$counter->next_number,
                    'reason'=>$v['reason'],'policy'=>'SEE-CONTRIB-2026-CDR7-INTERSECTION-EMISSION7','members'=>$members];
                $manifest=$this->files->save($id,$snapshot['name'],$this->xml->render($snapshot)); $written=true;
                $batch=VoidBatch::query()->create(['id'=>$id,'user_id'=>$user,'idempotency_key'=>$v['idempotency_key'],'payload_hash'=>$hash,
                    'route'=>$route,'name'=>$snapshot['name'],'reference_date'=>$date,'issue_date'=>$today,'snapshot'=>$snapshot,'artifacts'=>$manifest,'state'=>'pending_send']);
                foreach($members as $position=>$m){ DB::table('h5c1_members')->insert(['batch_id'=>$id,'kind'=>$m['kind'],
                    'document_id'=>$m['id'],'grant_id'=>$m['grant_id'],'position'=>$position+1]); }
                DB::table('h5c1_outbox')->insert(['batch_id'=>$id,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
                DB::table($table)->where($column,$route)->update(['next_number'=>$counter->next_number+1]);
                Event::dispatch('h5c1.prepared',[$id]); $committing=true; return $batch;
            });
        } catch(Throwable $e){ if($id && $written && !$committing){$this->files->discardOwn($id);} throw $e; }
    }

    public function verify(VoidBatch $batch): void
    {
        $this->files->verify($batch->id,$batch->artifacts);
        $actual=DB::table('h5c1_members')->where('batch_id',\App\Corrections\Family::anchor('void',$batch->id))->orderBy('position')->get();
        if ($actual->count()!==count($batch->snapshot['members'])) { throw new \LogicException('Membresia de baja alterada.'); }
        foreach($batch->snapshot['members'] as $i=>$m){
            $row=$actual[$i]; $grant=DB::table('h5c1_grants')->where('id',$m['grant_id'])->sole();
            $source=$this->source->capture($batch->user_id,$m['kind'],$m['id']);
            if ($row->kind!==$m['kind'] || $row->document_id!==$m['id'] || $row->grant_id!==$m['grant_id']
                || $grant->state!=='not_granted' || $grant->user_id!==$batch->user_id
                || Original::hash((array)$grant)!==$m['grant_hash']
                || Original::hash($source)!==Original::hash(array_diff_key($m,['grant_id'=>true,'grant_hash'=>true]))) { throw new \LogicException('Fuente, otorgamiento o correlacion de baja alterados.'); }
            $this->source->noDependants($m['kind'],$m['id']);
        }
    }
}
