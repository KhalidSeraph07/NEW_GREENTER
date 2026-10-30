@extends('layout')
@section('title', 'Revisar RC ficticio')
@section('milestone', 'H4')
@section('content')
<h1>Revisar resumen diario ficticio</h1>
<p>Durante esta revisión no se crea un RC, no se reserva numeración ni se generan archivos.</p>
@if($existing)<p>Esta clave ya corresponde a <a href="{{ route('summaries.show', $existing->id) }}">{{ $existing->name }}</a>. Confirmar devolverá ese mismo RC.</p>@endif
<p>Fecha de emisión de los miembros: <strong>{{ $receipts->first()->emission_date }}</strong></p>
<p>Generación prevista: {{ $existing?->issue_date ?? now('America/Lima')->toDateString() }}. El servidor vuelve a comprobar la elegibilidad al confirmar.</p>
<table class="totals"><thead><tr><th scope="col">Boleta</th><th scope="col">Subtotal PEN</th><th scope="col">IGV de ejemplo PEN</th><th scope="col">Total PEN</th></tr></thead><tbody>
@foreach($receipts as $receipt)<tr><td><a href="{{ route('boletas.show', $receipt->id) }}">B000-{{ $receipt->number }}</a> · {{ $receipt->snapshot['description'] }}</td>
@foreach(['subtotal_cents','tax_cents','total_cents'] as $amount)<td>{{ \App\Invoices\InvoiceInput::decimal($receipt->snapshot[$amount]) }}</td>@endforeach</tr>@endforeach
</tbody><tfoot><tr><th scope="row">Total del RC</th>
@foreach(['subtotal_cents','tax_cents','total_cents'] as $amount)<td>{{ \App\Invoices\InvoiceInput::decimal($receipts->sum(fn($r) => $r->snapshot[$amount])) }}</td>@endforeach
</tr></tfoot></table>
<form method="post" action="{{ route('summaries.store') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $form['idempotency_key'] }}">
@foreach($form['members'] as $id)<input type="hidden" name="members[]" value="{{ $id }}">@endforeach
<button>Confirmar y congelar RC ficticio</button>
<button formaction="{{ route('summaries.edit-review') }}">Volver a selección</button>
</form>
@endsection
