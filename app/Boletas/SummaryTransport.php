<?php

declare(strict_types=1);

namespace App\Boletas;

use App\Models\Boleta;
use App\Models\DailySummary;
use DOMDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class SummaryTransport
{
    public function __construct(private Artifacts $files, private LocalSummarySimulator $simulator) {}

    public function send(string $id, string $scenario = 'accepted'): ?DailySummary
    {
        $this->scenario($scenario, LocalSummarySimulator::SEND);
        $claim = $this->claim($id, 'send');
        if (!$claim) { return null; }
        [$summary, $token] = $claim;
        Event::dispatch('h4.send.claimed', [$id]);
        try { $response = $this->simulator->send($summary, $scenario); } catch (Throwable) { $response = []; }
        $result = $this->finish($id, $token, 'send', $response);
        Event::dispatch('h4.ticket.saved', [$id]);
        return $result;
    }

    public function poll(string $id, string $scenario = '0'): ?DailySummary
    {
        $this->scenario($scenario, LocalSummarySimulator::POLL);
        $claim = $this->claim($id, 'poll');
        if (!$claim) { return null; }
        [$summary, $token] = $claim;
        Event::dispatch('h4.poll.claimed', [$id]);
        try { $response = $this->simulator->poll($summary->ticket, $scenario); } catch (Throwable) { $response = []; }
        Event::dispatch('h4.poll.responded', [$id]);
        return $this->finish($id, $token, 'poll', $response);
    }

    public function recover(string $id): DailySummary
    {
        $this->outsideTransaction();
        return DB::transaction(function () use ($id) {
            $job = DB::table('h4_outbox')->where('summary_id', $id)->lockForUpdate()->sole();
            $summary = DailySummary::query()->findOrFail($id);
            if ($job->status === 'processing') {
                if ($summary->state !== ($job->phase === 'send' ? 'sending' : 'querying')) { throw new LogicException('Reserva RC incompatible con su estado.'); }
                if (Carbon::parse($job->reserved_until)->isFuture()) { return $summary; }
                $this->change($summary, $job, 'uncertain', 'uncertain', 'lease_expired', [], true);
                $job->status = 'uncertain';
            }
            if ($job->status !== 'uncertain') { return $summary; }
            if ($summary->state !== 'uncertain') { throw new LogicException('Recuperacion RC incompatible con su estado.'); }
            if (!$summary->ticket || $job->phase !== 'poll') {
                $this->event($summary, 'ticket_missing_no_recovery', $job->token, [], true);
                return $summary;
            }
            if(app(\App\Corrections\Policy::class)->queryExhausted('h4',$id,$job->poll_attempts)){
                $this->event($summary,'c2_query_budget_exhausted',$job->token,[],true);return $summary;
            }
            $this->verify($summary);
            $this->change($summary, $job, 'pending', 'awaiting', 'manual_query_rearmed', [], false,
                ['cycle_attempts' => 0, 'available_at' => now('UTC')]);
            return $summary;
        });
    }

    private function claim(string $id, string $phase): ?array
    {
        $this->outsideTransaction();
        return DB::transaction(function () use ($id, $phase) {
            $job = DB::table('h4_outbox')->where('summary_id', $id)->where('phase', $phase)->where('status', 'pending')
                ->lock('FOR UPDATE SKIP LOCKED')->first();
            if (!$job || ($job->available_at && Carbon::parse($job->available_at)->isFuture())) { return null; }
            $summary = DailySummary::query()->findOrFail($id);
            if ($summary->state !== ($phase === 'send' ? 'pending_send' : 'awaiting')) { throw new LogicException('Estados RC incompatibles.'); }
            if ($phase === 'send' && ($job->send_attempts !== 0 || $summary->ticket !== null)) { throw new LogicException('Reenvio RC prohibido.'); }
            if ($phase === 'poll' && (!$summary->ticket || $job->cycle_attempts >= 3)) { return null; }
            if ($phase === 'send' && $summary->reference_date < now('America/Lima')->subDays(7)->toDateString()) {
                throw ValidationException::withMessages(['summary' => 'Ventana de envio D+7 vencida; requiere revision.']);
            }
            if ($phase === 'send') { app(\App\Corrections\Policy::class)->beforeSend('h4',$summary); }
            $this->verify($summary);
            $token = (string) Str::uuid();
            DB::table('h4_outbox')->where('summary_id', $id)->update(['status' => 'processing', 'token' => $token,
                'reserved_until' => now('UTC')->addMinutes(5), 'updated_at' => now('UTC'),
                'send_attempts' => $job->send_attempts + ($phase === 'send' ? 1 : 0),
                'poll_attempts' => $job->poll_attempts + ($phase === 'poll' ? 1 : 0),
                'cycle_attempts' => $job->cycle_attempts + ($phase === 'poll' ? 1 : 0)]);
            $summary->state = $phase === 'send' ? 'sending' : 'querying'; $summary->save();
            $this->event($summary, $phase.'_claimed', $token);
            return [$summary, $token];
        });
    }

    private function finish(string $id, string $token, string $phase, array $response): DailySummary
    {
        return DB::transaction(function () use ($id, $token, $phase, $response) {
            $job = DB::table('h4_outbox')->where('summary_id', $id)->lockForUpdate()->sole();
            $summary = DailySummary::query()->findOrFail($id);
            if ($job->status !== 'processing' || $job->token !== $token || $job->phase !== $phase) {
                $this->event($summary, 'late_response_ignored', $token, $response, true); return $summary;
            }
            if ($summary->state !== ($phase === 'send' ? 'sending' : 'querying')) { throw new LogicException('Respuesta RC incompatible con su estado.'); }
            try { $this->verify($summary); } catch (Throwable) {
                $this->change($summary, $job, 'uncertain', 'uncertain', 'evidence_changed', $response, true); return $summary;
            }
            if ($phase === 'send') {
                if (($response['simulated'] ?? null) === true && ($response['source'] ?? null) === 'H4_LOCAL_FAKE'
                    && ($response['summary_id'] ?? null) === $id && ($response['request_hash'] ?? null) === $summary->artifacts['zip']['sha256']
                    && is_string($response['ticket'] ?? null) && preg_match('/\ASIM-H4-[0-9a-f-]{36}\z/', $response['ticket'])) {
                    $summary->ticket = $response['ticket'];
                    $this->change($summary, $job, 'pending', 'awaiting', 'ticket_saved', $response, false,
                        ['phase' => 'poll', 'available_at' => now('UTC')]);
                } else { $this->change($summary, $job, 'uncertain', 'uncertain', 'send_uncertain', $response, true); }
                return $summary;
            }
            $validEnvelope = ($response['simulated'] ?? null) === true && ($response['source'] ?? null) === 'H4_LOCAL_FAKE'
                && ($response['ticket'] ?? null) === $summary->ticket;
            if ($validEnvelope && ($response['statusCode'] ?? null) === '98') {
                $exhausted = $job->cycle_attempts >= 3;
                $this->change($summary, $job, $exhausted ? 'uncertain' : 'pending', $exhausted ? 'uncertain' : 'awaiting',
                    $exhausted ? 'query_budget_exhausted' : 'query_98', $response, $exhausted,
                    ['available_at' => now('UTC')->addSeconds(5)]);
                return $summary;
            }
            $result = $validEnvelope ? $this->examine($summary, $response) : null;
            if ($result === null) { $this->change($summary, $job, 'uncertain', 'uncertain', 'query_uncertain', $response, true); }
            else {
                $summary->result = ['simulated' => true, 'official_cdr' => false, 'outcome' => $result, 'response' => $response];
                $this->change($summary, $job, 'completed', $result === 'accepted' ? 'sim_accepted' : 'sim_rejected', 'result_saved', $response, $result === 'rejected');
            }
            return $summary;
        });
    }

    private function examine(DailySummary $summary, array $response): ?string
    {
        $code = $response['statusCode'] ?? null; $text = $response['constancia'] ?? null;
        if (!in_array($code, ['0', '99'], true) || !is_string($text) || strlen($text) > 65536 || stripos($text, '<!DOCTYPE') !== false) { return null; }
        $previous = libxml_use_internal_errors(true);
        try { $xml = new DOMDocument(); if (!$xml->loadXML($text, LIBXML_NONET)) { return null; } }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $root = $xml->documentElement;
        if ($root->tagName !== 'H4FictitiousConstancia' || $root->getAttribute('simulated') !== 'true') { return null; }
        foreach (['Source' => 'H4_LOCAL_FAKE', 'SummaryID' => $summary->id, 'RCName' => $summary->name,
            'RequestHash' => $summary->artifacts['zip']['sha256'], 'Ticket' => $summary->ticket,
            'Result' => $code === '0' ? 'accepted' : 'rejected', 'Message' => 'SIMULACION H4 SIN CDR OFICIAL'] as $tag => $expected) {
            $nodes = $root->getElementsByTagName($tag);
            if ($nodes->length !== 1 || $nodes->item(0)->textContent !== $expected) { return null; }
        }
        if (isset($response['technical_rejection'])) {
            $nodes=$root->getElementsByTagName('TechnicalRejectionHash');
            if ($nodes->length!==1 || $nodes->item(0)->textContent!==\App\Notes\Original::hash($response['technical_rejection'])) { return null; }
        }
        $members = []; foreach ($root->getElementsByTagName('Member') as $node) { $members[] = $node->textContent; }
        return $members === array_column($summary->snapshot['members'], 'id') ? ($code === '0' ? 'accepted' : 'rejected') : null;
    }

    private function verify(DailySummary $summary): void
    {
        app(\App\Corrections\Policy::class)->verifyLinked('h4',$summary);
        $this->files->verify($summary->id, $summary->artifacts);
        $ids = DB::table('h4_members')->where('summary_id', \App\Corrections\Family::anchor('h4', $summary->id))->orderBy('position')->pluck('boleta_id')->all();
        if ($ids !== array_column($summary->snapshot['members'], 'id')) { throw new LogicException('Miembros RC alterados.'); }
        foreach (Boleta::query()->whereIn('id', $ids)->get() as $receipt) { $this->files->verify($receipt->id, $receipt->artifacts); }
    }

    private function change(DailySummary $summary, object $job, string $status, string $state, string $event, array $payload, bool $attention, array $extra = []): void
    {
        DB::table('h4_outbox')->where('summary_id', $summary->id)->update($extra + ['status' => $status, 'reserved_until' => null, 'updated_at' => now('UTC')]);
        $summary->state = $state; $summary->save();
        $this->event($summary, $event, $job->token, $payload, $attention);
    }

    private function event(DailySummary $summary, string $kind, ?string $token, array $payload = [], bool $attention = false): void
    {
        DB::table('h4_events')->insert(['summary_id' => $summary->id, 'kind' => $kind, 'token' => $token,
            'attention' => $attention, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
    }

    private function scenario(string $scenario, array $allowed): void
    {
        if (!in_array($scenario, $allowed, true)) { throw ValidationException::withMessages(['scenario' => 'Escenario H4 no permitido.']); }
    }

    private function outsideTransaction(): void
    {
        if (DB::transactionLevel() !== 0 || config('h4.mode') !== 'fictitious') { throw new LogicException('Solo H4 ficticio con reserva durable independiente.'); }
    }
}
