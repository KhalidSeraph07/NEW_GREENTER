<?php

namespace App\Http\Controllers;

use App\Invoices\InvoiceEvidence;
use App\Invoices\InvoiceIssuer;
use App\Invoices\InvoiceInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Invoices\SimulatedTransport;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

final class InvoiceController
{
    public function index(Request $request)
    {
        $invoices = Invoice::query()->where('user_id', $request->user()->id)->orderByDesc('number')->simplePaginate(20);
        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('invoices.create', ['operation' => (string) Str::uuid()]);
    }

    public function review(Request $request, InvoiceInput $input)
    {
        $values = $this->validated($request, $input);
        // Solo una huella en sesión: no crea borrador, artefactos ni reserva correlativo.
        $request->session()->put('invoice_reviews.'.$request->input('idempotency_key'), $this->reviewHash($request, $values));
        return view('invoices.review', ['values'=>$values, 'form'=>$request->only(['idempotency_key','description','quantity','unit_price'])]);
    }

    public function editReview(Request $request, InvoiceInput $input)
    {
        $this->validated($request, $input);
        return view('invoices.create', ['operation'=>$request->input('idempotency_key'),
            'form'=>$request->only(['description','quantity','unit_price'])]);
    }

    public function store(Request $request, InvoiceIssuer $issuer, InvoiceInput $input)
    {
        $values = $this->validated($request, $input);
        abort_unless(hash_equals($request->session()->get('invoice_reviews.'.$request->input('idempotency_key'), ''),
            $this->reviewHash($request, $values)), 409, 'Revisa los datos de esta operación antes de confirmar.');
        $invoice = $issuer->issue($request->user()->id, $request->all());
        return redirect()->route('invoices.show', $invoice->id, 303);
    }

    public function show(Request $request, string $invoice)
    {
        $document = $this->owned($request, $invoice);
        $job = DB::table('invoice_outbox')->where('invoice_id', $document->id)->sole();
        return view('invoices.show', ['invoice'=>$document, 'job'=>$job, 'canRecover'=>$this->canRecover($document, $job),
            'events'=>DB::table('invoice_transport_events')->where('invoice_id', $document->id)->orderBy('id')->simplePaginate(20, ['*'], 'events_page')]);
    }

    public function download(Request $request, string $invoice, string $kind, InvoiceEvidence $evidence)
    {
        $document = $this->owned($request, $invoice);
        abort_unless(in_array($kind, ['xml', 'zip', 'certificate'], true), 404);
        try {
            $path = $evidence->verifiedPath($document->id, $kind, $document->artifacts);
        } catch (RuntimeException) {
            abort(409, 'Evidencia ausente o alterada; descarga detenida.');
        }
        return response()->download($path, 'H2-FICTICIO-'.$document->series.'-'.$document->number.'.'.match ($kind) {
            'xml' => 'xml', 'zip' => 'zip', 'certificate' => 'pem',
        });
    }

    public function simulate(Request $request, string $invoice, SimulatedTransport $transport)
    {
        $document = $this->owned($request, $invoice);
        abort_unless($document->transport_status === 'pending_simulation', 409, 'El estado actual no permite procesar otra simulación.');
        $values = $request->validate(['scenario' => 'required|in:accepted,rejected,uncertain,timeout']);
        try { $transport->dispatch($document->id, $values['scenario']); }
        catch (RuntimeException) { abort(409, 'Evidencia ausente o alterada; operación detenida.'); }
        return redirect()->route('invoices.show', $document->id, 303);
    }

    public function recover(Request $request, string $invoice, SimulatedTransport $transport)
    {
        $document = $this->owned($request, $invoice);
        $job = DB::table('invoice_outbox')->where('invoice_id', $document->id)->sole();
        abort_unless($this->canRecover($document, $job), 409, 'La recuperación no corresponde al estado actual.');
        try { $transport->recover($document->id); }
        catch (RuntimeException) { abort(409, 'Evidencia ausente o alterada; consulta detenida.'); }
        return redirect()->route('invoices.show', $document->id, 303);
    }

    private function canRecover(Invoice $invoice, object $job): bool
    {
        return ($invoice->transport_status === 'simulation_uncertain' && $job->status === 'uncertain')
            || ($invoice->transport_status === 'simulation_processing' && $job->status === 'processing'
                && $job->reserved_until !== null && \Illuminate\Support\Carbon::parse($job->reserved_until)->isPast());
    }

    private function validated(Request $request, InvoiceInput $input): array
    {
        try { return $input->validate($request->all()); }
        catch (ValidationException $error) { throw $error->redirectTo(route('invoices.create')); }
    }

    private function reviewHash(Request $request, array $values): string
    {
        return hash('sha256', json_encode([$request->user()->id, $values], JSON_THROW_ON_ERROR));
    }

    private function owned(Request $request, string $id): Invoice
    {
        abort_unless(Str::isUuid($id), 404);
        return Invoice::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
