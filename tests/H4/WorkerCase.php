<?php

namespace Tests\H4;

use App\Boletas\Preparation;
use App\Models\DailySummary;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

abstract class WorkerCase extends TestCase
{
    protected function summaryFixture(): DailySummary
    {
        $receipt = app(Preparation::class)->issue($this->operator->id, $this->input(['emission_date' => now()->toDateString()]));
        return app(Preparation::class)->freeze($this->operator->id, ['members' => [$receipt->id], 'idempotency_key' => (string) Str::uuid()]);
    }

    protected function launch(string $action, array $payloads, string $pause = ''): array
    {
        $barrier = storage_path('framework/testing/h4-barriers/'.Str::uuid()); mkdir($barrier, 0700, true);
        $workers = [];
        foreach ($payloads as $payload) {
            $worker = new Process([PHP_BINARY, base_path('tests/h4-worker.php'), $action, (string) $this->operator->id, $payload, $barrier, $pause], base_path(),
                ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emisor_h2_test', 'CACHE_STORE' => 'array']);
            $worker->setTimeout(60); $worker->start(); $workers[] = $worker;
        }
        $deadline = microtime(true) + 25;
        while (count(glob($barrier.'/ready-*') ?: []) < count($workers) && microtime(true) < $deadline) { usleep(10000); }
        $ready = count(glob($barrier.'/ready-*') ?: []); file_put_contents($barrier.'/start', 'start');
        if ($ready !== count($workers)) {
            foreach ($workers as $worker) { $worker->stop(0, 9); }
            $this->fail('No llegaron todos los procesos H4 a barrera.');
        }
        if ($pause !== '') {
            $deadline = microtime(true) + 20;
            while (!is_file($barrier.'/paused') && $workers[0]->isRunning() && microtime(true) < $deadline) { usleep(10000); }
            if (!is_file($barrier.'/paused')) {
                $error = $workers[0]->getErrorOutput().$workers[0]->getOutput(); $workers[0]->stop(0, 9); $this->fail('Pausa H4 no alcanzada: '.$error);
            }
        }
        return [$workers, $barrier];
    }

    protected function together(string $action, array $payloads): array
    {
        [$workers, $barrier] = $this->launch($action, $payloads); $results = [];
        try {
            foreach ($workers as $worker) {
                $worker->wait(); $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount(count($workers), array_unique(array_column($results, 'pid')));
            $this->evidence('concurrency', ['action' => $action, 'ready' => count($workers), 'results' => $results]);
            return $results;
        } finally { foreach ($workers as $worker) { if ($worker->isRunning()) { $worker->stop(0, 9); } } }
    }

    protected function evidence(string $label, array $data): void
    {
        $dir = storage_path('audit/h4'); if (!is_dir($dir)) { mkdir($dir, 0700, true); }
        file_put_contents($dir.'/'.$label.'-'.Str::uuid().'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
