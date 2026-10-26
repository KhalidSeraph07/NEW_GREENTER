<?php

declare(strict_types=1);

namespace App\BoletaNotes;

use App\Boletas\Artifacts;
use App\Invoices\InvoiceInput;
use App\Models\Boleta;
use App\Models\BoletaNote;
use App\Models\DailySummary;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Original
{
    public function __construct(private Artifacts $files, private InvoiceInput $input) {}

    public function eligible(Boleta $b): void
    {
        $s = $b->snapshot;
        if (($s['document_type'] ?? null) !== '03' || ($s['issuer_ruc'] ?? null) !== '00000000000'
            || ($s['series'] ?? null) !== 'B000' || ($s['number'] ?? null) !== $b->number
            || ($s['emission_date'] ?? null) !== $b->emission_date) { $this->fail(); }
        $values = $this->input->validate(['idempotency_key' => $b->id, 'description' => $s['description'],
            'quantity' => InvoiceInput::decimal($s['quantity_milli'], 3), 'unit_price' => InvoiceInput::decimal($s['unit_price_cents'])]);
        foreach ($values as $key => $value) { if (($s[$key] ?? null) !== $value) { $this->fail(); } }
        $this->files->verify($b->id, $b->artifacts);
        $rc = $this->acceptedSummary($b);
        $this->files->verify($rc->id, $rc->artifacts);
        $r = $rc->result;
        $receipt = DB::table('h4_simulation_receipts')->where('summary_id', $rc->id)->first();
        $response = $r['response'] ?? [];
        if ($rc->user_id !== $b->user_id || $rc->reference_date !== $b->emission_date || $rc->state !== 'sim_accepted'
            || ($r['simulated'] ?? null) !== true || ($r['official_cdr'] ?? null) !== false || ($r['outcome'] ?? null) !== 'accepted'
            || ($response['simulated'] ?? null) !== true || ($response['source'] ?? null) !== 'H4_LOCAL_FAKE'
            || ($response['statusCode'] ?? null) !== '0' || ($response['ticket'] ?? null) !== $rc->ticket
            || !$receipt || $receipt->ticket !== $rc->ticket || $receipt->request_hash !== $rc->artifacts['zip']['sha256']
            || DB::table('h4_outbox')->where('summary_id', $rc->id)->value('status') !== 'completed') { $this->fail(); }
        $members = $rc->snapshot['members'];
        $ids = DB::table('h4_members')->where('summary_id', $rc->id)->orderBy('position')->pluck('boleta_id')->all();
        if ($ids !== array_column($members, 'id')) { $this->fail(); }
        $member = array_values(array_filter($members, fn ($m) => $m['id'] === $b->id));
        $expected = ['id' => $b->id, 'number' => $b->number, 'emission_date' => $b->emission_date,
            'subtotal_cents' => $s['subtotal_cents'], 'tax_cents' => $s['tax_cents'], 'total_cents' => $s['total_cents']];
        if (count($member) !== 1 || \App\Notes\Original::hash($member[0]) !== \App\Notes\Original::hash($expected)) { $this->fail(); }
        $text = $response['constancia'] ?? '';
        if (!is_string($text) || strlen($text) > 65536 || stripos($text, '<!DOCTYPE') !== false) { $this->fail(); }
        $xml = new \DOMDocument();
        if (!$xml->loadXML($text, LIBXML_NONET) || $xml->documentElement->tagName !== 'H4FictitiousConstancia'
            || $xml->documentElement->getAttribute('simulated') !== 'true') { $this->fail(); }
        foreach (['Source' => 'H4_LOCAL_FAKE', 'SummaryID' => $rc->id, 'RCName' => $rc->name, 'RequestHash' => $receipt->request_hash,
            'Ticket' => $rc->ticket, 'Result' => 'accepted', 'Message' => 'SIMULACION H4 SIN CDR OFICIAL'] as $tag => $value) {
            $nodes = $xml->documentElement->getElementsByTagName($tag);
            if ($nodes->length !== 1 || $nodes->item(0)->textContent !== $value) { $this->fail(); }
        }
        $reported = []; foreach ($xml->getElementsByTagName('Member') as $m) { $reported[] = $m->textContent; }
        if ($reported !== $ids) { $this->fail(); }
    }

    public function frozen(Boleta $b): array
    {
        $source = $b->attributesToArray(); unset($source['created_at'], $source['updated_at'], $source['idempotency_key']);
        $rc = $this->acceptedSummary($b);
        $source['accepted_rc'] = ['id' => $rc->id, 'name' => $rc->name, 'snapshot' => $rc->snapshot,
            'artifacts' => $rc->artifacts, 'ticket' => $rc->ticket, 'result' => $rc->result];
        return $source;
    }

    public function verify(BoletaNote $note): void
    {
        $b = Boleta::query()->findOrFail($note->original_id); $this->eligible($b);
        if ($b->user_id !== $note->user_id || !hash_equals($note->original_hash, \App\Notes\Original::hash($this->frozen($b)))) { $this->fail(); }
    }

    private function acceptedSummary(Boleta $b): DailySummary
    {
        $id = DB::table('h4_members')->where('boleta_id', $b->id)->value('summary_id');
        $rc = $id ? DailySummary::query()->find($id) : null;
        if (!$rc) { $this->fail(); }
        return $rc;
    }

    private function fail(): never { throw new ConflictHttpException('Boleta o RC aceptado ficticio no correlacionado; detener e investigar.'); }
}
