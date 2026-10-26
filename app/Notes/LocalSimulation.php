<?php

declare(strict_types=1);

namespace App\Notes;

use App\Models\InvoiceNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class LocalSimulation
{
    public const SCENARIOS = ['accepted','rejected','uncertain','timeout','incomplete','wrong_reference','wrong_hash','non_simulated','unexpected_error','not_processed'];

    public function response(InvoiceNote $n, string $outcome): array
    {
        return ['simulated' => true, 'source' => 'H5A_LOCAL_FAKE', 'official_cdr' => false,
            'note_id' => $n->id, 'name' => '00000000000-'.$n->document_type.'-'.$n->series.'-'.$n->number,
            'document_type' => $n->document_type, 'reason_code' => $n->reason_code,
            'original_id' => $n->original_id, 'original_hash' => $n->original_hash,
            'request_sha256' => $n->artifacts['zip']['sha256'], 'outcome' => $outcome,
            'message' => 'RESPUESTA H5-A FICTICIA SIN CDR OFICIAL NI ENVIO SUNAT.'];
    }

    public function exchange(InvoiceNote $n, string $scenario): array
    {
        if ($scenario === 'not_processed') { return $this->response($n, 'not_processed'); }
        if ($scenario === 'unexpected_error') { throw new \RuntimeException('Fallo inyectado H5-A.'); }
        if ($scenario === 'uncertain') { return []; }
        $response = $this->response($n, $scenario === 'rejected' ? 'rejected' : 'accepted');
        DB::table('h5_receipts')->insertOrIgnore(['note_id' => $n->id, 'response' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
        Event::dispatch('h5.simulation.recorded', [$n->id]);
        $response = $this->lookup($n->id) ?? [];
        return match ($scenario) {
            'timeout' => [], 'incomplete' => ['simulated' => true, 'outcome' => 'accepted'],
            'wrong_reference' => array_replace($response, ['original_id' => '00000000-0000-0000-0000-000000000000']),
            'wrong_hash' => array_replace($response, ['request_sha256' => str_repeat('0', 64)]),
            'non_simulated' => array_replace($response, ['simulated' => false]), default => $response,
        };
    }

    public function lookup(string $id): ?array
    {
        $r = DB::table('h5_receipts')->where('note_id', $id)->value('response');
        return $r === null ? null : json_decode($r, true, flags: JSON_THROW_ON_ERROR);
    }
}
