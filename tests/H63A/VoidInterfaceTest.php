<?php

namespace Tests\H63A;

use App\Models\VoidBatch;
use App\Voids\{Evidence, Grant, Preparation, Source, Transport};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\H5C1\VoidCase;

final class VoidInterfaceTest extends VoidCase
{
    private function login(): void { $this->signIn($this->operator->email,$this->password); }
    private function fact(array $key, array $more=[]): array { return $more+['document'=>$key['kind'].':'.$key['id'],'idempotency_key'=>(string)Str::uuid(),'state'=>'not_granted','provenance'=>'PROCEDENCIA EXPLICITA FICTICIA H63A','confirmed'=>'1']; }
    private function selection(array $keys, array $more=[]): array { return $more+['idempotency_key'=>(string)Str::uuid(),'members'=>array_map(fn($k)=>$k['kind'].':'.$k['id'],$keys),'reason'=>'NO OTORGADO - BAJA FICTICIA H63A']; }
    public static function documents(): array { return [['invoice','NC01','RA'],['invoice_note','NC01','RA'],['invoice_note','ND02','RA'],['boleta','NC01','RC'],['boleta_note','NC01','RC'],['boleta_note','ND02','RC']]; }

    #[DataProvider('documents')]
    public function test_six_existing_document_cases_complete_via_http_without_changing_original(string $kind,string $operation,string $route): void
    {
        $k=$this->accepted($kind,$operation);$source=app(Source::class)->document($this->operator->id,$kind,$k['id']);$before=$source->attributesToArray();$this->login();
        $this->get('/voids')->assertOk()->assertSee($k['id'])->assertSee('No otorgamiento desconocido');
        $grant=$this->fact($k);$this->post('/voids/grants',$grant)->assertStatus(303);$this->post('/voids/grants',$grant)->assertStatus(303);
        $this->assertSame(1,DB::table('h5c1_grants')->where('user_id',$this->operator->id)->count());
        $form=$this->selection([$k]);$this->post('/voids/review',$form)->assertOk()->assertSee('no reserva numeración')->assertSee($route==='RC'?'RC operación 3':'RA');
        $url=$this->post('/voids',$form)->assertStatus(303)->headers->get('Location');$this->post('/voids',$form)->assertRedirect($url);
        $b=VoidBatch::query()->where('user_id',$this->operator->id)->sole();$this->assertSame($route,$b->route);
        $this->post($url.'/simulate',['action'=>'send'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('SIM-H5C1-')->assertSee('Recibir un ticket no confirma la baja');
        $this->post($url.'/simulate',['action'=>'poll','scenario'=>'timeout'])->assertStatus(303);
        $this->postJson($url.'/simulate',['action'=>'send'])->assertConflict();
        $this->post($url.'/simulate',['action'=>'recover'])->assertStatus(303);
        $this->post($url.'/simulate',['action'=>'poll','scenario'=>'0'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('Baja confirmada únicamente en simulación')->assertSee('manual_query_rearmed')->assertSee('result_saved')->assertSee('Sin CDR oficial');
        foreach(['xml','zip'] as $file){$this->get($url.'/evidence/'.$file)->assertOk();}
        foreach(['send','poll','recover'] as $action){$this->postJson($url.'/simulate',['action'=>$action])->assertConflict();}
        $this->assertSame(1,$this->job($b->id)->send_attempts);$this->assertSame(2,$this->job($b->id)->poll_attempts);
        $this->assertSame($before,$source->fresh()->attributesToArray());
        $this->assertSame(1,DB::table('h5c1_members')->where('document_id',$k['id'])->count());
    }

    public function test_grant_requires_explicit_confirmation_and_preserves_values_key_and_audited_history(): void
    {
        $k=$this->accepted();$this->login();$f=$this->fact($k,['provenance'=>'<script>PROCEDENCIA FICTICIA</script>']);
        $this->post('/voids/grants',array_diff_key($f,['confirmed'=>true]))->assertStatus(303)->assertSessionHasErrors('confirmed')->assertSessionHasInput('grant_form.idempotency_key',$f['idempotency_key']);
        $this->get('/voids')->assertOk()->assertSee($f['idempotency_key'])->assertSee('&lt;script&gt;PROCEDENCIA',false)->assertDontSee('<script>PROCEDENCIA',false)->assertSee('selected',false);
        $this->assertSame(0,DB::table('h5c1_grants')->where('user_id',$this->operator->id)->count());
        $this->post('/voids/grants',$f)->assertStatus(303);$this->postJson('/voids/grants',$f+['proof'=>[]])->assertUnprocessable();
        $this->post('/voids/grants',array_replace($f,['state'=>'granted']))->assertConflict()->assertSee($f['idempotency_key']);
        $this->post('/voids/grants',$this->fact($k,['state'=>'granted']))->assertStatus(303);
        $this->postJson('/voids/grants',$this->fact($k))->assertConflict();
        $facts=DB::table('h5c1_grants')->where('user_id',$this->operator->id)->orderBy('sequence')->get();
        $this->assertSame(['not_granted','granted'],$facts->pluck('state')->all());$this->assertSame($f['provenance'],$facts[0]->provenance);
        $this->postJson('/voids/review',$this->selection([$k]))->assertConflict();
    }

    public function test_review_has_no_persistence_or_reservation_and_confirmation_is_bound_to_exact_content(): void
    {
        $a=$this->accepted();$b=$this->accepted();$this->grant($a);$this->grant($b);$this->login();$f=$this->selection([$b,$a]);
        $before=[];foreach(['h5c1_batches','h5c1_members','h5c1_outbox','h5c1_events'] as $table){$before[$table]=DB::table($table)->count();}
        $counter=DB::table('h5c1_counters')->get()->toJson();$rcCounter=DB::table('h4_counters')->get()->toJson();$files=glob(config('h5c1.evidence_root').'/*');
        $this->postJson('/voids',$f)->assertConflict();$this->post('/voids/review',$f)->assertOk()->assertSee('118.00')->assertSee('2026-10-26');
        foreach($before as $table=>$count){$this->assertSame($count,DB::table($table)->count());}
        $this->assertSame($counter,DB::table('h5c1_counters')->get()->toJson());$this->assertSame($rcCounter,DB::table('h4_counters')->get()->toJson());$this->assertSame($files,glob(config('h5c1.evidence_root').'/*'));
        $this->post('/voids/edit-review',$f)->assertOk()->assertSee($f['idempotency_key'])->assertSee('checked',false);
        $this->postJson('/voids',array_replace($f,['reason'=>'OTRO MOTIVO']))->assertConflict();
        $url=$this->post('/voids',$f)->assertStatus(303)->headers->get('Location');$f['members']=array_reverse($f['members']);
        $this->post('/voids',$f)->assertRedirect($url);
        $this->postJson('/voids/review',array_replace($f,['members'=>[$f['members'][0]]]))->assertConflict();
        session()->forget('void_reviews');$this->postJson('/voids',$f)->assertConflict();
        $this->post('/voids/review',$f)->assertOk()->assertSee('ya corresponde');$this->post('/voids',$f)->assertRedirect($url);
    }

    public function test_changed_grant_and_session_or_day_expiry_force_revalidation_without_preparation(): void
    {
        $k=$this->accepted();$this->grant($k);$this->login();$f=$this->selection([$k]);$this->post('/voids/review',$f)->assertOk();
        $this->grant($k,'granted');$this->postJson('/voids',$f)->assertConflict();
        $a=$this->accepted();$this->grant($a);$form=$this->selection([$a]);$this->post('/voids/review',$form)->assertOk();
        $this->travel(1)->days();$this->postJson('/voids',$form)->assertConflict();
        $this->post('/voids/review',$form)->assertOk();$this->post('/voids',$form)->assertStatus(303);
        $this->assertSame(1,VoidBatch::query()->where('user_id',$this->operator->id)->count());
    }

    public function test_unknown_unaccepted_dependent_mixed_duplicate_and_forged_selections_are_blocked(): void
    {
        $a=$this->accepted();$b=$this->accepted('boleta');$pending=$this->issue();$note=$this->accepted('invoice_note');$parent=\App\Models\InvoiceNote::query()->findOrFail($note['id'])->original_id;
        $this->login();$this->postJson('/voids/review',$this->selection([$a]))->assertConflict();
        $this->postJson('/voids/grants',$this->fact(['kind'=>'invoice','id'=>$pending->id]))->assertConflict();
        $this->postJson('/voids/grants',$this->fact(['kind'=>'invoice','id'=>$parent]))->assertConflict();
        $this->grant($a);$this->grant($b);
        $this->postJson('/voids/review',$this->selection([$a,$b]))->assertUnprocessable();
        $this->postJson('/voids/review',$this->selection([$a,$a]))->assertUnprocessable();
        $this->postJson('/voids/review',$this->selection([$a])+['route'=>'RC'])->assertUnprocessable();
        $this->postJson('/voids/review',$this->selection([$a],['members'=>['bad:'.$a['id']]]))->assertUnprocessable();
        $this->postJson('/voids/review',$this->selection([$a],['members'=>['invoice:'.Str::uuid()]]))->assertNotFound();
        $this->travel(1)->days();$c=$this->accepted();$this->grant($c);
        $this->postJson('/voids/review',$this->selection([$a,$c]))->assertUnprocessable();
        $this->assertSame(0,VoidBatch::query()->where('user_id',$this->operator->id)->count());
    }

    public function test_deadlines_block_review_and_send_but_do_not_block_late_queries(): void
    {
        $a=$this->accepted();$this->grant($a);$b=$this->batch();$this->login();$this->travel(8)->days();
        $this->postJson('/voids/review',$this->selection([$a]))->assertUnprocessable();
        $this->postJson('/voids/'.$b->id.'/simulate',['action'=>'send'])->assertConflict();$this->assertSame(0,$this->job($b->id)->send_attempts);
        Carbon::setTestNow(Carbon::parse('2026-12-31T12:00:00-05:00'));$c=$this->batch('boleta');app(Transport::class)->send($c->id);
        $this->travel(1)->days();$this->post('/voids/'.$c->id.'/simulate',['action'=>'poll','scenario'=>'0'])->assertStatus(303);
        $this->assertSame('sim_accepted',$c->fresh()->state);
        $this->postJson('/voids/review',$this->selection([$a]))->assertUnprocessable();
    }

    public static function routes(): array { return [['invoice'],['boleta']]; }
    #[DataProvider('routes')]
    public function test_lost_ticket_and_rejection_do_not_free_members_or_offer_recovery_by_uuid(string $kind): void
    {
        $b=$this->batch($kind);$this->login();$url='/voids/'.$b->id;
        $this->post($url.'/simulate',['action'=>'send','scenario'=>'lost_ticket'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('Sin ticket conservado')->assertDontSee('Recuperación manual permitida');
        foreach(['send','poll','recover'] as $action){$this->postJson($url.'/simulate',['action'=>$action])->assertConflict();}
        $this->assertNull($b->fresh()->ticket);$this->assertSame(1,$this->job($b->id)->send_attempts);
        $other=$this->batch($kind);$url='/voids/'.$other->id;
        $this->post($url.'/simulate',['action'=>'send'])->assertStatus(303);$this->post($url.'/simulate',['action'=>'poll','scenario'=>'99'])->assertStatus(303);
        $this->get($url)->assertOk()->assertSee('sin liberar reservas')->assertDontSee('Baja confirmada únicamente');
        $key=$other->snapshot['members'][0];$this->postJson('/voids/review',$this->selection([$key]))->assertConflict();
        $this->assertSame(1,DB::table('h5c1_members')->where('document_id',$key['id'])->count());
    }

    public function test_poll_backoff_budget_and_expired_send_lease_remain_bounded(): void
    {
        $b=$this->batch();$this->login();$url='/voids/'.$b->id.'/simulate';$this->post($url,['action'=>'send'])->assertStatus(303);
        $this->post($url,['action'=>'poll','scenario'=>'98'])->assertStatus(303);$this->postJson($url,['action'=>'poll'])->assertConflict();
        $this->travel(6)->seconds();$this->post($url,['action'=>'poll','scenario'=>'98'])->assertStatus(303);$this->travel(6)->seconds();
        $this->post($url,['action'=>'poll','scenario'=>'98'])->assertStatus(303);$this->assertSame('uncertain',$b->fresh()->state);
        $this->post($url,['action'=>'recover'])->assertStatus(303);$this->post($url,['action'=>'poll','scenario'=>'0'])->assertStatus(303);
        $pending=$this->batch();DB::table('h5c1_outbox')->where('batch_id',$pending->id)->update(['status'=>'processing','reserved_until'=>now('UTC')->subMinute(),'send_attempts'=>1,'token'=>(string)Str::uuid()]);$pending->state='sending';$pending->save();
        $this->post('/voids/'.$pending->id.'/simulate',['action'=>'recover'])->assertStatus(303);$this->assertSame('uncertain',$pending->fresh()->state);
        $this->postJson('/voids/'.$pending->id.'/simulate',['action'=>'recover'])->assertConflict();$this->assertNull($pending->fresh()->ticket);
    }

    public static function roles(): array { return [['ADMIN'],['OPERADOR']]; }
    #[DataProvider('roles')]
    public function test_both_roles_are_isolated_for_grants_review_process_history_and_private_evidence(string $role): void
    {
        $k=$this->accepted();$b=$this->batch();$foreign=$this->newOperator();$foreign->update(['role'=>$role]);
        $this->signIn($foreign->email,$this->password);
        $this->get('/voids')->assertOk()->assertDontSee($k['id'])->assertDontSee($b->id);
        $this->postJson('/voids/grants',$this->fact($k))->assertNotFound();
        foreach(['/voids/review','/voids/edit-review','/voids'] as $url){$this->postJson($url,$this->selection([$k]))->assertNotFound();}
        foreach(['/voids/'.$b->id,'/voids/'.$b->id.'/evidence/xml','/voids/'.$b->id.'/evidence/zip'] as $url){$this->get($url)->assertNotFound();}
        $this->postJson('/voids/'.$b->id.'/simulate',['action'=>'send'])->assertNotFound();
        $this->assertSame(0,$this->job($b->id)->send_attempts);
    }

    public function test_altered_new_evidence_blocks_review_actions_and_downloads_without_repair(): void
    {
        $k=$this->accepted();$this->grant($k);$this->login();$source=app(Source::class)->document($this->operator->id,$k['kind'],$k['id']);
        $xml=app(\App\Invoices\InvoiceEvidence::class)->verifiedPath($source->id,'xml',$source->artifacts);
        file_put_contents($xml,'H63A ALTERED NEW ORIGINAL');
        $this->post('/voids/review',$this->selection([$k]))->assertConflict();
        $b=$this->batch();app(Transport::class)->send($b->id);app(Transport::class)->poll($b->id,'timeout');
        $path=app(Evidence::class)->path($b->id,'xml',$b->artifacts);file_put_contents($path,'H63A ALTERED NEW BATCH');
        $this->get('/voids/'.$b->id)->assertOk()->assertSee('acciones están bloqueadas')->assertDontSee('Recuperación manual permitida');
        $this->post('/voids/'.$b->id.'/simulate',['action'=>'recover'])->assertConflict();$this->get('/voids/'.$b->id.'/evidence/xml')->assertConflict();
        $this->assertSame('H63A ALTERED NEW BATCH',file_get_contents($path));$this->assertSame('H63A ALTERED NEW ORIGINAL',file_get_contents($xml));
    }

    public function test_history_filters_pagination_and_all_mutating_routes_keep_csrf(): void
    {
        $b=$this->batch();$this->login();$n=$b->snapshot['members'][0]['number'];
        $this->get('/voids?kind=invoice&number='.$n.'&name='.urlencode($b->name))->assertOk()->assertSee($b->name)->assertSee('Documento reservado');
        foreach(range(1,21) as $i){DB::table('h5c1_events')->insert(['batch_id'=>$b->id,'kind'=>$i===21?'ULTIMO H63A':'<script>H63A</script>','token'=>null,'attention'=>false,'payload'=>json_encode(['message'=>'<script>H63A</script>']),'created_at'=>now('UTC')]);}
        $this->get('/voids/'.$b->id)->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>H63A</script>',false)->assertDontSee('ULTIMO H63A');
        $this->get('/voids/'.$b->id.'?events_page=2')->assertOk()->assertSee('ULTIMO H63A');
        $this->app->instance('env','local');
        foreach(['/voids/grants','/voids/review','/voids/edit-review','/voids','/voids/'.$b->id.'/simulate'] as $url){$this->post($url,[])->assertStatus(419);}
    }
}
