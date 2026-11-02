<?php

use App\Console\Commands\InvoicePoc;
use App\Console\Commands\H2Setup;
use App\Console\Commands\H2Dispatch;
use App\Console\Commands\H3Recover;
use App\Console\Commands\H3Inspect;
use App\Console\Commands\H4Process;
use App\Http\Middleware\LocalOperator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

// Una restauración incompleta no puede arrancar HTTP ni comandos que cambien operaciones.
$recoveryGate = __DIR__.'/../storage/framework/h72-state.json';
if (file_exists($recoveryGate)) {
    $recoveryState = json_decode((string) file_get_contents($recoveryGate), true);
    if (($recoveryState['status'] ?? null) !== 'verified'
        || (PHP_SAPI === 'cli' && ($_SERVER['argv'][1] ?? null) !== 'serve')) {
        if (PHP_SAPI === 'cli') { fwrite(STDERR, "Instalación H7-2 bloqueada; requiere conciliación supervisada.\n"); exit(1); }
        \App\Http\Middleware\LocalEnvironment::secure(new \Symfony\Component\HttpFoundation\Response('Restauración no verificada.', 503))->send();
        exit;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([\App\Console\Commands\BootstrapLocalAdmin::class, InvoicePoc::class, H2Setup::class, H2Dispatch::class, H3Recover::class, H3Inspect::class, H4Process::class, \App\Console\Commands\H5Process::class, \App\Console\Commands\H5C1Process::class, \App\Console\Commands\H5C2Process::class])
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\LocalEnvironment::class);
        $middleware->alias(['h2.operator' => LocalOperator::class]);
    })
    ->withExceptions(function (\Illuminate\Foundation\Configuration\Exceptions $exceptions): void {
        $exceptions->respond(fn ($response) => \App\Http\Middleware\LocalEnvironment::secure($response));
    })
    ->create();
