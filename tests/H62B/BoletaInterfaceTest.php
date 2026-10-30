<?php

namespace Tests\H62B;

use App\Boletas\{Artifacts, Preparation, SummaryTransport};
use App\Models\{Boleta, DailySummary};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BoletaInterfaceTest extends TestCase
{
    private function login(): void { $this->signIn($this->operator->email, $this->password); }
    private function receipt(array $values = []): Boleta { return app(Preparation::class)->issue($this->operator->id, $this->input($values + ['emission_date'=>now('America/Lima')->toDateString()])); }
    private function form(array $members): array { return ['idempotency_key'=>(string) Str::uuid(), 'members'=>$members]; }
    private function summary(): DailySummary { return app(Preparation::class)->freeze($this->operator->id, $this->form([$this->receipt()->id])); }
    private function job(string $id): object { return DB::table('h4_outbox')->where('summary_id', $id)->sole(); }

    public function test_boleta_validation_preserves_its_form_key_and_safe_escaped_values(): void
    {
        $this->login(); $form = $this->input(['emission_date'=>now()->toDateString(), 'description'=>'<script>FICTICIO</script>', 'quantity'=>'0']);
        $this->post('/boletas', $form)->assertStatus(303)->assertRedirect('/boletas')->assertSessionHasErrors('quantity')->assertSessionHasInput('boleta_form.idempotency_key', $form['idempotency_key']);
        $this->get('/boletas')->assertOk()->assertSee($form['idempotency_key'])->assertSee('&lt;script&gt;FICTICIO&lt;/script&gt;', false)->assertDontSee('<script>FICTICIO</script>', false);
        $this->assertSame(0, Boleta::query()->where('user_id', $this->operator->id)->count());
        $form['quantity']='2'; $url=$this->post('/boletas', $form)->assertStatus(303)->headers->get('Location');
        $this->post('/boletas', $form)->assertRedirect($url);
        $this->post('/boletas', $form + ['total_cents'=>1])->assertSessionHasErrors();
        $this->post('/boletas', array_replace($form, ['quantity'=>'3']))->assertConflict()->assertSee($form['idempotency_key'])->assertSee('Clave reutilizada');
        $this->assertSame(1, Boleta::query()->where('user_id', $this->operator->id)->count());
    }

    public function test_selection_explains_reservation_and_window_and_keeps_key_members_on_validation_error(): void
    {
        $a=$this->receipt(); $old=$this->receipt(['emission_date'=>now()->subDays(8)->toDateString()]); $reserved=$this->summary();
        $other=$this->receipt(['emission_date'=>now()->subDay()->toDateString()]); $this->login();
        $page=$this->get('/boletas')->assertOk()->assertSee('Reservada en un RC')->assertSee('Fuera de la ventana D+7');
        $page->assertSee($a->id)->assertSee($old->id)->assertSee('disabled', false);
        $form=$this->form([$a->id, $other->id]);
        $this->post('/summaries/review', $form)->assertStatus(303)->assertSessionHasInput('summary_form.members', $form['members'])->assertSessionHasInput('summary_form.idempotency_key', $form['idempotency_key']);
        $this->get('/boletas')->assertOk()->assertSee($form['idempotency_key'])->assertSee('checked', false);
        $this->postJson('/summaries/review', $this->form([$old->id]))->assertUnprocessable();
        $this->postJson('/summaries/review', $this->form(array_column($reserved->snapshot['members'], 'id')))->assertConflict();
        $this->postJson('/summaries/review', $this->form([$a->id, $a->id]))->assertUnprocessable();
        $this->postJson('/summaries/review', $this->form([$a->id]) + ['issue_date'=>'2026-10-30'])->assertUnprocessable();
    }

    public function test_review_is_read_only_shows_frozen_server_amounts_and_edit_keeps_operation(): void
    {
        $a=$this->receipt(['quantity'=>'2', 'unit_price'=>'12.50']); $form=$this->form([$a->id]); $this->login();
        $counts=[]; foreach (['h4_boletas','h4_summaries','h4_members','h4_outbox','h4_events'] as $table) { $counts[$table]=DB::table($table)->count(); }
        $counter=DB::table('h4_counters')->get()->toJson(); $files=glob(config('h4.evidence_root').'/*');
        $this->post('/summaries/review', $form)->assertOk()->assertSee('25.00')->assertSee('4.50')->assertSee('29.50')->assertSee('no se reserva numeración')->assertSee($form['idempotency_key']);
        foreach ($counts as $table=>$count) { $this->assertSame($count, DB::table($table)->count()); }
        $this->assertSame($counter, DB::table('h4_counters')->get()->toJson()); $this->assertSame($files, glob(config('h4.evidence_root').'/*'));
        $this->assertIsString(session('summary_reviews.'.$form['idempotency_key']));
        $this->post('/summaries/edit-review', $form)->assertOk()->assertSee($form['idempotency_key'])->assertSee('checked', false);
    }

    public function test_confirmation_is_bound_to_review_and_double_submit_and_expired_session_keep_one_identity(): void
    {
        $a=$this->receipt(); $b=$this->receipt(); $form=$this->form([$a->id, $b->id]); $this->login();
        $this->postJson('/summaries', $form)->assertConflict(); $this->post('/summaries/review', $form)->assertOk();
        $this->postJson('/summaries', array_replace($form, ['members'=>[$a->id]]))->assertConflict();
        $url=$this->post('/summaries', $form)->assertStatus(303)->headers->get('Location');
        $form['members']=array_reverse($form['members']); $this->post('/summaries', $form)->assertRedirect($url);
        $rc=DailySummary::query()->where('user_id', $this->operator->id)->sole(); $hash=$rc->artifacts;
        session()->forget('summary_reviews'); $this->postJson('/summaries', $form)->assertConflict();
        $this->post('/summaries/review', $form)->assertOk()->assertSee('ya corresponde'); $this->post('/summaries', $form)->assertRedirect($url);
        $this->assertSame($hash, $rc->fresh()->artifacts); $this->assertSame(2, DB::table('h4_members')->where('summary_id', $rc->id)->count());
        $this->assertSame(1, DB::table('h4_outbox')->where('summary_id', $rc->id)->count());
    }

    public function test_competing_freeze_and_date_expiry_after_review_are_revalidated_by_h4(): void
    {
        $a=$this->receipt(); $form=$this->form([$a->id]); $this->login(); $this->post('/summaries/review', $form)->assertOk();
        $winner=app(Preparation::class)->freeze($this->operator->id, $this->form([$a->id]));
        $this->post('/summaries', $form)->assertConflict()->assertSee($form['idempotency_key'])->assertSee($a->id)->assertSee('Miembro ya reservado');
        $this->assertSame($winner->id, DB::table('h4_members')->where('boleta_id', $a->id)->value('summary_id'));
        $old=$this->receipt(['emission_date'=>now()->subDays(7)->toDateString()]); $f=$this->form([$old->id]);
        $this->post('/summaries/review', $f)->assertOk(); $this->travel(1)->days();
        $this->postJson('/summaries', $f)->assertUnprocessable();
        $this->assertFalse(DB::table('h4_members')->where('boleta_id', $old->id)->exists());
    }

    public static function roles(): array { return [['ADMIN'], ['OPERADOR']]; }
    #[DataProvider('roles')]
    public function test_both_roles_deny_foreign_selection_details_actions_history_and_downloads(string $role): void
    {
        $other=$this->newOperator(); $a=app(Preparation::class)->issue($other->id, $this->input(['emission_date'=>now()->toDateString(), 'description'=>'SECRETO AJENO H62B']));
        $rc=app(Preparation::class)->freeze($other->id, $this->form([$a->id]));
        $this->operator->update(['role'=>$role]);
        try {
            $this->login(); $this->get('/boletas')->assertOk()->assertDontSee($a->id)->assertDontSee($rc->name);
            $this->get('/boletas/'.$a->id)->assertNotFound(); $this->get('/summaries/'.$rc->id)->assertNotFound();
            foreach (['review','edit-review'] as $route) { $this->postJson('/summaries/'.$route, $this->form([$a->id]))->assertUnprocessable(); }
            $this->postJson('/summaries', $this->form([$a->id]))->assertUnprocessable();
            foreach (['send','poll','recover'] as $action) { $this->postJson('/summaries/'.$rc->id.'/simulate', ['action'=>$action])->assertNotFound(); }
            $this->get('/h4/boleta/'.$a->id.'/evidence/xml')->assertNotFound(); $this->get('/h4/rc/'.$rc->id.'/evidence/zip')->assertNotFound();
        } finally { $this->operator->update(['role'=>'OPERADOR']); }
    }

    public static function outcomes(): array { return [['0','sim_accepted'], ['99','sim_rejected']]; }
    #[DataProvider('outcomes')]
    public function test_ui_ticket_98_then_result_distinguishes_rc_from_its_boleta_and_prevents_terminal_actions(string $code, string $state): void
    {
        $a=$this->receipt(); $form=$this->form([$a->id]); $this->login();
        $this->post('/summaries/review', $form)->assertOk(); $this->post('/summaries', $form)->assertStatus(303);
        $rc=DailySummary::query()->where('user_id', $this->operator->id)->sole(); $url='/summaries/'.$rc->id; $hash=$rc->artifacts;
        $this->get($url)->assertOk()->assertSee('Procesar envío ficticio')->assertDontSee('id="poll_scenario"', false);
        $this->post($url.'/simulate', ['action'=>'send'])->assertStatus(303); $ticket=$rc->fresh()->ticket;
        $this->get($url)->assertOk()->assertSee($ticket)->assertSee('Sin resultado definitivo confirmado')->assertDontSee('id="send_scenario"', false);
        $this->post($url.'/simulate', ['action'=>'poll','scenario'=>'98'])->assertStatus(303);
        $this->postJson($url.'/simulate', ['action'=>'poll','scenario'=>'0'])->assertConflict();
        $this->travel(6)->seconds(); $this->post($url.'/simulate', ['action'=>'poll','scenario'=>$code])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee($state)->assertSee('result_saved')->assertSee('Sin CDR oficial')->assertDontSee('id="poll_scenario"', false);
        $this->get('/boletas/'.$a->id)->assertOk()->assertSee('El resultado corresponde al RC')->assertSee($state);
        foreach (['send','poll','recover'] as $action) { $this->postJson($url.'/simulate', ['action'=>$action])->assertConflict(); }
        $this->assertSame(1, $this->job($rc->id)->send_attempts); $this->assertSame(2, $this->job($rc->id)->poll_attempts);
        $this->assertSame($ticket, $rc->fresh()->ticket); $this->assertSame($hash, $rc->fresh()->artifacts);
        foreach (['xml','zip','certificate'] as $file) { $this->get('/h4/rc/'.$rc->id.'/evidence/'.$file)->assertOk(); }
    }

    public function test_lost_ticket_blocks_lookup_recovery_and_resending_without_manufacturing_result(): void
    {
        $rc=$this->summary(); $this->login(); $url='/summaries/'.$rc->id;
        $this->post($url.'/simulate', ['action'=>'send','scenario'=>'lost_ticket'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('Sin ticket conservado')->assertDontSee('Revisar recuperación local')->assertDontSee('id="send_scenario"', false);
        foreach (['send','poll','recover'] as $action) { $this->postJson($url.'/simulate', ['action'=>$action])->assertConflict(); }
        $this->assertNull($rc->fresh()->ticket); $this->assertNull($rc->fresh()->result); $this->assertSame(1, $this->job($rc->id)->send_attempts);
        $this->assertSame(0, $this->job($rc->id)->poll_attempts);
    }

    public function test_recovery_only_rearms_a_conserved_ticket_and_expired_reservations_remain_safe(): void
    {
        $rc=$this->summary(); $this->login(); $url='/summaries/'.$rc->id;
        $this->post($url.'/simulate', ['action'=>'send'])->assertStatus(303); $ticket=$rc->fresh()->ticket;
        $this->post($url.'/simulate', ['action'=>'poll','scenario'=>'timeout'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('Revisar recuperación local')->assertSee('Resultado incierto');
        $this->post($url.'/simulate', ['action'=>'recover'])->assertStatus(303); $this->assertSame(1, $this->job($rc->id)->send_attempts);
        Event::listen('h4.poll.claimed', fn () => throw new \RuntimeException('H62B interrupción propia'));
        try { app(SummaryTransport::class)->poll($rc->id); $this->fail(); } catch (\RuntimeException $error) { $this->assertSame('H62B interrupción propia', $error->getMessage()); }
        finally { Event::forget('h4.poll.claimed'); }
        $this->postJson($url.'/simulate', ['action'=>'recover'])->assertConflict();
        $this->travel(6)->minutes(); $this->post($url.'/simulate', ['action'=>'recover'])->assertStatus(303);
        $this->post($url.'/simulate', ['action'=>'poll','scenario'=>'0'])->assertStatus(303);
        $this->assertSame('sim_accepted', $rc->fresh()->state); $this->assertSame($ticket, $rc->fresh()->ticket);
        $this->assertSame(1, $this->job($rc->id)->send_attempts);
    }

    public function test_altered_new_evidence_blocks_review_freeze_processing_and_private_download_without_repair(): void
    {
        $a=$this->receipt(); $f=$this->form([$a->id]); $this->login(); $this->post('/summaries/review', $f)->assertOk();
        $path=app(Artifacts::class)->path($a->id, 'xml', $a->artifacts); file_put_contents($path, 'H62B FIXTURE ALTERADA');
        $this->post('/summaries/review', $f)->assertConflict(); $this->post('/summaries', $f)->assertConflict();
        $this->get('/h4/boleta/'.$a->id.'/evidence/xml')->assertConflict();
        $rc=$this->summary(); $rcPath=app(Artifacts::class)->path($rc->id, 'xml', $rc->artifacts); file_put_contents($rcPath, 'H62B RC ALTERADO');
        $this->post('/summaries/'.$rc->id.'/simulate', ['action'=>'send'])->assertConflict();
        $this->assertSame(0, $this->job($rc->id)->send_attempts); $this->assertSame('H62B FIXTURE ALTERADA', file_get_contents($path));
    }

    public function test_list_filters_and_pages_locate_older_own_documents_and_keep_off_page_selections(): void
    {
        $first=$this->receipt(); foreach (range(1,20) as $i) { $this->receipt(); } $this->login();
        $this->get('/boletas')->assertOk()->assertDontSee('value="'.$first->id.'"', false)->assertSee('boletas_page=2', false);
        $this->get('/boletas?boletas_page=2')->assertOk()->assertSee($first->id);
        $this->get('/boletas?boleta_number='.$first->number)->assertOk()->assertSee($first->id);
        $form=$this->form([$first->id]); $this->post('/summaries/edit-review', $form)->assertOk()->assertSee($first->id)->assertSee('checked', false)->assertSee($form['idempotency_key']);
        $rc=app(Preparation::class)->freeze($this->operator->id, $form);
        $this->get('/boletas?rc_name='.urlencode($rc->name))->assertOk()->assertSee($rc->name);
        $this->get('/boletas?boleta_date='.now()->subDay()->toDateString())->assertOk()->assertDontSee('value="'.$first->id.'"', false);
    }

    public function test_history_is_scoped_paginated_and_escaped_and_all_new_actions_require_auth_and_csrf(): void
    {
        $rc=$this->summary(); $this->getJson('/summaries/'.$rc->id)->assertUnauthorized(); $this->login();
        foreach (range(1,21) as $n) { DB::table('h4_events')->insert(['summary_id'=>$rc->id,'kind'=>$n === 21 ? 'ULTIMO H62B' : '<script>H62B</script>', 'token'=>null,'attention'=>false,'payload'=>'{}','created_at'=>now('UTC')]); }
        $this->get('/summaries/'.$rc->id)->assertOk()->assertSee('&lt;script&gt;H62B&lt;/script&gt;', false)->assertDontSee('<script>H62B</script>', false)->assertDontSee('ULTIMO H62B');
        $this->get('/summaries/'.$rc->id.'?events_page=2')->assertOk()->assertSee('ULTIMO H62B');
        $this->app->instance('env', 'local');
        foreach (['/boletas','/summaries/review','/summaries/edit-review','/summaries','/summaries/'.$rc->id.'/simulate'] as $url) { $this->post($url, [])->assertStatus(419); }
    }
}
