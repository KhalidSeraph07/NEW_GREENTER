<?php

namespace App\Console\Commands;

use App\BoletaNotes\Evidence;
use App\BoletaNotes\SummaryTransport;
use App\Models\BoletaNote;
use App\Models\NoteSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class H5BProcess extends Command
{
    protected $signature = 'h5b:process {action} {id?} {--scenario=}';
    protected $description = 'RC notas ficticio: send/poll/recover por UUID; inspect conserva evidencias';

    public function handle(SummaryTransport $transport, Evidence $files): int
    {
        if (config('h5b.mode') !== 'fictitious') { throw new \LogicException('Solo H5-B ficticio.'); }
        $action = $this->argument('action'); $id = $this->argument('id');
        if ($action === 'inspect') {
            $issues = [];
            foreach ([BoletaNote::class, NoteSummary::class] as $model) {
                foreach ($model::query()->cursor() as $record) {
                    try { $files->verify($record->id, $record->artifacts); }
                    catch (\RuntimeException) { $issues[] = ['id' => $record->id, 'issue' => 'evidence_missing_or_changed']; }
                }
            }
            foreach (glob(rtrim(config('h5b.evidence_root'), '/').'/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (!BoletaNote::query()->where('id', basename($dir))->exists() && !NoteSummary::query()->where('id', basename($dir))->exists()) {
                    $issues[] = ['id' => basename($dir), 'issue' => 'orphan_candidate'];
                }
            }
            foreach (DB::table('h5b_events')->where('attention', true)->orderBy('id')->get(['summary_id','kind']) as $e) {
                $issues[] = ['id' => $e->summary_id, 'issue' => $e->kind];
            }
            $this->line(json_encode(['simulated' => true, 'official_cdr' => false, 'issues' => $issues], JSON_THROW_ON_ERROR));
            return $issues ? 1 : 0;
        }
        if (!Str::isUuid($id ?? '') || !in_array($action, ['send','poll','recover'], true)) { throw new \RuntimeException('Accion/UUID H5-B invalido.'); }
        $rc = match ($action) {
            'send' => $transport->send($id, $this->option('scenario') ?: 'accepted'),
            'poll' => $transport->poll($id, $this->option('scenario') ?: '0'),
            'recover' => $transport->recover($id),
        };
        $this->line(json_encode(['simulated' => true, 'official_cdr' => false, 'id' => $rc?->id, 'state' => $rc?->state], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
