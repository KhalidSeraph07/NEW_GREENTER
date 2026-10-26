<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!app()->environment('testing') || config('database.connections.pgsql.database') !== 'emisor_h2_test') {
    throw new RuntimeException('El proceso de prueba solo admite emisor_h2_test.');
}
[$script, $action, $userId, $payload, $barrier] = $argv;
$root = storage_path('framework/testing/barriers/');
if (!str_starts_with($barrier, $root) || !preg_match('/\A[0-9a-f-]{36}\z/', substr($barrier, strlen($root)))) {
    throw new RuntimeException('Barrera de prueba no permitida.');
}
file_put_contents($barrier.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 25;
while (!is_file($barrier.'/start')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('La barrera no fue liberada.');
    }
    usleep(10000);
}
$started = microtime(true);
try {
    $invoice = match ($action) {
        'issue' => app(App\Invoices\InvoiceIssuer::class)->issue((int) $userId, json_decode($payload, true, flags: JSON_THROW_ON_ERROR)),
        'dispatch' => app(App\Invoices\SimulatedTransport::class)->dispatch($payload),
        'recover' => app(App\Invoices\SimulatedTransport::class)->recover($payload),
        default => throw new RuntimeException('Accion de prueba no permitida.'),
    };
    echo json_encode(['pid' => getmypid(), 'started' => $started, 'ended' => microtime(true),
        'id' => $invoice?->id, 'number' => $invoice?->number, 'conflict' => false], JSON_THROW_ON_ERROR);
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo json_encode(['pid' => getmypid(), 'started' => $started, 'ended' => microtime(true), 'conflict' => true], JSON_THROW_ON_ERROR);
}
