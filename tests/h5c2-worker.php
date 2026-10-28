<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('testing')||config('database.connections.pgsql.database')!=='emisor_h2_test'){throw new RuntimeException('Solo testing ficticio.');}
[$script,$action,$user,$json,$barrier,$pauseAt]=$argv;$root=storage_path('framework/testing/h5c2-barriers/');
if(!str_starts_with($barrier,$root)||!preg_match('/\A[0-9a-f-]{36}\z/',substr($barrier,strlen($root)))){throw new RuntimeException('Barrera C2 invalida.');}
if($pauseAt!==''){Illuminate\Support\Facades\Event::listen($pauseAt,function()use($barrier){
    file_put_contents($barrier.'/paused',(string)getmypid());$until=microtime(true)+40;
    while(!is_file($barrier.'/release')){if(microtime(true)>$until){throw new RuntimeException('Pausa C2 vencida.');}usleep(10000);}
});}
file_put_contents($barrier.'/ready-'.getmypid(),'ready');$until=microtime(true)+40;
while(!is_file($barrier.'/start')){if(microtime(true)>$until){throw new RuntimeException('Barrera C2 vencida.');}usleep(10000);}
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse(file_get_contents($barrier.'/clock')));
$p=json_decode($json,true,flags:JSON_THROW_ON_ERROR);$f=$p['family'];$started=microtime(true);
try{
    if($action==='prepare'){$r=$f==='invoice'?app(App\Corrections\InvoiceReissue::class)->prepare((int)$user,$p['input']):app(App\Corrections\CommunicationCorrection::class)->prepare($f,(int)$user,$p['input']);}
    elseif($f==='invoice'){$t=app(App\Invoices\SimulatedTransport::class);$r=$action==='send'?$t->dispatch($p['id'],$p['scenario']??'accepted'):$t->recover($p['id']);}
    else{$t=app(match($f){'h4'=>App\Boletas\SummaryTransport::class,'h5b'=>App\BoletaNotes\SummaryTransport::class,'void'=>App\Voids\Transport::class});
        $r=match($action){'send'=>$t->send($p['id'],$p['scenario']??'accepted'),'poll'=>$t->poll($p['id'],$p['scenario']??'0'),'recover'=>$t->recover($p['id'])};}
    echo json_encode(['pid'=>getmypid(),'started'=>$started,'ended'=>microtime(true),'id'=>$r?->id,'conflict'=>false],JSON_THROW_ON_ERROR);
}catch(Symfony\Component\HttpKernel\Exception\ConflictHttpException){echo json_encode(['pid'=>getmypid(),'started'=>$started,'ended'=>microtime(true),'conflict'=>true],JSON_THROW_ON_ERROR);}
