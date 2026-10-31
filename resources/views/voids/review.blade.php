@extends('layout')
@section('title', 'Revisión de baja ficticia')
@section('milestone', 'H6-3A')
@section('content')
<h1>Revisar baja ficticia {{ $route==='RA'?'RA':'RC operación 3' }}</h1>
<p>Esta revisión no crea documentos, no reserva numeración ni genera archivos.</p>
@if($existing)<p>La clave ya corresponde a <a href="{{ route('voids.show',$existing->id) }}">{{ $existing->name }}</a>; confirmar devuelve la misma comunicación.</p>@endif
<p>Motivo: {{ $form['reason'] }} · Fecha de emisión de los miembros: {{ $preview[0]['emission_date'] }} · Generación {{ $existing?'conservada':'prevista' }}: {{ $existing?->issue_date ?? now('America/Lima')->toDateString() }}</p>
<table><thead><tr><th scope="col">Documento / referencia</th><th scope="col">Generación</th><th scope="col">Recepción de aceptación simulada</th><th scope="col">Total PEN</th></tr></thead><tbody>
@foreach($preview as $m)<tr><td>{{ $m['document_type'] }} {{ $m['series'] }}-{{ $m['number'] }}<small>{{ $m['id'] }}</small>@if($m['original'])<small>Referencia: {{ $m['original']['type'] }} {{ $m['original']['number'] }}</small>@endif</td><td>{{ $m['generation_date'] }}</td><td>{{ $m['acceptance']['received_at'] }}</td><td>{{ App\Invoices\InvoiceInput::decimal($m['total_cents']) }}</td></tr>@endforeach</tbody></table>
<p>Solo no otorgamiento acreditado. B-01/2957 y la revisión de reglas para 2027 permanecen pendientes. El motor vuelve a validar al confirmar.</p>
<form method="post" action="{{ route('voids.freeze') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $form['idempotency_key'] }}"><input type="hidden" name="reason" value="{{ $form['reason'] }}">
@foreach($form['members'] as $token)<input type="hidden" name="members[]" value="{{ $token }}">@endforeach
<button>Confirmar y preparar baja ficticia</button><button formaction="{{ route('voids.edit-review') }}">Volver a selección</button></form>
@endsection
