<?php

namespace Tests\H5B;

use App\BoletaNotes\Evidence;
use App\BoletaNotes\Preparation;
use App\BoletaNotes\SummaryTransport;
use App\Models\NoteSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

final class InterruptionTest extends WorkerCase
{
    protected function summaryFixture(): NoteSummary
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        return $this->summary([$n->id]);
    }

    public static function sendPoints(): array { return [['h5b.send.claimed', false], ['h5b.send.recorded', false], ['h5b.ticket.saved', true]]; }

    #[DataProvider('sendPoints')]
    public function test_killed_sender_recovers_only_if_ticket_was_committed(string $point, bool $ticketSaved): void
    {
        $summary = $this->summaryFixture();
        [$workers, $barrier] = $this->launch('send', [['id'=>$summary->id]], $point); $worker = $workers[0];
        $pid = $worker->getPid(); $worker->signal(9); $worker->wait();
        $this->assertFalse($worker->isSuccessful());
        $this->assertSame(1, DB::table('h5b_outbox')->where('summary_id', $summary->id)->value('send_attempts'));
        $this->assertNull(app(SummaryTransport::class)->send($summary->id));
        $this->travel(6)->minutes();
        $result = app(SummaryTransport::class)->recover($summary->id);
        $this->assertSame($ticketSaved ? 'awaiting' : 'uncertain', $result->state);
        if ($ticketSaved) { $this->assertSame('sim_accepted', app(SummaryTransport::class)->poll($summary->id)->state); }
        else { $this->assertNull($result->ticket); $this->assertNull(app(SummaryTransport::class)->poll($summary->id)); }
        $this->evidence('interruption', ['point' => $point, 'pid' => $pid, 'killed' => true, 'id' => $summary->id, 'state' => $summary->fresh()->state]);
    }

    public function test_query_response_after_recovery_is_fenced_and_cannot_change_terminal_result(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class); $transport->send($summary->id);
        [$workers, $barrier] = $this->launch('poll', [['id'=>$summary->id]], 'h5b.poll.responded'); $worker = $workers[0];
        try {
            $this->assertSame('querying', $transport->recover($summary->id)->state);
            $this->travel(6)->minutes(); $transport->recover($summary->id);
            $this->assertSame('sim_rejected', $transport->poll($summary->id, '99')->state);
            $result = $summary->fresh()->result;
            file_put_contents($barrier.'/release', 'release'); $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertSame($result, $summary->fresh()->result);
            $this->assertSame(1, DB::table('h5b_events')->where('summary_id', $summary->id)->where('kind', 'late_response_ignored')->count());
            $this->assertSame(1, DB::table('h5b_outbox')->where('summary_id', $summary->id)->value('send_attempts'));
        } finally { if ($worker->isRunning()) { $worker->stop(0, 9); } }
    }

    public function test_crash_before_rc_commit_preserves_orphan_but_not_counter_or_membership(): void
    {
        $receipt = app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        $key = (string) Str::uuid(); $payload = json_encode(['members' => [$receipt->id], 'idempotency_key' => $key]);
        $before = glob(config('h5b.evidence_root').'/*') ?: [];
        $counter = DB::table('h4_counters')->where('series', 'RC')->value('next_number');
        [$workers, $barrier] = $this->launch('freeze', [json_decode($payload,true)], 'prepared'); $workers[0]->signal(9); $workers[0]->wait();
        $this->assertSame($counter, DB::table('h4_counters')->where('series', 'RC')->value('next_number'));
        $this->assertSame(0, DB::table('h5b_members')->where('note_id', $receipt->id)->count());
        $this->assertSame(0, NoteSummary::query()->where('user_id', $this->operator->id)->count());
        $orphans = array_values(array_diff(glob(config('h5b.evidence_root').'/*') ?: [], $before)); $this->assertCount(1, $orphans);
        $hash = hash_file('sha256', $orphans[0].'/signed.xml');
        $summary = app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id, json_decode($payload, true));
        $this->assertNotSame(basename($orphans[0]), $summary->id);
        $this->assertSame($hash, hash_file('sha256', $orphans[0].'/signed.xml'));
        $this->assertSame($summary->id, app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id, json_decode($payload, true))->id);
        $this->evidence('orphan', ['orphan' => basename($orphans[0]), 'preserved_sha256' => $hash, 'committed_id' => $summary->id]);
    }
    public function test_sigkill_during_note_preparation_rolls_back_identity_reservation_and_keeps_orphan_evidence(): void
    {
        $b=$this->original();$input=$this->noteInput($b);$counter=DB::table('h5b_counters')->where('document_type','07')->value('next_number');
        $before=glob(config('h5b.evidence_root').'/*') ?: [];
        [$workers,$barrier]=$this->launch('issue',[$input],'note_prepared');$workers[0]->signal(9);$workers[0]->wait();
        $this->assertSame($counter,DB::table('h5b_counters')->where('document_type','07')->value('next_number'));
        $this->assertSame(0,\App\Models\BoletaNote::query()->where('original_id',$b->id)->count());
        $orphans=array_values(array_diff(glob(config('h5b.evidence_root').'/*') ?: [],$before));$this->assertCount(1,$orphans);
        $hash=hash_file('sha256',$orphans[0].'/signed.xml');
        $n=app(Preparation::class)->issue($this->operator->id,$input);
        $this->assertNotSame(basename($orphans[0]),$n->id);$this->assertSame($hash,hash_file('sha256',$orphans[0].'/signed.xml'));
        $this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('h5b:process',['action'=>'inspect']));
        $report=json_decode(\Illuminate\Support\Facades\Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertContains(['id'=>basename($orphans[0]),'issue'=>'orphan_candidate'],$report['issues']);
        $this->assertSame($hash,hash_file('sha256',$orphans[0].'/signed.xml'));
        $this->evidence('interruption',['point'=>'h5b.note.prepared','killed'=>true,'orphan'=>basename($orphans[0]),'preserved_sha256'=>$hash,'new_id'=>$n->id]);
    }
}
