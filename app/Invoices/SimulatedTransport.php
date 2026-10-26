<?php

declare(strict_types=1);

namespace App\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class SimulatedTransport
{
    public function __construct(private InvoiceEvidence $evidence, private LocalSimulation $simulation) {}

    public function dispatch(?string $invoiceId = null, string $scenario = 'accepted'): ?Invoice
    {
        $this->outsideTransaction();
        if (!in_array($scenario, LocalSimulation::SCENARIOS, true)) {
            throw ValidationException::withMessages(['scenario' => 'Respuesta simulada no permitida.']);
        }
        $claim = DB::transaction(function () use ($invoiceId) {
            $query = DB::table('invoice_outbox')->where(function ($q) {
                $q->where('status', 'pending')->orWhere(function ($retry) {
                    $retry->where('status', 'retry_wait')->where('available_at', '<=', now('UTC'))->where('attempts', '<', 3);
                });
            });
            if ($invoiceId !== null) { $query->where('invoice_id', $invoiceId); }
            $job = $query->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if (!$job) { return null; }
            $invoice = Invoice::query()->findOrFail($job->invoice_id);
            $expected = $job->status === 'pending' ? 'pending_simulation' : 'simulation_retry_wait';
            if ($invoice->transport_status !== $expected || ($job->status === 'pending' && $job->attempts !== 0)) {
                throw new LogicException('Estados inconsistentes: operacion detenida.');
            }
            $this->verify($invoice);
            $token = (string) Str::uuid();
            DB::table('invoice_outbox')->where('id', $job->id)->update([
                'status' => 'processing', 'attempts' => $job->attempts + 1, 'attempt_token' => $token,
                'reserved_until' => now('UTC')->addMinutes(5), 'available_at' => null, 'updated_at' => now('UTC'),
            ]);
            $invoice->transport_status = 'simulation_processing';
            $invoice->save();
            $this->record($invoice->id, 'claimed', $token, $job->attempts + 1);
            return [$invoice, $token];
        });
        if ($claim === null) { return null; }
        [$invoice, $token] = $claim;
        // La reserva debe estar confirmada ANTES de cruzar el limite del simulador.
        Event::dispatch('h3.transport.claimed', [$invoice->id]);
        try {
            $response = $this->simulation->exchange($invoice, $scenario);
        } catch (Throwable) {
            $response = []; // Una excepcion no demuestra que el receptor no proceso.
        }
        return $this->finish($invoice->id, $token, $response);
    }

    public function recover(string $id): Invoice
    {
        $this->outsideTransaction();
        return DB::transaction(function () use ($id) {
            $job = DB::table('invoice_outbox')->where('invoice_id', $id)->lockForUpdate()->sole();
            $invoice = Invoice::query()->findOrFail($id);
            if ($job->status === 'processing') {
                if ($invoice->transport_status !== 'simulation_processing') {
                    throw new LogicException('Reserva y factura inconsistentes.');
                }
                if (\Illuminate\Support\Carbon::parse($job->reserved_until)->isFuture()) { return $invoice; }
                $this->transition($invoice, $job, 'uncertain', 'simulation_uncertain', 'reservation_expired', true);
                $job->status = 'uncertain';
            }
            if ($job->status !== 'uncertain') { return $invoice; }
            if ($invoice->transport_status !== 'simulation_uncertain') {
                throw new LogicException('Consulta incompatible con estado de factura.');
            }
            $this->verify($invoice);
            try { $response = $this->simulation->lookup($id); } catch (Throwable) { $response = null; }
            if ($response === null || !$this->valid($invoice, $response)) {
                $this->record($id, 'query_unresolved', $job->attempt_token, $job->attempts, true);
                return $invoice;
            }
            $this->complete($invoice, $job, $response, 'recovered');
            return $invoice;
        });
    }

    private function finish(string $id, string $token, array $response): Invoice
    {
        return DB::transaction(function () use ($id, $token, $response) {
            $job = DB::table('invoice_outbox')->where('invoice_id', $id)->lockForUpdate()->sole();
            $invoice = Invoice::query()->findOrFail($id);
            if ($job->status !== 'processing' || $job->attempt_token !== $token) {
                $this->record($id, 'late_response_ignored', $token, $job->attempts, true);
                return $invoice;
            }
            if ($invoice->transport_status !== 'simulation_processing') {
                throw new LogicException('Resultado incompatible con estado de transporte.');
            }
            try { $this->verify($invoice); } catch (Throwable) { $response = []; }
            if ($this->valid($invoice, $response)) {
                $this->complete($invoice, $job, $response, 'result');
            } elseif ($response === ['not_processed' => true]) {
                $exhausted = $job->attempts >= 3;
                $this->transition($invoice, $job, $exhausted ? 'exhausted' : 'retry_wait',
                    $exhausted ? 'simulation_failed' : 'simulation_retry_wait',
                    $exhausted ? 'retry_exhausted' : 'confirmed_not_processed', $exhausted,
                    $exhausted ? null : now('UTC')->addSeconds(5 * $job->attempts));
            } else {
                $invoice->simulated_response = $this->simulation->response($invoice, 'uncertain');
                $this->transition($invoice, $job, 'uncertain', 'simulation_uncertain', 'uncertain', true);
            }
            return $invoice;
        });
    }

    private function valid(Invoice $invoice, array $response): bool
    {
        $scenario = $response['scenario'] ?? null;
        return ($response['simulated'] ?? null) === true && ($response['source'] ?? null) === 'H2_LOCAL_FAKE'
            && ($response['invoice_id'] ?? null) === $invoice->id
            && in_array($scenario, ['accepted', 'rejected'], true)
            && ($response['code'] ?? null) === 'SIM-'.strtoupper($scenario)
            && ($response['request_sha256'] ?? null) === $invoice->artifacts['zip']['sha256']
            && ($response['message'] ?? null) === 'RESPUESTA FICTICIA. No se envió a SUNAT y no existe CDR oficial.';
    }

    private function complete(Invoice $invoice, object $job, array $response, string $kind): void
    {
        $invoice->simulated_response = $response;
        $this->transition($invoice, $job, 'completed', 'simulated_'.$response['scenario'], $kind, $response['scenario'] === 'rejected');
    }

    private function transition(Invoice $invoice, object $job, string $status, string $transport, string $kind, bool $attention, mixed $available = null): void
    {
        DB::table('invoice_outbox')->where('id', $job->id)->update([
            'status' => $status, 'reserved_until' => null, 'available_at' => $available, 'updated_at' => now('UTC'),
        ]);
        $invoice->transport_status = $transport;
        $invoice->save();
        $this->record($invoice->id, $kind, $job->attempt_token, $job->attempts, $attention);
    }

    private function verify(Invoice $invoice): void
    {
        foreach (['unsigned', 'xml', 'zip', 'certificate'] as $kind) {
            $this->evidence->verifiedPath($invoice->id, $kind, $invoice->artifacts);
        }
    }

    private function record(string $id, string $kind, ?string $token, int $attempt, bool $attention = false): void
    {
        DB::table('invoice_transport_events')->insert([
            'invoice_id' => $id, 'attempt_token' => $token, 'attempt' => $attempt,
            'kind' => $kind, 'attention' => $attention, 'created_at' => now('UTC'),
        ]);
    }

    private function outsideTransaction(): void
    {
        if (DB::transactionLevel() !== 0 || config('h2.mode') !== 'fictitious') {
            throw new LogicException('Transporte solo ficticio, sin transaccion externa que impida confirmar la reserva.');
        }
    }
}
