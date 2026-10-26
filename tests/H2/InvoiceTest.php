<?php

namespace Tests\H2;

use App\Invoices\InvoiceEvidence;
use App\Invoices\InvoiceInput;
use App\Invoices\InvoiceIssuer;
use App\Invoices\SimulatedTransport;
use App\Models\Invoice;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

final class InvoiceTest extends TestCase
{
    public function test_invoice_has_exact_amounts_and_atomic_outbox_and_valid_signed_zip(): void
    {
        $invoice = $this->issue();
        $this->assertSame(10000, $invoice->subtotal_cents);
        $this->assertSame(1800, $invoice->tax_cents);
        $this->assertSame(11800, $invoice->total_cents);
        $this->assertSame('pending_simulation', $invoice->transport_status);
        $job = DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->sole();
        $this->assertSame('pending', $job->status);
        $this->assertSame(0, $job->attempts);
        $store = app(InvoiceEvidence::class);
        $xmlPath = $store->verifiedPath($invoice->id, 'xml', $invoice->artifacts);
        $certPath = $store->verifiedPath($invoice->id, 'certificate', $invoice->artifacts);
        $verification = new Process(['xmlsec1', '--verify', '--lax-key-search', '--enabled-key-data', 'rsa',
            '--enabled-reference-uris', 'empty', '--pubkey-cert-pem', $certPath, $xmlPath]);
        $verification->run();
        $this->assertTrue($verification->isSuccessful(), $verification->getErrorOutput());
        $document = new DOMDocument();
        $this->assertTrue($document->load($xmlPath, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('118.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($store->verifiedPath($invoice->id, 'zip', $invoice->artifacts)));
        $this->assertSame(file_get_contents($xmlPath), $zip->getFromName('invoice.xml'));
        $zip->close();
        $this->assertStringNotContainsString('PRIVATE KEY', file_get_contents($certPath));
    }

    public function test_replay_normalizes_numbers_and_never_allocates_another_identity(): void
    {
        $input = $this->input();
        $a = app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $before = DB::table('invoice_series')->value('next_number');
        $input['quantity'] = '1.000';
        $input['unit_price'] = '0100.00';
        $b = app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $this->assertSame($a->id, $b->id);
        foreach ($a->artifacts as $kind => $artifact) {
            $this->assertSame($artifact['sha256'], $b->artifacts[$kind]['sha256']);
            $this->assertSame($artifact['bytes'], $b->artifacts[$kind]['bytes']);
        }
        $this->assertSame($before, DB::table('invoice_series')->value('next_number'));
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $a->id)->count());
    }

    public function test_reuse_with_changed_content_is_a_conflict_without_new_number(): void
    {
        $input = $this->input();
        app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $before = DB::table('invoice_series')->value('next_number');
        $input['unit_price'] = '101.00';
        try {
            app(InvoiceIssuer::class)->issue($this->operator->id, $input);
            $this->fail('Se aceptó un contenido distinto para la misma operación.');
        } catch (ConflictHttpException) {
            $this->assertSame($before, DB::table('invoice_series')->value('next_number'));
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'negative' => [['quantity' => '-1']], 'zero' => [['quantity' => '0']],
            'exponent' => [['unit_price' => '1e2']], 'precision' => [['unit_price' => '1.001']],
            'quantity_precision' => [['quantity' => '1.0001']], 'oversized' => [['unit_price' => '1000000']],
            'subcent_total' => [['quantity' => '0.001', 'unit_price' => '0.01']],
            'empty_description' => [['description' => ' ']], 'control' => [['description' => "x\x01"]],
            'spoofed_identity' => [['issuer_ruc' => '00000000001']], 'spoofed_total' => [['total_cents' => 1]],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_validation_failure_does_not_consume_a_number(array $overrides): void
    {
        $before = DB::table('invoice_series')->value('next_number');
        try {
            $this->issue($overrides);
            $this->fail('Se aceptó un dato inválido.');
        } catch (ValidationException) {
            $this->assertSame($before, DB::table('invoice_series')->value('next_number'));
            $this->assertSame(0, Invoice::query()->where('user_id', $this->operator->id)->count());
        }
    }

    public function test_rounding_is_half_up_on_exact_integers(): void
    {
        $a = app(InvoiceInput::class)->validate($this->input(['quantity' => '0.5', 'unit_price' => '0.05']));
        $this->assertSame(3, $a['subtotal_cents']);
        $this->assertSame(1, $a['tax_cents']);
        $this->assertSame(4, $a['total_cents']);
        $b = app(InvoiceInput::class)->validate($this->input(['quantity' => '0.333', 'unit_price' => '0.05']));
        $this->assertSame(2, $b['subtotal_cents']);
        $this->assertSame(0, $b['tax_cents']);
        $this->assertSame('0.02', InvoiceInput::decimal($b['total_cents']));
    }

    public function test_fiscal_configuration_cannot_be_switched_to_a_real_identity(): void
    {
        $before = DB::table('invoice_series')->value('next_number');
        config(['h2.issuer_ruc' => '00000000001']);
        $this->expectException(RuntimeException::class);
        try {
            $this->issue();
        } finally {
            $this->assertSame($before, DB::table('invoice_series')->value('next_number'));
        }
    }

    public function test_outbox_insert_failure_rolls_back_invoice_counter_and_owned_files(): void
    {
        $trigger = 'h2_fault_'.str_replace('-', '', (string) Str::uuid());
        DB::unprepared("CREATE FUNCTION $trigger() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''H2 injected failure''; END'");
        DB::unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON invoice_outbox FOR EACH ROW EXECUTE FUNCTION $trigger()");
        $beforeNumber = DB::table('invoice_series')->value('next_number');
        $beforeDirs = glob(config('h2.evidence_root').'/*') ?: [];
        try {
            try {
                $this->issue();
                $this->fail('No se propagó la falla de persistencia.');
            } catch (QueryException) {
                $this->assertSame(0, Invoice::query()->where('user_id', $this->operator->id)->count());
                $this->assertSame($beforeNumber, DB::table('invoice_series')->value('next_number'));
                $this->assertSame($beforeDirs, glob(config('h2.evidence_root').'/*') ?: []);
            }
        } finally {
            DB::unprepared("DROP TRIGGER $trigger ON invoice_outbox");
            DB::unprepared("DROP FUNCTION $trigger()");
        }
    }

    public function test_collision_never_removes_existing_evidence(): void
    {
        $existing = $this->issue();
        $hash = $existing->artifacts['xml']['sha256'];
        Str::createUuidsUsing(fn () => Uuid::fromString($existing->id));
        try {
            $this->expectException(RuntimeException::class);
            $this->issue(['idempotency_key' => '11111111-1111-4111-8111-111111111111']);
        } finally {
            Str::createUuidsNormally();
            $path = app(InvoiceEvidence::class)->verifiedPath($existing->id, 'xml', $existing->artifacts);
            $this->assertSame($hash, hash_file('sha256', $path));
        }
    }

    public function test_failure_after_commit_preserves_committed_identity_and_evidence(): void
    {
        $input = $this->input();
        Event::listen(TransactionCommitted::class, function () {
            throw new RuntimeException('H2: confirmación perdida después del commit');
        });
        try {
            try {
                app(InvoiceIssuer::class)->issue($this->operator->id, $input);
                $this->fail('No se produjo el fallo posterior al commit.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('confirmación perdida', $exception->getMessage());
            }
        } finally {
            Event::forget(TransactionCommitted::class);
        }
        $committed = Invoice::query()->where('user_id', $this->operator->id)->sole();
        $path = app(InvoiceEvidence::class)->verifiedPath($committed->id, 'xml', $committed->artifacts);
        $this->assertSame($committed->artifacts['xml']['sha256'], hash_file('sha256', $path));
        $retry = app(InvoiceIssuer::class)->issue($this->operator->id, $input);
        $this->assertSame($committed->id, $retry->id);
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $committed->id)->count());
    }

    #[DataProvider('scenarios')]
    public function test_mock_results_are_explicit_and_processed_once(string $scenario, string $status): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $first = $transport->dispatch($invoice->id, $scenario);
        $this->assertSame($status, $first->transport_status);
        $this->assertTrue($first->simulated_response['simulated']);
        $this->assertSame('H2_LOCAL_FAKE', $first->simulated_response['source']);
        $this->assertStringContainsString('No se envió a SUNAT', $first->simulated_response['message']);
        $this->assertNull($transport->dispatch($invoice->id, 'accepted'));
        $this->assertSame($status, $invoice->fresh()->transport_status);
        $this->assertSame(1, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
    }

    public static function scenarios(): array
    {
        return [['accepted', 'simulated_accepted'], ['rejected', 'simulated_rejected'], ['uncertain', 'simulation_uncertain']];
    }

    public function test_tampered_artifacts_stop_dispatch_without_success_or_attempt_consumption(): void
    {
        $invoice = $this->issue();
        $path = app(InvoiceEvidence::class)->verifiedPath($invoice->id, 'xml', $invoice->artifacts);
        file_put_contents($path, 'FICTICIO ALTERADO');
        try {
            app(SimulatedTransport::class)->dispatch($invoice->id);
            $this->fail('Se procesó evidencia alterada.');
        } catch (RuntimeException) {
            $this->assertSame('pending_simulation', $invoice->fresh()->transport_status);
            $this->assertSame(0, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts'));
        }
    }
}
