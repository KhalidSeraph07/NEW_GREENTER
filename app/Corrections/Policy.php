<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Notes\Original;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Policy
{
    public function window(string $family,mixed $parent,?string $operationDate=null): void
    {
        $today=now('America/Lima')->toDateString();
        if($today<'2026-01-01'||$today>='2027-01-01'){throw ValidationException::withMessages(['window'=>'Ensayo C2 2026; revisar vigencia antes de preparar/enviar.']);}
        if($family==='void'){foreach($parent->snapshot['members'] as $m){app(\App\Voids\Window::class)->check($m);}return;}
        $date=$family==='invoice'?Carbon::parse($parent->snapshot['emitted_at'])->timezone('America/Lima')->toDateString():$parent->reference_date;
        if($family==='invoice' && $operationDate!==$date){throw ValidationException::withMessages(['operation_date_test'=>'Recorte C2: fecha de operacion ficticia explicita igual a emision original.']);}
        if($date>$today || $today>Carbon::parse($date)->addDays($family==='invoice'?3:7)->toDateString()){
            throw ValidationException::withMessages(['window'=>'Ventana original del ensayo vencida; una sucesora no la reinicia.']);}
    }
    public function verifyLinked(string $family,mixed $record): void
    {
        $link=DB::table(Family::LINKS[$family])->where('successor_id',$record->id)->first();
        if(!$link){return;}
        $parent=Family::record($family,$record->user_id,$link->original_id);
        $proof=app(Rejection::class)->capture($family,$parent);
        if(Original::hash($proof)!==Original::hash(json_decode($link->proof,true,flags:JSON_THROW_ON_ERROR))){throw new ConflictHttpException('Prueba causal C2 alterada.');}
        PackingDefect::assertRepaired($family,$record);
        if($family!=='invoice'){app(CommunicationCorrection::class)->sources($family,$record);}
        $this->terminalResult($family,$record);
    }
    public function beforeSend(string $family,mixed $record): void
    {
        $link=DB::table(Family::LINKS[$family])->where('successor_id',$record->id)->first();if(!$link){return;}
        $this->verifyLinked($family,$record);$this->window($family,Family::record($family,$record->user_id,$link->original_id),$link->operation_date);
    }
    public function queryExhausted(string $family,string $id,int $attempts): bool
    {
        return $attempts>=9 && DB::table(Family::LINKS[$family])->where('successor_id',$id)->exists();
    }
    private function terminalResult(string $family,mixed $r): void
    {
        $state=$family==='invoice'?$r->transport_status:$r->state;
        if(!in_array($state,['sim_accepted','sim_rejected','simulated_accepted','simulated_rejected'],true)){return;}
        $accepted=in_array($state,['sim_accepted','simulated_accepted'],true);
        $job=DB::table(Family::OUTBOX[$family])->where(Family::COLUMN[$family],$r->id)->sole();
        if($family==='invoice'){
            $expected=app(\App\Invoices\LocalSimulation::class)->response($r,$accepted?'accepted':'rejected');$actual=$r->simulated_response;
            $receipt=DB::table('simulation_receipts')->where('invoice_id',$r->id)->first();
            $event=DB::table('invoice_transport_events')->where('invoice_id',$r->id)->whereIn('kind',['result','recovered'])->orderBy('id')->first();
            $valid=$receipt && Original::hash(json_decode($receipt->response,true,flags:JSON_THROW_ON_ERROR))===Original::hash($expected)
                && $event && $event->attempt_token===$job->attempt_token;
        }else{
            if(!$r->ticket){throw new ConflictHttpException('Terminal C2 sin ticket conservado.');}
            $response=match($family){'h4'=>app(\App\Boletas\LocalSummarySimulator::class)->poll($r->ticket,$accepted?'0':'99'),
                'h5b'=>app(\App\BoletaNotes\LocalSummarySimulator::class)->poll($r->ticket,$accepted?'0':'99'),
                'void'=>app(\App\Voids\LocalSimulator::class)->poll($r->ticket,$accepted?'0':'99')};
            $expected=['simulated'=>true,'official_cdr'=>false,'outcome'=>$accepted?'accepted':'rejected','response'=>$response];$actual=$r->result;
            $prefix=$family==='void'?'h5c1':$family;$col=Family::COLUMN[$family];
            $receipt=DB::table($prefix.'_simulation_receipts')->where($col,$r->id)->first();
            $event=DB::table($prefix.'_events')->where($col,$r->id)->where('kind','result_saved')->orderBy('id')->first();
            $valid=$receipt && $receipt->ticket===$r->ticket && $receipt->request_hash===$r->artifacts['zip']['sha256']
                && $job->phase==='poll' && $event && $event->token===$job->token
                && Original::hash(json_decode($event->payload,true,flags:JSON_THROW_ON_ERROR))===Original::hash($response);
        }
        if(!$valid || $job->status!=='completed' || (bool)$event->attention===$accepted || Original::hash($actual)!==Original::hash($expected)){
            throw new ConflictHttpException('Estado terminal C2 sin resultado completo y correlacionado; conservar e investigar.');
        }
    }
}
