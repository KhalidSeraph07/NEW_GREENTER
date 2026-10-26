<?php

namespace App\Console\Commands;

use App\Models\InvoiceNote;
use App\Notes\Evidence;
use App\Notes\Transport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class H5Process extends Command
{
    protected $signature = 'h5:process {action} {id?} {--scenario=accepted}';
    protected $description = 'Solo H5-A ficticio: dispatch/recover por UUID e inspect sin borrado';

    public function handle(Transport $transport, Evidence $files): int
    {
        if (config('h5.mode') !== 'fictitious' || !app()->environment(['local','testing'])) { throw new \RuntimeException('Solo entorno ficticio.'); }
        $action = $this->argument('action'); $id = $this->argument('id');
        if ($action === 'inspect') {
            $report = DB::transaction(function () use ($files) {
                DB::table('h5_counters')->orderBy('document_type')->lock('FOR UPDATE NOWAIT')->get();
                $issues = [];
                foreach (InvoiceNote::query()->get() as $n) {
                    try { $files->verify($n); app(\App\Notes\Original::class)->verify($n); }
                    catch (\Throwable) { $issues[] = ['id'=>$n->id,'issue'=>'evidence_or_original_mismatch']; }
                    if (in_array($n->state,['uncertain','sim_rejected','failed','expired'],true)) { $issues[] = ['id'=>$n->id,'issue'=>$n->state]; }
                }
                foreach (glob(rtrim(config('h5.evidence_root'),'/').'/*',GLOB_ONLYDIR) ?: [] as $dir) {
                    if (!InvoiceNote::query()->where('id',basename($dir))->exists()) { $issues[] = ['id'=>basename($dir),'issue'=>'orphan_candidate']; }
                }
                return ['simulated'=>true,'official_cdr'=>false,'issues'=>$issues];
            });
            $this->line(json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); return $report['issues'] ? 1 : 0;
        }
        if (!Str::isUuid($id ?? '') || !in_array($action,['dispatch','recover'],true)) { throw new \RuntimeException('Accion/UUID H5-A invalido.'); }
        $n = $action === 'dispatch' ? $transport->dispatch($id,$this->option('scenario')) : $transport->recover($id);
        $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'id'=>$n?->id,'state'=>$n?->state],JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
