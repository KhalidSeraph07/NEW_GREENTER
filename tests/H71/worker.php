<?php
putenv('APP_ENV=testing');putenv('DB_DATABASE=emisor_h2_test');
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.connections.pgsql.database')!=='emisor_h2_test') { exit(2); }
set_exception_handler(function(){echo json_encode(['status'=>2,'unexpected'=>true]);exit(2);});
$p=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::listen(function($q)use($p){
    $sql=strtolower($q->sql);
    if (str_starts_with($sql,'insert into "local_access_recoveries"') && isset($p['barrier'])) {
        $root=storage_path('framework/testing/h71/');
        if (!str_starts_with($p['barrier'],$root) || str_contains($p['barrier'],'..')) { exit(2); }
        file_put_contents($p['barrier'].'.'.$p['worker'].'.ready','ready');$deadline=microtime(true)+15;
        while(!is_file($p['barrier'].'.release')) { if(microtime(true)>$deadline){exit(2);}usleep(10000); }
    }
    $checkpoint=(($p['mode'] ?? '')==='kill_user' && str_starts_with($sql,'update "users" set "password"'))
        || (($p['mode'] ?? '')==='kill_audit' && str_starts_with($sql,'update "local_access_recoveries"') && in_array('completed',$q->bindings,true));
    if ($checkpoint) {
        if(!str_starts_with($p['checkpoint'],storage_path('framework/testing/h71/')) || str_contains($p['checkpoint'],'..')) { exit(2); }
        file_put_contents($p['checkpoint'],'checkpoint');
        while(true) { usleep(10000); }
    }
});
$c=new App\Console\Commands\RecoverLocalAccess();$c->setLaravel($app);$t=new Symfony\Component\Console\Tester\CommandTester($c);
$t->setInputs(['yes',$p['password'],$p['password']]);
$t->execute(['--user-id'=>(string)$p['user_id'],'--authorized-by'=>'RESPONSABLE-ENSAYO-CONCURRENCIA'],['interactive'=>true]);
echo json_encode(['status'=>$t->getStatusCode(),'display'=>$t->getDisplay()]);exit($t->getStatusCode());
