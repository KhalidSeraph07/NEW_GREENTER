<?php

declare(strict_types=1);

namespace App\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EvidenceReconciler
{
    public function __construct(private InvoiceEvidence $evidence) {}

    public function inspect(): array
    {
        if (config('h2.mode') !== 'fictitious') { throw new RuntimeException('Solo reconciliacion ficticia.'); }
        return DB::transaction(function () {
            // El emisor conserva este bloqueo desde antes de crear archivos hasta el commit.
            // NOWAIT evita diagnosticar como huerfana una creacion aun activa.
            DB::table('invoice_series')->where('series', 'F000')->lock('FOR UPDATE NOWAIT')->sole();
            $root = rtrim(config('h2.evidence_root'), '/');
            if (is_link($root)) { throw new RuntimeException('Raiz de evidencia enlazada: inspeccion detenida.'); }
            $issues = [];
            $checked = 0;
            foreach (Invoice::query()->orderBy('id')->cursor() as $invoice) {
                $checked++;
                foreach (['unsigned', 'xml', 'zip', 'certificate'] as $kind) {
                    try { $this->evidence->verifiedPath($invoice->id, $kind, $invoice->artifacts); }
                    catch (RuntimeException) { $issues[] = ['id' => $invoice->id, 'kind' => $kind, 'issue' => 'missing_or_changed']; }
                }
            }
            foreach (is_dir($root) ? scandir($root) : [] as $name) {
                if ($name === '.' || $name === '..') { continue; }
                if (is_link($root.'/'.$name) || !preg_match('/\A[0-9a-f-]{36}\z/', $name) || !is_dir($root.'/'.$name)) {
                    $issues[] = ['entry' => $name, 'issue' => 'unexpected_entry'];
                } elseif (!Invoice::query()->whereKey($name)->exists()) {
                    $issues[] = ['id' => $name, 'issue' => 'orphan_candidate'];
                }
            }
            return ['simulated' => true, 'read_only' => true, 'invoices_checked' => $checked, 'issues' => $issues,
                'message' => 'Solo investigacion local. No borra, repara ni confirma resultados fiscales.'];
        });
    }
}
