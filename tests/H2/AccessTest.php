<?php

namespace Tests\H2;

use App\Invoices\InvoiceIssuer;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AccessTest extends TestCase
{
    public function test_access_requires_authentication_and_limits_failed_attempts(): void
    {
        $key = 'h2-basic:127.0.0.1';
        RateLimiter::clear($key);
        for ($i = 0; $i < 10; $i++) {
            $this->get('/')->assertUnauthorized();
        }
        $this->get('/')->assertStatus(429);
    }

    public function test_form_and_creation_use_server_calculated_values(): void
    {
        $this->withBasicAuth($this->operator->email, $this->password)
            ->get('/invoices/create')->assertOk()->assertSee('Sin validez fiscal')->assertSee('name="_token"', false);
        $this->post('/invoices', $this->input())->assertStatus(303);
        $this->get('/')->assertOk()->assertSee('118.00')->assertSee('Pendiente de simulación');
    }

    public function test_invoice_download_and_simulation_are_owned_by_the_operator(): void
    {
        $other = $this->newOperator();
        $foreign = app(InvoiceIssuer::class)->issue($other->id, $this->input(['description' => 'MARCADOR AJENO']));
        $this->withBasicAuth($this->operator->email, $this->password);
        $this->get('/invoices/'.$foreign->id)->assertNotFound();
        $this->get('/invoices/'.$foreign->id.'/evidence/xml')->assertNotFound();
        $this->post('/invoices/'.$foreign->id.'/simulate', ['scenario' => 'accepted'])->assertNotFound();
        $this->get('/')->assertDontSee('MARCADOR AJENO');
    }

    public function test_safe_html_and_private_evidence_have_no_public_path(): void
    {
        $invoice = $this->issue(['description' => '<script>alert(1)</script>']);
        $this->withBasicAuth($this->operator->email, $this->password);
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/invoices/'.$invoice->id.'/evidence/xml')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/.env')->assertNotFound();
        $this->get('/storage/app/private/h2/'.$invoice->id.'/signed.xml')->assertNotFound();
        $this->get('/invoices/'.$invoice->id.'/evidence/private-key')->assertNotFound();
        $this->put('/invoices/'.$invoice->id, ['number' => 1000])->assertStatus(405);
    }

    public function test_forged_fiscal_fields_are_rejected_and_replay_conflict_is_409(): void
    {
        $this->withBasicAuth($this->operator->email, $this->password);
        $this->postJson('/invoices', $this->input(['total_cents' => 1]))->assertUnprocessable();
        $input = $this->input();
        $this->post('/invoices', $input)->assertStatus(303);
        $input['unit_price'] = '101.00';
        $this->postJson('/invoices', $input)->assertConflict();
    }

    public function test_csrf_is_enforced_when_unit_test_bypass_does_not_apply(): void
    {
        $this->app->instance('env', 'local');
        $this->withBasicAuth($this->operator->email, $this->password)
            ->post('/invoices', $this->input())->assertStatus(419);
    }
}
