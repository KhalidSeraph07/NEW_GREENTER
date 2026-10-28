<?php

declare(strict_types=1);

namespace App\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

// Registro del receptor ficticio, sin representar capacidades oficiales SUNAT.
class LocalSimulation
{
    public const SCENARIOS = ['accepted', 'rejected', 'rejected_c2_zip', 'uncertain', 'communication_error', 'timeout', 'incomplete', 'unexpected_error'];

    public function exchange(Invoice $invoice, string $scenario): array
    {
        if ($scenario === 'communication_error') {
            return ['not_processed' => true]; // Fallo inyectado ANTES del procesamiento ficticio.
        }
        if ($scenario === 'unexpected_error') { throw new RuntimeException('Fallo no clasificado del simulador.'); }
        if ($scenario === 'uncertain') { return []; }
        $response = $this->response($invoice, in_array($scenario,['rejected','rejected_c2_zip'],true) ? 'rejected' : 'accepted');
        if ($scenario === 'rejected_c2_zip') { $response['technical_rejection'] = \App\Corrections\PackingDefect::cause('invoice', $invoice); }
        DB::table('simulation_receipts')->insertOrIgnore([
            'invoice_id' => $invoice->id, 'response' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
        Event::dispatch('h3.simulation.recorded', [$invoice->id]);
        if ($scenario === 'timeout') { return []; }
        if ($scenario === 'incomplete') { return ['simulated' => true, 'code' => 'SIM-ACCEPTED']; }
        return $this->lookup($invoice->id) ?? [];
    }

    public function lookup(string $invoiceId): ?array
    {
        $receipt = DB::table('simulation_receipts')->where('invoice_id', $invoiceId)->value('response');
        return $receipt === null ? null : json_decode($receipt, true, flags: JSON_THROW_ON_ERROR);
    }

    public function response(Invoice $invoice, string $scenario): array
    {
        return [
            'simulated' => true, 'source' => 'H2_LOCAL_FAKE', 'scenario' => $scenario,
            'invoice_id' => $invoice->id, 'code' => 'SIM-'.strtoupper($scenario),
            'request_sha256' => $invoice->artifacts['zip']['sha256'],
            'message' => 'RESPUESTA FICTICIA. No se envió a SUNAT y no existe CDR oficial.',
        ];
    }
}
