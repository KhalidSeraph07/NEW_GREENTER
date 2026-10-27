<?php

namespace Tests\H5B;

use App\BoletaNotes\Preparation;
use App\BoletaNotes\Evidence;
use App\BoletaNotes\SummaryTransport;
use Illuminate\Support\Facades\DB;

final class PreparationTest extends NoteCase
{
    public function test_note_and_new_rc_freeze_distinct_identity_and_original_reference(): void
    {
        $original = $this->original();
        $before = DB::table('h4_summaries')->orderBy('id')->get()->toJson();
        $note = app(Preparation::class)->issue($this->operator->id, $this->noteInput($original));
        $rc = $this->summary([$note->id]);
        $this->assertSame('BC00', $note->series);
        $this->assertSame(11800, $note->snapshot['total_cents']);
        $this->assertSame($note->emission_date, $rc->reference_date);
        $this->assertSame('pending_send', $rc->state);
        $xml = file_get_contents(app(Evidence::class)->path($rc->id, 'xml', $rc->artifacts));
        $d = new \DOMDocument(); $this->assertTrue($d->loadXML($xml, LIBXML_NONET));
        $x = new \DOMXPath($d);
        $x->registerNamespace('sac','urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1');
        $x->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $p = '/*/sac:SummaryDocumentsLine';
        $this->assertSame('07', $x->evaluate('string('.$p.'/cbc:DocumentTypeCode)'));
        $this->assertSame('BC00-'.$note->number, $x->evaluate('string('.$p.'/cbc:ID)'));
        $this->assertSame('03', $x->evaluate('string('.$p.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode)'));
        $this->assertSame('B000-'.$original->number, $x->evaluate('string('.$p.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)'));
        $this->assertSame('1', $x->evaluate('string('.$p.'/cac:Status/cbc:ConditionCode)'));
        app(SummaryTransport::class)->send($rc->id);
        $this->assertSame('sim_accepted', app(SummaryTransport::class)->poll($rc->id)->state);
        $this->assertSame($before, DB::table('h4_summaries')->orderBy('id')->get()->toJson());
    }
}
