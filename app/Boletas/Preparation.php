<?php

declare(strict_types=1);

namespace App\Boletas;

use App\Invoices\InvoiceInput;
use App\Models\Boleta;
use App\Models\DailySummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class Preparation
{
    public function __construct(private InvoiceInput $input, private FictitiousXml $xml, private Artifacts $files) {}

    public function issue(int $user, array $input): Boleta
    {
        $date = Validator::make($input, ['emission_date' => 'required|date_format:Y-m-d|before_or_equal:today'])->validate()['emission_date'];
        unset($input['emission_date']);
        $values = $this->input->validate($input) + ['emission_date' => $date, 'issuer_ruc' => '00000000000', 'document_type' => '03', 'series' => 'B000'];
        $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        return $this->create(function (&$id, &$written, &$committing) use ($user, $input, $values, $hash) {
            $counter = DB::table('h4_counters')->where('series', 'B000')->lockForUpdate()->sole();
            $existing = Boleta::query()->where('user_id', $user)->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing) { return $this->replay($existing, $hash); }
            $id = (string) Str::uuid(); $snapshot = $values + ['number' => (int) $counter->next_number];
            $manifest = $this->files->save($id, '00000000000-03-B000-'.$snapshot['number'], $this->xml->boleta($snapshot));
            $written = true;
            $receipt = Boleta::query()->create(['id' => $id, 'user_id' => $user, 'idempotency_key' => $input['idempotency_key'],
                'payload_hash' => $hash, 'number' => $snapshot['number'], 'emission_date' => $values['emission_date'], 'snapshot' => $snapshot, 'artifacts' => $manifest]);
            DB::table('h4_counters')->where('series', 'B000')->update(['next_number' => $counter->next_number + 1]);
            $committing = true;
            return $receipt;
        });
    }

    public function freeze(int $user, array $input): DailySummary
    {
        if (array_diff(array_keys($input), ['idempotency_key', 'members', '_token'])) {
            throw ValidationException::withMessages(['summary' => 'Solo identidad y miembros explicitos.']);
        }
        $values = Validator::make($input, ['idempotency_key' => 'required|uuid', 'members' => 'required|array|min:1|max:100',
            'members.*' => 'required|uuid|distinct'])->validate();
        sort($values['members']);
        $hash = hash('sha256', json_encode($values['members'], JSON_THROW_ON_ERROR));
        return $this->create(function (&$id, &$written, &$committing) use ($user, $values, $hash) {
            DB::table('h4_counters')->where('series', 'B000')->lockForUpdate()->sole();
            $existing = DailySummary::query()->where('user_id', $user)->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) { return $this->replay($existing, $hash); }
            $receipts = Boleta::query()->where('user_id', $user)->whereIn('id', $values['members'])->orderBy('id')->get();
            if ($receipts->count() !== count($values['members']) || $receipts->pluck('emission_date')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['members' => 'Miembros propios de una misma fecha requeridos.']);
            }
            if (DB::table('h4_members')->whereIn('boleta_id', $values['members'])->exists()) {
                throw new ConflictHttpException('Miembro ya reservado; no se informa otra vez por un RC nuevo.');
            }
            $date = $receipts->first()->emission_date;
            $today = now('America/Lima')->toDateString();
            if ($date > $today || $date < now('America/Lima')->subDays(7)->toDateString()) {
                throw ValidationException::withMessages(['members' => 'Fuera de ventana D+7 del ensayo.']);
            }
            $counter = DB::table('h4_counters')->where('series', 'RC')->lockForUpdate()->sole();
            $id = (string) Str::uuid();
            $snapshot = ['reference_date' => $date, 'issue_date' => $today, 'number' => (int) $counter->next_number,
                'name' => '00000000000-RC-'.str_replace('-', '', $today).'-'.$counter->next_number,
                'members' => $receipts->map(fn ($r) => ['id' => $r->id, 'number' => $r->number, 'emission_date' => $r->emission_date,
                    'subtotal_cents' => $r->snapshot['subtotal_cents'], 'tax_cents' => $r->snapshot['tax_cents'], 'total_cents' => $r->snapshot['total_cents']])->all()];
            foreach ($receipts as $receipt) { $this->files->verify($receipt->id, $receipt->artifacts); }
            $manifest = $this->files->save($id, $snapshot['name'], $this->xml->summary($snapshot)); $written = true;
            $summary = DailySummary::query()->create(['id' => $id, 'user_id' => $user, 'idempotency_key' => $values['idempotency_key'],
                'payload_hash' => $hash, 'name' => $snapshot['name'], 'reference_date' => $date, 'issue_date' => $today,
                'snapshot' => $snapshot, 'artifacts' => $manifest, 'state' => 'pending_send']);
            foreach ($receipts as $position => $receipt) {
                DB::table('h4_members')->insert(['summary_id' => $id, 'boleta_id' => $receipt->id, 'position' => $position + 1]);
            }
            DB::table('h4_outbox')->insert(['summary_id' => $id, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            DB::table('h4_counters')->where('series', 'RC')->update(['next_number' => $counter->next_number + 1]);
            $committing = true;
            return $summary;
        });
    }

    private function replay(Boleta|DailySummary $record, string $hash): Boleta|DailySummary
    {
        if (!hash_equals($record->payload_hash, $hash)) { throw new ConflictHttpException('Clave reutilizada con otro contenido.'); }
        $this->files->verify($record->id, $record->artifacts);
        return $record;
    }

    private function create(callable $operation): Boleta|DailySummary
    {
        $id = null; $written = false; $committing = false;
        if (config('h4.mode') !== 'fictitious' || DB::transactionLevel() !== 0) { throw new \RuntimeException('Solo ensayo H4 ficticio, sin transaccion externa.'); }
        try { return DB::transaction(function () use ($operation, &$id, &$written, &$committing) { return $operation($id, $written, $committing); }); }
        catch (Throwable $error) {
            if ($id !== null && $written && !$committing) { $this->files->discardOwn($id); }
            throw $error;
        }
    }
}
