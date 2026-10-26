<?php

namespace Tests\H3;

use App\Invoices\SimulatedTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ConcurrencyTest extends TestCase
{
    public function test_eight_queries_recover_once_without_another_attempt(): void
    {
        $invoice = $this->issue();
        app(SimulatedTransport::class)->dispatch($invoice->id, 'timeout');
        $this->workers('recover', $invoice->id);
        $this->assertSame('simulated_accepted', $invoice->fresh()->transport_status);
        $this->assertSame(1, DB::table('invoice_transport_events')->where('invoice_id', $invoice->id)->where('kind', 'recovered')->count());
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
    }

    public function test_eight_due_retry_consumers_produce_only_one_new_attempt(): void
    {
        $invoice = $this->issue();
        app(SimulatedTransport::class)->dispatch($invoice->id, 'communication_error');
        $this->travel(6)->seconds();
        // Los procesos hijos usan reloj real: habilitar solo este fixture propio.
        DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->update(['available_at' => now('UTC')->subMinutes(1)]);
        $results = $this->workers('dispatch', $invoice->id);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['id'] !== null)));
        $this->assertSame(2, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
        $this->assertSame('simulated_accepted', $invoice->fresh()->transport_status);
    }

    public function test_parallel_queries_without_receipt_never_authorize_resend(): void
    {
        $invoice = $this->issue();
        app(SimulatedTransport::class)->dispatch($invoice->id, 'uncertain');
        $this->workers('recover', $invoice->id);
        $this->assertSame('simulation_uncertain', $invoice->fresh()->transport_status);
        $this->assertNull(app(SimulatedTransport::class)->dispatch($invoice->id));
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
    }

    private function workers(string $action, string $id): array
    {
        $barrier = storage_path('framework/testing/barriers/'.Str::uuid());
        mkdir($barrier, 0700, true);
        $workers = [];
        try {
            foreach (range(1, 8) as $_) {
                $worker = new Process([PHP_BINARY, base_path('tests/concurrent-worker.php'), $action,
                    (string) $this->operator->id, $id, $barrier], base_path(), [
                    'APP_ENV' => 'testing', 'DB_DATABASE' => 'emisor_h2_test', 'CACHE_STORE' => 'array',
                ]);
                $worker->setTimeout(50);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 20;
            while (count(glob($barrier.'/ready-*') ?: []) < 8 && microtime(true) < $deadline) { usleep(10000); }
            $this->assertCount(8, glob($barrier.'/ready-*') ?: []);
            file_put_contents($barrier.'/start', 'start');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount(8, array_unique(array_column($results, 'pid')));
            if (!is_dir(storage_path('audit/h3'))) { mkdir(storage_path('audit/h3'), 0700, true); }
            file_put_contents(storage_path('audit/h3/concurrency-'.Str::uuid().'.json'), json_encode([
                'action' => $action, 'barrier_ready' => 8, 'invoice_id' => $id, 'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return $results;
        } finally {
            foreach ($workers as $worker) { if ($worker->isRunning()) { $worker->stop(0, 9); } }
        }
    }
}
