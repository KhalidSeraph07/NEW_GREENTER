<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Invoices\{InvoiceInput,InvoiceIssuer};
use App\Models\Invoice;
use App\Notes\Original;
use Illuminate\Support\Facades\{DB,Event,Validator};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InvoiceReissue
{
    public function prepare(int $user,array $input): Invoice
    {
        Family::guard();if(DB::transactionLevel()!==0){throw new \LogicException('Reemision sin transaccion externa.');}
        if(array_diff(array_keys($input),['original_id','idempotency_key','reason','operation_date_test','operation_subsists_test'])){
            throw ValidationException::withMessages(['reissue'=>'No se editan datos fiscales/economicos para subsanar el ZIP.']);}
        $v=Validator::make($input,['original_id'=>'required|uuid','idempotency_key'=>'required|uuid','reason'=>['required','string','min:10','max:250','not_regex:/[\x00-\x1F]/u'],
            'operation_date_test'=>'required|date_format:Y-m-d','operation_subsists_test'=>'required|accepted'])->validate();
        $v['original_id']=strtolower($v['original_id']);$v['idempotency_key']=strtolower($v['idempotency_key']);
        $hash=Original::hash(['original_id'=>$v['original_id'],'reason'=>$v['reason'],'operation_date'=>$v['operation_date_test'],'operation_subsists_test'=>true]);
        $result=DB::transaction(function()use($user,$v,$hash){
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['h5c2-invoice:'.$user.':'.$v['idempotency_key']]);
            $parent=Family::record('invoice',$user,$v['original_id'],true);
            DB::table('invoice_series')->where('series','F000')->lockForUpdate()->sole();
            $prior=Invoice::query()->where('user_id',$user)->where('idempotency_key',$v['idempotency_key'])->first();
            if($prior){
                $l=DB::table(Family::LINKS['invoice'])->where('successor_id',$prior->id)->first();
                if(!$l || $l->original_id!==$parent->id || $l->request_hash!==$hash){throw new ConflictHttpException('Clave de otro intento/flujo.');}
                app(Policy::class)->verifyLinked('invoice',$prior);return $prior;
            }
            if(DB::table(Family::LINKS['invoice'])->where('original_id',$parent->id)->orWhere('successor_id',$parent->id)->exists()){
                throw new ConflictHttpException('Una sola reemision; sin cadenas.');}
            $proof=app(Rejection::class)->capture('invoice',$parent);app(Policy::class)->window('invoice',$parent,$v['operation_date_test']);
            $s=$parent->snapshot;
            $child=app(InvoiceIssuer::class)->issue($user,['idempotency_key'=>$v['idempotency_key'],'description'=>$s['description'],
                'quantity'=>InvoiceInput::decimal($s['quantity_milli'],3),'unit_price'=>InvoiceInput::decimal($s['unit_price_cents'])]);
            PackingDefect::assertRepaired('invoice',$child);
            // El hash propio de InvoiceIssuer se conserva: el vínculo contiene el hash de la solicitud causal C2.
            DB::table(Family::LINKS['invoice'])->insert(['original_id'=>$parent->id,'successor_id'=>$child->id,'user_id'=>$user,'idempotency_key'=>$v['idempotency_key'],
                'request_hash'=>$hash,'reason'=>$v['reason'],'operation_date'=>$v['operation_date_test'],'prepared_on'=>now('America/Lima')->toDateString(),
                'proof'=>json_encode($proof,JSON_THROW_ON_ERROR),'created_at'=>now('UTC')]);
            Event::dispatch('h5c2.prepared',['invoice',$child->id]);return $child;
        });
        Event::dispatch('h5c2.committed',['invoice',$result->id]);return $result;
    }
}
