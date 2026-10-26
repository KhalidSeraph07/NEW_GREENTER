<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local') || config('database.connections.pgsql.host') !== 'postgres') {
    throw new RuntimeException('Snapshot limitado a Compose ficticio.');
}
$snapshot = ['rows' => [], 'files' => []];
foreach (['emisor_h1', 'emisor_h2_test'] as $database) {
    config(['database.connections.pgsql.database' => $database]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    foreach (['users', 'invoices', 'invoice_outbox', 'simulation_receipts', 'invoice_transport_events'] as $table) {
        foreach (Illuminate\Support\Facades\DB::table($table)->get() as $row) {
            $id = $row->id ?? $row->invoice_id;
            $snapshot['rows'][$database][$table][$id] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        }
    }
}
foreach (['app', 'framework/testing/h2', 'audit/h2', 'audit/h3', 'audit/h4-d07'] as $directory) {
    $root = storage_path($directory);
    if (!is_dir($root)) { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && !$file->isLink()) {
            $snapshot['files'][substr($file->getPathname(), strlen(storage_path()) + 1)] = hash_file('sha256', $file->getPathname());
        }
    }
}
$path = storage_path('audit/h4/preservation-before.json');
if (($argv[1] ?? '') === 'before') {
    if (file_exists($path)) { throw new RuntimeException('Snapshot anterior protegido.'); }
    file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} elseif (($argv[1] ?? '') === 'after') {
    $before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    foreach ($before['rows'] as $db => $tables) {
        foreach ($tables as $table => $rows) {
            foreach ($rows as $id => $hash) {
                if (($snapshot['rows'][$db][$table][$id] ?? null) !== $hash) { throw new RuntimeException('Fila preexistente alterada.'); }
            }
        }
    }
    foreach ($before['files'] as $file => $hash) {
        if (($snapshot['files'][$file] ?? null) !== $hash) { throw new RuntimeException('Evidencia preexistente alterada.'); }
    }
} else { throw new RuntimeException('Usar before/after.'); }
echo json_encode(['mode' => $argv[1], 'preserved' => true, 'files' => count($snapshot['files']),
    'rows' => array_map(fn ($tables) => array_map('count', $tables), $snapshot['rows'])], JSON_PRETTY_PRINT).PHP_EOL;
