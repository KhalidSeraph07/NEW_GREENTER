<?php
namespace Tests\H5C2;
use App\Corrections\Family;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
final class ConcurrencyTest extends WorkerCase
{
    public static function families(): array {return PreparationTest::families();}
    #[DataProvider('families')]
    public function test_same_key_race_reuses_one_child_and_distinct_key_race_has_one_winner(string $f): void
    {
        foreach([true,false] as $same){
            $r=$this->root($f);$a=$f==='invoice'?$this->reissueInput($r):$this->correctionInput($r);$b=$same?$a:array_replace($a,['idempotency_key'=>(string)\Illuminate\Support\Str::uuid()]);
            $rows=$this->together('prepare',[['family'=>$f,'input'=>$a],['family'=>$f,'input'=>$b]]);
            $this->assertSame($same?0:1,count(array_filter($rows,fn($x)=>$x['conflict'])));
            $this->assertSame(1,DB::table(Family::LINKS[$f])->where('original_id',$r->id)->count());
            $ids=array_unique(array_filter(array_column($rows,'id')));$this->assertCount(1,$ids);
            $child=Family::record($f,$this->operator->id,array_values($ids)[0]);
            $this->together('send',[['family'=>$f,'id'=>$child->id],['family'=>$f,'id'=>$child->id]]);
            $this->assertSame(1,DB::table(Family::OUTBOX[$f])->where(Family::COLUMN[$f],$child->id)->value($f==='invoice'?'attempts':'send_attempts'));
            if($f!=='invoice'){$this->together('poll',[['family'=>$f,'id'=>$child->id],['family'=>$f,'id'=>$child->id]]);}
            $this->assertSame($f==='invoice'?'simulated_accepted':'sim_accepted',$f==='invoice'?$child->fresh()->transport_status:$child->fresh()->state);
        }
    }
    public function test_h4_h5b_and_rc3_share_counter_without_identity_collision(): void
    {
        $requests=[];foreach(['h4','h5b','void'] as $f){$r=$this->root($f,'boleta');$requests[]=['family'=>$f,'input'=>$this->correctionInput($r)];}
        $rows=$this->together('prepare',$requests);$names=[];
        foreach($rows as $i=>$row){$this->assertFalse($row['conflict']);$names[]=Family::record($requests[$i]['family'],$this->operator->id,$row['id'])->name;}
        $this->assertCount(3,array_unique($names));
    }
    public function test_reissue_racing_a_note_and_void_correction_racing_grant_cannot_change_source_eligibility(): void
    {
        $r=$this->root('invoice');$rows=$this->together('prepare',[
            ['family'=>'invoice','input'=>$this->reissueInput($r)],['family'=>'invoice','_action'=>'noteF','input'=>$this->noteInput($r->id)]]);
        $this->assertFalse($rows[0]['conflict']);$this->assertTrue($rows[1]['conflict']);$this->assertSame(0,DB::table('h5_notes')->where('original_id',$r->id)->count());
        $v=$this->root('void');$m=$v->snapshot['members'][0];$rows=$this->together('prepare',[
            ['family'=>'void','input'=>$this->correctionInput($v)],['family'=>'void','_action'=>'grant','input'=>['kind'=>$m['kind'],'id'=>$m['id'],'state'=>'granted',
                'provenance'=>'AFIRMACION FICTICIA EN CARRERA NO HISTORICA','idempotency_key'=>(string)\Illuminate\Support\Str::uuid()]]]);
        $this->assertFalse($rows[0]['conflict']);$this->assertTrue($rows[1]['conflict']);$this->assertSame(1,DB::table('h5c1_members')->where('batch_id',$v->id)->count());
    }
}
