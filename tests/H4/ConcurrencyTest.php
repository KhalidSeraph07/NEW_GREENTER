<?php

namespace Tests\H4;

use App\Boletas\Preparation;
use App\Boletas\SummaryTransport;
use App\Models\Boleta;
use App\Models\DailySummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConcurrencyTest extends WorkerCase
{
    public function test_eight_receipt_creations_are_contiguous_and_replays_have_one_identity(): void
    {
        $counter = (int) DB::table('h4_counters')->where('series', 'B000')->value('next_number');
        $payloads = array_map(fn () => json_encode($this->input(['emission_date' => now()->toDateString()])), range(1, 8));
        $results = $this->together('issue', $payloads); $numbers = array_column($results, 'number'); sort($numbers);
        $this->assertSame(range($counter, $counter + 7), $numbers);
        $same = json_encode($this->input(['emission_date' => now()->toDateString()]));
        $results = $this->together('issue', array_fill(0, 8, $same));
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $this->assertSame(9, Boleta::query()->where('user_id', $this->operator->id)->count());
    }

    public function test_parallel_rc_replays_freeze_one_manifest_and_overlaps_conflict(): void
    {
        $receipt = app(Preparation::class)->issue($this->operator->id, $this->input(['emission_date' => now()->toDateString()]));
        $payload = json_encode(['members' => [$receipt->id], 'idempotency_key' => (string) Str::uuid()]);
        $results = $this->together('freeze', array_fill(0, 8, $payload));
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $this->assertSame(1, DB::table('h4_members')->where('boleta_id', $receipt->id)->count());
        $other = app(Preparation::class)->issue($this->operator->id, $this->input(['emission_date' => now()->toDateString()]));
        $payloads = array_map(fn () => json_encode(['members' => [$other->id], 'idempotency_key' => (string) Str::uuid()]), range(1, 8));
        $results = $this->together('freeze', $payloads);
        $this->assertSame(7, count(array_filter($results, fn ($r) => $r['conflict'])));
        $this->assertSame(2, DailySummary::query()->where('user_id', $this->operator->id)->count());
    }

    public function test_parallel_send_and_poll_have_one_attempt_ticket_and_terminal_result(): void
    {
        $summary = $this->summaryFixture();
        $sent = $this->together('send', array_fill(0, 8, $summary->id));
        $this->assertSame(1, count(array_filter($sent, fn ($r) => $r['id'] !== null)));
        $this->assertSame('awaiting', $summary->fresh()->state);
        $polled = $this->together('poll', array_fill(0, 8, $summary->id));
        $this->assertSame(1, count(array_filter($polled, fn ($r) => $r['id'] !== null)));
        $this->assertSame('sim_accepted', $summary->fresh()->state);
        $this->assertSame(1, DB::table('h4_outbox')->where('summary_id', $summary->id)->value('send_attempts'));
        $this->assertSame(1, DB::table('h4_outbox')->where('summary_id', $summary->id)->value('poll_attempts'));
        $this->assertSame(1, DB::table('h4_simulation_receipts')->where('summary_id', $summary->id)->count());
        $this->assertSame(1, DB::table('h4_events')->where('summary_id', $summary->id)->where('kind', 'result_saved')->count());
    }

    public function test_parallel_recovery_rearms_only_query_without_resending(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class);
        $transport->send($summary->id); $transport->poll($summary->id, 'timeout');
        $this->together('recover', array_fill(0, 8, $summary->id));
        $this->assertSame(1, DB::table('h4_events')->where('summary_id', $summary->id)->where('kind', 'manual_query_rearmed')->count());
        $this->assertSame('sim_accepted', $transport->poll($summary->id)->state);
        $this->assertSame(1, DB::table('h4_outbox')->where('summary_id', $summary->id)->value('send_attempts'));
    }
}
