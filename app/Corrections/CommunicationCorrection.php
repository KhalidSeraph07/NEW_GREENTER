<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Models\{Boleta, BoletaNote};
use App\Notes\Original;
use Illuminate\Support\Facades\{DB,Event,Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class CommunicationCorrection
{
    public function prepare(string $family,int $user,array $input): mixed
    {
        Family::guard();if(DB::transactionLevel()!==0){throw new \LogicException('C2 sin transaccion externa.');}
        if(!in_array($family,['h4','h5b','void'],true)||array_diff(array_keys($input),['idempotency_key','original_id','reason'])){
            throw ValidationException::withMessages(['correction'=>'Solo familia, original, motivo y clave C2.']);}
        $v=Validator::make($input,['idempotency_key'=>'required|uuid','original_id'=>'required|uuid',
            'reason'=>['required','string','min:10','max:250','not_regex:/[\x00-\x1F]/u']])->validate();
        $v['idempotency_key']=strtolower($v['idempotency_key']);$v['original_id']=strtolower($v['original_id']);
        $hash=Original::hash(['family'=>$family,'original_id'=>$v['original_id'],'reason'=>$v['reason'],'defect'=>'SIM-C2-ZIP-ENTRY']);
        $id=null;$written=false;$committing=false;
        try{$result=DB::transaction(function()use($family,$user,$v,$hash,&$id,&$written,&$committing){
            if($family==='h4'){DB::table('h4_counters')->where('series','B000')->lockForUpdate()->sole();}
            elseif($family==='h5b'){DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h5b-rc:'.$user.':'.$v['idempotency_key']]);}
            else{DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h5c1-user:'.$user]);}
            $parent=Family::record($family,$user,$v['original_id'],true);
            $table=Family::LINKS[$family];$model=Family::MODELS[$family];
            $prior=$model::query()->where('user_id',$user)->where('idempotency_key',$v['idempotency_key'])->first();
            if($prior){
                $link=DB::table($table)->where('successor_id',$prior->id)->first();
                if(!$link || $link->original_id!==$parent->id || $link->request_hash!==$hash){throw new ConflictHttpException('Clave C2 reutilizada o perteneciente a otro flujo.');}
                app(Policy::class)->verifyLinked($family,$prior);return $prior;
            }
            if(DB::table($table)->where('original_id',$parent->id)->orWhere('successor_id',$parent->id)->exists()){
                throw new ConflictHttpException('Una unica sucesora; sin cadenas ni liberacion de reservas.');}
            $proof=app(Rejection::class)->capture($family,$parent);$this->sources($family,$parent);
            app(Policy::class)->window($family,$parent);
            $route=$family==='void'?$parent->route:'RC';$counterTable=$route==='RC'?'h4_counters':'h5c1_counters';$col=$route==='RC'?'series':'route';
            $counter=DB::table($counterTable)->where($col,$route)->lockForUpdate()->sole();
            if($counter->next_number>99999){throw new ConflictHttpException('Correlativo de ensayo agotado.');}
            $id=(string)Str::uuid();$today=now('America/Lima')->toDateString();
            $snapshot=array_replace($parent->snapshot,['number'=>(int)$counter->next_number,'issue_date'=>$today,
                'name'=>'00000000000-'.$route.'-'.str_replace('-','',$today).'-'.$counter->next_number]);
            $contents=match($family){'h4'=>app(\App\Boletas\FictitiousXml::class)->summary($snapshot),
                'h5b'=>app(\App\BoletaNotes\Xml::class)->summary($snapshot),'void'=>app(\App\Voids\Xml::class)->render($snapshot)};
            $files=match($family){'h4'=>app(\App\Boletas\Artifacts::class),'h5b'=>app(\App\BoletaNotes\Evidence::class),'void'=>app(\App\Voids\Evidence::class)};
            $manifest=$files->save($id,$snapshot['name'],$contents);$written=true;
            $fields=['id'=>$id,'user_id'=>$user,'idempotency_key'=>$v['idempotency_key'],'payload_hash'=>$hash,'name'=>$snapshot['name'],
                'reference_date'=>$parent->reference_date,'issue_date'=>$today,'snapshot'=>$snapshot,'artifacts'=>$manifest,'state'=>'pending_send'];
            if($family==='void'){$fields['route']=$route;}
            $child=$model::query()->create($fields);PackingDefect::assertRepaired($family,$child);
            DB::table(Family::OUTBOX[$family])->insert([Family::COLUMN[$family]=>$id,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
            DB::table($table)->insert(['original_id'=>$parent->id,'successor_id'=>$id,'user_id'=>$user,'idempotency_key'=>$v['idempotency_key'],
                'request_hash'=>$hash,'reason'=>$v['reason'],'prepared_on'=>$today,'proof'=>json_encode($proof,JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
            DB::table($counterTable)->where($col,$route)->update(['next_number'=>$counter->next_number+1]);
            Event::dispatch('h5c2.prepared',[$family,$id]);$committing=true;return $child;
        });Event::dispatch('h5c2.committed',[$family,$result->id]);return $result;}catch(Throwable $e){
            if($id && $written && !$committing){match($family){'h4'=>app(\App\Boletas\Artifacts::class)->discardOwn($id),
                'h5b'=>app(\App\BoletaNotes\Evidence::class)->discardOwn($id),'void'=>app(\App\Voids\Evidence::class)->discardOwn($id)};}throw $e;
        }
    }
    public function sources(string $family,mixed $r): void
    {
        if($family==='void'){app(\App\Voids\Preparation::class)->verify($r);return;}
        $anchor=Family::anchor($family,$r->id);$table=$family==='h4'?'h4_members':'h5b_members';$col=$family==='h4'?'boleta_id':'note_id';
        $ids=DB::table($table)->where('summary_id',$anchor)->orderBy('position')->pluck($col)->all();
        if($ids!==array_column($r->snapshot['members'],'id')){throw new ConflictHttpException('Conjunto raiz alterado.');}
        foreach($r->snapshot['members'] as $m){
            if($family==='h4'){
                $b=Boleta::query()->where('user_id',$r->user_id)->findOrFail($m['id']);app(\App\Boletas\Artifacts::class)->verify($b->id,$b->artifacts);
                $actual=['id'=>$b->id,'number'=>$b->number,'emission_date'=>$b->emission_date,'subtotal_cents'=>$b->snapshot['subtotal_cents'],
                    'tax_cents'=>$b->snapshot['tax_cents'],'total_cents'=>$b->snapshot['total_cents']];
            }else{
                $n=BoletaNote::query()->where('user_id',$r->user_id)->findOrFail($m['id']);app(\App\BoletaNotes\SummaryPreparation::class)->verifyNote($n);
                $actual=app(\App\BoletaNotes\SummaryPreparation::class)->member($n);
            }
            if(Original::hash($actual)!==Original::hash($m)){throw new ConflictHttpException('Miembro fiscal cambiado.');}
        }
    }
}
