<?php

declare(strict_types=1);

namespace App\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class InvoiceIssuer
{
    public function __construct(private InvoiceInput $input, private InvoiceXml $xml, private InvoiceEvidence $evidence) {}

    public function issue(int $userId, array $input): Invoice
    {
        $values = $this->input->validate($input);
        $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        $createdId = null;
        $filesWritten = false;
        $readyToCommit = false;
        try {
            return DB::transaction(function () use ($userId, $input, $values, $hash, &$createdId, &$filesWritten, &$readyToCommit) {
                $series = DB::table('invoice_series')->where('series', 'F000')->lockForUpdate()->sole();
                $existing = Invoice::query()->where('user_id', $userId)->where('idempotency_key', $input['idempotency_key'])->first();
                if ($existing) {
                    if (!hash_equals($existing->payload_hash, $hash)) {
                        throw new ConflictHttpException('La clave de operación ya corresponde a otro contenido.');
                    }
                    return $existing;
                }
                $createdId = (string) Str::uuid();
                $snapshot = $values + [
                    'mode' => config('h2.mode'), 'issuer_ruc' => config('h2.issuer_ruc'),
                    'issuer_name' => config('h2.issuer_name'), 'receiver_ruc' => config('h2.receiver_ruc'),
                    'receiver_name' => config('h2.receiver_name'), 'series' => 'F000',
                    'number' => (int) $series->next_number, 'currency' => 'PEN', 'tax_percent' => 18,
                    'emitted_at' => now()->toIso8601String(),
                ];
                $manifest = $this->evidence->save($createdId, $this->xml->render($snapshot));
                $filesWritten = true;
                $invoice = Invoice::query()->create([
                    'id' => $createdId, 'user_id' => $userId, 'idempotency_key' => $input['idempotency_key'],
                    'payload_hash' => $hash, 'issuer_ruc' => '00000000000', 'document_type' => '01', 'series' => 'F000',
                    'number' => $snapshot['number'], 'snapshot' => $snapshot,
                    'subtotal_cents' => $values['subtotal_cents'], 'tax_cents' => $values['tax_cents'],
                    'total_cents' => $values['total_cents'], 'artifacts' => $manifest,
                    'transport_status' => 'pending_simulation',
                ]);
                DB::table('invoice_outbox')->insert([
                    'invoice_id' => $createdId, 'status' => 'pending', 'attempts' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('invoice_series')->where('series', 'F000')->update(['next_number' => $series->next_number + 1]);
                $readyToCommit = true;
                return $invoice;
            });
        } catch (Throwable $exception) {
            // Después de devolver el callback el COMMIT puede haber ocurrido, aunque falle su confirmación.
            if ($createdId !== null && $filesWritten && !$readyToCommit) {
                $this->evidence->discardUncommitted($createdId);
            }
            throw $exception;
        }
    }
}
