<?php

namespace Tests\H4;

use App\Boletas\Artifacts;
use App\Boletas\Preparation;
use App\Models\Boleta;
use App\Models\DailySummary;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

final class PreparationTest extends TestCase
{
    public function test_boleta_and_rc_have_independent_numbers_valid_signatures_and_exact_zip_bytes(): void
    {
        $invoiceCounter = DB::table('invoice_series')->value('next_number');
        $receipt = $this->receipt();
        $summary = $this->summary([$receipt->id]);
        $this->assertSame($invoiceCounter, DB::table('invoice_series')->value('next_number'));
        $this->assertSame('03', $receipt->snapshot['document_type']);
        $this->assertSame(11800, $receipt->snapshot['total_cents']);
        $this->assertSame('pending_send', $summary->state);
        $this->assertSame(0, DB::table('h4_outbox')->where('summary_id', $summary->id)->value('send_attempts'));
        foreach ([$receipt, $summary] as $record) {
            $files = app(Artifacts::class);
            $xml = $files->path($record->id, 'xml', $record->artifacts);
            $cert = $files->path($record->id, 'certificate', $record->artifacts);
            $verify = new Process(['xmlsec1', '--verify', '--lax-key-search', '--enabled-key-data', 'rsa', '--enabled-reference-uris', 'empty', '--pubkey-cert-pem', $cert, $xml]);
            $verify->run(); $this->assertTrue($verify->isSuccessful(), $verify->getErrorOutput());
            $zip = new ZipArchive(); $this->assertTrue($zip->open($files->path($record->id, 'zip', $record->artifacts)));
            $this->assertSame(1, $zip->numFiles);
            $this->assertSame(file_get_contents($xml), $zip->getFromIndex(0)); $zip->close();
            $this->assertStringNotContainsString('PRIVATE KEY', file_get_contents($cert));
            $doc = new DOMDocument(); $this->assertTrue($doc->load($xml, LIBXML_NONET));
            $xpath = new DOMXPath($doc); $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $this->assertSame($record === $receipt ? '2.1' : '2.0', $xpath->evaluate('string(/*/cbc:UBLVersionID)'));
            if ($record === $receipt) { $this->assertSame('03', $xpath->evaluate('string(/*/cbc:InvoiceTypeCode)')); }
            else { $this->assertSame('1.1', $xpath->evaluate('string(/*/cbc:CustomizationID)')); }
            $bad = $xml.'.negative-test';
            file_put_contents($bad, str_replace('118.00', '119.00', file_get_contents($xml)));
            $verifyBad = new Process(['xmlsec1', '--verify', '--lax-key-search', '--enabled-key-data', 'rsa', '--enabled-reference-uris', 'empty', '--pubkey-cert-pem', $cert, $bad]);
            $verifyBad->run(); $this->assertFalse($verifyBad->isSuccessful());
        }
    }

    public function test_replays_normalize_values_and_member_order_without_regenerating_evidence(): void
    {
        $input = $this->input(['emission_date' => now()->toDateString()]);
        $one = app(Preparation::class)->issue($this->operator->id, $input);
        $input['quantity'] = '1.000'; $input['unit_price'] = '0100.00';
        $again = app(Preparation::class)->issue($this->operator->id, $input);
        $this->assertSame($one->id, $again->id);
        $two = $this->receipt();
        $key = (string) Str::uuid();
        $a = $this->summary([$one->id, $two->id], $key);
        $b = $this->summary([$two->id, $one->id], $key);
        $this->assertSame($a->id, $b->id);
        foreach ($a->artifacts as $kind => $data) { $this->assertSame($data['sha256'], $b->artifacts[$kind]['sha256']); }
        $this->assertSame(2, DB::table('h4_members')->where('summary_id', $a->id)->count());
    }

    public function test_changed_payload_or_members_conflict_and_existing_members_stay_reserved(): void
    {
        $input = $this->input(['emission_date' => now()->toDateString()]);
        $receipt = app(Preparation::class)->issue($this->operator->id, $input);
        try { app(Preparation::class)->issue($this->operator->id, array_replace($input, ['unit_price' => '101.00'])); $this->fail(); }
        catch (ConflictHttpException) { $this->assertSame(1, Boleta::query()->where('user_id', $this->operator->id)->count()); }
        $key = (string) Str::uuid(); $this->summary([$receipt->id], $key);
        $other = $this->receipt();
        try { $this->summary([$other->id], $key); $this->fail(); }
        catch (ConflictHttpException) { $this->assertSame(1, DailySummary::query()->where('user_id', $this->operator->id)->count()); }
        $this->expectException(ConflictHttpException::class); $this->summary([$receipt->id]);
    }

    public function test_mixed_dates_foreign_members_duplicates_and_unapproved_fields_are_refused(): void
    {
        $a = $this->receipt(); $b = $this->receipt(now()->subDay()->toDateString());
        $foreign = app(Preparation::class)->issue($this->newOperator()->id, $this->input(['emission_date' => now()->toDateString()]));
        foreach ([[$a->id, $b->id], [$foreign->id], [$a->id, $a->id]] as $members) {
            try { $this->summary($members); $this->fail(); } catch (ValidationException) { $this->assertTrue(true); }
        }
        $this->expectException(ValidationException::class);
        app(Preparation::class)->issue($this->operator->id, $this->input(['emission_date' => now()->toDateString(), 'issuer_ruc' => '00000000001']));
    }

    public function test_summary_window_includes_day_seven_and_excludes_day_eight_without_consuming_rc(): void
    {
        $a = $this->receipt(now()->subDays(7)->toDateString());
        $accepted = $this->summary([$a->id]);
        $this->assertSame($a->emission_date, $accepted->reference_date);
        $b = $this->receipt(now()->subDays(8)->toDateString());
        $counter = DB::table('h4_counters')->where('series', 'RC')->value('next_number');
        try { $this->summary([$b->id]); $this->fail(); } catch (ValidationException) {
            $this->assertSame($counter, DB::table('h4_counters')->where('series', 'RC')->value('next_number'));
        }
    }

    public function test_database_freezes_receipt_summary_and_members(): void
    {
        $receipt = $this->receipt(); $summary = $this->summary([$receipt->id]);
        foreach ([fn () => $receipt->update(['number' => 999999]), fn () => $summary->update(['snapshot' => []]),
            fn () => DB::table('h4_members')->where('summary_id', $summary->id)->update(['position' => 99])] as $change) {
            try { $change(); $this->fail('Se altero documento congelado.'); } catch (QueryException) { $this->assertTrue(true); }
        }
        $this->assertSame(1, DB::table('h4_members')->where('summary_id', $summary->id)->value('position'));
    }

    public function test_outbox_failure_rolls_back_only_new_rc_files_counter_and_members(): void
    {
        $receipt = $this->receipt(); $before = glob(config('h4.evidence_root').'/*') ?: [];
        $counter = DB::table('h4_counters')->where('series', 'RC')->value('next_number');
        $name = 'h4_fault_'.str_replace('-', '', (string) Str::uuid());
        DB::unprepared("CREATE FUNCTION $name() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''H4 test only''; END'");
        DB::unprepared("CREATE TRIGGER $name BEFORE INSERT ON h4_outbox FOR EACH ROW EXECUTE FUNCTION $name()");
        try {
            try { $this->summary([$receipt->id]); $this->fail(); } catch (QueryException) {
                $this->assertSame($before, glob(config('h4.evidence_root').'/*') ?: []);
                $this->assertSame(0, DB::table('h4_members')->where('boleta_id', $receipt->id)->count());
                $this->assertSame($counter, DB::table('h4_counters')->where('series', 'RC')->value('next_number'));
            }
        } finally { DB::unprepared("DROP TRIGGER $name ON h4_outbox"); DB::unprepared("DROP FUNCTION $name()"); }
    }

    public function test_frozen_rc_rejects_additional_member_insert_even_from_raw_sql(): void
    {
        $receipt = $this->receipt(); $summary = $this->summary([$receipt->id]); $other = $this->receipt();
        $this->expectException(QueryException::class);
        DB::table('h4_members')->insert(['summary_id' => $summary->id, 'boleta_id' => $other->id, 'position' => 2]);
    }

    public function test_lost_commit_confirmation_preserves_frozen_rc_identity_and_artifacts(): void
    {
        $receipt = $this->receipt(); $key = (string) Str::uuid();
        Event::listen(TransactionCommitted::class, fn () => throw new RuntimeException('H4 lost confirmation'));
        try {
            try { $this->summary([$receipt->id], $key); $this->fail(); } catch (RuntimeException $e) { $this->assertSame('H4 lost confirmation', $e->getMessage()); }
        } finally { Event::forget(TransactionCommitted::class); }
        $existing = DailySummary::query()->where('user_id', $this->operator->id)->sole();
        $this->assertSame($existing->id, $this->summary([$receipt->id], $key)->id);
        app(Artifacts::class)->verify($existing->id, $existing->artifacts);
        $this->assertSame(1, DB::table('h4_outbox')->where('summary_id', $existing->id)->count());
    }

    private function receipt(?string $date = null): Boleta
    {
        return app(Preparation::class)->issue($this->operator->id, $this->input(['emission_date' => $date ?? now()->toDateString()]));
    }

    private function summary(array $members, ?string $key = null): DailySummary
    {
        return app(Preparation::class)->freeze($this->operator->id, ['members' => $members, 'idempotency_key' => $key ?? (string) Str::uuid()]);
    }
}
