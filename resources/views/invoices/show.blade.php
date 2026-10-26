@extends('layout')
@section('content')
<h1>Factura ficticia F000-{{ $invoice->number }}</h1>
<p>{{ $invoice->snapshot['description'] }}</p>
<p>Cantidad: {{ \App\Invoices\InvoiceInput::decimal($invoice->snapshot['quantity_milli'], 3) }} · Valor unitario: S/ {{ \App\Invoices\InvoiceInput::decimal($invoice->snapshot['unit_price_cents']) }}</p>
<dl class="totals">
<dt>Subtotal</dt><dd>S/ {{ \App\Invoices\InvoiceInput::decimal($invoice->subtotal_cents) }}</dd>
<dt>IGV de ejemplo (18%)</dt><dd>S/ {{ \App\Invoices\InvoiceInput::decimal($invoice->tax_cents) }}</dd>
<dt>Total</dt><dd><strong>S/ {{ \App\Invoices\InvoiceInput::decimal($invoice->total_cents) }}</strong></dd>
</dl>
<p><strong>Estado de transporte:</strong> {{ match($invoice->transport_status) { 'pending_simulation' => 'Pendiente de simulación', 'simulated_accepted' => 'Aceptada en simulación', 'simulated_rejected' => 'Rechazada en simulación', default => 'Simulación incierta; detenida sin reintento automático' } }}</p>
<p><strong>Estado fiscal:</strong> No enviado a SUNAT. Sin CDR oficial.</p>
<p>Evidencias privadas:
<a href="{{ route('invoices.download', [$invoice->id, 'xml']) }}">XML firmado</a> ·
<a href="{{ route('invoices.download', [$invoice->id, 'zip']) }}">ZIP</a> ·
<a href="{{ route('invoices.download', [$invoice->id, 'certificate']) }}">Certificado público de prueba</a></p>
@if ($invoice->transport_status === 'pending_simulation')
<form method="post" action="{{ route('invoices.simulate', $invoice->id) }}">
@csrf
<label for="scenario">Respuesta ficticia que se ensayará</label>
<select id="scenario" name="scenario"><option value="accepted">Aceptación simulada</option><option value="rejected">Rechazo simulado</option><option value="uncertain">Sin respuesta (incierta)</option></select>
<button type="submit">Procesar simulación local</button>
</form>
@endif
@if ($invoice->simulated_response)
<p>{{ $invoice->simulated_response['message'] }}</p>
<small>Código ficticio: {{ $invoice->simulated_response['code'] }}</small>
@endif
@endsection
