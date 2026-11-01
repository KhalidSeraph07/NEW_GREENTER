<?php
namespace Tests\H71;

use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ConcurrencyTest extends TestCase
{
    private function spawn(array $data): array
    {
        $pipes=[];$process=proc_open([PHP_BINARY,base_path('tests/H71/worker.php')],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());
        $this->assertIsResource($process);fwrite($pipes[0],json_encode($data));fclose($pipes[0]);return [$process,$pipes];
    }
    private function collect(array $worker): array
    {
        [$p,$pipes]=$worker;$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
        return ['exit'=>$exit,'stdout'=>$stdout,'stderr'=>$stderr];
    }
    public function test_two_confirmed_recoveries_of_the_same_version_have_one_atomic_winner(): void
    {
        $root=storage_path('framework/testing/h71');if(!is_dir($root)){mkdir($root,0700,true);}$barrier=$root.'/'.Str::uuid();
        $passwords=['H71-Concurrent-First-Password-13579','H71-Concurrent-Second-Password-24680'];$workers=[];
        foreach($passwords as $i=>$password){$workers[]=$this->spawn(['user_id'=>$this->operator->id,'password'=>$password,'barrier'=>$barrier,'worker'=>$i]);}
        $deadline=microtime(true)+15;
        while(!is_file($barrier.'.0.ready')||!is_file($barrier.'.1.ready')){if(microtime(true)>$deadline){$this->fail('No llegaron ambas intervenciones al punto controlado');}usleep(10000);}
        file_put_contents($barrier.'.release','release');$results=array_map($this->collect(...),$workers);$statuses=[];
        foreach($results as $r){$v=json_decode($r['stdout'],true,512,JSON_THROW_ON_ERROR);$statuses[]=$v['status'];foreach($passwords as $pw){$this->assertStringNotContainsString($pw,$r['stdout'].$r['stderr']);}}
        sort($statuses);$this->assertSame([0,1],$statuses);$u=$this->operator->fresh();$this->assertSame(1,$u->credential_version);
        $this->assertSame(1,(int)Hash::check($passwords[0],$u->password)+(int)Hash::check($passwords[1],$u->password));
        $this->assertSame(1,DB::table('local_access_recoveries')->where('user_id',$u->id)->where('outcome','completed')->count());
        $this->assertSame(1,DB::table('local_access_recoveries')->where('user_id',$u->id)->where('outcome','rejected')->count());
    }
    public static function interruptions(): array { return [['kill_user'],['kill_audit']]; }
    #[DataProvider('interruptions')]
    public function test_sigkill_inside_transaction_preserves_credentials_and_a_durable_unconfirmed_attempt(string $mode): void
    {
        $before=$this->operator->fresh()->getAttributes();$root=storage_path('framework/testing/h71');if(!is_dir($root)){mkdir($root,0700,true);}$checkpoint=$root.'/'.Str::uuid();
        $worker=$this->spawn(['user_id'=>$this->operator->id,'password'=>'H71-Interrupted-Only-Password-86420','mode'=>$mode,'checkpoint'=>$checkpoint]);
        $deadline=microtime(true)+15;while(!is_file($checkpoint)){if(microtime(true)>$deadline){proc_terminate($worker[0],9);$this->fail('No se alcanzó el punto SIGKILL');}usleep(10000);}
        $this->assertTrue(proc_terminate($worker[0],9));$r=$this->collect($worker);
        $this->assertNotSame(0,$r['exit']);$this->assertSame($before,$this->operator->fresh()->getAttributes());
        $a=DB::table('local_access_recoveries')->where('user_id',$this->operator->id)->sole();$this->assertSame('started',$a->outcome);$this->assertNull($a->finished_at);$this->assertNull($a->version_after);
        $this->assertStringNotContainsString('Recuperación completada',$r['stdout'].$r['stderr']);
    }
}
