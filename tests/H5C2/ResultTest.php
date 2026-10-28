<?php
namespace Tests\H5C2;
use App\Corrections\{Family,Policy};
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
final class ResultTest extends CaseBase
{
    public static function families(): array {return PreparationTest::families();}
    #[DataProvider('families')]
    public function test_reader_refuses_terminal_state_without_receipt_even_if_storage_once_allowed_it(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$before=$n->getRawOriginal();
        if($f==='invoice'){$n->transport_status='simulated_accepted';}else{$n->state='sim_accepted';}
        try{app(Policy::class)->verifyLinked($f,$n);$this->fail('Aceptacion sin resultado.');}
        catch(ConflictHttpException){$this->assertSame($before,$n->fresh()->getRawOriginal());}
    }
    #[DataProvider('families')]
    public function test_terminal_receipt_is_revalidated_without_overwriting_history(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);
        if($f==='invoice'){app(\App\Invoices\SimulatedTransport::class)->dispatch($n->id);}
        else{$this->transport($f)->send($n->id);$this->transport($f)->poll($n->id);}
        $n=$n->fresh();app(Policy::class)->verifyLinked($f,$n);$before=$n->getRawOriginal();
        if($f==='invoice'){$v=$n->simulated_response;$v['invoice_id']=$r->id;$n->simulated_response=$v;}
        else{$v=$n->result;$v['response']['ticket']='SIM-AJENO';$n->result=$v;}
        try{app(Policy::class)->verifyLinked($f,$n);$this->fail('Resultado ajeno.');}
        catch(ConflictHttpException){$this->assertSame($before,$n->fresh()->getRawOriginal());}
    }
}
