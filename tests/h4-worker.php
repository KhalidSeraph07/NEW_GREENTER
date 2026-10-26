<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.database') !== 'emisor_h2_test') {
    throw new RuntimeException('Proceso H4 solo admite emisor_h2_test.');
}
[$script, $action, $user, $payload, $barrier, $pauseAt] = $argv;
$root = storage_path('framework/testing/h4-barriers/');
if (!str_starts_with($barrier, $root) || !preg_match('/\A[0-9a-f-]{36}\z/', substr($barrier, strlen($root)))) {
    throw new RuntimeException('Barrera H4 invalida.');
}
$pause = function () use ($barrier) {
    file_put_contents($barrier.'/paused', (string) getmypid());
    $end = microtime(true) + 30;
    while (!is_file($barrier.'/release')) {
        if (microtime(true) > $end) { throw new RuntimeException('Pausa H4 expirada.'); }
        usleep(10000);
    }
};
if ($pauseAt === 'prepared') {
    Illuminate\Support\Facades\DB::listen(function ($query) use ($pause) {
        if (str_contains($query->sql, 'insert into "h4_outbox"')) { $pause(); }
    });
} elseif ($pauseAt !== '') { Illuminate\Support\Facades\Event::listen($pauseAt, $pause); }
file_put_contents($barrier.'/ready-'.getmypid(), 'ready');
$end = microtime(true) + 30;
while (!is_file($barrier.'/start')) {
    if (microtime(true) > $end) { throw new RuntimeException('Barrera H4 no liberada.'); }
    usleep(10000);
}
$started = microtime(true);
try {
    $result = match ($action) {
        'issue' => app(App\Boletas\Preparation::class)->issue((int) $user, json_decode($payload, true, flags: JSON_THROW_ON_ERROR)),
        'freeze' => app(App\Boletas\Preparation::class)->freeze((int) $user, json_decode($payload, true, flags: JSON_THROW_ON_ERROR)),
        'send' => app(App\Boletas\SummaryTransport::class)->send($payload),
        'poll' => app(App\Boletas\SummaryTransport::class)->poll($payload),
        'recover' => app(App\Boletas\SummaryTransport::class)->recover($payload),
        default => throw new RuntimeException('Accion H4 no permitida.'),
    };
    echo json_encode(['pid' => getmypid(), 'started' => $started, 'ended' => microtime(true), 'id' => $result?->id,
        'number' => $result?->number, 'state' => $result?->state, 'ticket' => $result?->ticket, 'conflict' => false], JSON_THROW_ON_ERROR);
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo json_encode(['pid' => getmypid(), 'started' => $started, 'ended' => microtime(true), 'conflict' => true], JSON_THROW_ON_ERROR);
}
