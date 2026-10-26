@extends('layout')
@section('content')
<h1>Historial de facturas ficticias</h1>
@if ($invoices->isEmpty())
<p>Aún no tienes facturas de prueba.</p>
@else
<table><thead><tr><th scope="col">Factura</th><th scope="col">Total PEN</th><th scope="col">Transporte de prueba</th></tr></thead><tbody>
@foreach ($invoices as $invoice)
<tr><td><a href="{{ route('invoices.show', $invoice->id) }}">F000-{{ $invoice->number }}</a></td>
<td>{{ \App\Invoices\InvoiceInput::decimal($invoice->total_cents) }}</td>
<td>{{ match($invoice->transport_status) { 'pending_simulation' => 'Pendiente de simulación', 'simulated_accepted' => 'Aceptada en simulación', 'simulated_rejected' => 'Rechazada en simulación', default => 'Simulación incierta' } }}</td></tr>
@endforeach
</tbody></table>
{{ $invoices->links() }}
@endif
@endsection
