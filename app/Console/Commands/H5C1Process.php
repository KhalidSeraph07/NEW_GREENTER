<?php
namespace App\Console\Commands;

use App\Models\VoidBatch;
use App\Voids\{Grant, Preparation, Transport};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class H5C1Process extends Command
{
    protected $signature='h5c1:process {action} {id?} {--user=} {--input=} {--scenario=}';
    protected $description='Bajas exclusivamente ficticias: grant/freeze JSON, send/poll/recover/status UUID, inspect sin borrado';

    public function handle(Grant $grant, Preparation $preparation, Transport $transport): int
    {
        if (config('h5c1.mode')!=='fictitious' || !app()->environment(['local','testing'])) { throw new \LogicException('Solo POC local ficticia.'); }
        $action=$this->argument('action'); $id=$this->argument('id');
        if (in_array($action,['grant','freeze'],true)) {
            if (!ctype_digit((string)$this->option('user')) || (int)$this->option('user')<1) { throw new \RuntimeException('Operador local explicito requerido.'); }
            // JSON literal; no lectura arbitraria de rutas ni credenciales.
            $input=json_decode($this->option('input') ?? '',true,32,JSON_THROW_ON_ERROR);
            if (!is_array($input)) { throw new \RuntimeException('Objeto JSON requerido.'); }
            $record=$action==='grant'?$grant->record((int)$this->option('user'),$input):$preparation->freeze((int)$this->option('user'),$input);
            $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'id'=>$record->id,'state'=>$record->state],JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }
        if ($action==='inspect') {
            $issues=[];
            foreach (VoidBatch::query()->cursor() as $b) {
                try {$preparation->verify($b);} catch (\Throwable) {$issues[]=['id'=>$b->id,'issue'=>'evidence_or_source_changed'];}
            }
            foreach (glob(rtrim(config('h5c1.evidence_root'),'/').'/*',GLOB_ONLYDIR) ?: [] as $dir) {
                if (!VoidBatch::query()->where('id',basename($dir))->exists()) {$issues[]=['id'=>basename($dir),'issue'=>'orphan_candidate'];}
            }
            foreach (DB::table('h5c1_events')->where('attention',true)->orderBy('id')->get(['batch_id','kind']) as $e) {$issues[]=['id'=>$e->batch_id,'issue'=>$e->kind];}
            $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'issues'=>$issues],JSON_THROW_ON_ERROR));
            return $issues?1:0;
        }
        if (!Str::isUuid($id ?? '') || !in_array($action,['send','poll','recover','status'],true)) {throw new \RuntimeException('Accion/UUID H5-C1 no permitido.');}
        $b=match($action){'send'=>$transport->send($id,$this->option('scenario') ?: 'accepted'),
            'poll'=>$transport->poll($id,$this->option('scenario') ?: '0'),'recover'=>$transport->recover($id),
            'status'=>VoidBatch::query()->findOrFail($id)};
        if ($action==='status') {$preparation->verify($b);}
        $this->line(json_encode(['simulated'=>true,'official_cdr'=>false,'id'=>$b?->id,'state'=>$b?->state,'ticket'=>$b?->ticket,
            'effect'=>$b?->state==='sim_accepted'?'voided_simulated':'not_confirmed',
            'members'=>$b?->snapshot['members'] ?? []],JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
