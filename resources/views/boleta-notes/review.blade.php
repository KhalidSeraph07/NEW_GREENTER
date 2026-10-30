@extends('layout')
@section('title', 'Revisión RC de notas ficticio')
@section('milestone', 'H5-B')
@section('content')
<h1>Revisar RC de notas ficticio</h1>
<p>Esta revisión no crea documentos, no reserva numeración ni genera archivos.</p>
@if($existing)<p>Esta clave ya corresponde a <a href="{{ route('receipt-notes.show',['kind'=>'rc','id'=>$existing->id]) }}">{{ $existing->name }}</a>; confirmar devuelve el mismo RC.</p>@endif
<p>Fecha de emisión de las notas: {{ $notes->first()->emission_date }} · Generación prevista: {{ $existing?->issue_date ?? now('America/Lima')->toDateString() }}</p>
<table><thead><tr><th scope="col">Nota</th><th scope="col">Referencia original</th><th scope="col">Subtotal PEN</th><th scope="col">IGV de ejemplo PEN</th><th scope="col">Total PEN</th></tr></thead><tbody>
@foreach($preview as $m)<tr><td><a href="{{ route('receipt-notes.show',['kind'=>'note','id'=>$m['id']]) }}">{{ $m['document_type'] }} {{ $m['series'] }}-{{ $m['number'] }}</a></td><td>{{ $m['original_type'] }} {{ $m['original_number'] }}</td>
@foreach(['subtotal_cents','tax_cents','total_cents'] as $amount)<td>{{ App\Invoices\InvoiceInput::decimal($m[$amount]) }}</td>@endforeach</tr>@endforeach</tbody></table>
<p>Importe total seleccionado: {{ App\Invoices\InvoiceInput::decimal(array_sum(array_column($preview,'total_cents'))) }} PEN.</p>
<form method="post" action="{{ route('receipt-notes.freeze') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $form['idempotency_key'] }}">
@foreach($form['members'] as $id)<input type="hidden" name="members[]" value="{{ $id }}">@endforeach
<button>Confirmar y congelar RC de notas</button><button formaction="{{ route('receipt-notes.edit-review') }}">Volver a selección de notas</button>
</form>
@endsection
