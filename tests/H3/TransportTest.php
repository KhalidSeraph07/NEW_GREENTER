<?php

namespace Tests\H3;

use App\Invoices\LocalSimulation;
use App\Invoices\SimulatedTransport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class TransportTest extends TestCase
{
    public function test_only_proven_non_processing_retries_after_wait_and_exhausts_at_three(): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $result = $transport->dispatch($invoice->id, 'communication_error');
            $this->assertSame($attempt < 3 ? 'simulation_retry_wait' : 'simulation_failed', $result->transport_status);
            $this->assertSame($attempt, $this->job($invoice->id)->attempts);
            $this->assertNull($transport->dispatch($invoice->id));
            $this->travel(11)->seconds();
        }
        $this->assertNull($transport->dispatch($invoice->id));
        $this->assertSame('simulation_failed', $transport->recover($invoice->id)->transport_status);
        $this->assertSame(0, DB::table('simulation_receipts')->where('invoice_id', $invoice->id)->count());
        $this->assertTrue(DB::table('invoice_transport_events')->where('invoice_id', $invoice->id)->where('kind', 'retry_exhausted')->value('attention'));
    }

    public function test_safe_retry_preserves_identity_number_and_all_evidence(): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $transport->dispatch($invoice->id, 'communication_error');
        $this->travel(6)->seconds();
        $result = $transport->dispatch($invoice->id);
        $this->assertSame('simulated_accepted', $result->transport_status);
        $this->assertSame($invoice->id, $result->id);
        $this->assertSame($invoice->number, $result->number);
        $this->assertManifest($invoice->artifacts, $result->artifacts);
        $this->assertSame(2, $this->job($invoice->id)->attempts);
        $this->assertNull($transport->dispatch($invoice->id, 'rejected'));
    }

    public static function uncertainScenarios(): array
    {
        return [['timeout', true], ['incomplete', true], ['unexpected_error', false], ['uncertain', false]];
    }

    #[DataProvider('uncertainScenarios')]
    public function test_uncertainty_never_retries_and_query_requires_preexisting_receipt(string $scenario, bool $recoverable): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $this->assertSame('simulation_uncertain', $transport->dispatch($invoice->id, $scenario)->transport_status);
        $this->travel(1)->hours();
        $this->assertNull($transport->dispatch($invoice->id));
        $this->assertSame($recoverable ? 'simulated_accepted' : 'simulation_uncertain', $transport->recover($invoice->id)->transport_status);
        $this->assertSame(1, $this->job($invoice->id)->attempts);
        $this->assertNull($transport->dispatch($invoice->id));
        $this->assertManifest($invoice->artifacts, $invoice->fresh()->artifacts);
    }

    public static function badResponses(): array
    {
        return [['simulated', false], ['source', 'OFFICIAL'], ['invoice_id', '00000000-0000-4000-8000-000000000000'],
            ['request_sha256', 'bad'], ['scenario', 'observed'], ['code', '0'], ['message', 'CDR oficial']];
    }

    #[DataProvider('badResponses')]
    public function test_mismatched_query_cannot_confirm_a_result(string $field, mixed $value): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $transport->dispatch($invoice->id, 'timeout');
        $response = app(LocalSimulation::class)->lookup($invoice->id);
        $response[$field] = $value;
        DB::table('simulation_receipts')->where('invoice_id', $invoice->id)->update(['response' => json_encode($response)]);
        $this->assertSame('simulation_uncertain', $transport->recover($invoice->id)->transport_status);
        $this->assertNull($transport->dispatch($invoice->id));
        $this->assertSame(1, $this->job($invoice->id)->attempts);
    }

    public function test_query_failure_preserves_uncertainty_and_logs_only_fixed_event_metadata(): void
    {
        $invoice = $this->issue();
        app(SimulatedTransport::class)->dispatch($invoice->id, 'uncertain');
        $this->app->instance(LocalSimulation::class, new class extends LocalSimulation {
            public function lookup(string $invoiceId): ?array { throw new RuntimeException('SECRET-FIXTURE-NEVER-LOG'); }
        });
        $this->assertSame('simulation_uncertain', app(SimulatedTransport::class)->recover($invoice->id)->transport_status);
        $events = DB::table('invoice_transport_events')->where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertSame(['claimed', 'uncertain', 'query_unresolved'], $events->pluck('kind')->all());
        $this->assertStringNotContainsString('SECRET-FIXTURE', json_encode($events));
        $this->assertTrue($events->last()->attention);
    }

    public function test_nested_transaction_is_refused_before_attempt(): void
    {
        $invoice = $this->issue();
        DB::beginTransaction();
        try {
            try { app(SimulatedTransport::class)->dispatch($invoice->id); $this->fail('Reserva no durable admitida.'); }
            catch (LogicException) { $this->assertSame(0, $this->job($invoice->id)->attempts); }
        } finally { DB::rollBack(); }
    }

    public function test_inconsistent_states_are_stopped_without_a_simulated_receipt(): void
    {
        $invoice = $this->issue();
        $invoice->update(['transport_status' => 'simulated_accepted']);
        try { app(SimulatedTransport::class)->dispatch($invoice->id); $this->fail('Estados corruptos admitidos.'); }
        catch (LogicException) {
            $this->assertSame(0, $this->job($invoice->id)->attempts);
            $this->assertSame(0, DB::table('simulation_receipts')->where('invoice_id', $invoice->id)->count());
        }
    }

    public function test_database_rejects_attempt_four(): void
    {
        $invoice = $this->issue();
        $this->expectException(QueryException::class);
        DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->update(['attempts' => 4]);
    }

    public function test_final_persistence_failure_does_not_lose_receipt_or_allow_resend(): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $trigger = 'h3_fault_'.str_replace('-', '', (string) Str::uuid());
        DB::unprepared("CREATE FUNCTION $trigger() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.transport_status = ''simulated_accepted'' THEN RAISE EXCEPTION ''H3 final failure''; END IF; RETURN NEW; END'");
        DB::unprepared("CREATE TRIGGER $trigger BEFORE UPDATE ON invoices FOR EACH ROW EXECUTE FUNCTION $trigger()");
        try {
            try { $transport->dispatch($invoice->id); $this->fail('No se inyecto fallo final.'); }
            catch (QueryException) { $this->assertSame('processing', $this->job($invoice->id)->status); }
        } finally {
            DB::unprepared("DROP TRIGGER $trigger ON invoices");
            DB::unprepared("DROP FUNCTION $trigger()");
        }
        $this->assertNotNull(app(LocalSimulation::class)->lookup($invoice->id));
        $this->assertNull($transport->dispatch($invoice->id));
        $this->assertSame('simulation_processing', $transport->recover($invoice->id)->transport_status);
        $this->travel(6)->minutes();
        $this->assertSame('simulated_accepted', $transport->recover($invoice->id)->transport_status);
        $this->assertSame(1, $this->job($invoice->id)->attempts);
    }

    private function assertManifest(array $expected, array $actual): void
    {
        foreach ($expected as $kind => $artifact) {
            $this->assertSame($artifact['sha256'], $actual[$kind]['sha256']);
            $this->assertSame($artifact['bytes'], $actual[$kind]['bytes']);
        }
    }

    private function job(string $id): object
    {
        return DB::table('invoice_outbox')->where('invoice_id', $id)->sole();
    }
}
