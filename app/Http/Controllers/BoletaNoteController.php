<?php

namespace App\Http\Controllers;

use App\BoletaNotes\{Evidence, Original, Preparation, SummaryPreparation, SummaryTransport};
use App\Models\{Boleta, BoletaNote, NoteSummary};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\{MessageBag, Str, ViewErrorBag};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BoletaNoteController
{
    public function index(Request $r) { return view('boleta-notes.index',$this->listing($r)); }

    private function listing(Request $r, array $forms=[]): array
    {
        $filters=Validator::make($r->query(),['operation'=>'nullable|in:NC01,ND02','original_number'=>'nullable|integer|min:1',
            'note_number'=>'nullable|integer|min:1','notes_date'=>'nullable|date_format:Y-m-d','rc_name'=>'nullable|string|max:80'])->validate();
        $form=$forms['note'] ?? $r->old('note_form',[]); $summaryForm=$forms['summary'] ?? $r->old('summary_form',[]);
        $operation=$form['operation'] ?? $filters['operation'] ?? 'NC01';
        $originals=Boleta::query()->where('user_id',$r->user()->id)->when($filters['original_number'] ?? null,fn($q,$v)=>$q->where('number',$v))
            ->orderByDesc('number')->simplePaginate(20,['*'],'originals_page')->withQueryString();
        $selected=is_string($form['original_id'] ?? null) && Str::isUuid($form['original_id']) ? $form['original_id'] : null;
        $options=Boleta::query()->where('user_id',$r->user()->id)->where('id',$selected)->whereNotIn('id',$originals->pluck('id'))->get()->concat($originals->getCollection());
        $reasons=[];
        foreach($options as $original) {
            try {
                app(Original::class)->eligible($original); app(\App\Voids\Grant::class)->assertNoteAllowed('boleta',$original->id);
                if(BoletaNote::query()->where('original_id',$original->id)->exists()) { throw new ConflictHttpException('Original reservado por una nota; solo admite replay de la misma operación.'); }
                $reasons[$original->id]=null;
            } catch(\Illuminate\Database\QueryException $e) { throw $e; }
            catch(ConflictHttpException $e) { $reasons[$original->id]=$e->getMessage(); }
            catch(\RuntimeException) { $reasons[$original->id]='Evidencia del original ausente o alterada; requiere revisión.'; }
        }
        $notes=BoletaNote::query()->where('user_id',$r->user()->id)
            ->when($filters['note_number'] ?? null,fn($q,$v)=>$q->where('number',$v))
            ->when($filters['notes_date'] ?? null,fn($q,$v)=>$q->where('emission_date',$v))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20,['*'],'notes_page')->withQueryString();
        $members=array_slice(array_filter((array)($summaryForm['members'] ?? []),fn($id)=>is_string($id)&&Str::isUuid($id)),0,100);
        $noteOptions=BoletaNote::query()->where('user_id',$r->user()->id)->whereIn('id',$members)->whereNotIn('id',$notes->pluck('id'))->get()->concat($notes->getCollection());
        $reserved=DB::table('h5b_members')->whereIn('note_id',$noteOptions->pluck('id'))->pluck('summary_id','note_id');
        $memberReasons=$noteOptions->mapWithKeys(fn($n)=>[$n->id=>isset($reserved[$n->id]) ? 'Nota reservada en un RC; no se libera tras rechazo o incertidumbre.'
            : (($n->emission_date<now('America/Lima')->subDays(7)->toDateString() || $n->emission_date>now('America/Lima')->toDateString()) ? 'Fuera de ventana D+7 del ensayo.' : null)]);
        $summaries=NoteSummary::query()->where('user_id',$r->user()->id)->when($filters['rc_name'] ?? null,fn($q,$v)=>$q->where('name',$v))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20,['*'],'rc_page')->withQueryString();
        return compact('form','operation','filters','originals','options','reasons','selected','notes','noteOptions','members','memberReasons','summaries','summaryForm');
    }

    public function issue(Request $r, Preparation $p)
    {
        try { $n=$p->issue($r->user()->id,$r->all()); }
        catch(ValidationException|\RuntimeException $e) { return $this->formError($r,'note',$e); }
        return redirect()->route('receipt-notes.show',['kind'=>'note','id'=>$n->id],303);
    }

    private function selection(Request $r): array
    {
        if(array_diff(array_keys($r->all()),['idempotency_key','members','_token'])) { throw ValidationException::withMessages(['summary'=>'Solo identidad y miembros explícitos.']); }
        $form=Validator::make($r->all(),['idempotency_key'=>'required|uuid','members'=>'required|array|min:1|max:100','members.*'=>'required|uuid|distinct'])->validate();
        $form['idempotency_key']=strtolower($form['idempotency_key']); $form['members']=array_map('strtolower',$form['members']); sort($form['members']);
        $notes=BoletaNote::query()->where('user_id',$r->user()->id)->whereIn('id',$form['members'])->orderBy('id')->get();
        if($notes->count()!==count($form['members']) || $notes->pluck('emission_date')->unique()->count()!==1) { throw ValidationException::withMessages(['members'=>'Notas propias de una misma fecha requeridas.']); }
        return [$form,$notes];
    }

    private function reviewHash(Request $r, array $form): string { return hash('sha256',json_encode([$r->user()->id,$form],JSON_THROW_ON_ERROR)); }

    public function review(Request $r, SummaryPreparation $p, Evidence $files)
    {
        try {
            [$form,$notes]=$this->selection($r);
            $existing=NoteSummary::query()->where('user_id',$r->user()->id)->where('idempotency_key',$form['idempotency_key'])->first();
            foreach($notes as $n) { $p->verifyNote($n); }
            $preview=$notes->map(fn($n)=>$p->member($n))->all();
            if($existing) {
                if(!hash_equals($existing->payload_hash,hash('sha256',json_encode($form['members'],JSON_THROW_ON_ERROR)))) { throw new ConflictHttpException('Clave reutilizada con otros miembros.'); }
                $files->verify($existing->id,$existing->artifacts);
                if(\App\Notes\Original::hash($preview)!==\App\Notes\Original::hash($existing->snapshot['members'])) { throw new ConflictHttpException('Miembros diferentes del RC conservado.'); }
            } else {
                if(DB::table('h5b_members')->whereIn('note_id',$form['members'])->exists()) { throw new ConflictHttpException('Miembro ya reservado; conserva el RC anterior.'); }
                if($notes->first()->emission_date>now('America/Lima')->toDateString() || $notes->first()->emission_date<now('America/Lima')->subDays(7)->toDateString()) { throw ValidationException::withMessages(['members'=>'Fuera de ventana D+7 del ensayo.']); }
            }
            // Solo huella en sesión. H5-B revalida la selección dentro de su transacción al congelar.
            $r->session()->put('note_summary_reviews.'.$form['idempotency_key'],$this->reviewHash($r,$form));
            return view('boleta-notes.review',compact('form','notes','preview','existing'));
        } catch(ValidationException|\RuntimeException $e) { return $this->formError($r,'summary',$e); }
    }

    public function editReview(Request $r)
    {
        try { [$form]=$this->selection($r); }
        catch(ValidationException $e) { return $this->formError($r,'summary',$e); }
        return view('boleta-notes.index',$this->listing($r,['summary'=>$form]));
    }

    public function freeze(Request $r, SummaryPreparation $p)
    {
        try {
            [$form]=$this->selection($r);
            if(!hash_equals($r->session()->get('note_summary_reviews.'.$form['idempotency_key'],''),$this->reviewHash($r,$form))) { throw new ConflictHttpException('Revisa esta operación antes de congelar el RC de notas.'); }
            $s=$p->freeze($r->user()->id,$r->all());
        } catch(ValidationException|\RuntimeException $e) { return $this->formError($r,'summary',$e); }
        return redirect()->route('receipt-notes.show',['kind'=>'rc','id'=>$s->id],303);
    }

    private function formError(Request $r, string $kind, \Throwable $e)
    {
        if($e instanceof \Illuminate\Database\QueryException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) { throw $e; }
        if($r->expectsJson()) {
            if($e instanceof ValidationException || $e instanceof ConflictHttpException) { throw $e; }
            abort(409,'Evidencia ausente o alterada; operación detenida.');
        }
        $form=$r->only($kind==='note' ? ['idempotency_key','original_id','operation','reason','emission_date','original_granted_test','increment_unit_price'] : ['idempotency_key','members']);
        foreach($form as $field=>$value) { if($field!=='members' && !is_string($value)) { $form[$field]=''; } }
        if($e instanceof ValidationException) { return redirect()->route('receipt-notes.index',[],303)->withInput([$kind.'_form'=>$form])->withErrors($e->errors()); }
        $message=$e instanceof ConflictHttpException ? $e->getMessage() : 'Evidencia ausente o alterada; operación detenida.';
        return response()->view('boleta-notes.index',$this->listing($r,[$kind=>$form])+['errors'=>(new ViewErrorBag())->put('default',new MessageBag([$kind=>$message]))],409);
    }

    public function show(Request $r, string $kind, string $id)
    {
        $record=$this->owned($r,$kind,$id); $rc=$kind==='rc'?$record:null; $job=null; $actions=[];
        $member=$kind==='note'?DB::table('h5b_members')->where('note_id',$id)->first():null;
        if($member) { $root=\App\Corrections\Family::record('h5b',$r->user()->id,$member->summary_id); $effective=\App\Corrections\Family::effective('h5b',$root); $member->summary_id=$effective->id; $member->state=$effective->state; }
        if($rc) { $job=DB::table('h5b_outbox')->where('summary_id',$id)->sole(); $actions=$this->actions($rc,$job); }
        return view('boleta-notes.show',['record'=>$record,'kind'=>$kind,'rc'=>$rc,'job'=>$job,'actions'=>$actions,'member'=>$member,
            'events'=>$rc?DB::table('h5b_events')->where('summary_id',$id)->orderBy('id')->simplePaginate(20,['*'],'events_page'):[]]);
    }

    private function actions(NoteSummary $s, object $job): array
    {
        $available=!$job->available_at || Carbon::parse($job->available_at)->lte(now('UTC'));
        $canQuery=$s->ticket!==null && !app(\App\Corrections\Policy::class)->queryExhausted('h5b',$s->id,$job->poll_attempts);
        $expired=$job->status==='processing' && $job->reserved_until && Carbon::parse($job->reserved_until)->isPast() && $s->state===($job->phase==='send'?'sending':'querying');
        return ['send'=>$s->state==='pending_send' && $job->status==='pending' && $job->phase==='send' && $job->send_attempts===0 && $s->ticket===null && $available && $s->reference_date>=now('America/Lima')->subDays(7)->toDateString(),
            'poll'=>$s->state==='awaiting' && $job->status==='pending' && $job->phase==='poll' && $canQuery && $job->cycle_attempts<3 && $available,
            'recover'=>$expired || ($s->state==='uncertain' && $job->status==='uncertain' && $job->phase==='poll' && $canQuery)];
    }

    public function process(Request $r, string $id, SummaryTransport $t)
    {
        $s=$this->owned($r,'rc',$id); $v=$r->validate(['action'=>'required|in:send,poll,recover','scenario'=>'nullable|string']);
        $job=DB::table('h5b_outbox')->where('summary_id',$id)->sole();
        abort_unless($this->actions($s,$job)[$v['action']],409,'Acción incompatible con el estado, ticket o plazo actual. Conserva el RC.');
        try { $result=match($v['action']){'send'=>$t->send($id,$v['scenario'] ?? 'accepted'),'poll'=>$t->poll($id,$v['scenario'] ?? '0'),'recover'=>$t->recover($id)}; }
        catch(\Illuminate\Database\QueryException $e) { throw $e; }
        catch(\RuntimeException) { abort(409,'Evidencia ausente o alterada; operación detenida.'); }
        abort_if($result===null,409,'La operación cambió o está en curso; actualiza el detalle.');
        return redirect()->route('receipt-notes.show',['kind'=>'rc','id'=>$id],303);
    }

    public function download(Request $r, string $kind, string $id, string $file, Evidence $files)
    {
        $record=$this->owned($r,$kind,$id); abort_unless(in_array($file,['xml','zip','certificate'],true),404);
        try { $path=$files->path($id,$file,$record->artifacts); } catch(\RuntimeException) { abort(409,'Evidencia ausente/alterada.'); }
        return response()->download($path,'H5B-FICTICIO-'.$id.'.'.match($file){'xml'=>'xml','zip'=>'zip','certificate'=>'pem'});
    }

    private function owned(Request $r,string $kind,string $id): BoletaNote|NoteSummary
    {
        abort_unless(Str::isUuid($id) && in_array($kind,['note','rc'],true),404);
        return ($kind==='note'?BoletaNote::query():NoteSummary::query())->where('user_id',$r->user()->id)->findOrFail($id);
    }
}
