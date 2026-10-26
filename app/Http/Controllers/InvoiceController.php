<?php

namespace App\Http\Controllers;

use App\Invoices\InvoiceEvidence;
use App\Invoices\InvoiceIssuer;
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

    public function store(Request $request, InvoiceIssuer $issuer)
    {
        $invoice = $issuer->issue($request->user()->id, $request->all());
        return redirect()->route('invoices.show', $invoice->id, 303);
    }

    public function show(Request $request, string $invoice)
    {
        return view('invoices.show', ['invoice' => $this->owned($request, $invoice)]);
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
        $values = $request->validate(['scenario' => 'required|in:accepted,rejected,uncertain']);
        $transport->dispatch($document->id, $values['scenario']);
        return redirect()->route('invoices.show', $document->id, 303);
    }

    private function owned(Request $request, string $id): Invoice
    {
        abort_unless(Str::isUuid($id), 404);
        return Invoice::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
