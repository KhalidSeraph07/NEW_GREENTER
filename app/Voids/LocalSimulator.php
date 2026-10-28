<?php

declare(strict_types=1);

namespace App\Voids;

use App\Models\VoidBatch;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;

class LocalSimulator
{
    public const SEND = ['accepted', 'lost_ticket', 'incomplete', 'wrong_hash', 'duplicate', 'exception'];
    public const POLL = ['98', '0', '99', 'timeout', 'incomplete', 'wrong_hash', 'wrong_member', 'wrong_ticket', 'wrong_rc', 'non_simulated', 'doctype'];

    public function send(VoidBatch $summary, string $scenario): array
    {
        if ($scenario === 'exception') { throw new RuntimeException('Fallo local ficticio de comunicacion.'); }
        $ticket = 'SIM-H5C1-'.Str::uuid();
        $saved = DB::table('h5c1_simulation_receipts')->insertOrIgnore(['batch_id' => $summary->id,
            'ticket' => $ticket, 'request_hash' => $summary->artifacts['zip']['sha256'], 'created_at' => now('UTC')]);
        Event::dispatch('h5c1.send.recorded', [$summary->id]);
        if ($scenario === 'lost_ticket') { return []; }
        if ($scenario === 'duplicate' || !$saved) { return ['simulated' => true, 'code' => 'SIM-DUPLICATE']; }
        if ($scenario === 'incomplete') { return ['ticket' => $ticket]; }
        return ['simulated' => true, 'source' => 'H5C1_LOCAL_FAKE', 'batch_id' => $summary->id, 'ticket' => $ticket,
            'request_hash' => $scenario === 'wrong_hash' ? 'bad' : $summary->artifacts['zip']['sha256']];
    }

    // No consulta por UUID/nombre: la recuperacion usa SOLO el ticket ya conservado.
    public function poll(string $ticket, string $scenario): array
    {
        $receipt = DB::table('h5c1_simulation_receipts')->where('ticket', $ticket)->first();
        if (!$receipt || $scenario === 'timeout') { return []; }
        $summary = VoidBatch::query()->findOrFail($receipt->batch_id);
        $response = ['simulated' => true, 'source' => 'H5C1_LOCAL_FAKE', 'ticket' => $ticket,
            'statusCode' => $scenario === '98' ? '98' : ($scenario === '99' ? '99' : '0')];
        if ($scenario === '98') { return $response; }
        if ($scenario === 'incomplete') { return $response; }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElement('H5C1FictitiousConstancia'); $root->setAttribute('simulated', 'true'); $xml->appendChild($root);
        foreach (['Source' => 'H5C1_LOCAL_FAKE', 'SummaryID' => $summary->id, 'CommunicationName' => $summary->name,
            'RequestHash' => $receipt->request_hash, 'Ticket' => $ticket,
            'Result' => $scenario === '99' ? 'rejected' : 'accepted', 'Message' => 'SIMULACION H5-C1 SIN CDR OFICIAL'] as $tag => $value) {
            $root->appendChild($xml->createElement($tag, $value));
        }
        foreach ($summary->snapshot['members'] as $member) { $root->appendChild($xml->createElement('Member', \App\Notes\Original::hash($member))); }
        if ($scenario === 'wrong_hash') { $root->getElementsByTagName('RequestHash')->item(0)->nodeValue = 'bad'; }
        if ($scenario === 'wrong_member') { $root->getElementsByTagName('Member')->item(0)->nodeValue = (string) Str::uuid(); }
        if ($scenario === 'wrong_rc') { $root->getElementsByTagName('CommunicationName')->item(0)->nodeValue = '00000000000-RC-19000101-1'; }
        if ($scenario === 'wrong_ticket') { $response['ticket'] = 'SIM-H5C1-'.Str::uuid(); }
        if ($scenario === 'non_simulated') { $root->setAttribute('simulated', 'false'); }
        $response['constancia'] = $scenario === 'doctype' ? '<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>' : $xml->saveXML();
        return $response;
    }
}
