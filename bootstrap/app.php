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

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([InvoicePoc::class, H2Setup::class, H2Dispatch::class, H3Recover::class, H3Inspect::class, H4Process::class, \App\Console\Commands\H5Process::class, \App\Console\Commands\H5C1Process::class])
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['h2.operator' => LocalOperator::class]);
    })
    ->withExceptions()
    ->create();
