<?php

declare(strict_types=1);

namespace App\Notes;

use App\Models\InvoiceNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class Transport
{
    public function __construct(private Evidence $files, private Original $original, private LocalSimulation $simulation) {}

    public function dispatch(string $id, string $scenario = 'accepted'): ?InvoiceNote
    {
        $this->guard();
        if (!in_array($scenario, LocalSimulation::SCENARIOS, true)) { throw ValidationException::withMessages(['scenario' => 'Escenario H5-A no permitido.']); }
        $claim = DB::transaction(function () use ($id) {
            $job = DB::table('h5_outbox')->where('note_id', $id)->lock('FOR UPDATE SKIP LOCKED')->first();
            if (!$job || !in_array($job->status, ['pending','retry_wait'], true)) { return null; }
            if ($job->status === 'retry_wait' && (Carbon::parse($job->available_at, 'UTC')->isFuture() || $job->attempts >= 3)) { return null; }
            $n = InvoiceNote::query()->findOrFail($id);
            if ($n->state !== $job->status || ($job->status === 'pending' && $job->attempts !== 0)) { throw new LogicException('Estado H5-A inconsistente.'); }
            $this->verify($n);
            if ($n->emission_date < now('America/Lima')->subDays(3)->toDateString()) {
                $this->change($n, $job, 'expired', 'expired', 'send_deadline', null, true);
                return [$n, null];
            }
            $token = (string) Str::uuid();
            DB::table('h5_outbox')->where('note_id', $id)->update(['status' => 'processing', 'attempts' => $job->attempts + 1,
                'token' => $token, 'reserved_until' => now('UTC')->addMinutes(5), 'available_at' => null, 'updated_at' => now('UTC')]);
            $n->state = 'processing'; $n->save();
            $this->record($id, 'claimed', $token, ['attempt' => $job->attempts + 1]);
            return [$n, $token];
        });
        if ($claim === null) { return null; }
        [$n, $token] = $claim;
        if ($token === null) { return $n; }
        Event::dispatch('h5.transport.claimed', [$id]);
        try { $response = $this->simulation->exchange($n, $scenario); }
        catch (Throwable) { $response = []; }
        return $this->finish($id, $token, $response);
    }

    public function recover(string $id): InvoiceNote
    {
        $this->guard();
        return DB::transaction(function () use ($id) {
            $job = DB::table('h5_outbox')->where('note_id', $id)->lockForUpdate()->sole();
            $n = InvoiceNote::query()->findOrFail($id);
            if ($job->status === 'processing') {
                if ($n->state !== 'processing') { throw new LogicException('Reserva H5-A incompatible.'); }
                if (Carbon::parse($job->reserved_until, 'UTC')->isFuture()) { return $n; }
                $this->change($n, $job, 'uncertain', 'uncertain', 'lease_expired', null, true);
                $job->status = 'uncertain';
            }
            if ($job->status !== 'uncertain') { return $n; }
            if ($n->state !== 'uncertain') { throw new LogicException('Recuperacion H5-A incompatible.'); }
            try { $this->verify($n); $response = $this->simulation->lookup($id); }
            catch (Throwable) { $response = null; }
            if ($response !== null && $this->valid($n, $response, ['accepted','rejected'])) {
                $this->change($n, $job, 'completed', 'sim_'.$response['outcome'], 'recovered_local_fake', $response, $response['outcome'] === 'rejected');
            } else { $this->record($id, 'query_unresolved', $job->token, $response, true); }
            return $n;
        });
    }

    private function finish(string $id, string $token, array $response): InvoiceNote
    {
        return DB::transaction(function () use ($id, $token, $response) {
            $job = DB::table('h5_outbox')->where('note_id', $id)->lockForUpdate()->sole();
            $n = InvoiceNote::query()->findOrFail($id);
            if ($job->status !== 'processing' || $job->token !== $token) {
                $this->record($id, 'late_response_ignored', $token, $response, true); return $n;
            }
            if ($n->state !== 'processing') { throw new LogicException('Finalizacion H5-A incompatible.'); }
            $this->record($id, 'response_received', $token, $response);
            try { $this->verify($n); } catch (Throwable) {
                $this->change($n, $job, 'uncertain', 'uncertain', 'integrity_uncertain', null, true); return $n;
            }
            if ($this->valid($n, $response, ['accepted','rejected'])) {
                $this->change($n, $job, 'completed', 'sim_'.$response['outcome'], 'result', $response, $response['outcome'] === 'rejected');
            } elseif ($this->valid($n, $response, ['not_processed'])) {
                $exhausted = $job->attempts >= 3;
                $this->change($n, $job, $exhausted ? 'failed' : 'retry_wait', $exhausted ? 'failed' : 'retry_wait',
                    $exhausted ? 'retry_exhausted' : 'confirmed_not_processed', null, $exhausted,
                    $exhausted ? null : now('UTC')->addSeconds(5 * $job->attempts));
            } else { $this->change($n, $job, 'uncertain', 'uncertain', 'uncertain', null, true); }
            return $n;
        });
    }

    private function valid(InvoiceNote $n, array $response, array $outcomes): bool
    {
        $outcome = $response['outcome'] ?? null;
        return in_array($outcome, $outcomes, true) && Original::hash($response) === Original::hash($this->simulation->response($n, $outcome));
    }

    private function change(InvoiceNote $n, object $job, string $status, string $state, string $kind, ?array $result,
        bool $attention = false, mixed $available = null): void
    {
        DB::table('h5_outbox')->where('note_id', $n->id)->update(['status' => $status, 'reserved_until' => null,
            'available_at' => $available, 'updated_at' => now('UTC')]);
        $n->state = $state; $n->result = $result; $n->save();
        $this->record($n->id, $kind, $job->token, $result, $attention);
    }

    private function verify(InvoiceNote $n): void { $this->files->verify($n); $this->original->verify($n); }

    private function record(string $id, string $kind, ?string $token, ?array $payload = null, bool $attention = false): void
    {
        DB::table('h5_events')->insert(['note_id' => $id, 'kind' => $kind, 'token' => $token, 'attention' => $attention,
            'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
    }

    private function guard(): void
    {
        if (config('h5.mode') !== 'fictitious' || config('h2.mode') !== 'fictitious' || DB::transactionLevel() !== 0) {
            throw new LogicException('H5-A solo ficticio, reserva sin transaccion externa.');
        }
    }
}
