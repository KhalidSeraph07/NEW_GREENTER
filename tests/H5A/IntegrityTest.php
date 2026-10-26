<?php

namespace Tests\H5A;

use App\Models\InvoiceNote;
use App\Notes\Evidence;
use App\Notes\Preparation;
use App\Notes\Transport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IntegrityTest extends NoteCase
{
    public function test_boleta_uuid_is_not_an_eligible_invoice_and_creates_no_note(): void
    {
        $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now('America/Lima')->toDateString()]));
        try { app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original(),['original_id'=>$b->id])); $this->fail(); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { $this->assertSame(0,InvoiceNote::query()->where('user_id',$this->operator->id)->count()); }
    }

    public function test_new_note_timestamps_preserve_utc_instant(): void
    {
        $instant=\Illuminate\Support\Carbon::parse('2026-10-27T01:00:00+00:00'); $this->travelTo($instant);
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        $row=DB::table('h5_notes')->where('id',$n->id)->sole();
        $this->assertSame($instant->timestamp,\Illuminate\Support\Carbon::parse($row->created_at)->timestamp);
        $this->assertSame($instant->timestamp,\Illuminate\Support\Carbon::parse($row->updated_at)->timestamp);
    }

    public function test_source_and_note_evidence_tampering_block_dispatch_without_consuming_attempt(): void
    {
        foreach (['original','note'] as $kind) {
            $source=$this->original(); $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
            $path=$kind==='note' ? app(Evidence::class)->path($n->id,'xml',$n->artifacts)
                : app(\App\Invoices\InvoiceEvidence::class)->verifiedPath($source->id,'xml',$source->artifacts);
            file_put_contents($path,'<!-- altered fixture -->',FILE_APPEND);
            try { app(Transport::class)->dispatch($n->id); $this->fail('Debe detener evidencia alterada'); }
            catch (\RuntimeException) {
                $this->assertSame(0,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
                $this->assertSame('pending',$n->fresh()->state);
            }
        }
    }

    public function test_frozen_reference_detects_changed_original_without_repairing_it(): void
    {
        $source=$this->original(); $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
        $source->snapshot=array_replace($source->snapshot,['receiver_name'=>'ALTERADO FICTICIO']); $source->save();
        $before=$source->fresh()->getRawOriginal();
        try { app(Transport::class)->dispatch($n->id); $this->fail(); }
        catch (ConflictHttpException) { $this->assertSame($before,$source->fresh()->getRawOriginal()); }
        $this->assertSame(0,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
    }

    public function test_rejected_and_uncertain_notes_never_release_the_original_for_a_second_note(): void
    {
        foreach (['rejected','uncertain'] as $scenario) {
            $source=$this->original(); $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
            app(Transport::class)->dispatch($n->id,$scenario);
            try { app(Preparation::class)->issue($this->operator->id,$this->noteInput($source,['operation'=>'ND02','increment_unit_price'=>'1'])); $this->fail(); }
            catch (ConflictHttpException) { $this->assertSame(1,InvoiceNote::query()->where('original_id',$source->id)->count()); }
        }
    }

    public function test_failed_result_commit_is_recovered_without_resend_or_loss_of_attempt(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        DB::unprepared("CREATE FUNCTION h5_test_result_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.state='sim_accepted' THEN RAISE EXCEPTION 'injected result failure'; END IF; RETURN NEW; END $$; CREATE TRIGGER h5_test_result_fail BEFORE UPDATE ON h5_notes FOR EACH ROW EXECUTE FUNCTION h5_test_result_fail();");
        try {
            try { app(Transport::class)->dispatch($n->id); $this->fail(); }
            catch (QueryException) { $this->assertSame('processing',$n->fresh()->state); }
        } finally { DB::unprepared('DROP TRIGGER h5_test_result_fail ON h5_notes; DROP FUNCTION h5_test_result_fail();'); }
        $this->assertSame(1,DB::table('h5_receipts')->where('note_id',$n->id)->count());
        $this->travel(6)->minutes();
        $this->assertSame('sim_accepted',app(Transport::class)->recover($n->id)->state);
        $this->assertSame(1,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
        $this->assertNull(app(Transport::class)->dispatch($n->id));
    }

    public function test_nested_transactions_and_non_fictitious_configuration_are_refused(): void
    {
        $source=$this->original();
        try {
            DB::transaction(function () use ($source) {
                $this->expectException(\LogicException::class);
                app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
            });
        } finally { $this->assertSame(0,InvoiceNote::query()->where('original_id',$source->id)->count()); }
    }

    public function test_real_configuration_and_unsupported_transport_scenarios_are_not_accepted(): void
    {
        $source=$this->original(); $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source));
        config(['h5.mode'=>'production']);
        try { app(Transport::class)->dispatch($n->id); $this->fail(); }
        catch (\LogicException) { $this->assertSame('pending',$n->fresh()->state); }
        config(['h5.mode'=>'fictitious']);
        $this->expectException(\Illuminate\Validation\ValidationException::class); app(Transport::class)->dispatch($n->id,'sunat');
    }

    public function test_fractional_quantities_and_rounding_reuse_h2_integer_calculation(): void
    {
        $source=$this->issue(['quantity'=>'2.345','unit_price'=>'100']);
        $source=app(\App\Invoices\SimulatedTransport::class)->dispatch($source->id)->fresh();
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($source,['operation'=>'ND02','increment_unit_price'=>'0.03']));
        $this->assertSame(2345,$n->snapshot['quantity_milli']);
        $this->assertSame(7,$n->snapshot['subtotal_cents']); $this->assertSame(1,$n->snapshot['tax_cents']);
        $this->assertSame(8,$n->snapshot['total_cents']);
    }
}
