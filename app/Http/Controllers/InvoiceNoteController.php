<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, InvoiceNote};
use App\Notes\{Evidence, Original, Preparation, Transport};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\{MessageBag, Str, ViewErrorBag};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InvoiceNoteController
{
    public function index(Request $r) { return view('notes.index', $this->listing($r)); }

    private function listing(Request $r, ?array $form = null): array
    {
        $filters=Validator::make($r->query(), ['operation'=>'nullable|in:NC01,ND02','original_number'=>'nullable|integer|min:1','note_number'=>'nullable|integer|min:1'])->validate();
        $form=$form ?? $r->old('note_form', []);
        $operation=$form['operation'] ?? $filters['operation'] ?? 'NC01';
        $originals=Invoice::query()->where('user_id',$r->user()->id)
            ->when($filters['original_number'] ?? null, fn($q,$v)=>$q->where('number',$v))
            ->orderByDesc('number')->simplePaginate(20,['*'],'originals_page')->withQueryString();
        $selected=is_string($form['original_id'] ?? null) && Str::isUuid($form['original_id']) ? $form['original_id'] : null;
        $options=Invoice::query()->where('user_id',$r->user()->id)->where('id',$selected)->whereNotIn('id',$originals->pluck('id'))->get()->concat($originals->getCollection());
        $reasons=[];
        foreach($options as $original) {
            try {
                app(Original::class)->eligible($original); app(\App\Voids\Grant::class)->assertNoteAllowed('invoice',$original->id);
                if(InvoiceNote::query()->where('original_id',$original->id)->exists()) { throw new ConflictHttpException('Original reservado por una nota; solo admite replay de la misma operación.'); }
                $reasons[$original->id]=null;
            } catch(\Illuminate\Database\QueryException $e) { throw $e; }
            catch(ConflictHttpException $e) { $reasons[$original->id]=$e->getMessage(); }
            catch(\RuntimeException) { $reasons[$original->id]='Evidencia del original ausente o alterada; requiere revisión.'; }
        }
        $notes=InvoiceNote::query()->where('user_id',$r->user()->id)->when($filters['note_number'] ?? null, fn($q,$v)=>$q->where('number',$v))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20,['*'],'notes_page')->withQueryString();
        return compact('originals','notes','options','reasons','selected','form','operation','filters');
    }

    public function store(Request $r, Preparation $preparation)
    {
        try { $n=$preparation->issue($r->user()->id,$r->all()); }
        catch(ValidationException|\RuntimeException $e) { return $this->formError($r,$e); }
        return redirect()->route('notes.show',$n->id,303);
    }

    private function formError(Request $r, \Throwable $e)
    {
        if($e instanceof \Illuminate\Database\QueryException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) { throw $e; }
        if($r->expectsJson()) {
            if($e instanceof ValidationException || $e instanceof ConflictHttpException) { throw $e; }
            abort(409,'Evidencia ausente o alterada; operación detenida.');
        }
        $form=array_map(fn($v)=>is_string($v)?$v:'',$r->only(['idempotency_key','original_id','operation','reason','emission_date','original_granted_test','increment_unit_price']));
        if($e instanceof ValidationException) { return redirect()->route('notes.index',[],303)->withInput(['note_form'=>$form])->withErrors($e->errors()); }
        $message=$e instanceof ConflictHttpException ? $e->getMessage() : 'Evidencia ausente o alterada; operación detenida.';
        return response()->view('notes.index',$this->listing($r,$form)+['errors'=>(new ViewErrorBag())->put('default',new MessageBag(['note'=>$message]))],409);
    }

    public function show(Request $r, string $id)
    {
        $note=$this->owned($r,$id); $job=DB::table('h5_outbox')->where('note_id',$id)->sole();
        return view('notes.show',['note'=>$note,'job'=>$job,'actions'=>$this->actions($note,$job),
            'events'=>DB::table('h5_events')->where('note_id',$id)->orderBy('id')->simplePaginate(20,['*'],'events_page')]);
    }

    private function actions(InvoiceNote $n, object $job): array
    {
        return ['dispatch'=>$n->state===$job->status && $n->emission_date>=now('America/Lima')->subDays(3)->toDateString()
            && (($job->status==='pending' && $job->attempts===0) || ($job->status==='retry_wait' && $job->attempts<3 && $job->available_at && Carbon::parse($job->available_at)->lte(now('UTC')))),
            'recover'=>($n->state==='uncertain' && $job->status==='uncertain') || ($n->state==='processing' && $job->status==='processing' && $job->reserved_until && Carbon::parse($job->reserved_until)->isPast())];
    }

    public function process(Request $r, string $id, Transport $transport)
    {
        $n=$this->owned($r,$id); $v=$r->validate(['action'=>'required|in:dispatch,recover','scenario'=>'nullable|string']);
        $job=DB::table('h5_outbox')->where('note_id',$id)->sole();
        abort_unless($this->actions($n,$job)[$v['action']],409,'Acción incompatible con el estado o plazo actual; conserva la nota y revisa su historial.');
        try {
            app(Evidence::class)->verify($n); app(Original::class)->verify($n);
            $result=$v['action']==='recover' ? $transport->recover($id) : $transport->dispatch($id,$v['scenario'] ?? 'accepted');
        } catch(\Illuminate\Database\QueryException $e) { throw $e; }
        catch(\RuntimeException) { abort(409,'Evidencia ausente o alterada; operación detenida.'); }
        abort_if($result===null,409,'La operación cambió o está en curso; actualiza el detalle.');
        return redirect()->route('notes.show',$id,303);
    }

    public function download(Request $r, string $id, string $kind, Evidence $files)
    {
        $n=$this->owned($r,$id); abort_unless(in_array($kind,['xml','zip','certificate'],true),404);
        try { $path=$files->path($n->id,$kind,$n->artifacts); }
        catch(\RuntimeException) { abort(409,'Evidencia H5-A ausente/alterada.'); }
        return response()->download($path,'H5A-FICTICIO-'.$id.'.'.match($kind){'xml'=>'xml','zip'=>'zip','certificate'=>'pem'});
    }

    private function owned(Request $r, string $id): InvoiceNote
    {
        abort_unless(Str::isUuid($id),404);
        return InvoiceNote::query()->where('user_id',$r->user()->id)->findOrFail($id);
    }
}
