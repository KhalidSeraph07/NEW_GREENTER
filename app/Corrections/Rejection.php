<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Notes\Original;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Rejection
{
    public function capture(string $family,mixed $r): array
    {
        Family::guard();Family::verifyFiles($family,$r);
        $col=Family::COLUMN[$family];$job=DB::table(Family::OUTBOX[$family])->where($col,$r->id)->first();
        if(!$job || $job->status!=='completed'){ $this->fail(); }
        if($family==='invoice'){
            $response=$r->simulated_response;
            if(!isset($response['technical_rejection'])){$this->fail();}
            if($r->transport_status!=='simulated_rejected' || !is_array($response)){ $this->fail(); }
            $cause=PackingDefect::cause($family,$r);
            $expected=app(\App\Invoices\LocalSimulation::class)->response($r,'rejected')+['technical_rejection'=>$cause];
            $receipt=DB::table('simulation_receipts')->where('invoice_id',$r->id)->first();
            $event=DB::table('invoice_transport_events')->where('invoice_id',$r->id)->whereIn('kind',['result','recovered'])->orderBy('id')->first();
            if(!$receipt || !$event || !$event->attention || $event->attempt_token!==$job->attempt_token
                || Original::hash(json_decode($receipt->response,true,flags:JSON_THROW_ON_ERROR))!==Original::hash($response)){$this->fail();}
            if(DB::table('h5_notes')->where('original_id',$r->id)->exists()
                || DB::table('h5c1_members')->where('kind','invoice')->where('document_id',$r->id)->exists()
                || DB::table('h5c1_grants')->where('kind','invoice')->where('document_id',$r->id)->exists()){$this->fail();}
        }else{
            if($r->state!=='sim_rejected' || !$r->ticket || $job->phase!=='poll'
                || ($r->result['simulated']??null)!==true || ($r->result['official_cdr']??null)!==false
                || ($r->result['outcome']??null)!=='rejected'){$this->fail();}
            $response=$r->result['response']??[];
            if(!isset($response['technical_rejection'])){$this->fail();}
            $expected=match($family){'h4'=>app(\App\Boletas\LocalSummarySimulator::class)->poll($r->ticket,'99_c2_zip'),
                'h5b'=>app(\App\BoletaNotes\LocalSummarySimulator::class)->poll($r->ticket,'99_c2_zip'),
                'void'=>app(\App\Voids\LocalSimulator::class)->poll($r->ticket,'99_c2_zip')};
            $prefix=match($family){'h4'=>'h4','h5b'=>'h5b','void'=>'h5c1'};
            $receipt=DB::table($prefix.'_simulation_receipts')->where($col,$r->id)->first();
            $event=DB::table($prefix.'_events')->where($col,$r->id)->where('kind','result_saved')->orderBy('id')->first();
            if(!$receipt || $receipt->ticket!==$r->ticket || $receipt->request_hash!==$r->artifacts['zip']['sha256']
                || !$event || !$event->attention || $event->token!==$job->token
                || Original::hash(json_decode($event->payload,true,flags:JSON_THROW_ON_ERROR))!==Original::hash($response)){$this->fail();}
        }
        if(Original::hash($response)!==Original::hash($expected)){$this->fail();}
        return ['family'=>$family,'document_id'=>$r->id,'response'=>$response,'artifacts'=>$r->artifacts,
            'snapshot'=>$r->snapshot,'cause'=>$response['technical_rejection'],'event_id'=>$event->id,
            'received_at'=>(string)$event->created_at,'event_hash'=>Original::hash((array)$event)];
    }
    private function fail(): never {throw new ConflictHttpException('Rechazo definitivo con causa ZIP subsanable no acreditado; no reconstruir historicos.');}
}
