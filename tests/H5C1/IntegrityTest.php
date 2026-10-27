<?php
namespace Tests\H5C1;
use App\Voids\{Source, Grant, Preparation, Transport, Evidence};
use App\Models\VoidBatch;
use Illuminate\Support\Facades\{DB, Artisan};
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Process\Process;

final class IntegrityTest extends VoidCase
{
    public static function documents(): array {return [['invoice','NC01','RA'],['invoice_note','NC01','RA'],['invoice_note','ND02','RA'],
        ['boleta','NC01','RC'],['boleta_note','NC01','RC'],['boleta_note','ND02','RC']];}
    #[DataProvider('documents')]
    public function test_each_eligible_type_has_signed_xml_exact_zip_and_simulated_void_without_history_mutation(string $kind,string $op,string $route): void
    {
        $key=$this->accepted($kind,$op);$source=app(Source::class)->document($this->operator->id,$kind,$key['id']);
        $before=$source->getRawOriginal();$grant=$this->grant($key);
        $batch=app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$key]));
        $this->assertSame($route,$batch->route);$this->assertSame(1,DB::table('h5c1_members')->where('batch_id',$batch->id)->count());
        $f=app(Evidence::class);$path=$f->path($batch->id,'xml',$batch->artifacts);$cert=$f->path($batch->id,'certificate',$batch->artifacts);
        $xml=file_get_contents($path);$dom=new \DOMDocument();$this->assertTrue($dom->loadXML($xml,LIBXML_NONET));$xp=new \DOMXPath($dom);
        $this->assertSame('2.0',$xp->evaluate('string(//*[local-name()="UBLVersionID"])'));
        $this->assertSame($route==='RA'?'1.0':'1.1',$xp->evaluate('string(//*[local-name()="CustomizationID"])'));
        $this->assertSame($batch->reference_date,$xp->evaluate('string(//*[local-name()="ReferenceDate"])'));
        $this->assertSame($batch->issue_date,$xp->evaluate('string(//*[local-name()="IssueDate"])'));
        $this->assertSame($source->series ?? $source->snapshot['series'],$batch->snapshot['members'][0]['series']);
        if($route==='RC'){$this->assertSame('3',$xp->evaluate('string(//*[local-name()="ConditionCode"])'));}
        else{$this->assertSame('NO OTORGADO - BAJA FICTICIA',$xp->evaluate('string(//*[local-name()="VoidReasonDescription"])'));}
        if($kind==='boleta_note'){$this->assertSame('03',$xp->evaluate('string(//*[local-name()="BillingReference"]//*[local-name()="DocumentTypeCode"])'));}
        $cmd=['xmlsec1','--verify','--lax-key-search','--enabled-key-data','rsa','--enabled-reference-uris','empty','--pubkey-cert-pem',$cert];
        $p=new Process([...$cmd,$path]);$p->run();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());
        $bad=$path.'.negative';file_put_contents($bad,str_replace('<cbc:IssueDate>'.$batch->issue_date,'<cbc:IssueDate>1900-01-01',$xml));
        $p=new Process([...$cmd,$bad]);$p->run();$this->assertFalse($p->isSuccessful());
        $zip=new \ZipArchive();$this->assertTrue($zip->open($f->path($batch->id,'zip',$batch->artifacts)));$this->assertSame(1,$zip->numFiles);
        $this->assertSame($batch->name.'.xml',$zip->getNameIndex(0));$this->assertSame($xml,$zip->getFromIndex(0));$zip->close();
        $this->assertStringNotContainsString('PRIVATE KEY',file_get_contents($cert));
        $this->assertSame($grant->id,$batch->snapshot['members'][0]['grant_id']);
        app(Transport::class)->send($batch->id);$this->assertSame('sim_accepted',app(Transport::class)->poll($batch->id)->state);
        $this->assertSame($before,$source->fresh()->getRawOriginal());
        $this->assertSame(0,Artisan::call('h5c1:process',['action'=>'status','id'=>$batch->id]));
        $result=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);$this->assertSame('voided_simulated',$result['effect']);$this->assertFalse($result['official_cdr']);
    }
    public function test_grant_is_append_only_idempotent_and_granted_cannot_be_revoked(): void
    {
        $k=$this->accepted();$req=(string)Str::uuid();$a=$this->grant($k,'not_granted',$req);$this->assertSame($a->id,$this->grant($k,'not_granted',$req)->id);
        $b=$this->grant($k,'granted');$this->assertNotSame($a->id,$b->id);
        try{$this->grant($k);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        try{$this->grant($k,'granted',$req);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        try{app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$k]));$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        $this->expectException(QueryException::class);DB::table('h5c1_grants')->where('id',$a->id)->update(['state'=>'granted']);
    }
    public function test_unknown_granted_foreign_pending_rejected_and_uncertain_sources_never_become_eligible(): void
    {
        $k=$this->accepted();$this->grant($k,'granted');
        try{app(Grant::class)->record($this->newOperator()->id,$k+['idempotency_key'=>(string)Str::uuid(),'state'=>'not_granted','provenance'=>'AFIRMACION FICTICIA AJENA']);$this->fail();}
        catch(\Illuminate\Database\Eloquent\ModelNotFoundException){$this->assertTrue(true);}
        foreach(['pending','rejected','timeout'] as $scenario){
            $i=$this->issue();if($scenario!=='pending'){app(\App\Invoices\SimulatedTransport::class)->dispatch($i->id,$scenario);}
            try{$this->grant(['kind'=>'invoice','id'=>$i->id]);$this->fail();}catch(ConflictHttpException){$this->assertSame(0,DB::table('h5c1_grants')->where('document_id',$i->id)->count());}
        }
        $b=app(\App\Boletas\Preparation::class)->issue($this->operator->id,$this->input(['emission_date'=>now()->toDateString()]));
        try{$this->grant(['kind'=>'boleta','id'=>$b->id]);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
    }
    public function test_note_blocks_original_void_and_non_grant_blocks_new_note_until_explicit_grant(): void
    {
        foreach(['invoice','boleta'] as $kind){
            $k=$this->accepted($kind);$this->grant($k);
            $class=$kind==='invoice'?\App\Notes\Preparation::class:\App\BoletaNotes\Preparation::class;
            try{app($class)->issue($this->operator->id,$this->noteInput($k['id']));$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
            $this->grant($k,'granted');$note=app($class)->issue($this->operator->id,$this->noteInput($k['id']));
            try{$this->grant($k);$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
            $this->assertSame($k['id'],$note->original_id);
            $v=$this->batch($kind);$original=$v->snapshot['members'][0]['id'];
            try{app($class)->issue($this->operator->id,$this->noteInput($original));$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        }
    }
    public function test_members_dates_routes_and_reused_keys_are_strict_and_replay_preserves_identity(): void
    {
        $a=$this->accepted();$b=$this->accepted('boleta');$this->grant($a);$this->grant($b);
        foreach([[$a,$a],[$a,$b]] as $members){try{app(Preparation::class)->freeze($this->operator->id,$this->batchInput($members));$this->fail();}catch(ValidationException){$this->assertTrue(true);}}
        $this->travel(1)->days();$c=$this->accepted();$this->grant($c);
        try{app(Preparation::class)->freeze($this->operator->id,$this->batchInput([$a,$c]));$this->fail();}catch(ValidationException){$this->assertTrue(true);}
        $input=$this->batchInput([$a]);$v=app(Preparation::class)->freeze($this->operator->id,$input);$this->travel(8)->days();
        $this->assertSame($v->id,app(Preparation::class)->freeze($this->operator->id,$input)->id);
        try{app(Preparation::class)->freeze($this->operator->id,array_replace($input,['reason'=>'OTRO MOTIVO']));$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
        try{$this->grant($a,'granted');$this->fail();}catch(ConflictHttpException){$this->assertTrue(true);}
    }
    public function test_frozen_members_and_invalid_transitions_cannot_bypass_database(): void
    {
        $v=$this->batch();
        foreach([fn()=>DB::table('h5c1_batches')->where('id',$v->id)->update(['snapshot'=>'{}']),
            fn()=>DB::table('h5c1_batches')->where('id',$v->id)->update(['state'=>'sim_accepted']),
            fn()=>DB::table('h5c1_members')->where('batch_id',$v->id)->update(['position'=>99]),
            fn()=>DB::table('h5c1_members')->where('batch_id',$v->id)->delete()] as $change){try{$change();$this->fail();}catch(QueryException){$this->assertTrue(true);}}
        $this->assertSame('pending_send',$v->fresh()->state);
    }
    public function test_terminal_state_cannot_be_inserted_and_a_query_cannot_invent_an_empty_result(): void
    {
        $v=$this->batch();$copy=$v->getRawOriginal();$copy['id']=(string)Str::uuid();$copy['idempotency_key']=(string)Str::uuid();
        $copy['name']='00000000000-RA-20261026-99999';$copy['state']='sim_accepted';
        try{DB::table('h5c1_batches')->insert($copy);$this->fail();}catch(QueryException){$this->assertTrue(true);}
        app(Transport::class)->send($v->id);
        DB::table('h5c1_batches')->where('id',$v->id)->update(['state'=>'querying']);
        try{DB::table('h5c1_batches')->where('id',$v->id)->update(['state'=>'sim_accepted','result'=>'{}']);$this->fail();}
        catch(QueryException){$this->assertSame('querying',$v->fresh()->state);$this->assertNull($v->fresh()->result);}
    }
    public function test_cli_grant_freeze_and_status_are_explicitly_fictitious_and_private(): void
    {
        $k=$this->accepted('boleta');$input=$k+['idempotency_key'=>(string)Str::uuid(),'state'=>'not_granted','provenance'=>'AFIRMACION FICTICIA EXPLICITA VIA CLI'];
        $this->assertSame(0,Artisan::call('h5c1:process',['action'=>'grant','--user'=>$this->operator->id,'--input'=>json_encode($input)]));
        $this->assertSame(0,Artisan::call('h5c1:process',['action'=>'freeze','--user'=>$this->operator->id,'--input'=>json_encode($this->batchInput([$k]))]));
        $output=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);$this->assertTrue($output['simulated']);$this->assertFalse($output['official_cdr']);
        $id=$output['id'];$this->assertSame(0,Artisan::call('h5c1:process',['action'=>'status','id'=>$id]));
        $this->assertSame('not_confirmed',json_decode(Artisan::output(),true)['effect']);
        $this->get('/storage/private/h5c1/'.$id.'/signed.xml')->assertNotFound();
    }
    public static function badRequests(): array{return [[['operation'=>'2']],[['reason'=>']]>' ]],[['members'=>[]]],[['reference_date'=>'2026-10-01']],[['ticket'=>'REAL']]];}
    #[DataProvider('badRequests')]
    public function test_invalid_request_consumes_no_number(array $changes): void
    {
        $before=DB::table('h5c1_counters')->value('next_number');
        try{app(Preparation::class)->freeze($this->operator->id,array_replace($this->batchInput([['kind'=>'invoice','id'=>(string)Str::uuid()]]),$changes));$this->fail();}
        catch(ValidationException){$this->assertSame($before,DB::table('h5c1_counters')->value('next_number'));}
    }
}
