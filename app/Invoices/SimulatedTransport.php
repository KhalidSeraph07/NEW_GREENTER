<?php

declare(strict_types=1);

namespace App\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SimulatedTransport
{
    public function __construct(private InvoiceEvidence $evidence) {}

    public function dispatch(?string $invoiceId = null, string $scenario = 'accepted'): ?Invoice
    {
        if (!in_array($scenario, ['accepted', 'rejected', 'uncertain'], true)) {
            throw ValidationException::withMessages(['scenario' => 'Respuesta simulada no permitida.']);
        }
        return DB::transaction(function () use ($invoiceId, $scenario) {
            $query = DB::table('invoice_outbox')->where('status', 'pending');
            if ($invoiceId !== null) {
                $query->where('invoice_id', $invoiceId);
            }
            $job = $query->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if (!$job) {
                return null;
            }
            $invoice = Invoice::query()->findOrFail($job->invoice_id);
            foreach (['xml', 'zip', 'certificate'] as $kind) {
                $this->evidence->verifiedPath($invoice->id, $kind, $invoice->artifacts);
            }
            // No URL, cliente de red, SOAP ni credencial: respuesta creada solamente en memoria.
            $response = [
                'simulated' => true, 'source' => 'H2_LOCAL_FAKE', 'scenario' => $scenario,
                'code' => 'SIM-'.strtoupper($scenario), 'request_sha256' => $invoice->artifacts['zip']['sha256'],
                'message' => 'RESPUESTA FICTICIA. No se envió a SUNAT y no existe CDR oficial.',
            ];
            $invoice->transport_status = match ($scenario) {
                'accepted' => 'simulated_accepted', 'rejected' => 'simulated_rejected',
                'uncertain' => 'simulation_uncertain',
            };
            $invoice->simulated_response = $response;
            $invoice->save();
            DB::table('invoice_outbox')->where('id', $job->id)->update([
                'status' => $scenario === 'uncertain' ? 'uncertain' : 'completed',
                'attempts' => 1, 'updated_at' => now(),
            ]);
            return $invoice;
        });
    }
}
