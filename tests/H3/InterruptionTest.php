<?php

namespace Tests\H3;

use App\Invoices\EvidenceReconciler;
use App\Invoices\InvoiceEvidence;
use App\Invoices\InvoiceIssuer;
use App\Invoices\SimulatedTransport;
use App\Models\Invoice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class InterruptionTest extends TestCase
{
    public static function interruptionPoints(): array { return [['claim', false], ['receipt', true]]; }

    #[DataProvider('interruptionPoints')]
    public function test_killed_worker_keeps_durable_attempt_and_can_only_query(string $point, bool $hasReceipt): void
    {
        $invoice = $this->issue();
        [$worker, $marker] = $this->startWorker($point, $invoice->id);
        $job = $this->job($invoice->id);
        $this->assertSame('processing', $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertNotNull($job->attempt_token);
        $this->assertSame((string) $worker->getPid(), file_get_contents($marker));
        $worker->signal(9);
        $worker->wait();
        $this->assertFalse($worker->isSuccessful());
        $this->assertNull(app(SimulatedTransport::class)->dispatch($invoice->id));
        $this->travel(6)->minutes();
        $recovered = app(SimulatedTransport::class)->recover($invoice->id);
        $this->assertSame($hasReceipt ? 'simulated_accepted' : 'simulation_uncertain', $recovered->transport_status);
        $this->assertSame($invoice->number, $recovered->number);
        $this->assertSame(1, $this->job($invoice->id)->attempts);
        $this->assertSame($job->attempt_token, $this->job($invoice->id)->attempt_token);
        $this->assertNotEmpty(app(InvoiceEvidence::class)->verifiedPath($invoice->id, 'xml', $recovered->artifacts));
        $this->saveEvidence($point, ['pid' => (int) file_get_contents($marker), 'killed' => true,
            'invoice_id' => $invoice->id, 'recovered_status' => $recovered->transport_status, 'attempts' => 1]);
    }

    public function test_late_response_cannot_overwrite_recovered_terminal_state(): void
    {
        $invoice = $this->issue();
        [$worker, $marker] = $this->startWorker('receipt', $invoice->id);
        try {
            $this->travel(6)->minutes();
            $this->assertSame('simulated_accepted', app(SimulatedTransport::class)->recover($invoice->id)->transport_status);
            $response = $invoice->fresh()->simulated_response;
            file_put_contents($marker.'.release', 'release');
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertSame($response, $invoice->fresh()->simulated_response);
            $this->assertSame('completed', $this->job($invoice->id)->status);
            $this->assertSame(1, DB::table('invoice_transport_events')->where('invoice_id', $invoice->id)->where('kind', 'late_response_ignored')->count());
        } finally { if ($worker->isRunning()) { $worker->stop(0, 9); } }
    }

    public function test_crash_before_issue_commit_leaves_detectable_orphan_without_consuming_identity(): void
    {
        $input = $this->input();
        $beforeNumber = (int) DB::table('invoice_series')->value('next_number');
        $beforeDirs = glob(config('h2.evidence_root').'/*') ?: [];
        [$worker, $marker] = $this->startWorker('issue', json_encode(['user_id' => $this->operator->id, 'input' => $input]));
        $worker->signal(9);
        $worker->wait();
        $this->assertSame(0, Invoice::query()->where('user_id', $this->operator->id)->count());
        $this->assertSame($beforeNumber, (int) DB::table('invoice_series')->value('next_number'));
        $newDirs = array_values(array_diff(glob(config('h2.evidence_root').'/*') ?: [], $beforeDirs));
        $this->assertCount(1, $newDirs);
        $orphan = basename($newDirs[0]);
        $hashBefore = hash_file('sha256', $newDirs[0].'/signed.xml');
        $report = app(EvidenceReconciler::class)->inspect();
        $this->assertContains(['id' => $orphan, 'issue' => 'orphan_candidate'], $report['issues']);
        $issued = app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $this->assertSame($beforeNumber, $issued->number);
        $this->assertNotSame($orphan, $issued->id);
        $this->assertSame($hashBefore, hash_file('sha256', $newDirs[0].'/signed.xml'));
        $replay = app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $this->assertSame($issued->id, $replay->id);
        $this->saveEvidence('orphan', ['killed' => true, 'orphan_id' => $orphan, 'preserved_sha256' => $hashBefore,
            'committed_id' => $issued->id, 'number' => $issued->number]);
    }

    public function test_reconciliation_refuses_active_series_lock(): void
    {
        [$worker, $marker] = $this->startWorker('hold_series', 'none');
        try {
            $this->expectException(QueryException::class);
            app(EvidenceReconciler::class)->inspect();
        } finally {
            file_put_contents($marker.'.release', 'release');
            $worker->wait();
        }
    }

    private function startWorker(string $point, string $payload): array
    {
        $marker = storage_path('framework/testing/h3-'.Str::uuid());
        $worker = new Process([PHP_BINARY, base_path('tests/h3-interruption-worker.php'), $point, $payload, $marker], base_path(), [
            'APP_ENV' => 'testing', 'DB_DATABASE' => 'emisor_h2_test', 'CACHE_STORE' => 'array',
        ]);
        $worker->setTimeout(40);
        $worker->start();
        $deadline = microtime(true) + 15;
        while (!is_file($marker) && $worker->isRunning() && microtime(true) < $deadline) { usleep(10000); }
        if (!is_file($marker)) {
            $worker->stop(0, 9);
            $this->fail('Proceso no llego al punto de interrupcion: '.$worker->getErrorOutput().$worker->getOutput());
        }
        return [$worker, $marker];
    }

    private function job(string $id): object { return DB::table('invoice_outbox')->where('invoice_id', $id)->sole(); }

    private function saveEvidence(string $point, array $data): void
    {
        $directory = storage_path('audit/h3');
        if (!is_dir($directory)) { mkdir($directory, 0700, true); }
        file_put_contents($directory.'/interruption-'.$point.'-'.Str::uuid().'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
