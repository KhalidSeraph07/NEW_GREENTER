<?php
namespace Tests\H5C2;
use App\Corrections\{Family,Policy,Rejection};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Illuminate\Validation\ValidationException;

final class LifecycleTest extends CaseBase
{
    public static function families(): array {return PreparationTest::families();}
    #[DataProvider('families')]
    public function test_replay_is_stable_after_deadline_and_second_successor_is_blocked(string $f): void
    {
        $r=$this->root($f);$input=$f==='invoice'?$this->reissueInput($r):$this->correctionInput($r);$n=$this->child($f,$r,$input);
        $this->travel(9)->days();$this->assertSame($n->id,$this->child($f,$r,$input)->id);
        $this->assertSame(1,DB::table(Family::LINKS[$f])->where('original_id',$r->id)->count());
        $this->expectException(ConflictHttpException::class);$this->child($f,$r);
    }
    #[DataProvider('families')]
    public function test_deadline_checked_again_before_send_and_pending_state_is_preserved(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$this->travel($f==='invoice'?4:8)->days();
        try {if($f==='invoice'){app(\App\Invoices\SimulatedTransport::class)->dispatch($n->id);}else{$this->transport($f)->send($n->id);}$this->fail('Envio fuera de ventana.');}
        catch(ValidationException) {$this->assertSame($f==='invoice'?'pending_simulation':'pending_send',$f==='invoice'?$n->fresh()->transport_status:$n->fresh()->state);}
        $this->assertSame(0,DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$n->id)->value($f==='invoice'?'attempts':'send_attempts'));
    }
    #[DataProvider('families')]
    public function test_changed_replay_and_arbitrary_fiscal_changes_are_blocked(string $f): void
    {
        $r=$this->root($f);$input=$f==='invoice'?$this->reissueInput($r):$this->correctionInput($r);$this->child($f,$r,$input);
        try {$this->child($f,$r,array_replace($input,['reason'=>'OTRO MOTIVO FICTICIO']));$this->fail('Clave reutilizada.');}catch(ConflictHttpException){$this->assertTrue(true);}
        $this->expectException(ValidationException::class);$this->child($f,$r,$input+['unit_price'=>'99.00']);
    }
    #[DataProvider('families')]
    public function test_parent_and_child_artifact_tampering_is_detected(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$path=Family::path($f,$r,'xml');$bytes=file_get_contents($path);
        try {file_put_contents($path,$bytes.' ');try{app(Policy::class)->verifyLinked($f,$n);$this->fail('Huella alterada.');}catch(\Throwable $e){$this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$e);}}
        finally{file_put_contents($path,$bytes);}
        app(Policy::class)->verifyLinked($f,$n);$this->assertSame(hash('sha256',$bytes),hash_file('sha256',$path));
    }
    public static function sources(): array {return [['h5b','boleta','ND02'],['void','invoice','NC01'],['void','invoice_note','NC01'],['void','invoice_note','ND02'],['void','boleta','NC01'],['void','boleta_note','NC01'],['void','boleta_note','ND02']];}
    #[DataProvider('sources')]
    public function test_all_approved_member_types_keep_reservations_and_can_accept_successor(string $f,string $source,string $op): void
    {
        $r=$this->root($f,$source,$op);$table=$f==='void'?'h5c1_members':'h5b_members';$column=$f==='void'?'batch_id':'summary_id';
        $members=DB::table($table)->where($column,$r->id)->get()->map(fn($x)=>(array)$x)->all();
        $n=$this->child($f,$r);$this->transport($f)->send($n->id);$this->assertSame('sim_accepted',$this->transport($f)->poll($n->id)->state);
        $this->assertSame($members,DB::table($table)->where($column,$r->id)->get()->map(fn($x)=>(array)$x)->all());
        $this->assertSame(0,DB::table($table)->where($column,$n->id)->count());
    }
    public function test_effective_rc_is_seen_by_note_and_void_readers_without_changing_root(): void
    {
        $r=$this->root('h4');$n=$this->child('h4',$r);$t=$this->transport('h4');$t->send($n->id);$t->poll($n->id);
        $id=$r->snapshot['members'][0]['id'];
        $note=app(\App\BoletaNotes\Preparation::class)->issue($this->operator->id,$this->noteInput($id));
        $this->assertSame($id,$note->original_id);$this->assertSame('sim_rejected',$r->fresh()->state);
        $root=$this->root('h4');$child=$this->child('h4',$root);$t->send($child->id);$t->poll($child->id);
        $key=['kind'=>'boleta','id'=>$root->snapshot['members'][0]['id']];$this->grant($key);
        $batch=app(\App\Voids\Preparation::class)->freeze($this->operator->id,$this->batchInput([$key]));$this->assertSame('RC',$batch->route);
    }
    public function test_new_correctable_scenario_on_good_zip_does_not_fabricate_a_rejection(): void
    {
        $i=$this->issue();$r=app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id,'rejected_c2_zip');
        $this->assertSame('simulation_uncertain',$r->transport_status);
        $this->expectException(ConflictHttpException::class);$this->child('invoice',$r);
    }
    public function test_queries_after_deadline_are_allowed_but_rejected_successor_cannot_start_chain(): void
    {
        $r=$this->root('h4');$n=$this->child('h4',$r);$t=$this->transport('h4');$t->send($n->id);$this->travel(8)->days();
        $this->assertSame('sim_rejected',$t->poll($n->id,'99')->state);
        $this->expectException(ConflictHttpException::class);$this->child('h4',$n);
    }
    public function test_corrected_notes_rc_is_seen_by_void_source_with_child_receipt_date(): void
    {
        $r=$this->root('h5b');$n=$this->child('h5b',$r);$t=$this->transport('h5b');$t->send($n->id);$t->poll($n->id);
        $key=['kind'=>'boleta_note','id'=>$r->snapshot['members'][0]['id']];$this->grant($key);
        $batch=app(\App\Voids\Preparation::class)->freeze($this->operator->id,$this->batchInput([$key]));
        $this->assertSame($n->id,$batch->snapshot['members'][0]['acceptance']['container_id']);
        $this->assertSame('sim_rejected',$r->fresh()->state);
    }
    public static function invoiceStates(): array {return [['pending'],['accepted'],['timeout'],['communication_error'],['uncertain'],['incomplete'],['unexpected_error']];}
    #[DataProvider('invoiceStates')]
    public function test_other_invoice_states_never_allow_manual_reissue(string $scenario): void
    {
        $i=app(\App\Corrections\Fixture::class)->prepare('invoice',$this->operator->id,$this->input());
        if($scenario!=='pending'){app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id,$scenario);}
        $this->expectException(ConflictHttpException::class);$this->child('invoice',$i->fresh());
    }
    #[DataProvider('families')]
    public function test_prepare_deadline_is_not_reset_by_new_generation_or_rejection(string $f): void
    {
        $r=$this->root($f);$this->travel($f==='invoice'?4:8)->days();
        $this->expectException(ValidationException::class);$this->child($f,$r);
    }
    public function test_2027_preparation_is_blocked_until_explicit_policy_review(): void
    {
        $r=$this->root('invoice');Carbon::setTestNow(Carbon::parse('2027-01-01T12:00:00-05:00'));
        $this->expectException(ValidationException::class);$this->child('invoice',$r);
    }
    public static function communications(): array {return [['h4'],['h5b'],['void']];}
    #[DataProvider('communications')]
    public function test_query_budget_is_bounded_and_lost_ticket_has_no_recovery(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$t=$this->transport($f);$ticket=$t->send($n->id)->ticket;
        for($cycle=0;$cycle<3;$cycle++){for($i=0;$i<3;$i++){$t->poll($n->id,'98');$this->travel(6)->seconds();}$this->assertSame('uncertain',$n->fresh()->state);$t->recover($n->id);}
        $this->assertSame($ticket,$n->fresh()->ticket);$this->assertNull($t->send($n->id));$this->assertNull($t->poll($n->id));
        $this->assertSame(9,DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$n->id)->value('poll_attempts'));
        $other=$this->root($f);$lost=$this->child($f,$other);$t->send($lost->id,'lost_ticket');$this->travel(6)->minutes();
        $this->assertSame('uncertain',$t->recover($lost->id)->state);$this->assertNull($lost->fresh()->ticket);$this->assertNull($t->poll($lost->id));
    }
    public static function uncertain(): array {return [['h4','exception'],['h4','lost_ticket'],['h4','incomplete'],['h4','99'],['h4','98'],['h4','0']];}
    #[DataProvider('uncertain')]
    public function test_only_complete_explicit_definitive_rejection_is_eligible(string $f,string $scenario): void
    {
        $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now()->toDateString()]));
        $r=app(\App\Corrections\Fixture::class)->prepare($f,$this->operator->id,['members'=>[$b->id],'idempotency_key'=>(string)\Illuminate\Support\Str::uuid()]);$t=$this->transport($f);
        // The names below are taken from the existing simulator contract, not inferred HTTP failures.
        if(in_array($scenario,['98','0','99','incomplete'])){$t->send($r->id);$t->poll($r->id,$scenario);}else{$t->send($r->id,$scenario);}
        $this->expectException(ConflictHttpException::class);$this->child($f,$r->fresh());
    }
}
