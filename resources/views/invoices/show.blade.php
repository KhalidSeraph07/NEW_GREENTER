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
<p><strong>Preparación:</strong> Factura preparada y registrada. La descarga verifica las evidencias.</p>
<p><strong>Estado de transporte:</strong> {{ match($invoice->transport_status) { 'pending_simulation' => 'Pendiente de simulación', 'simulation_processing' => 'Simulación en curso; resultado pendiente', 'simulation_retry_wait' => 'Reintento local seguro pendiente', 'simulation_failed' => 'Intentos locales agotados; requiere revisión', 'simulated_accepted', 'simulated_rejected' => 'Procesamiento ficticio finalizado', default => 'Simulación incierta; detenida sin reintento automático' } }}</p>
<p><strong>Intentos registrados:</strong> {{ $job->attempts }}</p>
<p><strong>Resultado del ensayo:</strong>
@if(in_array($invoice->transport_status, ['simulated_accepted','simulated_rejected'], true) && $invoice->simulated_response)
{{ match($invoice->simulated_response['code'] ?? null) { 'SIM-ACCEPTED'=>'Aceptada en simulación', 'SIM-REJECTED'=>'Rechazada en simulación', default=>'Respuesta incompleta; requiere revisión' } }} · {{ $invoice->simulated_response['code'] ?? 'Sin código' }} (exclusivamente ficticio).
@else Sin resultado definitivo confirmado. @endif</p>
<p><strong>Estado fiscal:</strong> No enviado a SUNAT. Sin CDR oficial.</p>
<p>Evidencias privadas:
<a href="{{ route('invoices.download', [$invoice->id, 'xml']) }}">XML firmado</a> ·
<a href="{{ route('invoices.download', [$invoice->id, 'zip']) }}">ZIP</a> ·
<a href="{{ route('invoices.download', [$invoice->id, 'certificate']) }}">Certificado público de prueba</a></p>
@if ($invoice->transport_status === 'pending_simulation')
<form method="post" action="{{ route('invoices.simulate', $invoice->id) }}">
@csrf
<label for="scenario">Respuesta ficticia que se ensayará</label>
<select id="scenario" name="scenario"><option value="accepted">Aceptación simulada</option><option value="rejected">Rechazo simulado</option><option value="uncertain">Sin respuesta (incierta)</option><option value="timeout">Timeout después de registrar respuesta ficticia</option></select>
<button type="submit">Procesar simulación local</button>
</form>
@endif
@if($canRecover)
<form method="post" action="{{ route('invoices.recover', $invoice->id) }}">@csrf
<p>Consulta solo el registro del simulador H3. Puede quedar sin resolver; nunca reenvía ni consulta SUNAT.</p>
<button>Consultar recuperación local</button></form>
@endif
<h2>Historial de intentos y eventos</h2>
@if($events->isEmpty())<p>Aún no hay intentos de transporte.</p>@else
<table><thead><tr><th scope="col">Fecha y zona horaria</th><th scope="col">Intento</th><th scope="col">Evento registrado</th><th scope="col">Atención</th></tr></thead><tbody>
@foreach($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->attempt }}</td><td>{{ match($event->kind) {
'claimed'=>'Intento reservado', 'result'=>'Resultado ficticio registrado', 'uncertain'=>'Resultado incierto',
'recovered'=>'Resultado recuperado del simulador', 'query_unresolved'=>'Consulta sin resultado verificable',
'reservation_expired'=>'Reserva vencida; resultado incierto', 'confirmed_not_processed'=>'No procesamiento acreditado por el simulador',
'retry_exhausted'=>'Intentos agotados', 'late_response_ignored'=>'Respuesta tardía conservada sin confirmar', default=>$event->kind } }}</td><td>{{ $event->attention ? 'Requiere revisión' : '—' }}</td></tr>@endforeach
</tbody></table>{{ $events->links() }}@endif
<p><a href="{{ route('invoices.index') }}">Volver al historial de facturas</a></p>
@if ($invoice->simulated_response)
<p>{{ $invoice->simulated_response['message'] }}</p>
<small>Código ficticio: {{ $invoice->simulated_response['code'] }}</small>
@endif
@endsection
