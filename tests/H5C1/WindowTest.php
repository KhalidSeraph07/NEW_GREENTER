<?php
namespace Tests\H5C1;
use App\Voids\{Source, Preparation, Transport};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class WindowTest extends VoidCase
{
    public function test_emission_generation_and_cdr_dates_are_observed_fixture_events_not_updated_at(): void
    {
        $i=$this->issue();$this->travel(2)->days();app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id);
        $key=['kind'=>'invoice','id'=>$i->id];$this->grant($key);$source=app(Source::class)->capture($this->operator->id,'invoice',$i->id);
        $this->assertSame('2026-10-26',$source['emission_date']);$this->assertSame('2026-10-26',$source['generation_date']);
        $this->assertSame('2026-10-28',Carbon::parse($source['acceptance']['received_at'])->timezone('America/Lima')->toDateString());
        $this->travel(6)->days();DB::table('invoices')->where('id',$i->id)->update(['updated_at'=>now('UTC')]);
        $this->assertSame($source['acceptance'],app(Source::class)->capture($this->operator->id,'invoice',$i->id)['acceptance']);
        try{app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$key]));$this->fail();}
        catch(ValidationException $e){$this->assertStringContainsString('Bloqueo tecnico',json_encode($e->errors()));}
    }
    public function test_day_seven_inclusive_day_eight_blocks_from_accredited_reception(): void
    {
        $a=$this->accepted();$b=$this->accepted();$this->grant($a);$this->grant($b);$this->travel(7)->days();
        $v=app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$a]));$this->assertSame('awaiting',app(Transport::class)->send($v->id)->state);
        $this->travel(1)->days();
        try{app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$b]));$this->fail();}
        catch(ValidationException $e){$this->assertStringContainsString('recepcion simulada',json_encode($e->errors()));}
        $this->assertSame('sim_accepted',app(Transport::class)->poll($v->id)->state);
    }
    public function test_missing_acceptance_event_is_not_reconstructed_from_state_or_update_time(): void
    {
        $k=$this->accepted();DB::table('invoice_transport_events')->where('invoice_id',$k['id'])->where('kind','result')->delete(); // own fixture only
        $this->expectException(ConflictHttpException::class);$this->grant($k);
    }
    public function test_rule_epoch_blocks_new_sends_but_does_not_discard_late_queries(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31T12:00:00-05:00'));$v=$this->batch();$w=$this->batch();app(Transport::class)->send($v->id);
        $this->travel(1)->days();
        try{app(Transport::class)->send($w->id);$this->fail();}catch(ValidationException $e){$this->assertStringContainsString('vigencia',json_encode($e->errors()));}
        $this->assertSame(0,$this->job($w->id)->send_attempts);$this->assertSame('sim_accepted',app(Transport::class)->poll($v->id)->state);
    }
}
