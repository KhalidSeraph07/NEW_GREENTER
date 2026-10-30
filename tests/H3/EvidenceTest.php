<?php

namespace Tests\H3;

use App\Invoices\EvidenceReconciler;
use App\Invoices\InvoiceEvidence;
use App\Invoices\SimulatedTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EvidenceTest extends TestCase
{
    public function test_safe_retry_and_inflight_are_not_presented_as_uncertain_or_confirmed(): void
    {
        $invoice = $this->issue();
        $transport = app(SimulatedTransport::class);
        $transport->dispatch($invoice->id, 'communication_error');
        $this->signIn($this->operator->email, $this->password);
        foreach (['/', '/invoices/'.$invoice->id] as $url) {
            $this->get($url)->assertOk()->assertSee('Reintento local seguro pendiente')->assertDontSee('Simulación incierta');
        }
        foreach ([2, 3] as $attempt) { $this->travel(11)->seconds(); $transport->dispatch($invoice->id, 'communication_error'); }
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Intentos locales agotados; requiere revisión')->assertSee('Sin CDR oficial');
        $inflight = $this->issue();
        Event::listen('h3.transport.claimed', fn () => throw new RuntimeException('Pausa de prueba tras reserva'));
        try {
            try { $transport->dispatch($inflight->id); } catch (RuntimeException) { /* Reserva conservada. */ }
        } finally { Event::forget('h3.transport.claimed'); }
        $this->assertSame('simulation_processing', $inflight->fresh()->transport_status);
        $this->get('/invoices/'.$inflight->id)->assertOk()->assertSee('Simulación en curso; resultado pendiente')
            ->assertDontSee('Aceptada en simulación')->assertSee('Sin CDR oficial');
    }

    public function test_reconciliation_detects_missing_tampered_and_orphan_without_repair_or_deletion(): void
    {
        $invoice = $this->issue();
        $evidence = app(InvoiceEvidence::class);
        $xml = $evidence->verifiedPath($invoice->id, 'xml', $invoice->artifacts);
        $zip = $evidence->verifiedPath($invoice->id, 'zip', $invoice->artifacts);
        file_put_contents($xml, 'H3 TAMPERED FIXTURE');
        rename($zip, $zip.'.missing-test');
        $orphan = (string) Str::uuid();
        $directory = config('h2.evidence_root').'/'.$orphan;
        mkdir($directory, 0700);
        file_put_contents($directory.'/unknown.bin', 'H3 orphan test');
        $report = app(EvidenceReconciler::class)->inspect();
        $this->assertTrue($report['read_only']);
        $this->assertContains(['id' => $invoice->id, 'kind' => 'xml', 'issue' => 'missing_or_changed'], $report['issues']);
        $this->assertContains(['id' => $invoice->id, 'kind' => 'zip', 'issue' => 'missing_or_changed'], $report['issues']);
        $this->assertContains(['id' => $orphan, 'issue' => 'orphan_candidate'], $report['issues']);
        $this->assertSame('H3 TAMPERED FIXTURE', file_get_contents($xml));
        $this->assertSame('H3 orphan test', file_get_contents($directory.'/unknown.bin'));
        $this->assertFileDoesNotExist($zip);
        $this->assertSame('pending_simulation', $invoice->fresh()->transport_status);
    }

    public function test_symlinked_evidence_directory_is_not_followed_or_accepted(): void
    {
        $invoice = $this->issue();
        $directory = config('h2.evidence_root').'/'.$invoice->id;
        rename($directory, $directory.'-fixture-target');
        symlink($directory.'-fixture-target', $directory);
        $report = app(EvidenceReconciler::class)->inspect();
        $this->assertContains(['entry' => $invoice->id, 'issue' => 'unexpected_entry'], $report['issues']);
        try { app(SimulatedTransport::class)->dispatch($invoice->id); $this->fail('Se siguio enlace de evidencia.'); }
        catch (RuntimeException) { $this->assertSame(0, DB::table('invoice_outbox')->where('invoice_id', $invoice->id)->value('attempts')); }
        $this->assertTrue(is_link($directory));
    }

    public function test_cli_incident_alert_and_recovery_do_not_report_success_for_uncertainty(): void
    {
        $invoice = $this->issue();
        app(SimulatedTransport::class)->dispatch($invoice->id, 'uncertain');
        $this->assertSame(1, Artisan::call('h3:recover', ['invoice' => $invoice->id]));
        $this->assertFalse(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['official_cdr']);
        $this->assertSame(1, Artisan::call('h3:inspect'));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(0, $report['attention_count']);
        $this->assertSame('simulation_uncertain', $invoice->fresh()->transport_status);
    }
}
