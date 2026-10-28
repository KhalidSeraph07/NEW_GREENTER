<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Notes\Original;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Policy
{
    public function window(string $family,mixed $parent,?string $operationDate=null): void
    {
        $today=now('America/Lima')->toDateString();
        if($today<'2026-01-01'||$today>='2027-01-01'){throw ValidationException::withMessages(['window'=>'Ensayo C2 2026; revisar vigencia antes de preparar/enviar.']);}
        if($family==='void'){foreach($parent->snapshot['members'] as $m){app(\App\Voids\Window::class)->check($m);}return;}
        $date=$family==='invoice'?Carbon::parse($parent->snapshot['emitted_at'])->timezone('America/Lima')->toDateString():$parent->reference_date;
        if($family==='invoice' && $operationDate!==$date){throw ValidationException::withMessages(['operation_date_test'=>'Recorte C2: fecha de operacion ficticia explicita igual a emision original.']);}
        if($date>$today || $today>Carbon::parse($date)->addDays($family==='invoice'?3:7)->toDateString()){
            throw ValidationException::withMessages(['window'=>'Ventana original del ensayo vencida; una sucesora no la reinicia.']);}
    }
    public function verifyLinked(string $family,mixed $record): void
    {
        $link=DB::table(Family::LINKS[$family])->where('successor_id',$record->id)->first();
        if(!$link){return;}
        $parent=Family::record($family,$record->user_id,$link->original_id);
        $proof=app(Rejection::class)->capture($family,$parent);
        if(Original::hash($proof)!==Original::hash(json_decode($link->proof,true,flags:JSON_THROW_ON_ERROR))){throw new ConflictHttpException('Prueba causal C2 alterada.');}
        PackingDefect::assertRepaired($family,$record);
        if($family!=='invoice'){app(CommunicationCorrection::class)->sources($family,$record);}
    }
    public function beforeSend(string $family,mixed $record): void
    {
        $link=DB::table(Family::LINKS[$family])->where('successor_id',$record->id)->first();if(!$link){return;}
        $this->verifyLinked($family,$record);$this->window($family,Family::record($family,$record->user_id,$link->original_id),$link->operation_date);
    }
}
