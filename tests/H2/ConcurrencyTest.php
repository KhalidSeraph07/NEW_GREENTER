<?php

namespace Tests\H2;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ConcurrencyTest extends TestCase
{
    public function test_eight_independent_operations_get_unique_contiguous_numbers(): void
    {
        $before = (int) DB::table('invoice_series')->value('next_number');
        $inputs = array_map(fn () => $this->input(), range(1, 8));
        $results = $this->runWorkers('issue', array_map(fn ($input) => json_encode($input), $inputs));
        $numbers = array_column($results, 'number');
        sort($numbers);
        $this->assertSame(range($before, $before + 7), $numbers);
        $this->assertCount(8, array_unique(array_column($results, 'id')));
        $this->assertSame(8, Invoice::query()->where('user_id', $this->operator->id)->count());
        $this->assertSame($before + 8, (int) DB::table('invoice_series')->value('next_number'));
    }

    public function test_eight_simultaneous_replays_produce_one_invoice_and_one_outbox(): void
    {
        $before = (int) DB::table('invoice_series')->value('next_number');
        $input = json_encode($this->input());
        $results = $this->runWorkers('issue', array_fill(0, 8, $input));
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $id = $results[0]['id'];
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $id)->count());
        $this->assertSame($before + 1, (int) DB::table('invoice_series')->value('next_number'));
    }

    public function test_competing_content_with_one_key_has_exactly_one_conflict(): void
    {
        $input = $this->input();
        $other = $input;
        $other['unit_price'] = '101.00';
        $results = $this->runWorkers('issue', [json_encode($input), json_encode($other)]);
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['conflict'])));
        $this->assertSame(1, Invoice::query()->where('user_id', $this->operator->id)->count());
    }

    public function test_concurrent_dispatch_completes_one_outbox_attempt_only(): void
    {
        $invoice = $this->issue();
        $results = $this->runWorkers('dispatch', array_fill(0, 8, $invoice->id));
        $this->assertSame(1, count(array_filter($results, fn ($row) => $row['id'] !== null)));
        $this->assertSame('simulated_accepted', $invoice->fresh()->transport_status);
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
    }

    private function runWorkers(string $action, array $payloads): array
    {
        $barrierId = (string) Str::uuid();
        $barrier = storage_path('framework/testing/barriers/'.$barrierId);
        mkdir($barrier, 0700, true);
        $workers = [];
        foreach ($payloads as $payload) {
            $process = new Process([PHP_BINARY, base_path('tests/concurrent-worker.php'), $action,
                (string) $this->operator->id, $payload, $barrier], base_path(), [
                'APP_ENV' => 'testing', 'DB_DATABASE' => 'emisor_h2_test', 'CACHE_STORE' => 'array',
            ]);
            $process->setTimeout(60);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 25;
        while (count(glob($barrier.'/ready-*') ?: []) !== count($workers) && microtime(true) < $deadline) {
            usleep(20000);
        }
        $ready = count(glob($barrier.'/ready-*') ?: []);
        file_put_contents($barrier.'/start', 'start');
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertCount(count($workers), array_unique(array_column($results, 'pid')));
        $this->assertSame(count($workers), $ready, 'Todos los procesos deben esperar en la barrera antes de emitir.');
        file_put_contents(storage_path('audit/h2/concurrency-'.$barrierId.'.json'),
            json_encode(['action' => $action, 'barrier_ready' => $ready, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $results;
    }
}
