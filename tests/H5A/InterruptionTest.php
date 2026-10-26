<?php

namespace Tests\H5A;

use App\Models\InvoiceNote;
use App\Notes\Preparation;
use App\Notes\Transport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

final class InterruptionTest extends WorkerCase
{
    public static function points(): array { return [['h5.transport.claimed',false],['h5.simulation.recorded',true]]; }

    #[DataProvider('points')]
    public function test_sigkill_keeps_durable_attempt_and_only_recovers_local_result(string $point,bool $received): void
    {
        $source=$this->original(); $before=$source->getRawOriginal();
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
        [$workers,$barrier]=$this->launch('dispatch',[['id'=>$n->id]],$point); $w=$workers[0];
        $job=DB::table('h5_outbox')->where('note_id',$n->id)->sole();
        $this->assertSame('processing',$job->status); $this->assertSame(1,$job->attempts);
        $w->signal(9); $w->wait(); $this->assertFalse($w->isSuccessful());
        $this->assertNull(app(Transport::class)->dispatch($n->id));
        $this->assertSame('processing',app(Transport::class)->recover($n->id)->state);
        $this->travel(6)->minutes();
        $result=app(Transport::class)->recover($n->id);
        $this->assertSame($received ? 'sim_accepted' : 'uncertain',$result->state);
        $this->assertSame($n->id,$result->id); $this->assertSame(\App\Notes\Original::hash($n->artifacts),\App\Notes\Original::hash($result->artifacts));
        $this->assertSame(1,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
        $this->assertSame($before,$source->fresh()->getRawOriginal());
        $this->evidence('interruption',['point'=>$point,'pid'=>(int)file_get_contents($barrier.'/paused'),'killed'=>true,'state'=>$result->state,'attempts'=>1]);
    }

    public function test_late_response_is_fenced_after_recovery_committed_terminal_result(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        [$workers,$barrier]=$this->launch('dispatch',[['id'=>$n->id]],'h5.simulation.recorded'); $w=$workers[0];
        try {
            $this->travel(6)->minutes(); $result=app(Transport::class)->recover($n->id);
            file_put_contents($barrier.'/release','release'); $w->wait(); $this->assertTrue($w->isSuccessful(),$w->getErrorOutput());
            $this->assertSame('sim_accepted',$n->fresh()->state); $this->assertSame($result->result,$n->fresh()->result);
            $this->assertSame(1,DB::table('h5_events')->where('note_id',$n->id)->where('kind','late_response_ignored')->count());
        } finally { if ($w->isRunning()) { $w->stop(0,9); } }
    }

    public function test_precommit_kill_leaves_only_preserved_orphan_and_retry_does_not_lose_identity(): void
    {
        $input=$this->noteInput($this->original()); $dirs=glob(config('h5.evidence_root').'/*') ?: [];
        $counter=DB::table('h5_counters')->where('document_type','07')->value('next_number');
        [$workers,$barrier]=$this->launch('issue',[$input],'prepared'); $workers[0]->signal(9); $workers[0]->wait();
        $new=array_values(array_diff(glob(config('h5.evidence_root').'/*') ?: [],$dirs)); $this->assertCount(1,$new);
        $hash=hash_file('sha256',$new[0].'/signed.xml');
        $this->assertSame(0,InvoiceNote::query()->where('original_id',$input['original_id'])->count());
        $this->assertSame($counter,DB::table('h5_counters')->where('document_type','07')->value('next_number'));
        Artisan::call('h5:process',['action'=>'inspect']); $report=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertContains(['id'=>basename($new[0]),'issue'=>'orphan_candidate'],$report['issues']);
        $n=app(Preparation::class)->issue($this->operator->id,$input);
        $this->assertSame($counter,$n->number); $this->assertNotSame(basename($new[0]),$n->id);
        $this->assertSame($hash,hash_file('sha256',$new[0].'/signed.xml'));
        $this->evidence('orphan',['id'=>basename($new[0]),'sha256'=>$hash,'committed'=>$n->id]);
    }
}
