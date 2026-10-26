<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.database') !== 'emisor_h2_test') {
    throw new RuntimeException('Proceso limitado a la BD de pruebas.');
}
[$script, $action, $payload, $marker] = $argv;
$root = storage_path('framework/testing/');
if (!str_starts_with($marker, $root) || !preg_match('/\Ah3-[0-9a-f-]{36}\z/', substr($marker, strlen($root)))) {
    throw new RuntimeException('Marcador de prueba no permitido.');
}
$pause = function () use ($marker) {
    file_put_contents($marker, (string) getmypid());
    $deadline = microtime(true) + 30;
    while (!is_file($marker.'.release')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Pausa de prueba expirada.'); }
        usleep(10000);
    }
};
if ($action === 'issue') {
    Illuminate\Support\Facades\DB::listen(function ($event) use ($pause) {
        if (str_contains($event->sql, 'insert into "invoice_outbox"')) { $pause(); }
    });
    $input = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    $invoice = app(App\Invoices\InvoiceIssuer::class)->issue($input['user_id'], $input['input']);
} elseif ($action === 'hold_series') {
    Illuminate\Support\Facades\DB::transaction(function () use ($pause) {
        Illuminate\Support\Facades\DB::table('invoice_series')->where('series', 'F000')->lockForUpdate()->sole();
        $pause();
    });
    exit(0);
} else {
    Illuminate\Support\Facades\Event::listen($action === 'claim' ? 'h3.transport.claimed' : 'h3.simulation.recorded', $pause);
    $invoice = app(App\Invoices\SimulatedTransport::class)->dispatch($payload);
}
echo json_encode(['id' => $invoice?->id, 'transport_status' => $invoice?->transport_status], JSON_THROW_ON_ERROR);
