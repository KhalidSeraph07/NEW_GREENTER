<?php

// Sonda exclusiva del ensayo nuevo H7-3; nunca preparar ni inspeccionar la instalación original.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'Sonda H7-3 fallida; revisar el ensayo privado, sin imprimir secretos.'.PHP_EOL);
    exit(1);
});
if (!app()->environment('local') || config('h2.mode') !== 'fictitious'
    || config('h2.issuer_ruc') !== '00000000000'
    || config('database.connections.pgsql.host') !== 'postgres'
    || config('database.connections.pgsql.database') !== 'emisor_h1'
    || !preg_match('/\Aemisor_h73_[a-f0-9]{10}_[ab]\z/', config('session.cookie'))
    || !is_file(storage_path('framework/h73-test.json'))) {
    throw new RuntimeException('Fuera del ensayo H7-3.');
}
$v = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if ($v['mode'] === 'seed') {
    if (App\Models\User::query()->count() !== 1 || !str_ends_with($v['email'], '@example.invalid')) {
        throw new RuntimeException('Solo una instalación nueva.');
    }
    $u = app(App\Accounts\LocalAccounts::class)->create($v + [
        'name'=>'OPERADOR FICTICIO H73', 'role'=>'OPERADOR', 'password_confirmation'=>$v['password'],
    ]);
    echo json_encode(['id'=>$u->id]);
} elseif ($v['mode'] === 'snapshot') {
    $tables = [];
    foreach (Illuminate\Support\Facades\DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
        $rows = Illuminate\Support\Facades\DB::table($table->tablename)->get()->map(fn ($r)=>json_encode($r, JSON_THROW_ON_ERROR))->all();
        sort($rows, SORT_STRING);
        $tables[$table->tablename] = ['count'=>count($rows), 'sha256'=>hash('sha256', json_encode($rows))];
    }
    $sequences = Illuminate\Support\Facades\DB::select("SELECT sequencename, last_value FROM pg_sequences WHERE schemaname='public' ORDER BY sequencename");
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(storage_path('app'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink()) { throw new RuntimeException('Enlace no permitido.'); }
        if ($file->isFile()) { $files[substr($file->getPathname(), strlen(storage_path())+1)] = hash_file('sha256', $file->getPathname()); }
    }
    ksort($files);
    echo json_encode(['tables'=>$tables, 'sequences'=>$sequences, 'files'=>$files,
        'users'=>App\Models\User::query()->orderBy('id')->get(['id','email','role'])->toArray(),
        'invoices'=>App\Models\Invoice::query()->get(['id','user_id','number','transport_status'])->toArray()], JSON_THROW_ON_ERROR);
} elseif ($v['mode'] === 'boundaries') {
    $badPassword = false;
    try {
        new PDO('pgsql:host=postgres;port=5432;dbname=emisor_h1', config('database.connections.pgsql.username'), $v['peer_password']);
    } catch (PDOException $e) { $badPassword = str_contains($e->getMessage(), 'password authentication failed'); }
    $connection = @fsockopen($v['peer_ip'], 5432, $errno, $message, 2);
    if (is_resource($connection)) { fclose($connection); }
    echo json_encode(['peer_password_rejected'=>$badPassword, 'peer_database_unreachable'=>$connection === false]);
} else { throw new RuntimeException('Modo no permitido.'); }
