<?php
namespace Tests\H5C2;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
abstract class WorkerCase extends CaseBase
{
    protected function launch(string $action,array $payloads,string $pause=''): array
    {
        $barrier=storage_path('framework/testing/h5c2-barriers/'.Str::uuid());mkdir($barrier,0700,true);$workers=[];
        foreach($payloads as $p){$w=new Process([PHP_BINARY,base_path('tests/h5c2-worker.php'),$action,(string)$this->operator->id,json_encode($p,JSON_THROW_ON_ERROR),$barrier,$pause],base_path(),['APP_ENV'=>'testing','DB_DATABASE'=>'emisor_h2_test','CACHE_STORE'=>'array']);$w->setTimeout(90);$w->start();$workers[]=$w;}
        file_put_contents($barrier.'/clock',now()->toIso8601String());$until=microtime(true)+30;
        while(count(glob($barrier.'/ready-*')?:[])<count($workers)&&microtime(true)<$until){usleep(10000);}
        $ready=count(glob($barrier.'/ready-*')?:[]);file_put_contents($barrier.'/start','start');
        if($ready!==count($workers)){foreach($workers as $w){$w->stop(0,9);}$this->fail('Barrera incompleta.');}
        if($pause!==''){$until=microtime(true)+30;while(!is_file($barrier.'/paused')&&$workers[0]->isRunning()&&microtime(true)<$until){usleep(10000);}
            if(!is_file($barrier.'/paused')){$error=$workers[0]->getErrorOutput().$workers[0]->getOutput();$workers[0]->stop(0,9);$this->fail('Pausa no alcanzada: '.$error);}}
        return [$workers,$barrier];
    }
    protected function together(string $action,array $payloads): array
    {
        [$workers]=$this->launch($action,$payloads);$results=[];
        try{foreach($workers as $w){$w->wait();$this->assertTrue($w->isSuccessful(),$w->getErrorOutput().$w->getOutput());$results[]=json_decode($w->getOutput(),true,flags:JSON_THROW_ON_ERROR);}
            $this->assertCount(count($workers),array_unique(array_column($results,'pid')));$this->evidence('concurrency',['action'=>$action,'results'=>$results]);return $results;
        }finally{foreach($workers as $w){if($w->isRunning()){$w->stop(0,9);}}}
    }
    protected function evidence(string $name,array $data): void
    {
        $root=storage_path('audit/h5c2');if(!is_dir($root)){mkdir($root,0700,true);}file_put_contents($root.'/'.$name.'-'.Str::uuid().'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
}
