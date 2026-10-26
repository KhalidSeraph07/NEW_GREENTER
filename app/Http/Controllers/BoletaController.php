<?php

namespace App\Http\Controllers;

use App\Boletas\Artifacts;
use App\Boletas\Preparation;
use App\Boletas\SummaryTransport;
use App\Boletas\LocalSummarySimulator;
use App\Models\Boleta;
use App\Models\DailySummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class BoletaController
{
    public function index(Request $request)
    {
        return view('boletas.index', ['boletas' => Boleta::query()->where('user_id', $request->user()->id)->orderByDesc('number')->limit(30)->get(),
            'summaries' => DailySummary::query()->where('user_id', $request->user()->id)->latest()->limit(30)->get()]);
    }

    public function issue(Request $request, Preparation $preparation)
    {
        $receipt = $preparation->issue($request->user()->id, $request->all());
        return redirect()->route('boletas.show', $receipt->id, 303);
    }

    public function freeze(Request $request, Preparation $preparation)
    {
        $summary = $preparation->freeze($request->user()->id, $request->all());
        return redirect()->route('summaries.show', $summary->id, 303);
    }

    public function showBoleta(Request $request, string $id) { return $this->show($request, 'boleta', $id); }

    public function showSummary(Request $request, string $id) { return $this->show($request, 'rc', $id); }

    private function show(Request $request, string $kind, string $id)
    {
        $record = $this->owned($request, $kind, $id);
        return view('boletas.show', ['record' => $record, 'kind' => $kind,
            'events' => $kind === 'rc' ? DB::table('h4_events')->where('summary_id', $id)->orderBy('id')->get() : [],
            'membership' => $kind === 'boleta' ? DB::table('h4_members')->join('h4_summaries', 'h4_summaries.id', '=', 'h4_members.summary_id')
                ->where('boleta_id', $id)->first(['h4_members.summary_id', 'h4_summaries.state']) : null]);
    }

    public function process(Request $request, string $id, SummaryTransport $transport)
    {
        $summary = $this->owned($request, 'rc', $id);
        $values = $request->validate(['action' => 'required|in:send,poll,recover', 'scenario' => 'nullable|string']);
        match ($values['action']) {
            'send' => $transport->send($summary->id, $values['scenario'] ?? 'accepted'),
            'poll' => $transport->poll($summary->id, $values['scenario'] ?? '0'),
            'recover' => $transport->recover($summary->id),
        };
        return redirect()->route('summaries.show', $summary->id, 303);
    }

    public function download(Request $request, string $kind, string $id, string $file, Artifacts $files)
    {
        $record = $this->owned($request, $kind, $id);
        abort_unless(in_array($file, ['xml', 'zip', 'certificate'], true), 404);
        try { $path = $files->path($id, $file, $record->artifacts); }
        catch (RuntimeException) { abort(409, 'Evidencia H4 ausente/alterada.'); }
        return response()->download($path, 'H4-FICTICIO-'.$id.'.'.match ($file) { 'xml' => 'xml', 'zip' => 'zip', 'certificate' => 'pem' });
    }

    private function owned(Request $request, string $kind, string $id): Boleta|DailySummary
    {
        abort_unless(Str::isUuid($id) && in_array($kind, ['boleta', 'rc'], true), 404);
        $model = $kind === 'boleta' ? Boleta::class : DailySummary::class;
        return $model::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
