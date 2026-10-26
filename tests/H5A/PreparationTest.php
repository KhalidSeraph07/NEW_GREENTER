<?php

namespace Tests\H5A;

use App\Notes\Preparation;
use App\Notes\Evidence;
use App\Models\InvoiceNote;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class PreparationTest extends NoteCase
{
    public function test_complete_credit_and_positive_debit_keep_original_and_independent_identity(): void
    {
        $original = $this->original(); $before = $original->getRawOriginal();
        $credit = app(Preparation::class)->issue($this->operator->id, $this->noteInput($original));
        $this->assertSame('07', $credit->document_type);
        $this->assertSame('01', $credit->reason_code);
        $this->assertSame(11800, $credit->snapshot['total_cents']);
        $this->assertSame('pending', $credit->state);
        $this->assertSame($before, $original->fresh()->getRawOriginal());
        $this->assertSame(0, DB::table('h5_outbox')->where('note_id', $credit->id)->value('attempts'));
        $other = $this->original();
        $debit = app(Preparation::class)->issue($this->operator->id, $this->noteInput($other,
            ['operation' => 'ND02', 'increment_unit_price' => '20.00']));
        $this->assertSame('08', $debit->document_type);
        $this->assertSame('02', $debit->reason_code);
        $this->assertSame(2360, $debit->snapshot['total_cents']);
        $this->assertSame(10000, $other->fresh()->subtotal_cents);
        $this->assertNotSame($credit->series, $debit->series);
    }

    public function test_both_xml_types_have_exact_references_amounts_signatures_and_private_zip(): void
    {
        foreach (['NC01' => 'CreditNote', 'ND02' => 'DebitNote'] as $op => $root) {
            $source = $this->original();
            $n = app(Preparation::class)->issue($this->operator->id, $this->noteInput($source,
                $op === 'ND02' ? ['operation' => $op, 'increment_unit_price' => '20.00'] : []));
            $f = app(Evidence::class); $xml = $f->path($n->id, 'xml', $n->artifacts); $cert = $f->path($n->id, 'certificate', $n->artifacts);
            $doc = new DOMDocument(); $this->assertTrue($doc->load($xml, LIBXML_NONET)); $this->assertSame($root, $doc->documentElement->localName);
            $x = new DOMXPath($doc); $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $this->assertSame('2.1', $x->evaluate('string(/*/cbc:UBLVersionID)'));
            $this->assertSame('2.0', $x->evaluate('string(/*/cbc:CustomizationID)'));
            $this->assertSame($n->reason_code, $x->evaluate('string(/*/cac:DiscrepancyResponse/cbc:ResponseCode)'));
            $this->assertSame('F000-'.$source->number, $x->evaluate('string(/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)'));
            $this->assertSame('01', $x->evaluate('string(/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode)'));
            $this->assertStringContainsString($op === 'NC01' ? '>118.00<' : '>23.60<', file_get_contents($xml));
            $p = new Process(['xmlsec1','--verify','--lax-key-search','--enabled-key-data','rsa','--enabled-reference-uris','empty','--pubkey-cert-pem',$cert,$xml]);
            $p->run(); $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
            $bad = $xml.'.negative'; file_put_contents($bad, str_replace($source->series.'-'.$source->number, 'F000-99999999', file_get_contents($xml)));
            $p = new Process(['xmlsec1','--verify','--lax-key-search','--enabled-key-data','rsa','--enabled-reference-uris','empty','--pubkey-cert-pem',$cert,$bad]);
            $p->run(); $this->assertFalse($p->isSuccessful());
            $zip = new ZipArchive(); $this->assertTrue($zip->open($f->path($n->id, 'zip', $n->artifacts)));
            $this->assertSame(1, $zip->numFiles); $this->assertSame('00000000000-'.$n->document_type.'-'.$n->series.'-'.$n->number.'.xml', $zip->getNameIndex(0));
            $this->assertSame(file_get_contents($xml), $zip->getFromIndex(0)); $zip->close();
            $this->assertStringNotContainsString('PRIVATE KEY', file_get_contents($cert));
        }
    }

    public function test_normalized_replay_is_identical_and_other_key_or_content_cannot_reserve_original_again(): void
    {
        $input = $this->noteInput($this->original(), ['operation' => 'ND02', 'increment_unit_price' => '20']);
        $n = app(Preparation::class)->issue($this->operator->id, $input);
        $again = app(Preparation::class)->issue($this->operator->id, array_replace($input, ['increment_unit_price' => '020.00', 'reason' => ' '.$input['reason'].' ']));
        $this->assertSame($n->id, $again->id); $this->assertSame(\App\Notes\Original::hash($n->artifacts), \App\Notes\Original::hash($again->artifacts));
        foreach ([['idempotency_key' => (string) Str::uuid()], ['increment_unit_price' => '21.00'], ['original_id' => $this->original()->id]] as $change) {
            try { app(Preparation::class)->issue($this->operator->id, array_replace($input, $change)); $this->fail('Conflicto requerido'); }
            catch (ConflictHttpException) { $this->assertSame(1, InvoiceNote::query()->where('user_id', $this->operator->id)->count()); }
        }
    }

    public static function invalid(): array
    {
        return [[['operation'=>'NC06']], [['operation'=>'ND01']], [['receiver_ruc'=>'00000000000']], [['currency'=>'USD']],
            [['unit_price'=>'1']], [['quantity'=>'2']], [['original_granted_test'=>'0']], [['reason'=>"bad\ntext"]],
            [['reason'=>']]>' ]], [['increment_unit_price'=>'1']], [['operation'=>'ND02','increment_unit_price'=>'0']],
            [['operation'=>'ND02','increment_unit_price'=>'-1']], [['operation'=>'ND02','increment_unit_price'=>'1.001']],
            [['emission_date'=>'2099-01-01']], [['document_type'=>'03']]];
    }

    #[DataProvider('invalid')]
    public function test_unapproved_fields_causes_amounts_and_dates_consume_nothing(array $change): void
    {
        $source = $this->original(); $before = DB::table('h5_counters')->orderBy('document_type')->get()->toJson();
        try { app(Preparation::class)->issue($this->operator->id, $this->noteInput($source, $change)); $this->fail('Entrada invalida aceptada'); }
        catch (ValidationException) {
            $this->assertSame($before, DB::table('h5_counters')->orderBy('document_type')->get()->toJson());
            $this->assertSame(0, InvoiceNote::query()->where('original_id', $source->id)->count());
        }
    }

    public static function originalStates(): array { return [['pending_simulation'],['simulation_processing'],['simulation_uncertain'],['simulated_rejected']]; }

    #[DataProvider('originalStates')]
    public function test_incompatible_original_states_are_refused_without_rewriting_them(string $state): void
    {
        $invoice = $this->issue();
        DB::table('invoices')->where('id', $invoice->id)->update(['transport_status' => $state]);
        $before = $invoice->fresh()->getRawOriginal();
        try { app(Preparation::class)->issue($this->operator->id, $this->noteInput($invoice)); $this->fail(); }
        catch (ConflictHttpException) { $this->assertSame($before, $invoice->fresh()->getRawOriginal()); }
    }

    public function test_database_freezes_note_reference_content_and_terminal_results(): void
    {
        $n = app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original()));
        foreach ([['original_id' => $this->original()->id], ['snapshot' => '{}'], ['artifacts'=>'{}'], ['number'=>$n->number+1], ['state'=>'sim_accepted']] as $changes) {
            try { DB::table('h5_notes')->where('id', $n->id)->update($changes); $this->fail(); }
            catch (QueryException) { $this->assertSame('pending', $n->fresh()->state); }
        }
        app(\App\Notes\Transport::class)->dispatch($n->id);
        $this->expectException(QueryException::class); DB::table('h5_notes')->where('id', $n->id)->update(['state'=>'uncertain']);
    }

    public function test_outbox_failure_rolls_back_reference_counter_and_new_files_then_allows_retry(): void
    {
        $input = $this->noteInput($this->original()); $dirs = glob(config('h5.evidence_root').'/*') ?: [];
        $counter = DB::table('h5_counters')->where('document_type','07')->value('next_number');
        DB::unprepared("CREATE FUNCTION h5_test_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected H5 outbox failure'; END $$; CREATE TRIGGER h5_test_fail BEFORE INSERT ON h5_outbox FOR EACH ROW EXECUTE FUNCTION h5_test_fail();");
        try {
            try { app(Preparation::class)->issue($this->operator->id, $input); $this->fail(); }
            catch (QueryException) { $this->assertSame(0, InvoiceNote::query()->where('original_id',$input['original_id'])->count()); }
        } finally { DB::unprepared('DROP TRIGGER h5_test_fail ON h5_outbox; DROP FUNCTION h5_test_fail();'); }
        $this->assertSame($dirs, glob(config('h5.evidence_root').'/*') ?: []);
        $this->assertSame($counter, DB::table('h5_counters')->where('document_type','07')->value('next_number'));
        $this->assertSame($counter, app(Preparation::class)->issue($this->operator->id, $input)->number);
    }

    public function test_lost_commit_confirmation_preserves_files_for_same_identity_replay(): void
    {
        $input = $this->noteInput($this->original());
        Event::listen(TransactionCommitted::class, function () { if (DB::transactionLevel() === 0) { throw new \RuntimeException('lost commit confirmation'); } });
        try {
            try { app(Preparation::class)->issue($this->operator->id, $input); $this->fail(); } catch (\RuntimeException $e) { $this->assertSame('lost commit confirmation', $e->getMessage()); }
        } finally { Event::forget(TransactionCommitted::class); }
        $existing = InvoiceNote::query()->where('user_id',$this->operator->id)->sole();
        $this->assertSame($existing->id, app(Preparation::class)->issue($this->operator->id,$input)->id);
        app(Evidence::class)->verify($existing);
    }

    public function test_d_plus_three_and_original_date_boundaries_are_calendar_days(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20T23:55:00-05:00'));
        $source = $this->original();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-23T00:01:00-05:00'));
        $n = app(Preparation::class)->issue($this->operator->id, $this->noteInput($source,['emission_date'=>'2026-10-20']));
        $this->assertSame('sim_accepted', app(\App\Notes\Transport::class)->dispatch($n->id)->state);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-24T00:01:00-05:00'));
        $this->expectException(ValidationException::class);
        app(Preparation::class)->issue($this->operator->id, $this->noteInput($this->original(), ['emission_date'=>'2026-10-20']));
    }
}
