<?php

namespace Tests\H4;

use App\Boletas\Preparation;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccessTest extends TestCase
{
    public function test_minimum_ui_creates_receipt_freezes_rc_and_only_reports_a_simulated_result(): void
    {
        $this->signIn($this->operator->email, $this->password)->get('/boletas')->assertOk()->assertSee('H4')->assertSee('Sin validez fiscal');
        $input = $this->input(['emission_date' => now()->toDateString()]);
        $receipt = app(Preparation::class)->issue($this->operator->id, $input);
        $this->post('/boletas', $input)->assertRedirectToRoute('boletas.show', $receipt->id);
        $this->get('/boletas/'.$receipt->id)->assertOk()->assertSee('Sin CDR oficial');
        $summary = app(Preparation::class)->freeze($this->operator->id, ['idempotency_key' => (string) Str::uuid(), 'members' => [$receipt->id]]);
        $this->get('/summaries/'.$summary->id)->assertOk()->assertSee('pending_send')->assertSee('No recibido');
        $this->post('/summaries/'.$summary->id.'/simulate', ['action' => 'send'])->assertStatus(303);
        $this->get('/summaries/'.$summary->id)->assertOk()->assertSee('awaiting')->assertSee('SIM-H4-')->assertDontSee('sim_accepted');
        $this->post('/summaries/'.$summary->id.'/simulate', ['action' => 'poll', 'scenario' => '0'])->assertStatus(303);
        $this->get('/summaries/'.$summary->id)->assertOk()->assertSee('sim_accepted')->assertSee('Sin CDR oficial');
        $this->get('/boletas/'.$receipt->id)->assertOk()->assertSee('sim_accepted')->assertSee('Sin CDR oficial');
        $this->get('/h4/rc/'.$summary->id.'/evidence/zip')->assertOk();
        $this->get('/h4/boleta/'.$receipt->id.'/evidence/xml')->assertOk();
    }

    public function test_server_rejects_cross_owner_reads_downloads_and_actions(): void
    {
        $other = $this->newOperator();
        $receipt = app(Preparation::class)->issue($other->id, $this->input(['emission_date' => now()->toDateString()]));
        $summary = app(Preparation::class)->freeze($other->id, ['idempotency_key' => (string) Str::uuid(), 'members' => [$receipt->id]]);
        $this->signIn($this->operator->email, $this->password);
        $this->get('/boletas/'.$receipt->id)->assertNotFound();
        $this->get('/summaries/'.$summary->id)->assertNotFound();
        $this->get('/h4/rc/'.$summary->id.'/evidence/xml')->assertNotFound();
        $this->post('/summaries/'.$summary->id.'/simulate', ['action' => 'send'])->assertNotFound();
        $this->postJson('/summaries', ['idempotency_key' => (string) Str::uuid(), 'members' => [$receipt->id]])->assertUnprocessable();
        $this->postJson('/summaries/'.$summary->id.'/simulate', ['action' => 'send', 'scenario' => 'individual'])->assertNotFound();
    }

    public function test_csrf_and_unsupported_operations_remain_blocked(): void
    {
        $this->app->instance('env', 'local');
        $this->signIn($this->operator->email, $this->password)->post('/boletas', [])->assertStatus(419);
        $this->post('/summaries', [])->assertStatus(419);
        $this->get('/h4/boleta/'.Str::uuid().'/evidence/private-key')->assertNotFound();
        $this->get('/storage/app/private/h4/test.xml')->assertNotFound();
        $this->put('/boletas/'.Str::uuid(), [])->assertStatus(405);
    }
}
