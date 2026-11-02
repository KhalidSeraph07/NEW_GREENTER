<?php
// Solo prepara hechos nuevos antes del mantenimiento; no modifica ni reconstruye históricos.
require __DIR__.'/../vendor/autoload.php';
try {
    $root=dirname(__DIR__);
    $marker=json_decode(file_get_contents($root.'/.h74a-fixture.json'),true,flags:JSON_THROW_ON_ERROR);
    if (!preg_match('/\Aemisor-h74a-[a-f0-9]{10}\z/',$marker['project']??'') || is_file($root.'/storage/framework/h72-state.json')) {
        throw new RuntimeException('Solo instalación nueva H74A, nunca destino restaurado.');
    }
    $app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (!app()->environment('local') || config('h2.mode')!=='fictitious' || config('h2.issuer_ruc')!=='00000000000'
        || config('database.connections.pgsql.host')!=='postgres' || config('database.connections.pgsql.database')!=='emisor_h1'
        || App\Models\User::query()->count()!==1 || App\Models\Invoice::query()->exists()) {
        throw new RuntimeException('La fixture requiere base nueva ficticia.');
    }
    $v=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
    $owner=App\Models\User::query()->sole();$accounts=[];
    foreach (['other'=>'OPERADOR','admin'=>'ADMIN','receiver'=>'RECEPTOR'] as $key=>$role) {
        $u=app(App\Accounts\LocalAccounts::class)->create(['name'=>'CUENTA FICTICIA H74A '.$key,
            'email'=>$key.'.'.Illuminate\Support\Str::uuid().'@example.invalid','role'=>$role,
            'password'=>$v[$key],'password_confirmation'=>$v[$key]]);
        $accounts[$key]=['id'=>$u->id,'email'=>$u->email];
    }
    $key=fn()=>(string)Illuminate\Support\Str::uuid();$date=now('America/Lima')->toDateString();$cases=[];
    $input=fn()=>['idempotency_key'=>$key(),'description'=>'OPERACION FICTICIA NUEVA H74A','quantity'=>'1','unit_price'=>'10.00'];
    $invoice=function($scenario='accepted')use($owner,$input){
        $i=app(App\Invoices\InvoiceIssuer::class)->issue($owner->id,$input());
        if($scenario!=='pending'){app(App\Invoices\SimulatedTransport::class)->dispatch($i->id,$scenario);}
        return $i->fresh();
    };
    foreach (['pending','uncertain','accepted','rejected'] as $state) {$cases['invoice_'.$state]=$invoice($state)->id;}
    foreach (['NC01'=>'pending','ND02'=>'rejected'] as $operation=>$state) {
        $i=$invoice();$n=app(App\Notes\Preparation::class)->issue($owner->id,['idempotency_key'=>$key(),'original_id'=>$i->id,
            'operation'=>$operation,'reason'=>'MOTIVO FICTICIO H74A','emission_date'=>$date,'original_granted_test'=>'1']
            +($operation==='ND02'?['increment_unit_price'=>'1.00']:[]));
        if($state!=='pending'){app(App\Notes\Transport::class)->dispatch($n->id,$state);}
        $cases['invoice_note_'.$state]=$n->id;
    }
    $receipt=function()use($owner,$input,$date,$key){
        $b=app(App\Boletas\Preparation::class)->issue($owner->id,$input()+['emission_date'=>$date]);
        $r=app(App\Boletas\Preparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'members'=>[$b->id]]);
        app(App\Boletas\SummaryTransport::class)->send($r->id);app(App\Boletas\SummaryTransport::class)->poll($r->id,'0');
        return $b->fresh();
    };
    $b=$receipt();$cases['boleta_original']=$b->id;
    $n=app(App\BoletaNotes\Preparation::class)->issue($owner->id,['idempotency_key'=>$key(),'original_id'=>$b->id,'operation'=>'ND02',
        'reason'=>'MOTIVO FICTICIO H74A','emission_date'=>$date,'original_granted_test'=>'1','increment_unit_price'=>'1.00']);
    $r=app(App\BoletaNotes\SummaryPreparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'members'=>[$n->id]]);
    app(App\BoletaNotes\SummaryTransport::class)->send($r->id);app(App\BoletaNotes\SummaryTransport::class)->poll($r->id,'98');
    $cases['note_rc_ticket_waiting']=$r->id;$cases['boleta_note']=$n->id;
    foreach (['invoice','boleta'] as $kind) {
        $d=$kind==='invoice'?$invoice():$receipt();
        app(App\Voids\Grant::class)->record($owner->id,['kind'=>$kind,'id'=>$d->id,'state'=>'not_granted',
            'provenance'=>'DECLARACION FICTICIA EXPLICITA H74A','idempotency_key'=>$key()]);
        $r=app(App\Voids\Preparation::class)->freeze($owner->id,['idempotency_key'=>$key(),'reason'=>'BAJA FICTICIA H74A',
            'members'=>[['kind'=>$kind,'id'=>$d->id]]]);
        app(App\Voids\Transport::class)->send($r->id,$kind==='invoice'?'lost_ticket':'accepted');
        if($kind==='boleta'){app(App\Voids\Transport::class)->poll($r->id,'0');}
        $cases[$kind==='invoice'?'ra_uncertain_without_ticket':'rc3_terminal']=$r->id;
    }
    $access=app(App\Recipients\Access::class)->grant($owner->id,$accounts['receiver']['id'],'invoice',$cases['invoice_accepted']);
    echo json_encode(['created_at'=>now('UTC')->toIso8601String(),'owner'=>['id'=>$owner->id,'email'=>$owner->email],
        'accounts'=>$accounts,'cases'=>$cases,'recipient_access'=>$access->id],JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR,'Fixture H74A no confirmada ('.get_class($e).'); conservar el ensayo sin limpiar.'.PHP_EOL);exit(1);
}
