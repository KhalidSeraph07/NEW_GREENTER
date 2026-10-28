<?php
namespace Tests\H5C2;
use App\Corrections\Family;
use Illuminate\Support\Facades\{DB,Artisan,Event};
use PHPUnit\Framework\Attributes\DataProvider;
final class InterruptionTest extends WorkerCase
{
    public static function points(): array {return [['h4','h5c2.prepared',false],['h4','h5c2.committed',true],['h5b','h5c2.prepared',false],['void','h5c2.prepared',false],['invoice','h5c2.prepared',false],['invoice','h5c2.committed',true]];}
    #[DataProvider('points')]
    public function test_sigkill_preserves_parent_and_replay_is_atomic(string $f,string $point,bool $committed): void
    {
        $r=$this->root($f);$before=$r->getRawOriginal();$input=$f==='invoice'?$this->reissueInput($r):$this->correctionInput($r);
        $cfg=match($f){'invoice'=>'h2','void'=>'h5c1',default=>$f};$root=config($cfg.'.evidence_root');$dirs=glob($root.'/*')?:[];
        [$workers]=$this->launch('prepare',[['family'=>$f,'input'=>$input]],$point);$w=$workers[0];$pid=$w->getPid();$w->signal(9);$w->wait();$this->assertFalse($w->isSuccessful());
        $this->assertSame($committed?1:0,DB::table(Family::LINKS[$f])->where('original_id',$r->id)->count());
        $added=array_values(array_diff(glob($root.'/*')?:[],$dirs));$this->assertCount(1,$added);$hash=hash_file('sha256',$added[0].'/signed.xml');
        $n=$this->child($f,$r,$input);$this->assertSame($committed,basename($added[0])===$n->id);$this->assertSame($n->id,$this->child($f,$r,$input)->id);
        $this->assertSame($hash,hash_file('sha256',$added[0].'/signed.xml'));$this->assertSame($before,$r->fresh()->getRawOriginal());
        if(!$committed){$this->assertSame(1,Artisan::call('h5c2:process',['action'=>'inspect']));$report=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
            $this->assertContains(['family'=>$f,'id'=>basename($added[0]),'issue'=>'orphan_candidate'],$report['issues']);}
        $this->evidence('interruption',['point'=>$point,'family'=>$f,'pid'=>$pid,'killed'=>true,'committed'=>$committed,'artifact_id'=>basename($added[0]),'hash'=>$hash,'successor_id'=>$n->id]);
    }
    public static function sendPoints(): array {return [['h4','h4.send.claimed',false],['h4','h4.send.recorded',false],['h4','h4.ticket.saved',true],['void','h5c1.ticket.saved',true],['h5b','h5b.send.recorded',false],['invoice','h3.transport.claimed',false]];}
    #[DataProvider('sendPoints')]
    public function test_killed_successor_sender_cannot_repeat_send(string $f,string $point,bool $ticket): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);[$workers]=$this->launch('send',[['family'=>$f,'id'=>$n->id]],$point);$w=$workers[0];$pid=$w->getPid();$w->signal(9);$w->wait();$this->assertFalse($w->isSuccessful());
        $this->travel(6)->minutes();
        if($f==='invoice'){$t=app(\App\Invoices\SimulatedTransport::class);$this->assertNull($t->dispatch($n->id));$this->assertSame('simulation_uncertain',$t->recover($n->id)->transport_status);}
        else{$t=$this->transport($f);$this->assertNull($t->send($n->id));$state=$t->recover($n->id)->state;$this->assertSame($ticket?'awaiting':'uncertain',$state);
            if($ticket){$this->assertSame('sim_accepted',$t->poll($n->id)->state);}else{$this->assertNull($t->poll($n->id));}}
        $this->assertSame(1,DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$n->id)->value($f==='invoice'?'attempts':'send_attempts'));
        $this->evidence('interruption',['family'=>$f,'point'=>$point,'pid'=>$pid,'killed'=>true,'id'=>$n->id,'ticket_committed'=>$ticket]);
    }
    public function test_late_query_response_is_fenced_after_controlled_recovery(): void
    {
        $r=$this->root('h4');$n=$this->child('h4',$r);$t=$this->transport('h4');$t->send($n->id);
        [$workers,$barrier]=$this->launch('poll',[['family'=>'h4','id'=>$n->id]],'h4.poll.responded');$w=$workers[0];
        try{$this->travel(6)->minutes();$t->recover($n->id);$this->assertSame('sim_rejected',$t->poll($n->id,'99')->state);$result=$n->fresh()->result;
            file_put_contents($barrier.'/release','release');$w->wait();$this->assertTrue($w->isSuccessful(),$w->getErrorOutput());$this->assertSame($result,$n->fresh()->result);
            $this->assertSame(1,DB::table('h4_events')->where('summary_id',$n->id)->where('kind','late_response_ignored')->count());
        }finally{if($w->isRunning()){$w->stop(0,9);}}
    }
    public static function families(): array {return PreparationTest::families();}
    #[DataProvider('families')]
    public function test_failed_persistence_rolls_back_identity_and_outbox_then_same_key_succeeds(string $f): void
    {
        $r=$this->root($f);$input=$f==='invoice'?$this->reissueInput($r):$this->correctionInput($r);$before=$r->getRawOriginal();
        Event::listen('h5c2.prepared',function(){throw new \RuntimeException('C2_INJECTED_PERSISTENCE_FAILURE');});
        try{$this->child($f,$r,$input);$this->fail();}catch(\RuntimeException $e){$this->assertSame('C2_INJECTED_PERSISTENCE_FAILURE',$e->getMessage());}finally{Event::forget('h5c2.prepared');}
        $this->assertSame(0,DB::table(Family::LINKS[$f])->where('original_id',$r->id)->count());
        $this->assertFalse((Family::MODELS[$f])::query()->where('user_id',$this->operator->id)->where('idempotency_key',$input['idempotency_key'])->exists());
        $this->assertSame($before,$r->fresh()->getRawOriginal());$n=$this->child($f,$r,$input);$this->assertSame($n->id,$this->child($f,$r,$input)->id);
    }
}
