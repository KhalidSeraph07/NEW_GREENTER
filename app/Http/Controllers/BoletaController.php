<?php

namespace App\Http\Controllers;

use App\Boletas\Artifacts;
use App\Boletas\Preparation;
use App\Boletas\SummaryTransport;
use App\Models\Boleta;
use App\Models\DailySummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BoletaController
{
    public function index(Request $request)
    {
        return view('boletas.index', $this->listing($request));
    }

    private function listing(Request $request, array $forms = []): array
    {
        $filters = Validator::make($request->query(), ['boleta_date'=>'nullable|date_format:Y-m-d',
            'boleta_number'=>'nullable|integer|min:1', 'rc_name'=>'nullable|string|max:80'])->validate();
        $boletas = Boleta::query()->where('user_id', $request->user()->id)
            ->when($filters['boleta_date'] ?? null, fn ($q, $date) => $q->where('emission_date', $date))
            ->when($filters['boleta_number'] ?? null, fn ($q, $number) => $q->where('number', $number))
            ->orderByDesc('number')->simplePaginate(20, ['*'], 'boletas_page')->withQueryString();
        $summaries = DailySummary::query()->where('user_id', $request->user()->id)
            ->when($filters['rc_name'] ?? null, fn ($q, $name) => $q->where('name', $name))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20, ['*'], 'rc_page')->withQueryString();
        $boletaForm = $forms['boleta'] ?? $request->old('boleta_form', []);
        $summaryForm = $forms['summary'] ?? $request->old('summary_form', []);
        $selected = array_slice(array_filter((array) ($summaryForm['members'] ?? []), fn ($id) => is_string($id) && Str::isUuid($id)), 0, 100);
        // Mantener visibles las selecciones propias aunque el filtro o la página ya no las incluyan.
        $options = Boleta::query()->where('user_id', $request->user()->id)->whereIn('id', $selected)
            ->whereNotIn('id', $boletas->pluck('id'))->get()->concat($boletas->getCollection());
        $reserved = DB::table('h4_members')->whereIn('boleta_id', $options->pluck('id'))->pluck('summary_id', 'boleta_id');
        $today = now('America/Lima')->toDateString(); $oldest = now('America/Lima')->subDays(7)->toDateString();
        $reasons = $options->mapWithKeys(fn ($b) => [$b->id => isset($reserved[$b->id]) ? 'Reservada en un RC; no puede incorporarse a otro.'
            : (($b->emission_date < $oldest || $b->emission_date > $today) ? 'Fuera de la ventana D+7 del ensayo.' : null)]);
        return compact('boletas', 'summaries', 'filters', 'boletaForm', 'summaryForm', 'selected', 'options', 'reasons');
    }

    public function issue(Request $request, Preparation $preparation)
    {
        try { $receipt = $preparation->issue($request->user()->id, $request->all()); }
        catch (ValidationException|ConflictHttpException|RuntimeException $error) { return $this->formError($request, 'boleta', $error); }
        return redirect()->route('boletas.show', $receipt->id, 303);
    }

    public function review(Request $request, Artifacts $files)
    {
        try {
            [$form, $receipts] = $this->selection($request);
            $existing = DailySummary::query()->where('user_id', $request->user()->id)->where('idempotency_key', $form['idempotency_key'])->first();
            if ($existing) {
                if (!hash_equals($existing->payload_hash, hash('sha256', json_encode($form['members'], JSON_THROW_ON_ERROR)))) {
                    throw new ConflictHttpException('Clave reutilizada con otros miembros.');
                }
                $files->verify($existing->id, $existing->artifacts);
            } else {
                if (DB::table('h4_members')->whereIn('boleta_id', $form['members'])->exists()) {
                    throw new ConflictHttpException('Miembro ya reservado; conserva el RC anterior.');
                }
                $date = $receipts->first()->emission_date;
                if ($date > now('America/Lima')->toDateString() || $date < now('America/Lima')->subDays(7)->toDateString()) {
                    throw ValidationException::withMessages(['members'=>'Fuera de ventana D+7 del ensayo.']);
                }
            }
            foreach ($receipts as $receipt) { $files->verify($receipt->id, $receipt->artifacts); }
            // Huella en sesión, sin borrador, reserva ni artefactos. H4 revalida dentro de su transacción al confirmar.
            $request->session()->put('summary_reviews.'.$form['idempotency_key'], $this->reviewHash($request, $form));
            return view('boletas.review', compact('form', 'receipts', 'existing'));
        } catch (ValidationException|ConflictHttpException|RuntimeException $error) { return $this->formError($request, 'summary', $error); }
    }

    public function editReview(Request $request)
    {
        try { [$form] = $this->selection($request); }
        catch (ValidationException $error) { return $this->formError($request, 'summary', $error); }
        return view('boletas.index', $this->listing($request, ['summary'=>$form]));
    }

    public function freeze(Request $request, Preparation $preparation)
    {
        try {
            [$form] = $this->selection($request);
            if (!hash_equals($request->session()->get('summary_reviews.'.$form['idempotency_key'], ''), $this->reviewHash($request, $form))) {
                throw new ConflictHttpException('Revisa los miembros de esta operación antes de congelar el RC.');
            }
            $summary = $preparation->freeze($request->user()->id, $request->all());
        } catch (ValidationException|ConflictHttpException|RuntimeException $error) { return $this->formError($request, 'summary', $error); }
        return redirect()->route('summaries.show', $summary->id, 303);
    }

    private function selection(Request $request): array
    {
        if (array_diff(array_keys($request->all()), ['idempotency_key','members','_token'])) {
            throw ValidationException::withMessages(['summary'=>'Solo identidad y miembros explícitos.']);
        }
        $form = Validator::make($request->all(), ['idempotency_key'=>'required|uuid', 'members'=>'required|array|min:1|max:100',
            'members.*'=>'required|uuid|distinct'])->validate();
        sort($form['members']);
        $receipts = Boleta::query()->where('user_id', $request->user()->id)->whereIn('id', $form['members'])->orderBy('id')->get();
        if ($receipts->count() !== count($form['members']) || $receipts->pluck('emission_date')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['members'=>'Miembros propios de una misma fecha requeridos.']);
        }
        return [$form, $receipts];
    }

    private function reviewHash(Request $request, array $form): string
    {
        return hash('sha256', json_encode([$request->user()->id, $form], JSON_THROW_ON_ERROR));
    }

    private function formError(Request $request, string $kind, \Throwable $error)
    {
        if ($error instanceof \Illuminate\Database\QueryException) { throw $error; }
        if ($request->expectsJson()) {
            if ($error instanceof ValidationException || $error instanceof ConflictHttpException) { throw $error; }
            abort(409, 'Evidencia ausente o alterada; operación detenida.');
        }
        $form = $request->only($kind === 'boleta' ? ['idempotency_key','description','quantity','unit_price','emission_date'] : ['idempotency_key','members']);
        foreach ($form as $field=>$value) {
            if ($field !== 'members' && !is_string($value)) { $form[$field] = ''; }
        }
        if ($error instanceof ValidationException) {
            return redirect()->route('boletas.index', [], 303)->withInput([$kind.'_form'=>$form])->withErrors($error->errors());
        }
        $message = $error instanceof ConflictHttpException ? $error->getMessage() : 'Evidencia ausente o alterada; operación detenida.';
        return response()->view('boletas.index', $this->listing($request, [$kind=>$form]) + [
            'errors'=>(new ViewErrorBag())->put('default', new MessageBag([$kind=>$message]))], 409);
    }

    public function showBoleta(Request $request, string $id) { return $this->show($request, 'boleta', $id); }
    public function showSummary(Request $request, string $id) { return $this->show($request, 'rc', $id); }

    private function show(Request $request, string $kind, string $id)
    {
        $record = $this->owned($request, $kind, $id); $membership = null; $job = null; $actions = [];
        if ($kind === 'boleta') {
            $root = DB::table('h4_members')->where('boleta_id', $id)->value('summary_id');
            if ($root) {
                $rc = \App\Corrections\Family::effective('h4', \App\Corrections\Family::record('h4', $request->user()->id, $root));
                $membership = (object) ['summary_id'=>$rc->id, 'state'=>$rc->state];
            }
        } else {
            $job = DB::table('h4_outbox')->where('summary_id', $id)->sole();
            $actions = $this->actions($record, $job);
        }
        return view('boletas.show', ['record'=>$record, 'kind'=>$kind, 'job'=>$job, 'actions'=>$actions,
            'events'=>$kind === 'rc' ? DB::table('h4_events')->where('summary_id', $id)->orderBy('id')->simplePaginate(20, ['*'], 'events_page') : [],
            'membership'=>$membership]);
    }

    private function actions(DailySummary $summary, object $job): array
    {
        $available = !$job->available_at || Carbon::parse($job->available_at)->lte(now('UTC'));
        $canQuery = $summary->ticket !== null && !app(\App\Corrections\Policy::class)->queryExhausted('h4', $summary->id, $job->poll_attempts);
        $expired = $job->status === 'processing' && $job->reserved_until !== null && Carbon::parse($job->reserved_until)->isPast()
            && $summary->state === ($job->phase === 'send' ? 'sending' : 'querying');
        return [
            'send'=>$summary->state === 'pending_send' && $job->status === 'pending' && $job->phase === 'send' && $job->send_attempts === 0
                && $summary->ticket === null && $available && $summary->reference_date >= now('America/Lima')->subDays(7)->toDateString(),
            'poll'=>$summary->state === 'awaiting' && $job->status === 'pending' && $job->phase === 'poll' && $canQuery && $job->cycle_attempts < 3 && $available,
            'recover'=>$expired || ($summary->state === 'uncertain' && $job->status === 'uncertain' && $job->phase === 'poll' && $canQuery),
        ];
    }

    public function process(Request $request, string $id, SummaryTransport $transport)
    {
        $summary = $this->owned($request, 'rc', $id);
        $values = $request->validate(['action'=>'required|in:send,poll,recover', 'scenario'=>'nullable|string']);
        $job = DB::table('h4_outbox')->where('summary_id', $id)->sole();
        abort_unless($this->actions($summary, $job)[$values['action']], 409, 'La acción no corresponde al estado, ticket o plazo actual. Conserva el RC y revisa su historial.');
        try {
            $result = match ($values['action']) {
                'send'=>$transport->send($summary->id, $values['scenario'] ?? 'accepted'),
                'poll'=>$transport->poll($summary->id, $values['scenario'] ?? '0'),
                'recover'=>$transport->recover($summary->id),
            };
        } catch (\Illuminate\Database\QueryException $error) { throw $error; }
        catch (RuntimeException) { abort(409, 'Evidencia ausente o alterada; operación detenida.'); }
        abort_if($result === null, 409, 'La operación cambió o está en curso. Actualiza el detalle sin reenviar.');
        return redirect()->route('summaries.show', $summary->id, 303);
    }

    public function download(Request $request, string $kind, string $id, string $file, Artifacts $files)
    {
        $record = $this->owned($request, $kind, $id);
        abort_unless(in_array($file, ['xml','zip','certificate'], true), 404);
        try { $path = $files->path($id, $file, $record->artifacts); }
        catch (RuntimeException) { abort(409, 'Evidencia H4 ausente/alterada.'); }
        return response()->download($path, 'H4-FICTICIO-'.$id.'.'.match ($file) { 'xml'=>'xml', 'zip'=>'zip', 'certificate'=>'pem' });
    }

    private function owned(Request $request, string $kind, string $id): Boleta|DailySummary
    {
        abort_unless(Str::isUuid($id) && in_array($kind, ['boleta','rc'], true), 404);
        $model = $kind === 'boleta' ? Boleta::class : DailySummary::class;
        return $model::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
