<?php
namespace Tests\H62A;

use App\Invoices\{InvoiceEvidence, InvoiceIssuer, SimulatedTransport};
use App\Models\Invoice;
use Illuminate\Support\Facades\{DB, Event};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InvoiceInterfaceTest extends TestCase
{
    private function signInOperator(): void { $this->signIn($this->operator->email, $this->password); }
    private function job(string $id): object { return DB::table('invoice_outbox')->where('invoice_id',$id)->sole(); }
    private function prepare(array $input): Invoice
    {
        $this->reviewInvoice($input)->post('/invoices',$input)->assertStatus(303);
        return Invoice::query()->where('user_id',$this->operator->id)->where('idempotency_key',$input['idempotency_key'])->sole();
    }
    public function test_review_calculates_on_server_without_any_document_number_outbox_or_files(): void
    {
        $this->signInOperator();$input=$this->input(['quantity'=>'2','unit_price'=>'12.50']);
        $before=[Invoice::query()->count(), DB::table('invoice_series')->value('next_number'), DB::table('invoice_outbox')->count(), count(glob(config('h2.evidence_root').'/*')?:[])];
        $this->post('/invoices/review',$input)->assertOk()->assertSee('25.00 PEN')->assertSee('4.50 PEN')->assertSee('29.50 PEN')->assertSee($input['idempotency_key']);
        $after=[Invoice::query()->count(), DB::table('invoice_series')->value('next_number'), DB::table('invoice_outbox')->count(), count(glob(config('h2.evidence_root').'/*')?:[])];
        $this->assertSame($before,$after);
        $this->postJson('/invoices/review',$input+['total_cents'=>1])->assertUnprocessable();
    }
    public function test_validation_and_edit_preserve_form_values_and_operation_key(): void
    {
        $this->signInOperator();$input=$this->input(['quantity'=>'0','description'=>'CONCEPTO CONSERVADO']);
        $this->from('/invoices/create')->post('/invoices/review',$input)->assertRedirect('/invoices/create')->assertSessionHasErrors();
        $this->get('/invoices/create')->assertOk()->assertSee($input['idempotency_key'])->assertSee('CONCEPTO CONSERVADO')->assertSee('value="0"',false);
        $input['quantity']='2';$this->reviewInvoice($input);
        $this->post('/invoices/edit-review',$input)->assertOk()->assertSee($input['idempotency_key'])->assertSee('value="2"',false);
        $this->assertFalse(Invoice::query()->where('idempotency_key',$input['idempotency_key'])->exists());
    }
    public function test_confirmation_requires_matching_review_and_double_submit_keeps_identity_and_evidence(): void
    {
        $this->signInOperator();$input=$this->input();$number=DB::table('invoice_series')->value('next_number');
        $this->postJson('/invoices',$input)->assertConflict();
        $this->reviewInvoice($input);
        $this->postJson('/invoices',array_replace($input,['unit_price'=>'101.00']))->assertConflict();
        $this->assertSame($number,DB::table('invoice_series')->value('next_number'));
        $n=$this->prepare($input);$files=$n->artifacts;
        $this->post('/invoices',$input)->assertRedirectToRoute('invoices.show',$n->id);
        $this->assertSame(1,Invoice::query()->where('user_id',$this->operator->id)->where('idempotency_key',$input['idempotency_key'])->count());
        $this->assertSame($files,$n->fresh()->artifacts);$this->assertSame($number+1,DB::table('invoice_series')->value('next_number'));
        $changed=array_replace($input,['unit_price'=>'102.00']);$this->reviewInvoice($changed)->postJson('/invoices',$changed)->assertConflict();
        $this->assertSame($files,$n->fresh()->artifacts);
    }
    public function test_review_is_bound_to_user_even_if_session_and_key_are_reused(): void
    {
        $this->signInOperator();$input=$this->input();$this->reviewInvoice($input);
        $other=$this->newOperator();$this->signIn($other->email,$this->password);
        $this->postJson('/invoices',$input)->assertConflict();
        $this->assertFalse(Invoice::query()->where('idempotency_key',$input['idempotency_key'])->exists());
    }
    public static function roles(): array { return [['OPERADOR'],['ADMIN']]; }
    #[DataProvider('roles')]
    public function test_roles_never_grant_access_to_foreign_history_recovery_or_downloads(string $role): void
    {
        $owner=$this->newOperator();$n=app(InvoiceIssuer::class)->issue($owner->id,$this->input());
        app(SimulatedTransport::class)->dispatch($n->id,'timeout');
        $this->operator->role=$role;$this->operator->save();$this->signInOperator();
        $this->get('/invoices/'.$n->id)->assertNotFound();
        $this->post('/invoices/'.$n->id.'/recover')->assertNotFound();
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'accepted'])->assertNotFound();
        $this->get('/invoices/'.$n->id.'/evidence/xml')->assertNotFound();
        $this->assertSame('simulation_uncertain',$n->fresh()->transport_status);$this->assertSame(1,$this->job($n->id)->attempts);
    }
    public function test_timeout_recovery_shows_stored_attempts_and_events_without_resending(): void
    {
        $this->signInOperator();$n=$this->prepare($this->input());
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'timeout'])->assertStatus(303);
        $this->get('/invoices/'.$n->id)->assertOk()->assertSee('Resultado incierto')->assertSee('Consultar recuperación local')->assertSee('Sin resultado definitivo confirmado');
        $this->post('/invoices/'.$n->id.'/recover')->assertStatus(303);
        $this->get('/invoices/'.$n->id)->assertOk()->assertSee('Resultado recuperado del simulador')->assertSee('SIM-ACCEPTED')->assertSee('Sin CDR oficial')->assertDontSee('Consultar recuperación local');
        $this->assertSame(['claimed','uncertain','recovered'],DB::table('invoice_transport_events')->where('invoice_id',$n->id)->orderBy('id')->pluck('kind')->all());
        $this->assertSame(1,$this->job($n->id)->attempts);
        $this->post('/invoices/'.$n->id.'/recover')->assertConflict();
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'accepted'])->assertConflict();
        $this->get('/invoices/'.$n->id.'/evidence/zip')->assertOk();
    }
    public function test_unresolved_uncertainty_stays_visible_and_cannot_be_resent(): void
    {
        $this->signInOperator();$n=$this->prepare($this->input());$manifest=$n->artifacts;
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'uncertain'])->assertStatus(303);
        $this->post('/invoices/'.$n->id.'/recover')->assertStatus(303);
        $this->get('/invoices/'.$n->id)->assertOk()->assertSee('Consulta sin resultado verificable')->assertSee('Requiere revisión')->assertDontSee('SIM-ACCEPTED');
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'accepted'])->assertConflict();
        $this->assertSame('simulation_uncertain',$n->fresh()->transport_status);$this->assertSame(1,$this->job($n->id)->attempts);$this->assertSame($manifest,$n->fresh()->artifacts);
    }
    public function test_manual_recovery_respects_live_lease_and_handles_only_expired_processing(): void
    {
        $n=$this->issue();$this->signInOperator();
        Event::listen('h3.transport.claimed',fn()=>throw new \RuntimeException('INTERRUPCIÓN FICTICIA H6-2A'));
        try { app(SimulatedTransport::class)->dispatch($n->id); }
        catch (\RuntimeException) {} finally { Event::forget('h3.transport.claimed'); }
        $this->get('/invoices/'.$n->id)->assertOk()->assertDontSee('Consultar recuperación local');
        $this->post('/invoices/'.$n->id.'/recover')->assertConflict();
        $this->travel(6)->minutes();
        $this->get('/invoices/'.$n->id)->assertOk()->assertSee('Consultar recuperación local');
        $this->post('/invoices/'.$n->id.'/recover')->assertStatus(303);
        $this->assertSame('simulation_uncertain',$n->fresh()->transport_status);$this->assertSame(1,$this->job($n->id)->attempts);
    }
    public function test_history_is_scoped_paginated_and_escaped(): void
    {
        $n=$this->issue();$other=$this->issue();$this->signInOperator();
        DB::table('invoice_transport_events')->insert(['invoice_id'=>$other->id,'kind'=>'MARCADOR DE OTRA FACTURA','attempt'=>0,'created_at'=>now('UTC')]);
        for($i=0;$i<21;$i++){DB::table('invoice_transport_events')->insert(['invoice_id'=>$n->id,'kind'=>$i===20?'ULTIMO EVENTO':'<script>MARCADOR</script>','attempt'=>0,'created_at'=>now('UTC')]);}
        $this->get('/invoices/'.$n->id)->assertOk()->assertDontSee('MARCADOR DE OTRA FACTURA')->assertDontSee('<script>MARCADOR</script>',false)->assertSee('&lt;script&gt;',false)->assertDontSee('ULTIMO EVENTO');
        $this->get('/invoices/'.$n->id.'?events_page=2')->assertOk()->assertSee('ULTIMO EVENTO');
    }
    public function test_altered_new_fixture_blocks_download_dispatch_and_recovery_without_repair(): void
    {
        $n=$this->issue();$this->signInOperator();$files=app(InvoiceEvidence::class);
        $path=$files->verifiedPath($n->id,'xml',$n->artifacts);file_put_contents($path,'H6-2A FIXTURE ALTERADA');
        $this->get('/invoices/'.$n->id.'/evidence/xml')->assertConflict();
        $this->post('/invoices/'.$n->id.'/simulate',['scenario'=>'accepted'])->assertConflict();
        $this->assertSame(0,$this->job($n->id)->attempts);
        $m=$this->issue();app(SimulatedTransport::class)->dispatch($m->id,'timeout');
        $otherPath=$files->verifiedPath($m->id,'xml',$m->artifacts);file_put_contents($otherPath,'H6-2A OTRA FIXTURE ALTERADA');
        $this->post('/invoices/'.$m->id.'/recover')->assertConflict();
        $this->assertSame('simulation_uncertain',$m->fresh()->transport_status);$this->assertSame(1,$this->job($m->id)->attempts);
        $this->assertSame('H6-2A FIXTURE ALTERADA',file_get_contents($path));
    }
    public function test_csrf_and_guest_guards_cover_review_edit_preparation_and_recovery(): void
    {
        $this->getJson('/invoices/create')->assertUnauthorized();$this->signInOperator();$n=$this->issue();
        $this->app->instance('env','local');
        foreach(['/invoices/review','/invoices/edit-review','/invoices','/invoices/'.$n->id.'/recover'] as $url){$this->post($url,$this->input())->assertStatus(419);}
    }
}
