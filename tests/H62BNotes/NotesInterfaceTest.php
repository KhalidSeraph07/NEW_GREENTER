<?php

namespace Tests\H62BNotes;

use App\Models\{InvoiceNote, BoletaNote, NoteSummary};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NotesInterfaceTest extends TestCase
{
    private function login(): void { $this->signIn($this->operator->email,$this->password); }
    private function original(string $family): mixed
    {
        if($family==='invoice') { $n=$this->issue(); return app(\App\Invoices\SimulatedTransport::class)->dispatch($n->id)->fresh(); }
        $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now()->toDateString()]));
        $s=app(\App\Boletas\Preparation::class)->freeze($this->operator->id,$this->selection([$b->id]));
        app(\App\Boletas\SummaryTransport::class)->send($s->id); app(\App\Boletas\SummaryTransport::class)->poll($s->id);
        return $b;
    }
    private function form(mixed $source,array $more=[]): array { return $more+['idempotency_key'=>(string)Str::uuid(),'original_id'=>$source->id,'operation'=>'NC01','reason'=>'ANULACION COMPLETA FICTICIA','emission_date'=>now()->toDateString(),'original_granted_test'=>'1']; }
    private function selection(array $ids): array { return ['members'=>$ids,'idempotency_key'=>(string)Str::uuid()]; }
    private function issueNote(string $family,mixed $source,array $more=[]): mixed { return app($family==='invoice'?\App\Notes\Preparation::class:\App\BoletaNotes\Preparation::class)->issue($this->operator->id,$this->form($source,$more)); }
    private function route(string $family): string { return $family==='invoice'?'/invoice-notes':'/receipt-notes'; }
    private function noteUrl(string $family,string $id): string { return $this->route($family).($family==='invoice'?'/':'/note/').$id; }
    private function summary(): NoteSummary { $n=$this->issueNote('boleta',$this->original('boleta')); return app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id,$this->selection([$n->id])); }
    public static function families(): array { return [['invoice'],['boleta']]; }
    public static function operations(): array { return [['invoice','NC01'],['invoice','ND02'],['boleta','NC01'],['boleta','ND02']]; }

    #[DataProvider('families')]
    public function test_forms_keep_operation_original_values_key_and_grant_after_validation_errors(string $family): void
    {
        $s=$this->original($family);$url=$this->route($family);$this->login();
        $this->get($url.'?operation=NC01')->assertOk()->assertDontSee('name="increment_unit_price"',false)->assertSee('Anulación completa');
        $this->get($url.'?operation=ND02')->assertOk()->assertSee('name="increment_unit_price"',false);
        $f=$this->form($s,['operation'=>'ND02','increment_unit_price'=>'0','reason'=>'<script>ENSAYO</script>']);
        $this->post($url,$f)->assertStatus(303)->assertSessionHasInput('note_form.idempotency_key',$f['idempotency_key'])->assertSessionHasInput('note_form.original_id',$s->id)->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee($f['idempotency_key'])->assertSee('&lt;script&gt;ENSAYO&lt;/script&gt;',false)->assertDontSee('<script>ENSAYO</script>',false)->assertSee('name="increment_unit_price"',false)->assertSee('checked',false)->assertSee('selected',false);
        $this->postJson($url,$this->form($s,['increment_unit_price'=>'1']))->assertUnprocessable();
    }

    #[DataProvider('operations')]
    public function test_nc_nd_preparation_replays_without_duplicate_and_conflicting_keys_cannot_change_history(string $family,string $operation): void
    {
        $s=$this->original($family);$f=$this->form($s,['operation'=>$operation]+($operation==='ND02'?['increment_unit_price'=>'20.00']:[]));$this->login();
        $url=$this->post($this->route($family),$f)->assertStatus(303)->headers->get('Location');
        $this->post($this->route($family),$f)->assertRedirect($url);
        $model=$family==='invoice'?InvoiceNote::class:BoletaNote::class;$n=$model::query()->where('user_id',$this->operator->id)->sole();$snapshot=$n->attributesToArray();
        $this->assertSame($operation==='ND02'?2360:11800,$n->snapshot['total_cents']);
        $this->post($this->route($family),array_replace($f,['reason'=>'OTRO SUSTENTO FICTICIO']))->assertConflict()->assertSee($f['idempotency_key'])->assertSee($s->id);
        $this->postJson($this->route($family),$f+['currency'=>'USD'])->assertUnprocessable();
        $this->postJson($this->route($family),array_replace($f,['idempotency_key'=>(string)Str::uuid()]))->assertConflict();
        $this->assertSame($snapshot,$n->fresh()->attributesToArray());
        $path=$this->noteUrl($family,$n->id);$this->get($path)->assertOk()->assertSee('Sin CDR oficial')->assertSee($family==='invoice'?'/invoices/'.$s->id:'/boletas/'.$s->id);
        foreach(['xml','zip'] as $kind){$this->get($path.'/evidence/'.$kind)->assertOk();}
    }

    #[DataProvider('families')]
    public function test_eligibility_changing_after_form_display_is_revalidated_without_reserving_a_note(string $family): void
    {
        $s=$this->original($family);$this->login();$url=$this->route($family);$this->get($url)->assertOk()->assertSee($s->id);
        app(\App\Voids\Grant::class)->record($this->operator->id,['idempotency_key'=>(string)Str::uuid(),'kind'=>$family==='invoice'?'invoice':'boleta','id'=>$s->id,'state'=>'not_granted','provenance'=>'PRUEBA FICTICIA H62B NOTAS NO OTORGADO']);
        $this->postJson($url,$this->form($s))->assertConflict();
        $this->get($url)->assertOk()->assertSee('no otorgamiento acreditado');
        $this->assertSame(0,DB::table($family==='invoice'?'h5_notes':'h5b_notes')->where('original_id',$s->id)->count());
    }

    public function test_invoice_note_timeout_recovery_and_safe_retry_use_existing_attempts_without_resending_uncertain(): void
    {
        $n=$this->issueNote('invoice',$this->original('invoice'));$this->login();$url='/invoice-notes/'.$n->id;
        $this->post($url.'/simulate',['action'=>'dispatch','scenario'=>'timeout'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('Resultado incierto')->assertSee('Consultar registro del simulador')->assertDontSee('Procesar outbox ficticio');
        $this->postJson($url.'/simulate',['action'=>'dispatch'])->assertConflict();
        $this->post($url.'/simulate',['action'=>'recover'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('sim_accepted')->assertSee('recovered_local_fake')->assertSee('Sin CDR oficial');
        $this->postJson($url.'/simulate',['action'=>'recover'])->assertConflict();
        $this->assertSame(1,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
        $m=$this->issueNote('invoice',$this->original('invoice'));$other='/invoice-notes/'.$m->id;
        $this->post($other.'/simulate',['action'=>'dispatch','scenario'=>'not_processed'])->assertStatus(303);
        $this->postJson($other.'/simulate',['action'=>'dispatch'])->assertConflict();$this->travel(6)->seconds();
        $this->post($other.'/simulate',['action'=>'dispatch','scenario'=>'rejected'])->assertStatus(303);
        $this->assertSame('sim_rejected',$m->fresh()->state);$this->assertSame(2,DB::table('h5_outbox')->where('note_id',$m->id)->value('attempts'));
    }

    public function test_unresolved_invoice_recovery_and_expired_deadline_do_not_invent_results(): void
    {
        $n=$this->issueNote('invoice',$this->original('invoice'));$m=$this->issueNote('invoice',$this->original('invoice'));$this->login();
        $url='/invoice-notes/'.$n->id;$this->post($url.'/simulate',['action'=>'dispatch','scenario'=>'uncertain'])->assertStatus(303);
        $this->post($url.'/simulate',['action'=>'recover'])->assertStatus(303);$this->assertNull($n->fresh()->result);
        $this->get($url)->assertOk()->assertSee('query_unresolved')->assertDontSee('Procesar outbox ficticio');
        $this->travel(4)->days();$this->postJson('/invoice-notes/'.$m->id.'/simulate',['action'=>'dispatch'])->assertConflict();
        $this->assertSame(0,DB::table('h5_outbox')->where('note_id',$m->id)->value('attempts'));
    }

    public function test_note_rc_review_is_read_only_with_explicit_references_and_confirmation_is_idempotent(): void
    {
        $a=$this->issueNote('boleta',$this->original('boleta'));$b=$this->issueNote('boleta',$this->original('boleta'),['operation'=>'ND02','increment_unit_price'=>'20.00']);$this->login();$f=$this->selection([$b->id,$a->id]);
        $before=[];foreach(['h5b_summaries','h5b_members','h5b_outbox','h5b_events'] as $table){$before[$table]=DB::table($table)->count();}
        $counter=DB::table('h4_counters')->get()->toJson();$files=glob(config('h5b.evidence_root').'/*');
        $this->postJson('/note-summaries',$f)->assertConflict();
        $this->post('/note-summaries/review',$f)->assertOk()->assertSee('141.60')->assertSee('03 B000-')->assertSee('no reserva numeración')->assertSee($f['idempotency_key']);
        foreach($before as $table=>$count){$this->assertSame($count,DB::table($table)->count());}
        $this->assertSame($counter,DB::table('h4_counters')->get()->toJson());$this->assertSame($files,glob(config('h5b.evidence_root').'/*'));
        $this->post('/note-summaries/edit-review',$f)->assertOk()->assertSee($f['idempotency_key'])->assertSee('checked',false);
        $this->postJson('/note-summaries',array_replace($f,['members'=>[$a->id]]))->assertConflict();
        $url=$this->post('/note-summaries',$f)->assertStatus(303)->headers->get('Location');
        $f['members']=array_reverse($f['members']);$this->post('/note-summaries',$f)->assertRedirect($url);
        session()->forget('note_summary_reviews');$this->postJson('/note-summaries',$f)->assertConflict();
        $this->post('/note-summaries/review',$f)->assertOk()->assertSee('ya corresponde');$this->post('/note-summaries',$f)->assertRedirect($url);
        $this->assertSame(1,NoteSummary::query()->where('user_id',$this->operator->id)->count());
    }

    public function test_rc_membership_conflict_after_review_keeps_selection_key_and_reservations(): void
    {
        $n=$this->issueNote('boleta',$this->original('boleta'));$f=$this->selection([$n->id]);$this->login();
        $this->post('/note-summaries/review',$f)->assertOk();$winner=app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id,$this->selection([$n->id]));
        $this->post('/note-summaries',$f)->assertConflict()->assertSee($f['idempotency_key'])->assertSee($n->id)->assertSee('reservado');
        $this->assertSame($winner->id,DB::table('h5b_members')->where('note_id',$n->id)->value('summary_id'));
        $this->post('/note-summaries/review',array_replace($f,['members'=>[]]))->assertStatus(303)->assertSessionHasInput('summary_form.idempotency_key',$f['idempotency_key']);
    }

    public static function rcResults(): array {return [['0','sim_accepted'],['99','sim_rejected']];}
    #[DataProvider('rcResults')]
    public function test_rc_ticket_98_uncertainty_recovery_and_terminal_actions_are_safe(string $code,string $state): void
    {
        $s=$this->summary();$this->login();$url='/note-summaries/'.$s->id.'/simulate';
        $this->post($url,['action'=>'send'])->assertStatus(303);$ticket=$s->fresh()->ticket;
        $this->post($url,['action'=>'poll','scenario'=>'98'])->assertStatus(303);$this->postJson($url,['action'=>'poll'])->assertConflict();$this->travel(6)->seconds();
        $this->post($url,['action'=>'poll','scenario'=>'timeout'])->assertStatus(303);$this->postJson($url,['action'=>'send'])->assertConflict();
        $this->post($url,['action'=>'recover'])->assertStatus(303);$this->post($url,['action'=>'poll','scenario'=>$code])->assertStatus(303);
        $this->get('/receipt-notes/rc/'.$s->id)->assertOk()->assertSee($state)->assertSee('result_saved')->assertSee('H5B_LOCAL_FAKE');
        $this->get('/receipt-notes/note/'.$s->snapshot['members'][0]['id'])->assertOk()->assertSee('El resultado corresponde al RC')->assertSee($state);
        foreach(['send','poll','recover'] as $action){$this->postJson($url,['action'=>$action])->assertConflict();}
        $this->assertSame($ticket,$s->fresh()->ticket);$this->assertSame(1,DB::table('h5b_outbox')->where('summary_id',$s->id)->value('send_attempts'));
    }

    public function test_lost_ticket_never_uses_uuid_lookup_or_releases_members(): void
    {
        $s=$this->summary();$this->login();$url='/note-summaries/'.$s->id.'/simulate';
        $this->post($url,['action'=>'send','scenario'=>'lost_ticket'])->assertStatus(303);
        $this->get('/receipt-notes/rc/'.$s->id)->assertOk()->assertSee('Sin ticket no hay consulta')->assertDontSee('Rearmar consulta con ticket conservado');
        foreach(['send','poll','recover'] as $action){$this->postJson($url,['action'=>$action])->assertConflict();}
        $this->assertNull($s->fresh()->ticket);$this->assertNull($s->fresh()->result);$this->assertSame(1,DB::table('h5b_members')->where('summary_id',$s->id)->count());
    }

    public static function roles(): array {return [['ADMIN'],['OPERADOR']];}
    #[DataProvider('roles')]
    public function test_roles_never_access_foreign_originals_notes_rc_actions_or_evidence(string $role): void
    {
        $s=$this->original('invoice');$n=$this->issueNote('invoice',$s);$rc=$this->summary();$b=$rc->snapshot['members'][0]['id'];
        $foreign=$this->newOperator();$foreign->update(['role'=>$role]);
        try {
            $this->signIn($foreign->email,$this->password);
            $this->get('/invoice-notes')->assertOk()->assertDontSee($s->id)->assertDontSee($n->id);
            $this->get('/receipt-notes')->assertOk()->assertDontSee($rc->id)->assertDontSee($b);
            $this->postJson('/invoice-notes',$this->form($s))->assertNotFound();
            $originalId=BoletaNote::query()->findOrFail($b)->original_id;
            $this->postJson('/receipt-notes',$this->form((object)['id'=>$originalId]))->assertNotFound();
            foreach(['/invoice-notes/'.$n->id,'/invoice-notes/'.$n->id.'/evidence/zip','/receipt-notes/note/'.$b,'/receipt-notes/rc/'.$rc->id,'/receipt-notes/rc/'.$rc->id.'/evidence/xml'] as $url){$this->get($url)->assertNotFound();}
            foreach(['review','edit-review'] as $route){$this->postJson('/note-summaries/'.$route,$this->selection([$b]))->assertUnprocessable();}
            $this->postJson('/note-summaries/'.$rc->id.'/simulate',['action'=>'send'])->assertNotFound();
            $this->postJson('/invoice-notes/'.$n->id.'/simulate',['action'=>'dispatch'])->assertNotFound();
        } finally {$foreign->update(['role'=>'OPERADOR']);}
    }

    public function test_altered_new_evidence_blocks_dispatch_recovery_review_and_download_without_repair(): void
    {
        $n=$this->issueNote('invoice',$this->original('invoice'));$this->login();app(\App\Notes\Transport::class)->dispatch($n->id,'timeout');
        $path=app(\App\Notes\Evidence::class)->path($n->id,'xml',$n->artifacts);file_put_contents($path,'H62B NOTES ALTERED INVOICE NOTE');
        $this->post('/invoice-notes/'.$n->id.'/simulate',['action'=>'recover'])->assertConflict();$this->get('/invoice-notes/'.$n->id.'/evidence/xml')->assertConflict();
        $s=$this->summary();$member=BoletaNote::query()->findOrFail($s->snapshot['members'][0]['id']);
        $other=app(\App\BoletaNotes\Evidence::class)->path($member->id,'xml',$member->artifacts);file_put_contents($other,'H62B NOTES ALTERED BOLETA NOTE');
        $this->post('/note-summaries/review',$this->selection([$member->id]))->assertConflict();
        $this->post('/note-summaries/'.$s->id.'/simulate',['action'=>'send'])->assertConflict();
        $this->assertSame(0,DB::table('h5b_outbox')->where('summary_id',$s->id)->value('send_attempts'));$this->assertSame('H62B NOTES ALTERED INVOICE NOTE',file_get_contents($path));
    }

    public function test_note_events_are_paginated_and_escaped_and_new_review_endpoints_require_csrf(): void
    {
        $n=$this->issueNote('invoice',$this->original('invoice'));$this->login();
        foreach(range(1,21) as $i){DB::table('h5_events')->insert(['note_id'=>$n->id,'kind'=>$i===21?'ULTIMO H62B NOTES':'<script>H62B NOTES</script>','token'=>null,'attention'=>false,'payload'=>null,'created_at'=>now('UTC')]);}
        $this->get('/invoice-notes/'.$n->id)->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>H62B NOTES</script>',false)->assertDontSee('ULTIMO H62B NOTES');
        $this->get('/invoice-notes/'.$n->id.'?events_page=2')->assertOk()->assertSee('ULTIMO H62B NOTES');
        $this->app->instance('env','local');
        foreach(['/invoice-notes','/receipt-notes','/note-summaries/review','/note-summaries/edit-review','/note-summaries'] as $url){$this->post($url,[])->assertStatus(419);}
    }
}
