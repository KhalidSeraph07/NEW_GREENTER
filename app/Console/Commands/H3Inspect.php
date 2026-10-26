<?php

namespace App\Console\Commands;

use App\Invoices\EvidenceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class H3Inspect extends Command
{
    protected $signature = 'h3:inspect {--evidence : Revisar archivos privados sin modificarlos}';
    protected $description = 'Incidentes y alertas locales H3; sin notificaciones externas';

    public function handle(EvidenceReconciler $reconciler): int
    {
        $query = DB::table('invoice_outbox')->whereIn('status', ['uncertain', 'exhausted'])->orWhere(function ($q) {
            $q->where('status', 'processing')->where('reserved_until', '<=', now('UTC'));
        });
        $count = (clone $query)->count();
        $report = ['simulated' => true, 'attention_count' => $count,
            'incidents' => $query->orderBy('id')->limit(100)->get(['invoice_id', 'status', 'attempts', 'reserved_until']),
            'limit' => 100];
        if ($this->option('evidence')) { $report['evidence'] = $reconciler->inspect(); }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $count > 0 || !empty($report['evidence']['issues']) ? self::FAILURE : self::SUCCESS;
    }
}
