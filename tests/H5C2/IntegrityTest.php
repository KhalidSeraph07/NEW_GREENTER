<?php
namespace Tests\H5C2;
use App\Corrections\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
final class IntegrityTest extends CaseBase
{
    public static function families(): array {return PreparationTest::families();}
    private function blocked(callable $attack): void
    {
        try{DB::transaction($attack);$this->fail('SQL indebido permitido.');}
        catch(\PDOException $e){$this->assertContains((string)$e->getCode(),['P0001','23503','23505','23514']);}
    }
    #[DataProvider('families')]
    public function test_signed_successor_and_negative_signature_and_zip_identity(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$xml=Family::path($f,$n,'xml');$cert=Family::path($f,$n,'certificate');
        $cmd=['xmlsec1','--verify','--lax-key-search','--enabled-key-data','rsa','--enabled-reference-uris','empty','--pubkey-cert-pem',$cert];
        $p=new Process([...$cmd,$xml]);$p->run();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());
        $bad=storage_path('audit/h5c2/negative-'.$n->id.'.xml');$bytes=file_get_contents($xml);
        file_put_contents($bad,preg_replace_callback('/(<cbc:IssueDate>)[^<]+/',fn($m)=>$m[1].'1900-01-01',$bytes,1));
        $p=new Process([...$cmd,$bad]);$p->run();$this->assertFalse($p->isSuccessful(),$p->getErrorOutput());
        $z=new \ZipArchive();$this->assertTrue($z->open(Family::path($f,$n,'zip')));$this->assertSame(1,$z->numFiles);
        $this->assertSame(Family::expectedEntry($f,$n),$z->getNameIndex(0));$this->assertSame($bytes,$z->getFromIndex(0));$z->close();
        $this->assertStringNotContainsString('PRIVATE KEY',file_get_contents($cert));
    }
    #[DataProvider('families')]
    public function test_sql_cannot_mutate_parent_child_link_causal_receipt_or_reservations(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$table=Family::TABLES[$f];$link=Family::LINKS[$f];
        $this->blocked(fn()=>DB::table($table)->where('id',$r->id)->delete());
        $this->blocked(fn()=>DB::table($table)->where('id',$n->id)->delete());
        $this->blocked(fn()=>DB::table($table)->where('id',$n->id)->update(['snapshot'=>json_encode(array_replace($n->snapshot,['currency'=>'USD']))]));
        $this->blocked(fn()=>DB::table($link)->where('original_id',$r->id)->delete());
        $this->blocked(fn()=>DB::table($link)->where('original_id',$r->id)->update(['reason'=>'OTRA CAUSA']));
        $this->blocked(fn()=>DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$r->id)->update(['status'=>'pending']));
        $receipts=match($f){'invoice'=>'simulation_receipts','void'=>'h5c1_simulation_receipts',default=>$f.'_simulation_receipts'};
        $this->blocked(fn()=>DB::table($receipts)->where(Family::COLUMN[$f],$r->id)->delete());
        $events=match($f){'invoice'=>'invoice_transport_events','void'=>'h5c1_events',default=>$f.'_events'};
        $this->blocked(fn()=>DB::table($events)->where(Family::COLUMN[$f],$r->id)->delete());
        $this->blocked(fn()=>DB::table($table)->where('id',$n->id)->update([$f==='invoice'?'transport_status':'state'=>$f==='invoice'?'simulated_accepted':'sim_accepted']));
        if($f!=='invoice'){$members=match($f){'void'=>'h5c1_members',default=>$f.'_members'};$this->blocked(fn()=>DB::table($members)->where($f==='void'?'batch_id':'summary_id',$r->id)->delete());}
        $this->assertSame(\App\Notes\Original::hash($r->snapshot),\App\Notes\Original::hash($r->fresh()->snapshot));
        $this->assertSame(\App\Notes\Original::hash($n->snapshot),\App\Notes\Original::hash($n->fresh()->snapshot));
    }
    public function test_sql_cannot_adopt_an_existing_unrelated_invoice_as_successor(): void
    {
        $r=$this->root('invoice');$unrelated=$this->issue();$proof=app(\App\Corrections\Rejection::class)->capture('invoice',$r);
        $this->blocked(fn()=>DB::table(Family::LINKS['invoice'])->insert(['original_id'=>$r->id,'successor_id'=>$unrelated->id,'user_id'=>$this->operator->id,
            'idempotency_key'=>$unrelated->idempotency_key,'request_hash'=>str_repeat('a',64),'reason'=>'CAUSA SQL FICTICIA','operation_date'=>now()->toDateString(),'prepared_on'=>now()->toDateString(),
            'proof'=>json_encode($proof),'created_at'=>now('UTC')]));
        $this->assertSame(0,DB::table(Family::LINKS['invoice'])->where('original_id',$r->id)->count());
    }
    public function test_ownership_and_explicit_operation_evidence_are_required(): void
    {
        $r=$this->root('invoice');$input=$this->reissueInput($r);
        try{app(\App\Corrections\InvoiceReissue::class)->prepare($this->newOperator()->id,$input);$this->fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException){$this->assertTrue(true);}
        foreach([['operation_subsists_test'=>'0'],['operation_date_test'=>'2026-10-27']] as $change){
            try{$this->child('invoice',$r,array_replace($input,$change));$this->fail();}catch(\Illuminate\Validation\ValidationException){$this->assertTrue(true);}}
        $this->assertSame(0,DB::table(Family::LINKS['invoice'])->where('original_id',$r->id)->count());
    }
    public static function communications(): array {return [['h4'],['h5b'],['void']];}
    #[DataProvider('communications')]
    public function test_plausible_result_without_committed_event_is_rejected_by_deferred_sql_guard(string $f): void
    {
        $r=$this->root($f);$n=$this->child($f,$r);$t=$this->transport($f);$ticket=$t->send($n->id)->ticket;
        $response=match($f){'h4'=>app(\App\Boletas\LocalSummarySimulator::class)->poll($ticket,'0'),
            'h5b'=>app(\App\BoletaNotes\LocalSummarySimulator::class)->poll($ticket,'0'),'void'=>app(\App\Voids\LocalSimulator::class)->poll($ticket,'0')};
        $this->blocked(function()use($f,$n,$response){
            DB::table(Family::TABLES[$f])->where('id',$n->id)->update(['state'=>'querying']);
            DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$n->id)->update(['status'=>'completed']);
            DB::table(Family::TABLES[$f])->where('id',$n->id)->update(['state'=>'sim_accepted','result'=>json_encode(['simulated'=>true,'official_cdr'=>false,'outcome'=>'accepted','response'=>$response])]);
        });
        $this->assertSame('awaiting',$n->fresh()->state);$this->assertNull($n->fresh()->result);
        $this->assertSame('sim_accepted',$t->poll($n->id)->state);
    }
}
