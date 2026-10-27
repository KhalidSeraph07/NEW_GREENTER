<?php

namespace Tests\H5C1;

use App\Voids\Evidence;
use App\Voids\Preparation;
use App\Voids\Transport;
use App\Models\VoidBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

final class InterruptionTest extends WorkerCase
{
    protected function summaryFixture(): VoidBatch { return $this->batch(); }

    public static function sendPoints(): array { return [['h5c1.send.claimed', false], ['h5c1.send.recorded', false], ['h5c1.ticket.saved', true]]; }

    #[DataProvider('sendPoints')]
    public function test_killed_sender_recovers_only_if_ticket_was_committed(string $point, bool $ticketSaved): void
    {
        $summary = $this->summaryFixture();
        [$workers, $barrier] = $this->launch('send', [['id'=>$summary->id]], $point); $worker = $workers[0];
        $pid = $worker->getPid(); $worker->signal(9); $worker->wait();
        $this->assertFalse($worker->isSuccessful());
        $this->assertSame(1, DB::table('h5c1_outbox')->where('batch_id', $summary->id)->value('send_attempts'));
        $this->assertNull(app(Transport::class)->send($summary->id));
        $this->travel(6)->minutes();
        $result = app(Transport::class)->recover($summary->id);
        $this->assertSame($ticketSaved ? 'awaiting' : 'uncertain', $result->state);
        if ($ticketSaved) { $this->assertSame('sim_accepted', app(Transport::class)->poll($summary->id)->state); }
        else { $this->assertNull($result->ticket); $this->assertNull(app(Transport::class)->poll($summary->id)); }
        $this->evidence('interruption', ['point' => $point, 'pid' => $pid, 'killed' => true, 'id' => $summary->id, 'state' => $summary->fresh()->state]);
    }

    public function test_query_response_after_recovery_is_fenced_and_cannot_change_terminal_result(): void
    {
        $summary = $this->summaryFixture(); $transport = app(Transport::class); $transport->send($summary->id);
        [$workers, $barrier] = $this->launch('poll', [['id'=>$summary->id]], 'h5c1.poll.responded'); $worker = $workers[0];
        try {
            $this->assertSame('querying', $transport->recover($summary->id)->state);
            $this->travel(6)->minutes(); $transport->recover($summary->id);
            $this->assertSame('sim_rejected', $transport->poll($summary->id, '99')->state);
            $result = $summary->fresh()->result;
            file_put_contents($barrier.'/release', 'release'); $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertSame($result, $summary->fresh()->result);
            $this->assertSame(1, DB::table('h5c1_events')->where('batch_id', $summary->id)->where('kind', 'late_response_ignored')->count());
            $this->assertSame(1, DB::table('h5c1_outbox')->where('batch_id', $summary->id)->value('send_attempts'));
        } finally { if ($worker->isRunning()) { $worker->stop(0, 9); } }
    }

    public function test_sigkill_before_commit_rolls_back_members_and_identity_and_keeps_orphan(): void
    {
        $k=$this->accepted();$this->grant($k);$input=$this->batchInput([$k]);
        $before=glob(config('h5c1.evidence_root').'/*') ?: [];$counter=DB::table('h5c1_counters')->value('next_number');
        [$workers,$barrier]=$this->launch('freeze',[$input],'h5c1.prepared');$pid=$workers[0]->getPid();$workers[0]->signal(9);$workers[0]->wait();
        $this->assertSame($counter,DB::table('h5c1_counters')->value('next_number'));
        $this->assertSame(0,DB::table('h5c1_members')->where('document_id',$k['id'])->count());
        $orphans=array_values(array_diff(glob(config('h5c1.evidence_root').'/*') ?: [],$before));$this->assertCount(1,$orphans);
        $hash=hash_file('sha256',$orphans[0].'/signed.xml');$v=app(Preparation::class)->freeze($this->operator->id,$input);
        $this->assertNotSame(basename($orphans[0]),$v->id);$this->assertSame($hash,hash_file('sha256',$orphans[0].'/signed.xml'));
        $this->assertSame($v->id,app(Preparation::class)->freeze($this->operator->id,$input)->id);
        $this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('h5c1:process',['action'=>'inspect']));
        $report=json_decode(\Illuminate\Support\Facades\Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertContains(['id'=>basename($orphans[0]),'issue'=>'orphan_candidate'],$report['issues']);
        $this->assertSame($hash,hash_file('sha256',$orphans[0].'/signed.xml'));
        $this->evidence('interruption',['point'=>'h5c1.prepared','pid'=>$pid,'killed'=>true,'orphan'=>basename($orphans[0]),'hash'=>$hash,'committed_id'=>$v->id]);
    }
}
