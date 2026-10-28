<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// Laravel registra un handler global que puede imprimir el error sin un exit != 0 en este script CLI.
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, ($error->getFile() === __FILE__ ? $error->getMessage() : 'Fallo de preservacion; revisar el entorno privado.').PHP_EOL);
    exit(1);
});
if (!app()->environment('local') || config('database.connections.pgsql.host') !== 'postgres') {
    throw new RuntimeException('Preservacion solo Compose ficticio.');
}
$snapshot = ['rows' => [], 'files' => []];
foreach (['emisor_h1', 'emisor_h2_test'] as $database) {
    config(['database.connections.pgsql.database' => $database]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    foreach (['users', 'invoices', 'invoice_outbox', 'simulation_receipts', 'invoice_transport_events',
        'h4_boletas', 'h4_summaries', 'h4_members', 'h4_outbox', 'h4_simulation_receipts', 'h4_events',
        'h5_notes', 'h5_outbox', 'h5_receipts', 'h5_events', 'h5b_notes', 'h5b_summaries', 'h5b_members', 'h5b_outbox', 'h5b_simulation_receipts', 'h5b_events', 'h5c1_grants', 'h5c1_batches', 'h5c1_members', 'h5c1_outbox', 'h5c1_simulation_receipts', 'h5c1_events', 'h5c2_h4_corrections', 'h5c2_h5b_corrections', 'h5c2_void_corrections', 'h5c2_invoice_reissues'] as $table) {
        if (!Illuminate\Support\Facades\Schema::hasTable($table)) { continue; }
        foreach (Illuminate\Support\Facades\DB::table($table)->get() as $row) {
            $id = $row->original_id ?? $row->id ?? ($row->document_id ?? ($row->boleta_id ?? ($row->note_id ?? ($row->batch_id ?? ($row->summary_id ?? $row->invoice_id)))));
            $snapshot['rows'][$database][$table][$id] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        }
    }
}
if (($argv[1] ?? '') === 'after') {
    // Comparar cada ruta protegida, sin depender de que una nueva enumeracion del montaje la liste.
    $protected = json_decode(file_get_contents(storage_path('audit/h5c2/preservation-before.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($protected['files'] as $relative => $hash) {
        if (str_starts_with($relative, '/') || str_contains($relative, '..') || str_contains($relative, '\\')) { throw new RuntimeException('Ruta del baseline invalida.'); }
        $filePath = storage_path($relative); $ancestor = $filePath;
        while ($ancestor !== storage_path()) {
            if (is_link($ancestor)) { throw new RuntimeException('Ruta protegida enlazada: '.$relative); }
            $ancestor = dirname($ancestor);
        }
        if (!is_file($filePath)) { throw new RuntimeException('Evidencia previa ausente: '.$relative); }
        $snapshot['files'][$relative] = hash_file('sha256', $filePath);
    }
} else foreach (['app', 'framework/testing/h2', 'framework/testing/h4', 'framework/testing/h5', 'audit/h2', 'audit/h3', 'audit/h4', 'audit/h4-d07', 'audit/h5-preparacion-20261026', 'audit/h5a', 'framework/testing/h5b', 'audit/h5b', 'audit/h5c-preparacion', 'framework/testing/h5c1', 'audit/h5c1', 'audit/h5c-preparacion'] as $directory) {
    $root = storage_path($directory);
    if (!is_dir($root)) { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && !$file->isLink()) {
            $snapshot['files'][substr($file->getPathname(), strlen(storage_path()) + 1)] = hash_file('sha256', $file->getPathname());
        }
    }
}
$directory = storage_path('audit/h5c2');
if (!is_dir($directory)) { mkdir($directory, 0700, true); }
$path = $directory.'/preservation-before.json';
if (($argv[1] ?? '') === 'before') {
    $handle = fopen($path, 'x');
    if (!$handle) { throw new RuntimeException('Snapshot previo protegido.'); }
    fwrite($handle, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); fclose($handle);
} elseif (($argv[1] ?? '') === 'after') {
    $before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    foreach ($before['rows'] as $db => $tables) {
        foreach ($tables as $table => $rows) {
            foreach ($rows as $id => $hash) {
                if (($snapshot['rows'][$db][$table][$id] ?? null) !== $hash) { throw new RuntimeException('Fila previa alterada: '.$table); }
            }
        }
    }
    foreach ($before['files'] as $file => $hash) {
        if (($snapshot['files'][$file] ?? null) !== $hash) { throw new RuntimeException('Evidencia previa alterada: '.$file); }
    }
} else { throw new RuntimeException('Usar before/after.'); }
echo json_encode(['mode' => $argv[1], 'preserved' => true, 'files' => count($snapshot['files']),
    'rows' => array_map(fn ($tables) => array_map('count', $tables), $snapshot['rows'])], JSON_PRETTY_PRINT).PHP_EOL;
