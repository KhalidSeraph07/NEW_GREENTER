<?php

declare(strict_types=1);

namespace App\BoletaNotes;

use App\Invoices\InvoiceInput;
use App\Models\Boleta;
use App\Models\BoletaNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class Preparation
{
    public function __construct(private Original $original, private InvoiceInput $input, private Xml $xml, private Evidence $files) {}

    public function issue(int $user, array $input): BoletaNote
    {
        if (config('h5b.mode') !== 'fictitious' || config('h4.mode') !== 'fictitious' || DB::transactionLevel() !== 0) {
            throw new \LogicException('H5-B solo ficticio y sin transaccion externa.');
        }
        if (array_diff(array_keys($input), ['idempotency_key','original_id','operation','reason','emission_date','original_granted_test','increment_unit_price','_token'])) {
            throw ValidationException::withMessages(['note' => 'Solo campos H5-B; receptor y moneda se heredan del original.']);
        }
        if (is_string($input['reason'] ?? null)) { $input['reason'] = trim($input['reason']); }
        $v = Validator::make($input, ['idempotency_key' => 'required|uuid', 'original_id' => 'required|uuid',
            'operation' => 'required|in:NC01,ND02', 'reason' => ['required','string','min:3','max:250','not_regex:/[\x00-\x1F]|\]\]>/u'],
            'emission_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'original_granted_test' => 'required|accepted'])->validate();
        $v['idempotency_key'] = strtolower($v['idempotency_key']); $v['original_id'] = strtolower($v['original_id']);
        $increment = $input['increment_unit_price'] ?? null;
        if ($v['operation'] === 'NC01' && $increment !== null && $increment !== '') {
            throw ValidationException::withMessages(['increment_unit_price' => 'NC 01 completa no admite importes editables.']);
        }
        if ($v['operation'] === 'ND02') {
            $increment = $this->input->validate(['idempotency_key' => $v['idempotency_key'], 'description' => 'INCREMENTO FICTICIO',
                'quantity' => '1', 'unit_price' => $increment])['unit_price_cents'];
        } else { $increment = null; }
        $request = ['original_id' => $v['original_id'], 'operation' => $v['operation'], 'reason' => $v['reason'],
            'emission_date' => $v['emission_date'], 'original_granted_test' => true, 'increment_unit_price_cents' => $increment];
        $hash = \App\Notes\Original::hash($request); $id = null; $written = false; $committing = false;
        try {
            return DB::transaction(function () use ($user, $v, $request, $hash, &$id, &$written, &$committing) {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['h5b-note:'.$user.':'.$v['idempotency_key']]);
                // Lock del original antes de los contadores: NC y ND compiten por la misma referencia.
                $boleta = Boleta::query()->where('user_id', $user)->lockForUpdate()->findOrFail($v['original_id']);
                $prior = BoletaNote::query()->where('user_id', $user)->where('idempotency_key', $v['idempotency_key'])->first();
                if ($prior) {
                    if (!hash_equals($prior->payload_hash, $hash)) { throw new ConflictHttpException('Clave ya usada con otro contenido.'); }
                    $this->files->verify($prior->id, $prior->artifacts); $this->original->verify($prior);
                    return $prior;
                }
                app(\App\Voids\Grant::class)->assertNoteAllowed('boleta', $boleta->id);
                $this->original->eligible($boleta);
                if (BoletaNote::query()->where('original_id', $boleta->id)->exists()) { throw new ConflictHttpException('Original ya reservado por una nota; no se libera automaticamente.'); }
                if ($v['emission_date'] < $boleta->emission_date
                    || $v['emission_date'] < now('America/Lima')->subDays(7)->toDateString()) {
                    throw ValidationException::withMessages(['emission_date' => 'Fecha anterior al original o fuera de D+7.']);
                }
                $source = $boleta->snapshot;
                $values = $v['operation'] === 'NC01' ? array_intersect_key($source, array_flip(['description','quantity_milli','unit_price_cents','subtotal_cents','tax_cents','total_cents']))
                    : $this->input->validate(['idempotency_key' => $v['idempotency_key'], 'description' => $source['description'],
                        'quantity' => InvoiceInput::decimal($source['quantity_milli'], 3), 'unit_price' => InvoiceInput::decimal($request['increment_unit_price_cents'])]);
                $type = $v['operation'] === 'NC01' ? '07' : '08'; $series = $type === '07' ? 'BC00' : 'BD00';
                $counter = DB::table('h5b_counters')->where('document_type', $type)->lockForUpdate()->sole();
                if ($counter->next_number > 99999999) { throw new ConflictHttpException('Correlativo ficticio agotado.'); }
                $id = (string) Str::uuid(); $frozen = $this->original->frozen($boleta);
                $snapshot = $values + $request + ['mode' => 'fictitious', 'document_type' => $type,
                    'reason_code' => $type === '07' ? '01' : '02', 'series' => $series, 'number' => (int) $counter->next_number,
                    'currency' => 'PEN', 'original' => $frozen];
                $manifest = $this->files->save($id, '00000000000-'.$type.'-'.$series.'-'.$snapshot['number'], $this->xml->note($snapshot));
                $written = true;
                $note = BoletaNote::query()->create(['id' => $id, 'user_id' => $user, 'original_id' => $boleta->id,
                    'idempotency_key' => $v['idempotency_key'], 'payload_hash' => $hash, 'original_hash' => \App\Notes\Original::hash($frozen),
                    'document_type' => $type, 'reason_code' => $snapshot['reason_code'], 'series' => $series, 'number' => $snapshot['number'],
                    'emission_date' => $v['emission_date'], 'snapshot' => $snapshot, 'artifacts' => $manifest, 'state' => 'prepared']);
                \Illuminate\Support\Facades\Event::dispatch('h5b.note.prepared', [$id]);
                DB::table('h5b_counters')->where('document_type', $type)->update(['next_number' => $counter->next_number + 1]);
                $committing = true;
                return $note;
            });
        } catch (Throwable $error) {
            // Si se pierde confirmacion del COMMIT, conservar evidencia para replay/reconciliacion.
            if ($id !== null && $written && !$committing) { $this->files->discardOwn($id); }
            throw $error;
        }
    }
}
