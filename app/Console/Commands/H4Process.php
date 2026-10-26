<?php

namespace App\Console\Commands;

use App\Boletas\Artifacts;
use App\Boletas\SummaryTransport;
use App\Models\Boleta;
use App\Models\DailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class H4Process extends Command
{
    protected $signature = 'h4:process {action : send, poll, recover o inspect} {id? : UUID de RC ficticio} {--scenario=}';
    protected $description = 'Solo RC ficticio local, sin SUNAT ni CDR oficial; inspect nunca modifica archivos';

    public function handle(SummaryTransport $transport, Artifacts $files): int
    {
        $action = $this->argument('action'); $id = $this->argument('id');
        if ($action === 'inspect') {
            $issues = DB::transaction(function () use ($files) {
                DB::table('h4_counters')->where('series', 'B000')->lock('FOR UPDATE NOWAIT')->sole();
                $issues = []; $known = [];
                foreach ([Boleta::class, DailySummary::class] as $model) {
                    foreach ($model::query()->cursor() as $record) {
                        $known[$record->id] = true;
                        try { $files->verify($record->id, $record->artifacts); }
                        catch (RuntimeException) { $issues[] = ['id' => $record->id, 'issue' => 'evidence_missing_or_changed']; }
                    }
                }
                foreach (glob(rtrim(config('h4.evidence_root'), '/').'/*') ?: [] as $path) {
                    if (is_link($path) || !isset($known[basename($path)])) { $issues[] = ['id' => basename($path), 'issue' => 'orphan_or_unexpected']; }
                }
                return $issues;
            });
            $incidents = DailySummary::query()->where('state', 'uncertain')->orWhere('state', 'sim_rejected')->get(['id', 'state']);
            $this->line(json_encode(['simulated' => true, 'read_only' => true, 'issues' => $issues, 'incidents' => $incidents], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return $issues || $incidents->isNotEmpty() ? self::FAILURE : self::SUCCESS;
        }
        if (!Str::isUuid($id)) { throw new RuntimeException('UUID RC requerido.'); }
        $result = match ($action) {
            'send' => $transport->send($id, $this->option('scenario') ?: 'accepted'),
            'poll' => $transport->poll($id, $this->option('scenario') ?: '0'),
            'recover' => $transport->recover($id),
            default => throw new RuntimeException('Accion H4 no permitida.'),
        };
        $this->line(json_encode(['simulated' => true, 'official_cdr' => false, 'id' => $result?->id, 'state' => $result?->state, 'ticket' => $result?->ticket], JSON_THROW_ON_ERROR));
        return $result && $result->state !== 'uncertain' ? self::SUCCESS : self::FAILURE;
    }
}
