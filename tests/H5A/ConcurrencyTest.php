<?php

namespace Tests\H5A;

use App\Models\InvoiceNote;
use App\Notes\Preparation;
use App\Notes\Transport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConcurrencyTest extends WorkerCase
{
    public function test_credit_and_debit_race_reserves_only_one_note_and_keeps_original_history(): void
    {
        $source=$this->original(); $before=$source->getRawOriginal(); $payloads=[];
        for ($i=0;$i<8;$i++) { $payloads[]=$this->noteInput($source,$i%2 ? ['operation'=>'ND02','increment_unit_price'=>'20'] : []); }
        $beforeCounters=(int) DB::table('h5_counters')->sum('next_number');
        $results=$this->together('issue',$payloads);
        $this->assertCount(7,array_filter($results,fn ($r)=>$r['conflict']));
        $this->assertSame(1,InvoiceNote::query()->where('original_id',$source->id)->count());
        $this->assertSame($beforeCounters+1,(int) DB::table('h5_counters')->sum('next_number'));
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
        $this->assertSame(1,InvoiceNote::query()->where('user_id',$this->operator->id)->where('idempotency_key',$key)->count());
    }

    public function test_dispatch_and_recovery_races_keep_one_attempt_and_terminal_result(): void
    {
        $n=app(Preparation::class)->issue($this->operator->id,$this->noteInput($this->original()));
        $results=$this->together('dispatch',array_fill(0,8,['id'=>$n->id,'scenario'=>'timeout']));
        $this->assertSame(1,DB::table('h5_outbox')->where('note_id',$n->id)->value('attempts'));
        $this->assertSame('uncertain',$n->fresh()->state);
        $this->assertSame(1,DB::table('h5_receipts')->where('note_id',$n->id)->count());
        $results=$this->together('recover',array_fill(0,8,['id'=>$n->id]));
        $this->assertSame(['sim_accepted'],array_unique(array_column($results,'state')));
        $this->assertSame(1,DB::table('h5_events')->where('note_id',$n->id)->where('kind','recovered_local_fake')->count());
    }
}
