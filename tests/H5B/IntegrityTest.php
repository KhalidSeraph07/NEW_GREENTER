<?php

namespace Tests\H5B;

use App\BoletaNotes\Preparation;
use App\BoletaNotes\Evidence;
use App\BoletaNotes\SummaryTransport;
use App\Models\BoletaNote;
use App\Models\NoteSummary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;

final class IntegrityTest extends NoteCase
{
    public static function badInputs(): array
    {
        return [[['operation'=>'NC06']], [['operation'=>'ND01']], [['currency'=>'USD']], [['receiver_ruc'=>'00000000000']],
            [['original_granted_test'=>'0']], [['reason'=>']]>' ]], [['increment_unit_price'=>'1']],
            [['operation'=>'ND02','increment_unit_price'=>'0']], [['operation'=>'ND02','increment_unit_price'=>'-1']],
            [['operation'=>'ND02','increment_unit_price'=>'1.001']], [['emission_date'=>'2099-01-01']]];
    }

    #[DataProvider('badInputs')]
    public function test_invalid_request_consumes_no_note_number(array $changes): void
    {
        $b=$this->original(); $counter=DB::table('h5b_counters')->orderBy('document_type')->get()->toJson();
        try { app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,$changes)); $this->fail(); }
        catch(ValidationException) { $this->assertSame($counter,DB::table('h5b_counters')->orderBy('document_type')->get()->toJson());
            $this->assertSame(0,BoletaNote::query()->where('original_id',$b->id)->count()); }
    }

    public function test_only_owned_boletas_with_correlated_accepted_member_are_eligible(): void
    {
        foreach (['none','awaiting','98','99','wrong_member','lost_ticket'] as $state) {
            $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now()->toDateString()]));
            if($state!=='none') {
                $s=app(\App\Boletas\Preparation::class)->freeze($this->operator->id,['members'=>[$b->id],'idempotency_key'=>(string)Str::uuid()]);
                app(\App\Boletas\SummaryTransport::class)->send($s->id,$state==='lost_ticket'?'lost_ticket':'accepted');
                if(in_array($state,['98','99','wrong_member'],true))app(\App\Boletas\SummaryTransport::class)->poll($s->id,$state);
            }
            try { app(Preparation::class)->issue($this->operator->id,$this->noteInput($b)); $this->fail($state); }
            catch(ConflictHttpException) { $this->assertSame(0,BoletaNote::query()->where('original_id',$b->id)->count()); }
        }
        $b=$this->original();
        try { app(Preparation::class)->issue($this->newOperator()->id,$this->noteInput($b)); $this->fail(); }
        catch(\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertTrue(true); }
        $invoice=$this->issue();
        try { app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,['original_id'=>$invoice->id])); $this->fail(); }
        catch(\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertSame(0,BoletaNote::query()->where('original_id',$invoice->id)->count()); }
    }

    public function test_credit_debit_and_rc_have_valid_signatures_exact_private_zips_and_negative_reference_test(): void
    {
        $records=[];
        foreach(['NC01','ND02'] as $operation) {
            $b=$this->original(); $changes=$operation==='ND02'?['operation'=>'ND02','increment_unit_price'=>'20']:[];
            $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,$changes)); $records[]=$n;
            $this->assertSame($operation==='NC01'?11800:2360,$n->snapshot['total_cents']);
            $xml=file_get_contents(app(Evidence::class)->path($n->id,'xml',$n->artifacts));
            $this->assertStringContainsString('<cbc:DocumentTypeCode>03</cbc:DocumentTypeCode>',$xml);
            $this->assertStringContainsString('<cbc:ID>B000-'.$b->number.'</cbc:ID>',$xml);
        }
        $rc=$this->summary(array_column(array_map(fn($n)=>['id'=>$n->id],$records),'id')); $records[]=$rc;
        foreach($records as $n) {
            $f=app(Evidence::class); $path=$f->path($n->id,'xml',$n->artifacts); $cert=$f->path($n->id,'certificate',$n->artifacts);
            $cmd=['xmlsec1','--verify','--lax-key-search','--enabled-key-data','rsa','--enabled-reference-uris','empty','--pubkey-cert-pem',$cert];
            $p=new Process([...$cmd,$path]);$p->run();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());
            $text=file_get_contents($path);$bad=$path.'.negative';
            $this->assertMatchesRegularExpression('/B000-[1-9][0-9]*/',$text);
            file_put_contents($bad,preg_replace('/B000-[1-9][0-9]*/','B000-99999999',$text));
            $p=new Process([...$cmd,$bad]);$p->run();$this->assertFalse($p->isSuccessful());
            $zip=new \ZipArchive();$this->assertTrue($zip->open($f->path($n->id,'zip',$n->artifacts)));$this->assertSame(1,$zip->numFiles);
            $this->assertSame($text,$zip->getFromIndex(0));$zip->close();
            $this->assertStringNotContainsString('PRIVATE KEY',file_get_contents($cert));
        }
        $this->assertSame(2,DB::table('h5b_members')->where('summary_id',$rc->id)->count());
    }

    public function test_replay_and_member_order_preserve_identity_after_window_and_terminality(): void
    {
        $input=$this->noteInput($this->original(),['operation'=>'ND02','increment_unit_price'=>'20']);
        $n=app(Preparation::class)->issue($this->operator->id,$input);
        $again=app(Preparation::class)->issue($this->operator->id,array_replace($input,['increment_unit_price'=>'020.00']));
        $this->assertSame($n->id,$again->id);
        $other=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));$key=(string)Str::uuid();
        $s=$this->summary([$n->id,$other->id],$key);app(SummaryTransport::class)->send($s->id);app(SummaryTransport::class)->poll($s->id);
        $this->travel(9)->days();
        $this->assertSame($s->id,$this->summary([$other->id,$n->id],$key)->id);
        $this->assertSame($n->id,app(Preparation::class)->issue($this->operator->id,$input)->id);
        try {$this->summary([$other->id],$key);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        $this->assertSame('sim_accepted',$s->fresh()->state);
    }

    public function test_mixed_dates_duplicates_foreign_and_invoice_note_members_are_refused(): void
    {
        $a=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        $b=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original(now()->subDay()->toDateString()),['emission_date'=>now()->subDay()->toDateString()]));
        foreach([[$a->id,$b->id],[$a->id,$a->id],[(string)Str::uuid()]] as $members) {
            try {$this->summary($members);$this->fail();}catch(ValidationException){$this->assertTrue(true);}
        }
        try {app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->newOperator()->id,['idempotency_key'=>(string)Str::uuid(),'members'=>[$a->id]]);$this->fail();}
        catch(ValidationException){$this->assertSame(0,DB::table('h5b_members')->where('note_id',$a->id)->count());}
    }

    public function test_day_seven_inclusive_day_eight_blocked_and_source_date_is_not_note_reference_date(): void
    {
        $b=$this->original(now()->subDay()->toDateString());$n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b));
        $this->travel(7)->days();$s=$this->summary([$n->id]);$this->assertSame($n->emission_date,$s->reference_date);
        $this->assertNotSame($b->emission_date,$s->reference_date);
        $this->assertSame('awaiting',app(SummaryTransport::class)->send($s->id)->state);
        $b2=$this->original();$n2=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b2));$this->travel(8)->days();
        try {$this->summary([$n2->id]);$this->fail();}catch(ValidationException){$this->assertSame(0,DB::table('h5b_members')->where('note_id',$n2->id)->count());}
    }

    public function test_frozen_records_and_direct_reference_change_cannot_bypass_database_guards(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));$s=$this->summary([$n->id]);
        foreach([fn()=>DB::table('h5b_notes')->where('id',$n->id)->update(['original_id'=>(string)Str::uuid()]),
            fn()=>DB::table('h5b_notes')->where('id',$n->id)->update(['snapshot'=>'{}']),
            fn()=>DB::table('h5b_summaries')->where('id',$s->id)->update(['state'=>'sim_accepted']),
            fn()=>DB::table('h5b_members')->where('summary_id',$s->id)->update(['position'=>2])] as $change) {
            try {$change();$this->fail();}catch(QueryException){$this->assertTrue(true);}
        }
        $this->assertSame('pending_send',$s->fresh()->state);
    }

    public function test_rejection_and_uncertainty_never_release_original_or_note_membership(): void
    {
        foreach(['99','lost_ticket'] as $scenario) {
            $b=$this->original();$n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b));$s=$this->summary([$n->id]);
            app(SummaryTransport::class)->send($s->id,$scenario==='lost_ticket'?'lost_ticket':'accepted');
            if($scenario==='99')app(SummaryTransport::class)->poll($s->id,'99');
            try {app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,['operation'=>'ND02','increment_unit_price'=>'1']));$this->fail();}
            catch(ConflictHttpException){$this->assertSame(1,BoletaNote::query()->where('original_id',$b->id)->count());}
            try {$this->summary([$n->id]);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        }
    }

    public function test_note_evidence_tampering_blocks_send_and_commit_loss_preserves_identity(): void
    {
        $input=$this->noteInput($this->original());
        Event::listen(\Illuminate\Database\Events\TransactionCommitted::class,fn()=>throw new \RuntimeException('lost confirmation'));
        try {try {app(Preparation::class)->issue($this->operator->id,$input);$this->fail();}catch(\RuntimeException $e){$this->assertSame('lost confirmation',$e->getMessage());}}
        finally{Event::forget(\Illuminate\Database\Events\TransactionCommitted::class);}
        $n=BoletaNote::query()->where('idempotency_key',$input['idempotency_key'])->sole();
        $this->assertSame($n->id,app(Preparation::class)->issue($this->operator->id,$input)->id);
        $s=$this->summary([$n->id]);$path=app(Evidence::class)->path($n->id,'xml',$n->artifacts);file_put_contents($path,'tampered',FILE_APPEND);
        try {app(SummaryTransport::class)->send($s->id);$this->fail();}catch(\RuntimeException){$this->assertSame(0,DB::table('h5b_outbox')->where('summary_id',$s->id)->value('send_attempts'));}
    }
    public function test_outbox_failure_rolls_back_new_rc_members_counter_and_own_evidence(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        $before=glob(config('h5b.evidence_root').'/*') ?: [];$counter=DB::table('h4_counters')->where('series','RC')->value('next_number');
        DB::unprepared("CREATE FUNCTION h5b_test_outbox_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected'; END $$; CREATE TRIGGER h5b_test_outbox_fail BEFORE INSERT ON h5b_outbox FOR EACH ROW EXECUTE FUNCTION h5b_test_outbox_fail();");
        try {try {$this->summary([$n->id]);$this->fail();}catch(QueryException){$this->assertSame($before,glob(config('h5b.evidence_root').'/*') ?: []);}}
        finally{DB::unprepared('DROP TRIGGER h5b_test_outbox_fail ON h5b_outbox; DROP FUNCTION h5b_test_outbox_fail();');}
        $this->assertSame($counter,DB::table('h4_counters')->where('series','RC')->value('next_number'));
        $this->assertSame(0,DB::table('h5b_members')->where('note_id',$n->id)->count());
    }

    public function test_non_fictitious_mode_and_nested_preparation_are_refused_without_writes(): void
    {
        $b=$this->original();$input=$this->noteInput($b);config(['h5b.mode'=>'production']);
        try {app(Preparation::class)->issue($this->operator->id,$input);$this->fail();}catch(\LogicException){$this->assertTrue(true);}
        config(['h5b.mode'=>'fictitious']);
        try {DB::transaction(fn()=>app(Preparation::class)->issue($this->operator->id,$input));$this->fail();}
        catch(\LogicException){$this->assertSame(0,BoletaNote::query()->where('original_id',$b->id)->count());}
        $n=app(Preparation::class)->issue($this->operator->id,$input);$s=$this->summary([$n->id]);config(['h5b.mode'=>'production']);
        try {app(SummaryTransport::class)->send($s->id);$this->fail();}
        catch(\LogicException){$this->assertSame(0,DB::table('h5b_outbox')->where('summary_id',$s->id)->value('send_attempts'));}
    }

    public function test_nd_fractional_quantity_reuses_integer_rounding_and_note_cannot_predate_original(): void
    {
        $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['quantity'=>'2.345','emission_date'=>now()->toDateString()]));
        $s=app(\App\Boletas\Preparation::class)->freeze($this->operator->id,['members'=>[$b->id],'idempotency_key'=>(string)Str::uuid()]);
        app(\App\Boletas\SummaryTransport::class)->send($s->id);app(\App\Boletas\SummaryTransport::class)->poll($s->id);
        try {app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,['emission_date'=>now()->subDay()->toDateString()]));$this->fail();}
        catch(ValidationException){$this->assertSame(0,BoletaNote::query()->where('original_id',$b->id)->count());}
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($b,['operation'=>'ND02','increment_unit_price'=>'0.03']));
        $this->assertSame(2345,$n->snapshot['quantity_milli']);$this->assertSame(7,$n->snapshot['subtotal_cents']);
        $this->assertSame(1,$n->snapshot['tax_cents']);$this->assertSame(8,$n->snapshot['total_cents']);
    }
}
