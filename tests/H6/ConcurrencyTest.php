<?php
namespace Tests\H6;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ConcurrencyTest extends TestCase
{
    private function worker(string $action,string $input,string $barrier): Process
    {
        $p=new Process([PHP_BINARY,base_path('tests/h6-account-worker.php'),$action,$input,$barrier],base_path(),
            ['APP_ENV'=>'testing','DB_DATABASE'=>'emisor_h2_test','CACHE_STORE'=>'array']);
        $p->setTimeout(30);$p->start();return $p;
    }
    public function test_deactivation_waits_for_the_shared_account_lock(): void
    {
        $target=$this->newOperator();$barrier=storage_path('framework/testing/h6-'.Str::uuid());mkdir($barrier,0700,true);
        DB::beginTransaction();DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h6-local-accounts']);
        $worker=$this->worker('deactivate',(string)$target->id,$barrier);
        try {
            $until=microtime(true)+15;$waiting=false;
            while(microtime(true)<$until){
                $files=glob($barrier.'/*');
                if($files){$pid=(int)file_get_contents($files[0]);$waiting=DB::selectOne("select exists(select 1 from pg_locks where pid=? and locktype='advisory' and not granted) as waiting",[$pid])->waiting;if($waiting){break;}}
                usleep(10000);
            }
            $this->assertTrue((bool)$waiting,'La desactivación debe serializarse con creación/último ADMIN.');
        } finally { DB::rollBack(); }
        $worker->wait();$this->assertTrue($worker->isSuccessful(),$worker->getErrorOutput());
        $this->assertTrue(json_decode($worker->getOutput(),true)['completed']);$this->assertFalse($target->fresh()->is_active);
    }
    public function test_duplicate_account_creation_race_keeps_one_identity(): void
    {
        $email=Str::uuid().'@example.invalid';$barrier=storage_path('framework/testing/h6-'.Str::uuid());mkdir($barrier,0700,true);
        $workers=[$this->worker('create',$email,$barrier),$this->worker('create',$email,$barrier)];$results=[];
        foreach($workers as $p){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$results[]=json_decode($p->getOutput(),true)['completed'];}
        $this->assertSame(1,count(array_filter($results)));$this->assertSame(1,DB::table('users')->where('email',$email)->count());
    }
}
