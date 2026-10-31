<?php

namespace App\Recipients;

use App\Models\{Invoice, Boleta, InvoiceNote, BoletaNote, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;

final class Access
{
    public const MODELS=['invoice'=>Invoice::class,'boleta'=>Boleta::class,'invoice_note'=>InvoiceNote::class,'boleta_note'=>BoletaNote::class];
    public const LABELS=['invoice'=>'Factura','boleta'=>'Boleta','invoice_note'=>'Nota de factura','boleta_note'=>'Nota de boleta'];

    public function document(int $owner, string $kind, string $id, bool $lock=false): Model
    {
        abort_unless(isset(self::MODELS[$kind]) && Str::isUuid($id),404);
        $model=self::MODELS[$kind];$q=$model::query()->where('user_id',$owner);
        return ($lock?$q->lockForUpdate():$q)->findOrFail($id);
    }

    public function verify(string $kind, Model $document): void
    {
        clearstatcache();
        foreach(['unsigned','xml','certificate','zip'] as $file) { $this->path($kind,$document,$file); }
    }

    public function path(string $kind, Model $d, string $file): string
    {
        return match($kind) {
            'invoice'=>app(\App\Invoices\InvoiceEvidence::class)->verifiedPath($d->id,$file,$d->artifacts),
            'boleta'=>app(\App\Boletas\Artifacts::class)->path($d->id,$file,$d->artifacts),
            'invoice_note'=>app(\App\Notes\Evidence::class)->path($d->id,$file,$d->artifacts),
            'boleta_note'=>app(\App\BoletaNotes\Evidence::class)->path($d->id,$file,$d->artifacts),
        };
    }

    public function grant(int $owner, int $recipient, string $kind, string $id): object
    {
        $this->operator($owner);
        [$access,$created]=DB::transaction(function() use($owner,$recipient,$kind,$id) {
            $d=$this->document($owner,$kind,$id,true);$this->verify($kind,$d);
            User::query()->whereKey($recipient)->where('role','RECEPTOR')->where('is_active',true)->lockForUpdate()->firstOrFail();
            $q=DB::table('recipient_document_access')->where('owner_user_id',$owner)->where('recipient_user_id',$recipient)
                ->where('kind',$kind)->where('document_id',$d->id)->whereNull('revoked_at');
            if($prior=$q->first()) { return [$prior,false]; }
            $key=(string)Str::uuid();
            DB::table('recipient_document_access')->insert(['id'=>$key,'owner_user_id'=>$owner,'recipient_user_id'=>$recipient,
                'kind'=>$kind,'document_id'=>$d->id,'created_at'=>now('UTC')]);
            return [DB::table('recipient_document_access')->where('id',$key)->sole(),true];
        });
        if($created) { $this->audit('granted',$owner,$access); }
        return $access;
    }

    public function revoke(int $owner, string $id): void
    {
        $this->operator($owner);abort_unless(Str::isUuid($id),404);
        [$access,$changed]=DB::transaction(function() use($owner,$id) {
            $row=DB::table('recipient_document_access')->where('owner_user_id',$owner)->where('id',$id)->firstOrFail();
            $this->document($owner,$row->kind,$row->document_id,true);
            $row=DB::table('recipient_document_access')->where('owner_user_id',$owner)->where('id',$id)->lockForUpdate()->sole();
            if($row->revoked_at!==null) { return [$row,false]; }
            DB::table('recipient_document_access')->where('id',$id)->update(['revoked_at'=>now('UTC')]);
            return [$row,true];
        });
        if($changed) { $this->audit('revoked',$owner,$access); }
    }

    public function assigned(int $recipient, string $id): array
    {
        abort_unless(Str::isUuid($id),404);
        $row=DB::table('recipient_document_access')->where('id',$id)->where('recipient_user_id',$recipient)->whereNull('revoked_at')->first();
        abort_unless($row,404);
        $d=$this->document($row->owner_user_id,$row->kind,$row->document_id);$this->verify($row->kind,$d);
        return [$row,$d];
    }

    public function present(string $kind, Model $d): array
    {
        $s=$d->snapshot;$original=$s['original'] ?? null;
        $date=$kind==='invoice'?Carbon::parse($s['emitted_at'])->timezone('America/Lima')->toDateString():$d->emission_date;
        $xml=file_get_contents($this->path($kind,$d,'xml'));
        if(stripos($xml,'<!DOCTYPE')!==false) { throw new \RuntimeException('XML no permitido.'); }
        $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        try { if(!$dom->loadXML($xml,LIBXML_NONET)) { throw new \RuntimeException('XML no legible.'); } }
        finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
        $xp=new \DOMXPath($dom);$xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $party=function(string $name) use($xp): array {
            $prefix='/*/cac:Accounting'.$name.'Party/cac:Party';
            return ['name'=>$xp->evaluate('string('.$prefix.'/cac:PartyLegalEntity/cbc:RegistrationName)'),
                'id'=>$xp->evaluate('string('.$prefix.'/cac:PartyIdentification/cbc:ID)')];
        };
        return ['label'=>self::LABELS[$kind],'type'=>$kind==='invoice'?'01':($kind==='boleta'?'03':$d->document_type),
            'number'=>$s['series'].'-'.$d->number,'date'=>$date,'currency'=>$xp->evaluate('string(/*/cbc:DocumentCurrencyCode)'),
            'issuer'=>$party('Supplier'),'receiver'=>$party('Customer'),
            'description'=>$s['description'],'quantity_milli'=>$s['quantity_milli'],'unit_price_cents'=>$s['unit_price_cents'],
            'subtotal_cents'=>$s['subtotal_cents'],'tax_cents'=>$s['tax_cents'],'total_cents'=>$s['total_cents'],
            'reason'=>$s['reason'] ?? null,'reason_code'=>$s['reason_code'] ?? null,
            'original'=>$original?['number'=>$original['snapshot']['series'].'-'.$original['number']]:null];
    }

    public function audit(string $event, int $actor, object $access): void
    {
        Log::notice('recipient_access_'.$event,['actor'=>$actor,'access_id'=>$access->id,'recipient'=>$access->recipient_user_id,
            'owner'=>$access->owner_user_id,'kind'=>$access->kind,'document_id'=>$access->document_id]);
    }

    private function operator(int $id): void
    {
        abort_unless(User::query()->whereKey($id)->where('is_active',true)->whereIn('role',['ADMIN','OPERADOR'])->exists(),403);
    }
}
