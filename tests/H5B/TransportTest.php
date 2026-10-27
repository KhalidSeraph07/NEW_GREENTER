<?php

namespace Tests\H5B;

use App\BoletaNotes\Evidence;
use App\BoletaNotes\SummaryPreparation;
use App\BoletaNotes\SummaryTransport;
use App\BoletaNotes\LocalSummarySimulator;
use App\Models\NoteSummary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class TransportTest extends NoteCase
{
    public function test_ticket_then_98_then_0_require_constancia_before_informing_members(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class);
        $sent = $transport->send($summary->id);
        $this->assertSame('awaiting', $sent->state); $this->assertNull($sent->result);
        $this->assertStringStartsWith('SIM-H5B-', $sent->ticket);
        $this->assertNull($transport->send($summary->id));
        $this->assertSame('awaiting', $transport->poll($summary->id, '98')->state);
        $this->assertNull($transport->poll($summary->id));
        $this->travel(6)->seconds();
        $result = $transport->poll($summary->id, '0');
        $this->assertSame('sim_accepted', $result->state);
        $this->assertFalse($result->result['official_cdr']);
        $this->assertSame('accepted', $result->result['outcome']);
        $this->assertSame($sent->ticket, $result->ticket);
        $this->assertNull($transport->poll($summary->id, '99'));
        $this->assertSame(1, $this->job($summary->id)->send_attempts);
        $this->assertSame(2, $this->job($summary->id)->poll_attempts);
        $events = DB::table('h5b_events')->where('summary_id', $summary->id)->orderBy('id')->get();
        $this->assertContains('ticket_saved', $events->pluck('kind')->all());
        $this->assertSame('result_saved', $events->last()->kind);
    }

    public function test_99_is_terminal_rejection_and_does_not_release_members_for_automatic_reissue(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class);
        $transport->send($summary->id);
        $this->assertSame('sim_rejected', $transport->poll($summary->id, '99')->state);
        $this->assertSame('sim_rejected', $transport->recover($summary->id)->state);
        $this->assertNull($transport->send($summary->id));
        $this->expectException(ConflictHttpException::class);
        app(SummaryPreparation::class)->freeze($this->operator->id, ['idempotency_key' => (string) Str::uuid(), 'members' => array_column($summary->snapshot['members'], 'id')]);
    }

    public static function uncertainSend(): array { return array_map(fn ($s) => [$s], ['lost_ticket', 'incomplete', 'wrong_hash', 'duplicate', 'exception']); }

    #[DataProvider('uncertainSend')]
    public function test_uncertain_send_never_retries_or_recovers_by_uuid(string $scenario): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class);
        $this->assertSame('uncertain', $transport->send($summary->id, $scenario)->state);
        $this->assertNull($summary->fresh()->ticket);
        $this->travel(1)->days();
        $this->assertSame('uncertain', $transport->recover($summary->id)->state);
        $this->assertNull($transport->send($summary->id)); $this->assertNull($transport->poll($summary->id));
        $this->assertSame(1, $this->job($summary->id)->send_attempts);
        $this->assertSame(0, $this->job($summary->id)->poll_attempts);
    }

    public static function uncertainPoll(): array
    {
        return array_map(fn ($s) => [$s], ['timeout', 'incomplete', 'wrong_hash', 'wrong_member', 'wrong_ticket', 'wrong_rc', 'non_simulated', 'doctype']);
    }

    #[DataProvider('uncertainPoll')]
    public function test_invalid_constancia_stops_queries_and_controlled_recovery_uses_same_ticket(string $scenario): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class);
        $ticket = $transport->send($summary->id)->ticket;
        $this->assertSame('uncertain', $transport->poll($summary->id, $scenario)->state);
        $this->assertNull($transport->poll($summary->id));
        $this->assertNull($transport->send($summary->id));
        $this->assertNull($summary->fresh()->result);
        $this->assertSame('awaiting', $transport->recover($summary->id)->state);
        $result = $transport->poll($summary->id, '0');
        $this->assertSame('sim_accepted', $result->state);
        $this->assertSame($ticket, $result->ticket);
        $this->assertSame(1, $this->job($summary->id)->send_attempts);
        $this->assertSame(2, $this->job($summary->id)->poll_attempts);
    }

    public function test_three_pending_queries_exhaust_only_technical_budget_not_a_fiscal_result(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class); $transport->send($summary->id);
        foreach (range(1, 3) as $attempt) {
            $result = $transport->poll($summary->id, '98'); $this->travel(6)->seconds();
            $this->assertSame($attempt < 3 ? 'awaiting' : 'uncertain', $result->state);
        }
        $this->assertNull($transport->poll($summary->id)); $this->assertNull($summary->fresh()->result);
        $transport->recover($summary->id); $this->assertSame('sim_accepted', $transport->poll($summary->id)->state);
        $this->assertSame(4, $this->job($summary->id)->poll_attempts);
    }

    public function test_send_after_deadline_is_stopped_but_late_consultation_preserves_real_trial_result(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class); $transport->send($summary->id);
        $waiting = $this->summaryFixture(); $this->travel(8)->days();
        try { $transport->send($waiting->id); $this->fail(); } catch (ValidationException) {
            $this->assertSame(0, $this->job($waiting->id)->send_attempts);
        }
        $this->assertSame('sim_accepted', $transport->poll($summary->id)->state);
    }

    public function test_altered_evidence_does_not_consume_attempt_and_ticket_cannot_be_replaced(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class); $transport->send($summary->id);
        $path = app(Evidence::class)->path($summary->id, 'xml', $summary->artifacts);
        file_put_contents($path, 'H5-B altered own fixture');
        try { $transport->poll($summary->id); $this->fail(); } catch (RuntimeException) {
            $this->assertSame(0, $this->job($summary->id)->poll_attempts);
        }
        $this->expectException(QueryException::class);
        $summary->fresh()->update(['ticket' => 'SIM-H5B-'.Str::uuid()]);
    }

    public function test_final_persistence_failure_can_query_again_without_sending_again(): void
    {
        $summary = $this->summaryFixture(); $transport = app(SummaryTransport::class); $transport->send($summary->id);
        $name = 'h5b_result_'.str_replace('-', '', (string) Str::uuid());
        DB::unprepared("CREATE FUNCTION $name() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.state = ''sim_accepted'' THEN RAISE EXCEPTION ''H5-B result failure''; END IF; RETURN NEW; END'");
        DB::unprepared("CREATE TRIGGER $name BEFORE UPDATE ON h5b_summaries FOR EACH ROW EXECUTE FUNCTION $name()");
        try {
            try { $transport->poll($summary->id); $this->fail(); } catch (QueryException) { $this->assertSame('processing', $this->job($summary->id)->status); }
        } finally { DB::unprepared("DROP TRIGGER $name ON h5b_summaries"); DB::unprepared("DROP FUNCTION $name()"); }
        $this->assertSame('querying', $transport->recover($summary->id)->state);
        $this->travel(6)->minutes(); $transport->recover($summary->id);
        $this->assertSame('sim_accepted', $transport->poll($summary->id)->state);
        $this->assertSame(1, $this->job($summary->id)->send_attempts);
        $this->assertSame(2, $this->job($summary->id)->poll_attempts);
    }

    public function test_process_code_99_without_constancia_does_not_create_a_rejection(): void
    {
        $summary = $this->summaryFixture(); app(SummaryTransport::class)->send($summary->id);
        $this->app->instance(LocalSummarySimulator::class, new class extends LocalSummarySimulator {
            public function poll(string $ticket, string $scenario): array {
                return ['simulated' => true, 'source' => 'H5B_LOCAL_FAKE', 'ticket' => $ticket, 'statusCode' => '99'];
            }
        });
        $this->assertSame('uncertain', app(SummaryTransport::class)->poll($summary->id, '99')->state);
        $this->assertNull($summary->fresh()->result);
        $this->assertNotEmpty(DB::table('h5b_events')->where('summary_id', $summary->id)->where('kind', 'query_uncertain')->value('payload'));
    }

    public function test_external_transaction_and_unapproved_scenario_are_refused_before_attempt(): void
    {
        $summary = $this->summaryFixture();
        DB::beginTransaction();
        try {
            try { app(SummaryTransport::class)->send($summary->id); $this->fail(); }
            catch (\LogicException) { $this->assertSame(0, $this->job($summary->id)->send_attempts); }
        } finally { DB::rollBack(); }
        $this->expectException(ValidationException::class);
        app(SummaryTransport::class)->send($summary->id, 'individual');
    }

    private function summaryFixture(): NoteSummary
    {
        $n = app(\App\BoletaNotes\Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        return $this->summary([$n->id]);
    }

    private function job(string $id): object { return DB::table('h5b_outbox')->where('summary_id', $id)->sole(); }
}
