<?php
namespace Tests\H5C2;
use App\Corrections\{Fixture,Family,CommunicationCorrection,InvoiceReissue};
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
abstract class CaseBase extends \Tests\H5C1\VoidCase
{
    protected function setUp(): void {parent::setUp();Carbon::setTestNow(Carbon::parse('2026-10-28T12:00:00-05:00'));}
    protected function root(string $family='h4',string $source='invoice',string $op='NC01'): mixed
    {
        if($family==='invoice'){$r=app(Fixture::class)->prepare($family,$this->operator->id,$this->input());app(\App\Invoices\SimulatedTransport::class)->dispatch($r->id,'rejected_c2_zip');return $r->fresh();}
        if($family==='h4'){
            $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now()->toDateString()]));
            $input=['idempotency_key'=>(string)Str::uuid(),'members'=>[$b->id]];
        }elseif($family==='h5b'){
            $b=$this->accepted('boleta');$n=app(\App\BoletaNotes\Preparation::class)->issue($this->operator->id,$this->noteInput($b['id'],$op));
            $input=['idempotency_key'=>(string)Str::uuid(),'members'=>[$n->id]];
        }else{$k=$this->accepted($source,$op);$this->grant($k);$input=$this->batchInput([$k]);}
        $r=app(Fixture::class)->prepare($family,$this->operator->id,$input);
        $t=$this->transport($family);$t->send($r->id);$t->poll($r->id,'99_c2_zip');return $r->fresh();
    }
    protected function transport(string $f): mixed {return app(match($f){'h4'=>\App\Boletas\SummaryTransport::class,'h5b'=>\App\BoletaNotes\SummaryTransport::class,'void'=>\App\Voids\Transport::class});}
    protected function correctionInput(mixed $r,array $changes=[]): array {return $changes+['original_id'=>$r->id,'idempotency_key'=>(string)Str::uuid(),'reason'=>'SUBSANAR ENTRADA DE ZIP EXCLUSIVAMENTE FICTICIO'];}
    protected function reissueInput(mixed $r,array $changes=[]): array {return $changes+$this->correctionInput($r)+['operation_date_test'=>Carbon::parse($r->snapshot['emitted_at'])->timezone('America/Lima')->toDateString(),'operation_subsists_test'=>'1'];}
    protected function child(string $f,mixed $r,?array $input=null): mixed {return $f==='invoice'?app(InvoiceReissue::class)->prepare($this->operator->id,$input??$this->reissueInput($r)):app(CommunicationCorrection::class)->prepare($f,$this->operator->id,$input??$this->correctionInput($r));}
}
