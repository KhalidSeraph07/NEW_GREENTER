<?php
declare(strict_types=1);
namespace App\Corrections;

use App\Models\{DailySummary, NoteSummary, VoidBatch, Invoice};
use Illuminate\Support\Facades\DB;
use LogicException;

// Conjunto cerrado de las cuatro relaciones autorizadas; no registro de flujos extensible.
final class Family
{
    public const MODELS=['h4'=>DailySummary::class,'h5b'=>NoteSummary::class,'void'=>VoidBatch::class,'invoice'=>Invoice::class];
    public const TABLES=['h4'=>'h4_summaries','h5b'=>'h5b_summaries','void'=>'h5c1_batches','invoice'=>'invoices'];
    public const LINKS=['h4'=>'h5c2_h4_corrections','h5b'=>'h5c2_h5b_corrections','void'=>'h5c2_void_corrections','invoice'=>'h5c2_invoice_reissues'];
    public const OUTBOX=['h4'=>'h4_outbox','h5b'=>'h5b_outbox','void'=>'h5c1_outbox','invoice'=>'invoice_outbox'];
    public const COLUMN=['h4'=>'summary_id','h5b'=>'summary_id','void'=>'batch_id','invoice'=>'invoice_id'];

    public static function guard(): void
    {
        if (!app()->environment(['local','testing']) || config('h2.mode')!=='fictitious' || config('h4.mode')!=='fictitious'
            || config('h5b.mode')!=='fictitious' || config('h5c1.mode')!=='fictitious') { throw new LogicException('Solo H5-C2 ficticio local.'); }
    }
    public static function record(string $family,int $user,string $id,bool $lock=false): mixed
    {
        if (!isset(self::MODELS[$family])) { throw new LogicException('Familia C2 no autorizada.'); }
        $q=(self::MODELS[$family])::query()->where('user_id',$user);
        if($lock){$q->lockForUpdate();}return $q->findOrFail($id);
    }
    public static function path(string $family, mixed $r,string $kind): string
    {
        return match($family){
            'invoice'=>app(\App\Invoices\InvoiceEvidence::class)->verifiedPath($r->id,$kind,$r->artifacts),
            'h4'=>app(\App\Boletas\Artifacts::class)->path($r->id,$kind,$r->artifacts),
            'h5b'=>app(\App\BoletaNotes\Evidence::class)->path($r->id,$kind,$r->artifacts),
            'void'=>app(\App\Voids\Evidence::class)->path($r->id,$kind,$r->artifacts),
            default=>throw new LogicException('Familia de artefactos no permitida.')};
    }
    public static function verifyFiles(string $family,mixed $r): void {foreach(['unsigned','xml','zip','certificate'] as $k){self::path($family,$r,$k);}}
    public static function anchor(string $family,string $id): string
    {
        return DB::table(self::LINKS[$family])->where('successor_id',$id)->value('original_id') ?? $id;
    }
    public static function effective(string $family,mixed $original): mixed
    {
        $id=DB::table(self::LINKS[$family])->where('original_id',$original->id)->value('successor_id');
        if(!$id){return $original;}
        $record=self::record($family,$original->user_id,$id);
        app(Policy::class)->verifyLinked($family,$record);
        return $record;
    }
    public static function expectedEntry(string $family,mixed $r): string {return $family==='invoice'?'invoice.xml':$r->name.'.xml';}
}
