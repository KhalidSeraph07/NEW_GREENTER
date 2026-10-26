<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.database') !== 'emisor_h2_test') { throw new RuntimeException('Solo BD ficticia testing.'); }
[$script,$action,$user,$payload,$barrier,$pauseAt] = $argv;
$root = storage_path('framework/testing/h5b-barriers/');
if (!str_starts_with($barrier,$root) || !preg_match('/\A[0-9a-f-]{36}\z/',substr($barrier,strlen($root)))) { throw new RuntimeException('Barrera H5 invalida.'); }
$pause = function () use ($barrier) {
    file_put_contents($barrier.'/paused',(string) getmypid()); $end = microtime(true)+40;
    while (!is_file($barrier.'/release')) { if (microtime(true)>$end) { throw new RuntimeException('Pausa expirada.'); } usleep(10000); }
};
if ($pauseAt === 'prepared') {
    Illuminate\Support\Facades\DB::listen(function ($query) use ($pause) { if (str_contains($query->sql,'insert into "h5b_outbox"')) { $pause(); } });
} elseif ($pauseAt === 'note_prepared') { Illuminate\Support\Facades\Event::listen('h5b.note.prepared',$pause); } elseif ($pauseAt !== '') { Illuminate\Support\Facades\Event::listen($pauseAt,$pause); }
file_put_contents($barrier.'/ready-'.getmypid(),'ready'); $end=microtime(true)+40;
while (!is_file($barrier.'/start')) { if (microtime(true)>$end) { throw new RuntimeException('Barrera expirada.'); } usleep(10000); }
$input=json_decode($payload,true,flags:JSON_THROW_ON_ERROR); $started=microtime(true);
try {
    $n=match ($action) {
        'issue'=>app(App\BoletaNotes\Preparation::class)->issue((int) $user,$input),
        'freeze'=>app(App\BoletaNotes\SummaryPreparation::class)->freeze((int) $user,$input),
        'send'=>app(App\BoletaNotes\SummaryTransport::class)->send($input['id'],$input['scenario'] ?? 'accepted'),
        'poll'=>app(App\BoletaNotes\SummaryTransport::class)->poll($input['id'],$input['scenario'] ?? '0'),
        'recover'=>app(App\BoletaNotes\SummaryTransport::class)->recover($input['id']),
        default=>throw new RuntimeException('Accion no permitida.'),
    };
    echo json_encode(['pid'=>getmypid(),'started'=>$started,'ended'=>microtime(true),'id'=>$n?->id,'number'=>$n?->number,'state'=>$n?->state,'conflict'=>false],JSON_THROW_ON_ERROR);
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo json_encode(['pid'=>getmypid(),'started'=>$started,'ended'=>microtime(true),'conflict'=>true],JSON_THROW_ON_ERROR);
}
