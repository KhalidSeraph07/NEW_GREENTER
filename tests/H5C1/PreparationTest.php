<?php
namespace Tests\H5C1;

use App\Voids\Grant;
use App\Voids\Preparation;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class PreparationTest extends VoidCase
{
    public function test_unknown_grant_blocks_void_without_creating_a_batch(): void
    {
        $i=$this->issue(); app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id);
        $this->expectException(ConflictHttpException::class);
        app(Preparation::class)->freeze($this->operator->id,['idempotency_key'=>(string) Str::uuid(),
            'reason'=>'BAJA FICTICIA NO OTORGADA','members'=>[['kind'=>'invoice','id'=>$i->id]]]);
    }
    public function test_explicit_not_granted_invoice_can_be_frozen_for_ra_once(): void
    {
        $i=$this->issue(); app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id);
        app(Grant::class)->record($this->operator->id,['kind'=>'invoice','id'=>$i->id,'state'=>'not_granted',
            'provenance'=>'AFIRMACION DE ENSAYO NUEVO, NO OTORGADO','idempotency_key'=>(string) Str::uuid()]);
        $input=['idempotency_key'=>(string) Str::uuid(),'reason'=>'BAJA FICTICIA NO OTORGADA','members'=>[['kind'=>'invoice','id'=>$i->id]]];
        $batch=app(Preparation::class)->freeze($this->operator->id,$input);
        $this->assertSame('RA',$batch->route); $this->assertSame('pending_send',$batch->state);
        $this->assertSame($batch->id,app(Preparation::class)->freeze($this->operator->id,$input)->id);
    }
}
