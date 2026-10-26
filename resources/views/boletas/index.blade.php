@extends('layout')
@section('title', 'Boleta y RC ficticios · H4')
@section('milestone', 'H4')
@section('content')
<h1>Boletas y RC ficticios</h1>
<form method="post" action="{{ route('boletas.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<label for="description">Concepto ficticio</label><input id="description" name="description" maxlength="120" required>
<label for="quantity">Cantidad</label><input id="quantity" name="quantity" value="1" required>
<label for="unit_price">Valor unitario PEN</label><input id="unit_price" name="unit_price" value="100.00" required>
<label for="emission_date">Fecha de emisión del ensayo</label><input id="emission_date" type="date" name="emission_date" value="{{ now()->toDateString() }}" required>
<button type="submit">Preparar boleta ficticia</button>
</form>
<h2>Boletas recientes</h2>
<form method="post" action="{{ route('summaries.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
@foreach ($boletas as $boleta)
<label><input style="width:auto" type="checkbox" name="members[]" value="{{ $boleta->id }}"> B000-{{ $boleta->number }} · {{ $boleta->emission_date }}</label>
<a href="{{ route('boletas.show', $boleta->id) }}">Ver boleta</a>
@endforeach
<button type="submit">Congelar RC de miembros seleccionados</button>
</form>
<h2>RC recientes</h2>
@foreach ($summaries as $summary)
<p><a href="{{ route('summaries.show', $summary->id) }}">{{ $summary->name }}</a> · {{ $summary->state }}</p>
@endforeach
@endsection
