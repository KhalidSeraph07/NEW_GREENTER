<?php
// Nuevos hechos de ensayo, mediante servicios aprobados; no reconstruir históricos.
require __DIR__.'/../vendor/autoload.php';
$stage='bootstrap';
try {
    $app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $marker=json_decode(file_get_contents(base_path('.h72-installation.json')),true);
    if (!app()->environment('local') || config('h2.mode')!=='fictitious' || config('h2.issuer_ruc')!=='00000000000'
        || ($marker['role']??null)!=='source' || !str_starts_with($marker['project']??'','emisor-h72-')
        || config('database.connections.pgsql.database')!=='emisor_h1' || App\Models\Invoice::query()->exists()
        || App\Models\User::query()->count()!==1) { throw new RuntimeException('Solo fuente nueva ficticia H72.'); }
    $v=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-11-02T12:00:00-05:00'));
    $owner=App\Models\User::query()->where('email',config('h2.operator_email'))->sole();
    $accounts=app(App\Accounts\LocalAccounts::class);$created=[];
    foreach (['admin'=>'ADMIN','B'=>'RECEPTOR','disabled'=>'RECEPTOR','pending'=>'OPERADOR'] as $key=>$role) {
        $created[$key]=$accounts->create(['name'=>'CUENTA FICTICIA H72 '.$key,'email'=>$key.'.'.Illuminate\Support\Str::uuid().'@example.invalid',
            'role'=>$role,'password'=>$v['initial'],'password_confirmation'=>$v['initial']]);
    }
    foreach (['admin','B'] as $key) {
        $command=new App\Console\Commands\RecoverLocalAccess();$command->setLaravel($app);
        $test=new Symfony\Component\Console\Tester\CommandTester($command);$test->setInputs(['yes',$v[$key],$v[$key]]);
        $test->execute(['--user-id'=>(string)$created[$key]->id,'--authorized-by'=>'RESPONSABLE-ENSAYO-H72'],['interactive'=>true]);
        if ($test->getStatusCode()!==0) { throw new RuntimeException('Recuperación ficticia no completada.'); }
    }
    $accounts->deactivate($created['disabled']->id);
    $accounts->startRecovery($created['pending']->id,'INTERVENCION-NUEVA-NO-CONFIRMADA-H72');
    $key=fn()=>(string)Illuminate\Support\Str::uuid();$date=now('America/Lima')->toDateString();
    $input=fn()=>['idempotency_key'=>$key(),'description'=>'PRODUCTO FICTICIO H72','quantity'=>'1','unit_price'=>'100.00'];
    $invoice=function(string $scenario='accepted')use($owner,$input){
        $i=app(App\Invoices\InvoiceIssuer::class)->issue($owner->id,$input());
        if($scenario!=='pending'){app(App\Invoices\SimulatedTransport::class)->dispatch($i->id,$scenario);}
        return $i->fresh();
    };
    $noteInput=fn($id,$operation)=>['idempotency_key'=>$key(),'original_id'=>$id,'operation'=>$operation,
        'reason'=>'MOTIVO NUEVO FICTICIO H72','emission_date'=>$date,'original_granted_test'=>'1']+($operation==='ND02'?['increment_unit_price'=>'10.00']:[]);
    $stage='notas-factura';
    foreach (['NC01','ND02'] as $operation) {
        $i=$invoice();$n=app(App\Notes\Preparation::class)->issue($owner->id,$noteInput($i->id,$operation));
        app(App\Notes\Transport::class)->dispatch($n->id,$operation==='NC01'?'accepted':'rejected');
    }
    $invoice('uncertain');$invoice('rejected');$invoice('pending');
    $boleta=function()use($owner,$input,$date,$key){
        $b=app(App\Boletas\Preparation::class)->issue($owner->id,$input()+['emission_date'=>$date]);
        $r=app(App\Boletas\Preparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'members'=>[$b->id]]);
        app(App\Boletas\SummaryTransport::class)->send($r->id);app(App\Boletas\SummaryTransport::class)->poll($r->id);
        return $b->fresh();
    };
    $stage='notas-boleta';$notes=[];
    foreach (['NC01','ND02'] as $operation) {
        $b=$boleta();$n=app(App\BoletaNotes\Preparation::class)->issue($owner->id,$noteInput($b->id,$operation));$notes[]=$n->id;
    }
    $rc=app(App\BoletaNotes\SummaryPreparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'members'=>$notes]);
    app(App\BoletaNotes\SummaryTransport::class)->send($rc->id);app(App\BoletaNotes\SummaryTransport::class)->poll($rc->id,'98');
    $stage='bajas';
    foreach (['invoice','boleta','lost_ticket'] as $kind) {
        $document=$kind==='invoice'?$invoice():$boleta();$type=$kind==='invoice'?'invoice':'boleta';
        app(App\Voids\Grant::class)->record($owner->id,['kind'=>$type,'id'=>$document->id,'state'=>'not_granted',
            'provenance'=>'DECLARACION EXPLICITA NUEVA DE ENSAYO H72','idempotency_key'=>$key()]);
        $batch=app(App\Voids\Preparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'reason'=>'BAJA FICTICIA H72',
            'members'=>[['kind'=>$type,'id'=>$document->id]]]);
        app(App\Voids\Transport::class)->send($batch->id,$kind==='lost_ticket'?'lost_ticket':'accepted');
        if($kind!=='lost_ticket'){app(App\Voids\Transport::class)->poll($batch->id,'0');}
    }
    echo json_encode(['fixture_clock'=>'2026-11-02T12:00:00-05:00','accounts'=>array_map(fn($u)=>['id'=>$u->id,'email'=>$u->email],$created)]);
} catch (Throwable $e) {
    fwrite(STDERR,'Fixture H72 no confirmada: '.$stage.' ('.get_class($e).'). Conservar el ensayo; no reconstruir ni limpiar. '.PHP_EOL);exit(1);
}
