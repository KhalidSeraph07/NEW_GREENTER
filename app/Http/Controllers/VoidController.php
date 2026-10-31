<?php

namespace App\Http\Controllers;

use App\Models\VoidBatch;
use App\Notes\Original;
use App\Voids\{Evidence, Grant, Preparation, Source, Transport, Window};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\{MessageBag, Str, ViewErrorBag};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class VoidController
{
    public function index(Request $r) { return view('voids.index',$this->listing($r)); }

    private function listing(Request $r, array $forms=[]): array
    {
        $filters=Validator::make($r->query(),['kind'=>'nullable|in:invoice,invoice_note,boleta,boleta_note','number'=>'nullable|integer|min:1','name'=>'nullable|string|max:80'])->validate();
        $grantForm=$forms['grant'] ?? $r->old('grant_form',[]); $form=$forms['batch'] ?? $r->old('batch_form',[]);
        $union=null;
        foreach(Source::MODELS as $kind=>$model) {
            $q=DB::table((new $model)->getTable())->where('user_id',$r->user()->id)
                ->selectRaw("? as kind, id, snapshot->>'series' as series, number, created_at",[$kind]);
            $union=$union?$union->unionAll($q):$q;
        }
        $documents=DB::query()->fromSub($union,'documents')->when($filters['kind'] ?? null,fn($q,$v)=>$q->where('kind',$v))
            ->when($filters['number'] ?? null,fn($q,$v)=>$q->where('number',$v))->orderByDesc('created_at')->orderBy('id')
            ->simplePaginate(20,['*'],'documents_page')->withQueryString();
        $selected=array_slice(array_filter((array)($form['members'] ?? []),'is_string'),0,100);
        $tokens=array_unique(array_merge($selected,[is_string($grantForm['document'] ?? null)?$grantForm['document']:'']));
        $options=$documents->getCollection()->keyBy(fn($d)=>$d->kind.':'.$d->id);
        foreach($tokens as $token) {
            if(!preg_match('/\A(invoice|invoice_note|boleta|boleta_note):([0-9a-f-]{36})\z/',$token,$m) || !Str::isUuid($m[2]) || $options->has($token)) { continue; }
            $d=app(Source::class)->document($r->user()->id,$m[1],$m[2]);
            $options->put($token,(object)['kind'=>$m[1],'id'=>$d->id,'series'=>$d->snapshot['series'],'number'=>$d->number]);
        }
        $reasons=[]; $grantReasons=[]; $facts=[];
        foreach($options as $token=>$d) {
            $key=['kind'=>$d->kind,'id'=>$d->id];
            $facts[$token]=DB::table('h5c1_grants')->where('user_id',$r->user()->id)->where('kind',$d->kind)->where('document_id',$d->id)->orderByDesc('sequence')->first();
            $proof=null;
            $grantReasons[$token]=$this->problem(function() use($r,$key,&$proof) { $proof=app(Source::class)->capture($r->user()->id,...array_values($key)); app(Source::class)->unreserved(...array_values($key)); });
            $reasons[$token]=$grantReasons[$token] ?? $this->problem(fn()=>$this->member($r->user()->id,$key,$proof));
        }
        $batches=VoidBatch::query()->where('user_id',$r->user()->id)->when($filters['name'] ?? null,fn($q,$v)=>$q->where('name',$v))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20,['*'],'batches_page')->withQueryString();
        $grants=DB::table('h5c1_grants')->where('user_id',$r->user()->id)->orderByDesc('sequence')->simplePaginate(20,['*'],'grants_page')->withQueryString();
        return compact('filters','grantForm','form','documents','options','selected','reasons','grantReasons','facts','batches','grants');
    }

    private function problem(callable $check): ?string
    {
        try { $check(); return null; }
        catch(\Illuminate\Database\QueryException $e) { throw $e; }
        catch(ValidationException $e) { return implode(' ',array_merge(...array_values($e->errors()))); }
        catch(ConflictHttpException $e) { return $e->getMessage(); }
        catch(\RuntimeException|\LogicException) { return 'Evidencia o correlación no verificable; conservar e investigar.'; }
    }

    public function recordGrant(Request $r, Grant $grant)
    {
        try {
            if(array_diff(array_keys($r->all()),['_token','idempotency_key','document','state','provenance','confirmed'])) { throw ValidationException::withMessages(['grant'=>'Campos de declaración no permitidos.']); }
            $v=$r->validate(['document'=>'required|string','confirmed'=>'required|accepted']);
            $key=$this->key($v['document']);
            $record=$grant->record($r->user()->id,$key+$r->only('idempotency_key','state','provenance'));
        } catch(ValidationException|\RuntimeException|\LogicException $e) { return $this->formError($r,'grant',$e); }
        return redirect()->route('voids.index',[],303)->with('grant_recorded',$record->id);
    }

    private function key(string $token): array
    {
        $parts=explode(':',$token,2);
        if(count($parts)!==2 || !isset(Source::MODELS[$parts[0]]) || !Str::isUuid($parts[1])) { throw ValidationException::withMessages(['members'=>'Identidad documental no permitida.']); }
        return ['kind'=>$parts[0],'id'=>strtolower($parts[1])];
    }

    private function selection(Request $r): array
    {
        if(array_diff(array_keys($r->all()),['_token','idempotency_key','members','reason'])) { throw ValidationException::withMessages(['batch'=>'Solo clave, motivo y miembros explícitos.']); }
        $form=Validator::make($r->all(),['idempotency_key'=>'required|uuid','reason'=>['required','string','min:3','max:100','not_regex:/[\x00-\x1F]|\]\]>/u'],
            'members'=>'required|array|min:1|max:100','members.*'=>'required|string'])->validate();
        $keys=array_map($this->key(...),$form['members']); usort($keys,fn($a,$b)=>strcmp($a['kind'].':'.$a['id'],$b['kind'].':'.$b['id']));
        $form['members']=array_map(fn($k)=>$k['kind'].':'.$k['id'],$keys); $form['idempotency_key']=strtolower($form['idempotency_key']);
        if(count(array_unique($form['members']))!==count($keys)) { throw ValidationException::withMessages(['members'=>'Documento repetido.']); }
        foreach($keys as $key) { app(Source::class)->document($r->user()->id,...array_values($key)); }
        return [$form,['idempotency_key'=>$form['idempotency_key'],'members'=>$keys,'reason'=>$form['reason']]];
    }

    private function member(int $user, array $key, ?array $proof=null): array
    {
        $source=app(Source::class); $source->unreserved(...array_values($key)); $source->noDependants(...array_values($key));
        $member=$proof ?? $source->capture($user,...array_values($key));
        $grant=DB::table('h5c1_grants')->where('kind',$key['kind'])->where('document_id',$key['id'])->orderByDesc('sequence')->first();
        if(!$grant || $grant->user_id!==$user || $grant->state!=='not_granted') { throw new ConflictHttpException('No otorgamiento desconocido o incompatible; baja bloqueada.'); }
        if(Original::hash($member)!==Original::hash(json_decode($grant->proof,true,flags:JSON_THROW_ON_ERROR))) { throw new ConflictHttpException('La declaración no corresponde a la evidencia actual.'); }
        app(Window::class)->check($member);
        if(str_starts_with($member['series'],'F') && $member['generation_date']!==$member['emission_date']) { throw ValidationException::withMessages(['members'=>'RA conservadora exige generación y emisión coincidentes acreditadas.']); }
        return $member;
    }

    private function reviewHash(Request $r, array $input): string { return Original::hash([$r->user()->id,now('America/Lima')->toDateString(),$input]); }

    public function review(Request $r, Preparation $p)
    {
        try {
            [$form,$input]=$this->selection($r);
            $existing=VoidBatch::query()->where('user_id',$r->user()->id)->where('idempotency_key',$input['idempotency_key'])->first();
            if($existing) {
                if(!hash_equals($existing->payload_hash,Original::hash(array_diff_key($input,['idempotency_key'=>true])))) { throw new ConflictHttpException('Clave de baja reutilizada con otro contenido.'); }
                $p->verify($existing); $preview=$existing->snapshot['members']; $route=$existing->route;
            } else {
                $preview=array_map(fn($key)=>$this->member($r->user()->id,$key),$input['members']);
                $routes=array_unique(array_map(fn($m)=>str_starts_with($m['series'],'F')?'RA':'RC',$preview));
                if(count($routes)!==1 || count(array_unique(array_column($preview,'emission_date')))!==1) { throw ValidationException::withMessages(['members'=>'Misma ruta y fecha de emisión requeridas; no mezclar familias.']); }
                $route=reset($routes);
            }
            // La revisión no persiste documentos. H5-C1 revalida bajo sus locks al congelar.
            $r->session()->put('void_reviews.'.$input['idempotency_key'],$this->reviewHash($r,$input));
            return view('voids.review',compact('form','preview','route','existing'));
        } catch(ValidationException|\RuntimeException|\LogicException $e) { return $this->formError($r,'batch',$e); }
    }

    public function editReview(Request $r)
    {
        try { [$form]=$this->selection($r); return view('voids.index',$this->listing($r,['batch'=>$form])); }
        catch(ValidationException|\RuntimeException|\LogicException $e) { return $this->formError($r,'batch',$e); }
    }

    public function freeze(Request $r, Preparation $p)
    {
        try {
            [$form,$input]=$this->selection($r);
            if(!hash_equals($r->session()->get('void_reviews.'.$input['idempotency_key'],''),$this->reviewHash($r,$input))) { throw new ConflictHttpException('Revisa esta operación antes de preparar la baja.'); }
            $batch=$p->freeze($r->user()->id,$input);
        } catch(ValidationException|\RuntimeException|\LogicException $e) { return $this->formError($r,'batch',$e); }
        return redirect()->route('voids.show',$batch->id,303);
    }

    private function formError(Request $r, string $kind, \Throwable $e)
    {
        if($e instanceof \Illuminate\Database\QueryException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) { throw $e; }
        if($r->expectsJson()) { if($e instanceof ValidationException || $e instanceof ConflictHttpException) { throw $e; } abort(409,'Evidencia o correlación no verificable; operación detenida.'); }
        $form=$r->only($kind==='grant'?['idempotency_key','document','state','provenance','confirmed']:['idempotency_key','members','reason']);
        foreach($form as $field=>$value) { if($field!=='members' && !is_string($value)) { $form[$field]=''; } }
        if($e instanceof ValidationException) { return redirect()->route('voids.index',[],303)->withInput([$kind.'_form'=>$form])->withErrors($e->errors()); }
        return response()->view('voids.index',$this->listing($r,[$kind=>$form])+['errors'=>(new ViewErrorBag())->put('default',new MessageBag([$kind=>$e instanceof ConflictHttpException?$e->getMessage():'Evidencia o correlación no verificable; operación detenida.']))],409);
    }

    private function owned(Request $r, string $id): VoidBatch
    {
        abort_unless(Str::isUuid($id),404);
        return VoidBatch::query()->where('user_id',$r->user()->id)->findOrFail($id);
    }

    private function actions(VoidBatch $b, object $job): array
    {
        $available=!$job->available_at || Carbon::parse($job->available_at)->lte(now('UTC'));
        $send=$b->state==='pending_send' && $job->status==='pending' && $job->phase==='send' && $job->send_attempts===0 && $b->ticket===null && $available;
        if($send) { $send=$this->problem(function() use($b) { foreach($b->snapshot['members'] as $m) { app(Window::class)->check($m); } app(\App\Corrections\Policy::class)->beforeSend('void',$b); })===null; }
        $expired=$job->status==='processing' && $job->reserved_until && Carbon::parse($job->reserved_until)->isPast() && $b->state===($job->phase==='send'?'sending':'querying');
        $canQuery=$b->ticket!==null && $job->poll_attempts<9;
        return ['send'=>$send,'poll'=>$b->state==='awaiting' && $job->status==='pending' && $job->phase==='poll' && $canQuery && $job->cycle_attempts<3 && $available,
            'recover'=>$expired || ($b->state==='uncertain' && $job->status==='uncertain' && $job->phase==='poll' && $canQuery)];
    }

    public function show(Request $r, string $id, Preparation $p)
    {
        $batch=$this->owned($r,$id); $job=DB::table('h5c1_outbox')->where('batch_id',$id)->sole();
        $integrityIssue=$this->problem(fn()=>$p->verify($batch));
        $actions=$integrityIssue?['send'=>false,'poll'=>false,'recover'=>false]:$this->actions($batch,$job);
        $events=DB::table('h5c1_events')->where('batch_id',$id)->orderBy('id')->simplePaginate(20,['*'],'events_page');
        return view('voids.show',compact('batch','job','integrityIssue','actions','events'));
    }

    public function process(Request $r, string $id, Transport $transport, Preparation $p)
    {
        $batch=$this->owned($r,$id); $v=$r->validate(['action'=>'required|in:send,poll,recover','scenario'=>'nullable|string']);
        $job=DB::table('h5c1_outbox')->where('batch_id',$id)->sole();
        abort_unless($this->actions($batch,$job)[$v['action']],409,'Acción incompatible con el estado, ticket o plazo actual; conserva la comunicación.');
        try {
            $p->verify($batch);
            $result=match($v['action']) { 'send'=>$transport->send($id,$v['scenario'] ?? 'accepted'),'poll'=>$transport->poll($id,$v['scenario'] ?? '0'),'recover'=>$transport->recover($id) };
        } catch(ValidationException $e) { throw $e; }
        catch(\Illuminate\Database\QueryException $e) { throw $e; }
        catch(\RuntimeException|\LogicException) { abort(409,'Evidencia o correlación no verificable; operación detenida.'); }
        abort_if($result===null,409,'La operación cambió o está en curso; actualiza el detalle.');
        return redirect()->route('voids.show',$id,303);
    }

    public function download(Request $r, string $id, string $kind, Evidence $files)
    {
        $b=$this->owned($r,$id); abort_unless(in_array($kind,['xml','zip'],true),404);
        try { $path=$files->path($id,$kind,$b->artifacts); } catch(\RuntimeException) { abort(409,'Evidencia ausente o alterada.'); }
        return response()->download($path,'H5C1-FICTICIO-'.$b->name.'.'.$kind);
    }
}
