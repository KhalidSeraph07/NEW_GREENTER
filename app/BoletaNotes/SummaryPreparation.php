<?php

declare(strict_types=1);
namespace App\BoletaNotes;

use App\Models\BoletaNote;
use App\Models\NoteSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class SummaryPreparation
{
    public function __construct(private Xml $xml, private Evidence $files, private Original $original) {}

    public function freeze(int $user, array $input): NoteSummary
    {
        if (array_diff(array_keys($input), ['idempotency_key', 'members', '_token'])) {
            throw ValidationException::withMessages(['summary' => 'Solo identidad y miembros explicitos.']);
        }
        $values = Validator::make($input, ['idempotency_key' => 'required|uuid', 'members' => 'required|array|min:1|max:100',
            'members.*' => 'required|uuid|distinct'])->validate();
        $values['members'] = array_map('strtolower', $values['members']); $values['idempotency_key'] = strtolower($values['idempotency_key']);
        sort($values['members']);
        $hash = hash('sha256', json_encode($values['members'], JSON_THROW_ON_ERROR));
        return $this->create(function (&$id, &$written, &$committing) use ($user, $values, $hash) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['h5b-rc:'.$user.':'.$values['idempotency_key']]);
            $existing = NoteSummary::query()->where('user_id', $user)->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) { return $this->replay($existing, $hash); }
            $receipts = BoletaNote::query()->where('user_id', $user)->whereIn('id', $values['members'])->orderBy('id')->lockForUpdate()->get();
            if ($receipts->count() !== count($values['members']) || $receipts->pluck('emission_date')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['members' => 'Miembros propios de una misma fecha requeridos.']);
            }
            if (DB::table('h5b_members')->whereIn('note_id', $values['members'])->exists()) {
                throw new ConflictHttpException('Miembro ya reservado; no se informa otra vez por un RC nuevo.');
            }
            $date = $receipts->first()->emission_date;
            $today = now('America/Lima')->toDateString();
            if ($date > $today || $date < now('America/Lima')->subDays(7)->toDateString()) {
                throw ValidationException::withMessages(['members' => 'Fuera de ventana D+7 del ensayo.']);
            }
            $counter = DB::table('h4_counters')->where('series', 'RC')->lockForUpdate()->sole();
            if ($counter->next_number > 99999) { throw new ConflictHttpException('Correlativo RC ficticio agotado; requiere revision.'); }
            $id = (string) Str::uuid();
            $snapshot = ['reference_date' => $date, 'issue_date' => $today, 'number' => (int) $counter->next_number,
                'name' => '00000000000-RC-'.str_replace('-', '', $today).'-'.$counter->next_number,
                'members' => $receipts->map(fn ($n) => $this->member($n))->all()];
            foreach ($receipts as $n) { $this->verifyNote($n); }
            $manifest = $this->files->save($id, $snapshot['name'], $this->xml->summary($snapshot)); $written = true;
            $summary = NoteSummary::query()->create(['id' => $id, 'user_id' => $user, 'idempotency_key' => $values['idempotency_key'],
                'payload_hash' => $hash, 'name' => $snapshot['name'], 'reference_date' => $date, 'issue_date' => $today,
                'snapshot' => $snapshot, 'artifacts' => $manifest, 'state' => 'pending_send']);
            foreach ($receipts as $position => $receipt) {
                DB::table('h5b_members')->insert(['summary_id' => $id, 'note_id' => $receipt->id, 'position' => $position + 1]);
            }
            DB::table('h5b_outbox')->insert(['summary_id' => $id, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            DB::table('h4_counters')->where('series', 'RC')->update(['next_number' => $counter->next_number + 1]);
            $committing = true;
            return $summary;
        });
    }

    public function member(BoletaNote $n): array
    {
        return ['id' => $n->id, 'document_type' => $n->document_type, 'series' => $n->series, 'number' => $n->number,
            'emission_date' => $n->emission_date, 'original_type' => '03', 'original_number' => 'B000-'.$n->snapshot['original']['number'],
            'original_hash' => $n->original_hash, 'note_hash' => \App\Notes\Original::hash($n->snapshot),
            'xml_hash' => $n->artifacts['xml']['sha256'], 'subtotal_cents' => $n->snapshot['subtotal_cents'],
            'tax_cents' => $n->snapshot['tax_cents'], 'total_cents' => $n->snapshot['total_cents']];
    }
    public function verifyNote(BoletaNote $n): void { $this->files->verify($n->id, $n->artifacts); $this->original->verify($n); }
    private function replay(NoteSummary $record, string $hash): NoteSummary
    {
        if (!hash_equals($record->payload_hash, $hash)) { throw new ConflictHttpException('Clave reutilizada con otros miembros.'); }
        $this->files->verify($record->id, $record->artifacts);
        foreach ($record->snapshot['members'] as $m) {
            $n = BoletaNote::query()->findOrFail($m['id']); $this->verifyNote($n);
            if (\App\Notes\Original::hash($m) !== \App\Notes\Original::hash($this->member($n))) { throw new \LogicException('Miembro alterado.'); }
        }
        return $record;
    }
    private function create(callable $operation): NoteSummary
    {
        $id = null; $written = false; $committing = false;
        if (config('h5b.mode') !== 'fictitious' || DB::transactionLevel() !== 0) { throw new \LogicException('Solo H5-B ficticio sin transaccion externa.'); }
        try { return DB::transaction(function () use ($operation, &$id, &$written, &$committing) { return $operation($id, $written, $committing); }); }
        catch (Throwable $e) { if ($id && $written && !$committing) { $this->files->discardOwn($id); } throw $e; }
    }
}
