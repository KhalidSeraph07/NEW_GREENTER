<?php
namespace Tests\H5C1;
use App\Voids\{Grant, Preparation};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class VoidCase extends TestCase
{
    protected function setUp(): void { parent::setUp(); Carbon::setTestNow(Carbon::parse('2026-10-26T12:00:00-05:00')); }
    protected function accepted(string $kind='invoice', string $op='NC01'): array
    {
        if(str_starts_with($kind,'invoice')){
            $r=$this->issue();app(\App\Invoices\SimulatedTransport::class)->dispatch($r->id);
            if($kind==='invoice_note'){
                $r=app(\App\Notes\Preparation::class)->issue($this->operator->id,$this->noteInput($r->id,$op));
                app(\App\Notes\Transport::class)->dispatch($r->id);
            }
        }else{
            $r=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now('America/Lima')->toDateString()]));
            $rc=app(\App\Boletas\Preparation::class)->freeze($this->operator->id,['members'=>[$r->id],'idempotency_key'=>(string)Str::uuid()]);
            app(\App\Boletas\SummaryTransport::class)->send($rc->id);app(\App\Boletas\SummaryTransport::class)->poll($rc->id);
            if($kind==='boleta_note'){
                $r=app(\App\BoletaNotes\Preparation::class)->issue($this->operator->id,$this->noteInput($r->id,$op));
                $rc=app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id,['members'=>[$r->id],'idempotency_key'=>(string)Str::uuid()]);
                app(\App\BoletaNotes\SummaryTransport::class)->send($rc->id);app(\App\BoletaNotes\SummaryTransport::class)->poll($rc->id);
            }
        }
        return ['kind'=>$kind,'id'=>$r->id];
    }
    protected function noteInput(string $id,string $op='NC01'): array
    {
        return ['original_id'=>$id,'idempotency_key'=>(string)Str::uuid(),'operation'=>$op,'reason'=>'MOTIVO FICTICIO JUSTIFICADO',
            'emission_date'=>now('America/Lima')->toDateString(),'original_granted_test'=>'1']+($op==='ND02'?['increment_unit_price'=>'10.00']:[]);
    }
    protected function grant(array $key,string $state='not_granted',?string $request=null): object
    {
        return app(Grant::class)->record($this->operator->id,$key+['state'=>$state,'provenance'=>'AFIRMACION EXPLICITA DE ENSAYO H5-C1 SIN ENTREGA REAL',
            'idempotency_key'=>$request ?? (string)Str::uuid()]);
    }
    protected function batchInput(array $keys): array { return ['idempotency_key'=>(string)Str::uuid(),'members'=>$keys,'reason'=>'NO OTORGADO - BAJA FICTICIA']; }
    protected function batch(string $kind='invoice') { $k=$this->accepted($kind);$this->grant($k);return app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$k])); }
    protected function job(string $id): object { return DB::table('h5c1_outbox')->where('batch_id',$id)->sole(); }
}
