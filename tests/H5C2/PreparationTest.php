<?php
namespace Tests\H5C2;
use Tests\TestCase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
final class PreparationTest extends CaseBase
{
    public function test_generic_historical_rejection_does_not_authorize_reissue(): void
    {
        $i=$this->issue();app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id,'rejected');
        $this->expectException(ConflictHttpException::class);
        app(\App\Corrections\InvoiceReissue::class)->prepare($this->operator->id,['original_id'=>$i->id,'idempotency_key'=>(string)Str::uuid(),
            'reason'=>'REEMISION FICTICIA CONTROLADA','operation_date_test'=>now()->toDateString(),'operation_subsists_test'=>'1']);
    }
    public static function families(): array {return [['invoice'],['h4'],['h5b'],['void']];}
    #[\PHPUnit\Framework\Attributes\DataProvider('families')]
    public function test_concrete_defect_produces_one_repaired_successor_with_frozen_parent(string $f): void
    {
        $r=$this->root($f);$before=$r->getRawOriginal();$this->assertSame($f==='invoice'?'simulated_rejected':'sim_rejected',$f==='invoice'?$r->transport_status:$r->state);
        $n=$this->child($f,$r);$this->assertNotSame($r->id,$n->id);$this->assertSame($before,$r->fresh()->getRawOriginal());
        \App\Corrections\PackingDefect::assertRepaired($f,$n);
        if($f==='invoice'){$this->assertSame('simulated_accepted',app(\App\Invoices\SimulatedTransport::class)->dispatch($n->id)->transport_status);}
        else{$t=$this->transport($f);$t->send($n->id);$this->assertSame('sim_accepted',$t->poll($n->id)->state);}
    }
}
