<?php
declare(strict_types=1);
namespace App\Voids;

use App\Models\{Invoice, InvoiceNote, Boleta, BoletaNote, DailySummary, NoteSummary};
use App\Notes\Original;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class Source
{
    public const MODELS = ['invoice' => Invoice::class, 'invoice_note' => InvoiceNote::class,
        'boleta' => Boleta::class, 'boleta_note' => BoletaNote::class];

    public function document(int $user, string $kind, string $id, bool $lock = false): mixed
    {
        if (!isset(self::MODELS[$kind])) { throw new LogicException('Tipo fuera de H5-C1.'); }
        $model = self::MODELS[$kind];
        $record = $model::query()->where('user_id', $user)->findOrFail($id);
        if ($lock) {
            // El mismo padre que bloquean NC/ND, antes de bloquear la nota.
            if (str_ends_with($kind, '_note')) {
                $parent = $kind === 'invoice_note' ? Invoice::class : Boleta::class;
                $parent::query()->where('user_id', $user)->lockForUpdate()->findOrFail($record->original_id);
            }
            $record = $model::query()->where('user_id', $user)->lockForUpdate()->findOrFail($id);
        }
        return $record;
    }

    public function capture(int $user, string $kind, string $id): array
    {
        $r = $this->document($user, $kind, $id); $s = $r->snapshot;
        $raw = $r->attributesToArray(); unset($raw['updated_at'], $raw['idempotency_key']);
        $original = null;
        switch ($kind) {
            case 'invoice':
                app(\App\Notes\Original::class)->eligible($r);
                $receipt=DB::table('simulation_receipts')->where('invoice_id',$id)->first();
                if (!$receipt || Original::hash(json_decode($receipt->response,true,flags:JSON_THROW_ON_ERROR))!==Original::hash($r->simulated_response)) { $this->fail(); }
                $acceptance = $this->event('invoice_transport_events', 'invoice_id', $id, ['result','recovered'], null,
                    DB::table('invoice_outbox')->where('invoice_id', $id)->value('attempt_token'));
                $emission = Carbon::parse($s['emitted_at'])->timezone('America/Lima')->toDateString();
                $response = $r->simulated_response;
                break;
            case 'invoice_note':
                app(\App\Notes\Evidence::class)->verify($r); app(\App\Notes\Original::class)->verify($r);
                $response = $r->result;
                if ($r->state !== 'sim_accepted' || !is_array($response)
                    || Original::hash($response) !== Original::hash(app(\App\Notes\LocalSimulation::class)->response($r, 'accepted'))
                    || DB::table('h5_outbox')->where('note_id', $id)->value('status') !== 'completed') { $this->fail(); }
                $acceptance = $this->event('h5_events','note_id',$id,['result','recovered_local_fake'],$response,
                    DB::table('h5_outbox')->where('note_id',$id)->value('token'));
                $emission = $r->emission_date;
                $original = ['kind'=>'invoice','id'=>$r->original_id,'type'=>'01',
                    'number'=>$s['original']['series'].'-'.$s['original']['number'],'hash'=>$r->original_hash];
                $this->unreserved('invoice', $r->original_id);
                break;
            case 'boleta':
                app(\App\BoletaNotes\Original::class)->eligible($r);
                $rc = DailySummary::query()->findOrFail(DB::table('h4_members')->where('boleta_id',$id)->value('summary_id'));
                $response = $rc->result['response'];
                $acceptance = $this->event('h4_events','summary_id',$rc->id,['result_saved'],$response,
                    DB::table('h4_outbox')->where('summary_id',$rc->id)->value('token'));
                $acceptance['container_id'] = $rc->id;
                $emission = $r->emission_date;
                break;
            case 'boleta_note':
                app(\App\BoletaNotes\SummaryPreparation::class)->verifyNote($r);
                $rc = NoteSummary::query()->findOrFail(DB::table('h5b_members')->where('note_id',$id)->value('summary_id'));
                app(\App\BoletaNotes\Evidence::class)->verify($rc->id,$rc->artifacts);
                $response = $rc->result['response'] ?? [];
                $receipt = DB::table('h5b_simulation_receipts')->where('summary_id',$rc->id)->first();
                if (!$receipt || $receipt->ticket !== $rc->ticket || $receipt->request_hash !== $rc->artifacts['zip']['sha256']
                    || $rc->user_id !== $user || $rc->state !== 'sim_accepted' || ($rc->result['official_cdr'] ?? null) !== false
                    || ($rc->result['simulated'] ?? null) !== true || ($rc->result['outcome'] ?? null) !== 'accepted'
                    || $rc->reference_date !== $r->emission_date || !is_string($rc->ticket)
                    || DB::table('h5b_outbox')->where('summary_id',$rc->id)->value('status') !== 'completed'
                    || Original::hash($response) !== Original::hash(app(\App\BoletaNotes\LocalSummarySimulator::class)->poll($rc->ticket,'0'))
                    || DB::table('h5b_members')->where('summary_id',$rc->id)->orderBy('position')->pluck('note_id')->all() !== array_column($rc->snapshot['members'],'id')) { $this->fail(); }
                $matches = array_values(array_filter($rc->snapshot['members'],fn ($m)=>$m['id']===$id));
                if (count($matches)!==1 || Original::hash($matches[0])!==Original::hash(app(\App\BoletaNotes\SummaryPreparation::class)->member($r))) { $this->fail(); }
                $acceptance = $this->event('h5b_events','summary_id',$rc->id,['result_saved'],$response,
                    DB::table('h5b_outbox')->where('summary_id',$rc->id)->value('token'));
                $acceptance['container_id'] = $rc->id;
                $emission = $r->emission_date;
                $original = ['kind'=>'boleta','id'=>$r->original_id,'type'=>'03',
                    'number'=>$s['original']['snapshot']['series'].'-'.$s['original']['number'],'hash'=>$r->original_hash];
                $this->unreserved('boleta', $r->original_id);
                break;
        }
        $generated = Carbon::parse($r->created_at)->timezone('America/Lima')->toDateString();
        $received = Carbon::parse($acceptance['received_at'])->timezone('America/Lima');
        if ($received->isFuture() || $received->toDateString() < max($emission, $generated)
            || $emission > now('America/Lima')->toDateString() || $generated > now('America/Lima')->toDateString()) { $this->fail(); }
        return ['kind'=>$kind,'id'=>$id,'document_type'=>$kind==='invoice'?'01':($kind==='boleta'?'03':$r->document_type),
            'series'=>$r->series ?? $s['series'],'number'=>$r->number,'emission_date'=>$emission,'generation_date'=>$generated,
            'source_hash'=>Original::hash($raw),'original'=>$original,'acceptance'=>$acceptance+['response_hash'=>Original::hash($response)],
            'subtotal_cents'=>$s['subtotal_cents'],'tax_cents'=>$s['tax_cents'],'total_cents'=>$s['total_cents']];
    }

    public function unreserved(string $kind, string $id): void
    {
        if (DB::table('h5c1_members')->where('kind',$kind)->where('document_id',$id)->exists()) {
            throw new ConflictHttpException('Documento reservado por baja; sin liberacion ni reemision H5-C2.');
        }
    }

    public function noDependants(string $kind, string $id): void
    {
        $model = match ($kind) { 'invoice'=>InvoiceNote::class,'boleta'=>BoletaNote::class, default=>null };
        if ($model && $model::query()->where('original_id',$id)->exists()) {
            throw new ConflictHttpException('Original con nota previa: otorgamiento/dependencia incompatible con baja.');
        }
    }

    private function event(string $table, string $column, string $id, array $kinds, ?array $response, ?string $token): array
    {
        $event = DB::table($table)->where($column,$id)->whereIn('kind',$kinds)->orderBy('id')->first();
        $tokenColumn = $table==='invoice_transport_events'?'attempt_token':'token';
        if (!$event || $event->attention || !$token || $event->$tokenColumn !== $token
            || ($response !== null && Original::hash(json_decode($event->payload ?? '{}',true,flags:JSON_THROW_ON_ERROR)) !== Original::hash($response))) { $this->fail(); }
        return ['table'=>$table,'event_id'=>$event->id,'received_at'=>Carbon::parse($event->created_at)->utc()->toIso8601String(),
            'event_hash'=>Original::hash((array)$event),'simulated'=>true,'official_cdr'=>false];
    }

    private function fail(): never { throw new ConflictHttpException('Aceptacion simulada/fechas no acreditadas y correlacionadas; investigar sin completar historicos.'); }
}
