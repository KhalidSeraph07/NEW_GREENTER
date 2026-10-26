<?php

// Snapshot de lectura: compara filas y bytes preexistentes; nunca restaura ni borra.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local') || config('database.connections.pgsql.host') !== 'postgres') {
    throw new RuntimeException('Solo Compose ficticio local.');
}
$path = storage_path('audit/h3/preservation-before.json');
$snapshot = ['databases' => [], 'files' => []];
foreach (['emisor_h1', 'emisor_h2_test'] as $database) {
    config(['database.connections.pgsql.database' => $database]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    foreach (Illuminate\Support\Facades\DB::table('invoices')->orderBy('id')->get() as $row) {
        $snapshot['databases'][$database][$row->id] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }
}
foreach (['app/private/h2', 'framework/testing/h2'] as $directory) {
    $root = storage_path($directory);
    if (!is_dir($root)) { continue; }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isLink()) {
            $relative = substr($file->getPathname(), strlen(storage_path()) + 1);
            $snapshot['files'][$relative] = hash_file('sha256', $file->getPathname());
        }
    }
}
if (($argv[1] ?? '') === 'before') {
    if (file_exists($path)) { throw new RuntimeException('No sobrescribir snapshot anterior.'); }
    file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} elseif (($argv[1] ?? '') === 'after') {
    $before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    foreach ($before['databases'] as $database => $rows) {
        foreach ($rows as $id => $hash) {
            if (($snapshot['databases'][$database][$id] ?? null) !== $hash) {
                throw new RuntimeException('Factura preexistente cambió: '.$id);
            }
        }
    }
    foreach ($before['files'] as $file => $hash) {
        if (($snapshot['files'][$file] ?? null) !== $hash) { throw new RuntimeException('Evidencia preexistente cambió.'); }
    }
} else { throw new RuntimeException('Usar before o after.'); }
echo json_encode(['mode' => $argv[1], 'preserved' => true,
    'invoice_counts' => array_map('count', $snapshot['databases']), 'files' => count($snapshot['files'])], JSON_PRETTY_PRINT).PHP_EOL;
