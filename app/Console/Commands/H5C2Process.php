<?php
namespace App\Console\Commands;

use App\Corrections\{Family,Fixture,CommunicationCorrection,InvoiceReissue,Policy};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class H5C2Process extends Command
{
    protected $signature='h5c2:process {action} {family?} {id?} {--user=} {--input=} {--scenario=}';
    protected $description='Solo C2 ficticio local: fixture/prepare/status/send/poll/recover/inspect. Sin fusion ni operaciones fiscales reales.';
    public function handle(): int
    {
        Family::guard();$action=$this->argument('action');$family=$this->argument('family');$id=$this->argument('id');
        if($action==='inspect'){
            $issues=[];$checked=0;
            // No clasificar como huerfano un artefacto aun en preparacion: mismos contadores, NOWAIT.
            DB::transaction(function()use(&$issues,&$checked){
                DB::table('h4_counters')->where('series','B000')->lock('FOR UPDATE NOWAIT')->sole();
                DB::table('h4_counters')->where('series','RC')->lock('FOR UPDATE NOWAIT')->sole();
                DB::table('h5c1_counters')->where('route','RA')->lock('FOR UPDATE NOWAIT')->sole();
                DB::table('invoice_series')->where('series','F000')->lock('FOR UPDATE NOWAIT')->sole();
                foreach(Family::LINKS as $f=>$table){
                    foreach(DB::table($table)->cursor() as $l){
                        $checked++;try{app(Policy::class)->verifyLinked($f,Family::record($f,$l->user_id,$l->successor_id));}
                        catch(\Throwable){$issues[]=['family'=>$f,'id'=>$l->successor_id,'issue'=>'causal_evidence_changed'];}
                    }
                    $cfg=match($f){'h4'=>'h4','h5b'=>'h5b','void'=>'h5c1','invoice'=>'h2'};
                    $root=rtrim(config($cfg.'.evidence_root'),'/');if(is_link($root)){throw new \RuntimeException('Raiz enlazada; detener inspeccion.');}
                    foreach(glob($root.'/*',GLOB_ONLYDIR)?:[] as $dir){
                        $model=Family::MODELS[$f];$key=basename($dir);
                        // h4/h5b comparten raiz de documentos fuente y comunicaciones.
                        $sourceExists=match($f){'h4'=>\App\Models\Boleta::query()->whereKey($key)->exists(),
                            'h5b'=>\App\Models\BoletaNote::query()->whereKey($key)->exists(),default=>false};
                        if(!$sourceExists && !$model::query()->whereKey($key)->exists()){$issues[]=['family'=>$f,'id'=>$key,'issue'=>'orphan_candidate'];}
                    }
                }
            });
            $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'read_only'=>true,'links_checked'=>$checked,'issues'=>$issues],JSON_THROW_ON_ERROR));return $issues?1:0;
        }
        if(!isset(Family::MODELS[$family])){throw new \RuntimeException('Familia C2 no permitida.');}
        if(in_array($action,['fixture','prepare'],true)){
            if(!ctype_digit((string)$this->option('user')) || (int)$this->option('user')<1){throw new \RuntimeException('Operador local explicito requerido.');}
            $input=json_decode($this->option('input')??'',true,32,JSON_THROW_ON_ERROR);if(!is_array($input)){throw new \RuntimeException('Objeto JSON requerido.');}
            $r=$action==='fixture'?app(Fixture::class)->prepare($family,(int)$this->option('user'),$input):($family==='invoice'
                ?app(InvoiceReissue::class)->prepare((int)$this->option('user'),$input):app(CommunicationCorrection::class)->prepare($family,(int)$this->option('user'),$input));
        }else{
            if(!Str::isUuid($id??'') || !in_array($action,['status','send','poll','recover'],true)){throw new \RuntimeException('Accion/identidad invalida.');}
            $r=(Family::MODELS[$family])::query()->findOrFail($id);
            if($action!=='status'){
                if($family==='invoice'){
                    if($action==='poll'){throw new \RuntimeException('Factura no usa getStatus de RA/RC.');}
                    $t=app(\App\Invoices\SimulatedTransport::class);
                    $r=$action==='send'?$t->dispatch($id,$this->option('scenario')?:'accepted'):$t->recover($id);
                }else{
                    $t=app(match($family){'h4'=>\App\Boletas\SummaryTransport::class,'h5b'=>\App\BoletaNotes\SummaryTransport::class,'void'=>\App\Voids\Transport::class});
                    $r=match($action){'send'=>$t->send($id,$this->option('scenario')?:'accepted'),'poll'=>$t->poll($id,$this->option('scenario')?:'0'),'recover'=>$t->recover($id)};
                }
            }
        }
        $effective=$r?Family::effective($family,$r):null;if($effective){app(Policy::class)->verifyLinked($family,$effective);}
        $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'id'=>$r?->id,'historical_state'=>$r?->state??$r?->transport_status,
            'effective_id'=>$effective?->id,'effective_state'=>$effective?->state??$effective?->transport_status,
            'effect'=>$family==='void'&&$effective?->state==='sim_accepted'?'voided_simulated':'not_confirmed'],JSON_THROW_ON_ERROR));return self::SUCCESS;
    }
}
