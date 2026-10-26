<?php

declare(strict_types=1);

namespace App\Notes;

use App\Invoices\InvoiceEvidence;
use App\Invoices\InvoiceInput;
use App\Models\Invoice;
use App\Models\InvoiceNote;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Original
{
    public function __construct(private InvoiceEvidence $files, private InvoiceInput $input) {}

    public function capture(Invoice $invoice): array
    {
        return $invoice->attributesToArray();
    }

    public function frozen(Invoice $invoice): array
    {
        $s = $this->capture($invoice);
        unset($s['created_at'], $s['updated_at'], $s['idempotency_key']);
        return $s;
    }

    public static function hash(array $value): string
    {
        $sort = function (array $v) use (&$sort): array {
            if (!array_is_list($v)) { ksort($v); }
            return array_map(fn ($x) => is_array($x) ? $sort($x) : $x, $v);
        };
        return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR));
    }

    public function eligible(Invoice $invoice): void
    {
        $s = $invoice->snapshot; $r = $invoice->simulated_response;
        if ($invoice->document_type !== '01' || $invoice->issuer_ruc !== '00000000000' || $invoice->series !== 'F000'
            || $invoice->transport_status !== 'simulated_accepted' || ($s['mode'] ?? null) !== 'fictitious'
            || ($s['issuer_ruc'] ?? null) !== '00000000000' || ($s['receiver_ruc'] ?? null) !== '00000000000'
            || ($s['currency'] ?? null) !== 'PEN' || ($s['tax_percent'] ?? null) !== 18
            || ($s['series'] ?? null) !== $invoice->series || ($s['number'] ?? null) !== $invoice->number
            || ($r['simulated'] ?? null) !== true || ($r['source'] ?? null) !== 'H2_LOCAL_FAKE'
            || ($r['invoice_id'] ?? null) !== $invoice->id || ($r['scenario'] ?? null) !== 'accepted'
            || ($r['code'] ?? null) !== 'SIM-ACCEPTED' || ($r['request_sha256'] ?? null) !== $invoice->artifacts['zip']['sha256']
            || DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('status') !== 'completed') {
            throw new ConflictHttpException('Original no elegible como factura aceptada ficticia.');
        }
        $computed = $this->input->validate(['idempotency_key' => $invoice->id, 'description' => $s['description'],
            'quantity' => InvoiceInput::decimal($s['quantity_milli'], 3), 'unit_price' => InvoiceInput::decimal($s['unit_price_cents'])]);
        foreach ($computed as $key => $value) {
            if (($s[$key] ?? null) !== $value || (str_ends_with($key, '_cents') && in_array($key, ['subtotal_cents','tax_cents','total_cents'], true) && $invoice->$key !== $value)) {
                throw new ConflictHttpException('Importes o item del original inconsistentes.');
            }
        }
        foreach (['xml', 'zip', 'unsigned', 'certificate'] as $kind) { $this->files->verifiedPath($invoice->id, $kind, $invoice->artifacts); }
    }

    public function verify(InvoiceNote $note): void
    {
        $invoice = Invoice::query()->findOrFail($note->original_id);
        $this->eligible($invoice);
        if (!hash_equals($note->original_hash, self::hash($this->frozen($invoice)))) {
            throw new ConflictHttpException('El original difiere de su referencia congelada; detener e investigar.');
        }
    }
}
