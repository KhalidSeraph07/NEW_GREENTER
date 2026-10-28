<?php
namespace Tests\H5C1;
use App\Voids\{Preparation, Source};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class ConcurrencyTest extends WorkerCase
{
    public function test_same_and_competing_freezes_then_senders_and_pollers_are_atomic(): void
    {
        $k=$this->accepted('boleta');$this->grant($k);$input=$this->batchInput([$k]);
        $before=app(Source::class)->document($this->operator->id,$k['kind'],$k['id'])->getRawOriginal();
        $results=$this->together('freeze',array_fill(0,6,$input));$this->assertCount(1,array_unique(array_column($results,'id')));$id=$results[0]['id'];
        $this->together('send',array_fill(0,6,['id'=>$id]));$this->assertSame(1,$this->job($id)->send_attempts);
        $this->together('poll',array_fill(0,6,['id'=>$id]));$this->assertSame(1,$this->job($id)->poll_attempts);
        $this->assertSame('sim_accepted',\App\Models\VoidBatch::query()->findOrFail($id)->state);
        $this->assertSame($before,app(Source::class)->document($this->operator->id,$k['kind'],$k['id'])->getRawOriginal());
        $other=$this->accepted();$this->grant($other);$payloads=[];
        for($i=0;$i<6;$i++){$payloads[]=$this->batchInput([$other]);}
        $results=$this->together('freeze',$payloads);$this->assertCount(5,array_filter($results,fn($r)=>$r['conflict']));
        $this->assertSame(1,DB::table('h5c1_members')->where('document_id',$other['id'])->count());
    }
    public function test_grant_against_void_cannot_leave_a_granted_reserved_document(): void
    {
        $k=$this->accepted();$this->grant($k);
        $results=$this->together('freeze',[$this->batchInput([$k]),$k+['_action'=>'grant','state'=>'granted',
            'idempotency_key'=>(string)Str::uuid(),'provenance'=>'OTORGAMIENTO EXPLICITO FICTICIO EN CARRERA']]);
        $this->assertCount(1,array_filter($results,fn($r)=>$r['conflict']));
        $reserved=DB::table('h5c1_members')->where('document_id',$k['id'])->exists();
        $state=DB::table('h5c1_grants')->where('document_id',$k['id'])->orderByDesc('sequence')->value('state');
        $this->assertSame(!$reserved,$state==='granted');
    }
    public function test_void_against_credit_or_debit_never_creates_incompatible_dependencies(): void
    {
        foreach(['invoice','boleta'] as $kind){
            $k=$this->accepted($kind);$this->grant($k);$payloads=[$this->batchInput([$k])];
            foreach(['NC01','ND02'] as $op){$payloads[]=$this->noteInput($k['id'],$op)+['_action'=>$kind==='invoice'?'noteF':'noteB'];}
            $r=$this->together('freeze',$payloads);$this->assertCount(2,array_filter($r,fn($x)=>$x['conflict']));
            $this->assertSame(1,DB::table('h5c1_members')->where('document_id',$k['id'])->count());
            $this->assertSame(0,DB::table($kind==='invoice'?'h5_notes':'h5b_notes')->where('original_id',$k['id'])->count());
        }
    }
    public function test_new_non_grant_fact_and_new_note_compete_for_the_same_original_lock(): void
    {
        foreach(['invoice','boleta'] as $kind){
            $k=$this->accepted($kind);
            $r=$this->together('grant',[$k+['state'=>'not_granted','idempotency_key'=>(string)Str::uuid(),
                'provenance'=>'NO OTORGAMIENTO FICTICIO EXPLICITO EN CARRERA'],
                $this->noteInput($k['id'])+['_action'=>$kind==='invoice'?'noteF':'noteB']]);
            $this->assertCount(1,array_filter($r,fn($x)=>$x['conflict']));
            $note=DB::table($kind==='invoice'?'h5_notes':'h5b_notes')->where('original_id',$k['id'])->exists();
            $fact=DB::table('h5c1_grants')->where('document_id',$k['id'])->where('state','not_granted')->exists();
            $this->assertNotSame($note,$fact);
        }
    }
}
