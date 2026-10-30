<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.database')!=='emisor_h2_test') { throw new RuntimeException('Solo testing local.'); }
[$script,$action,$input,$barrier]=$argv;
if (!str_starts_with($barrier,storage_path('framework/testing/h6-')) || !is_dir($barrier)) { throw new RuntimeException('Barrera inválida.'); }
file_put_contents($barrier.'/'.getmypid(),(string)Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid);
try {
    $accounts=app(App\Accounts\LocalAccounts::class);
    if ($action==='deactivate') { $accounts->deactivate((int)$input); }
    else { $accounts->create(['name'=>'Carrera ficticia','email'=>$input,'role'=>'OPERADOR',
        'password'=>'H6-Test-Only-Password-2468','password_confirmation'=>'H6-Test-Only-Password-2468']); }
    echo json_encode(['completed'=>true]);
} catch (Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo json_encode(['completed'=>false]);
}
