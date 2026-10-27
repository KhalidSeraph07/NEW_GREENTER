<?php

namespace Tests\H5B;

use App\Models\BoletaNote;
use App\BoletaNotes\Preparation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConcurrencyTest extends WorkerCase
{
    public function test_credit_and_debit_race_reserves_only_one_note_and_keeps_original_history(): void
    {
        $source=$this->original(); $before=$source->getRawOriginal(); $payloads=[];
        for ($i=0;$i<8;$i++) { $payloads[]=$this->noteInput($source,$i%2 ? ['operation'=>'ND02','increment_unit_price'=>'20'] : []); }
        $beforeCounters=(int) DB::table('h5b_counters')->sum('next_number');
        $results=$this->together('issue',$payloads);
        $this->assertCount(7,array_filter($results,fn ($r)=>$r['conflict']));
        $this->assertSame(1,BoletaNote::query()->where('original_id',$source->id)->count());
        $this->assertSame($beforeCounters+1,(int) DB::table('h5b_counters')->sum('next_number'));
        $this->assertSame($before,$source->fresh()->getRawOriginal());
    }

    public function test_same_key_replays_and_cross_original_key_conflicts_are_atomic(): void
    {
        $input=$this->noteInput($this->original()); $results=$this->together('issue',array_fill(0,8,$input));
        $this->assertCount(1,array_unique(array_column($results,'id')));
        $key=(string) Str::uuid();
        $payloads=[];
        for ($i=0;$i<8;$i++) { $payloads[]=$this->noteInput($this->original(),['idempotency_key'=>$key]); }
        $results=$this->together('issue',$payloads);
        $this->assertCount(7,array_filter($results,fn ($r)=>$r['conflict']));
        $this->assertSame(1,BoletaNote::query()->where('user_id',$this->operator->id)->where('idempotency_key',$key)->count());
    }

    public function test_same_and_competing_rc_freezes_and_send_poll_races_keep_single_membership_and_attempt(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        $payload=['idempotency_key'=>(string)Str::uuid(),'members'=>[$n->id]];
        $r=$this->together('freeze',array_fill(0,8,$payload));
        $this->assertCount(1,array_unique(array_column($r,'id')));$id=$r[0]['id'];
        $s=\App\Models\NoteSummary::query()->findOrFail($id);
        $this->assertSame(1,DB::table('h5b_members')->where('note_id',$n->id)->count());
        $this->together('send',array_fill(0,8,['id'=>$id]));
        $this->assertSame(1,DB::table('h5b_outbox')->where('summary_id',$id)->value('send_attempts'));
        $this->together('poll',array_fill(0,8,['id'=>$id]));
        $this->assertSame('sim_accepted',$s->fresh()->state);
        $this->assertSame(1,DB::table('h5b_outbox')->where('summary_id',$id)->value('poll_attempts'));
        $n2=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));$payloads=[];
        for($i=0;$i<8;$i++)$payloads[]=['idempotency_key'=>(string)Str::uuid(),'members'=>[$n2->id]];
        $r=$this->together('freeze',$payloads);$this->assertCount(7,array_filter($r,fn($x)=>$x['conflict']));
        $this->assertSame(1,DB::table('h5b_members')->where('note_id',$n2->id)->count());
    }
}
