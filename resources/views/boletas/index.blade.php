@extends('layout')
@section('title', 'Boletas y RC ficticios')
@section('milestone', 'H4')
@section('content')
<h1>Boletas y RC ficticios</h1>
<h2>Preparar boleta</h2>
<form method="post" action="{{ route('boletas.store') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ $boletaForm['idempotency_key'] ?? \Illuminate\Support\Str::uuid() }}">
<label for="description">Concepto ficticio</label><input id="description" name="description" maxlength="120" value="{{ $boletaForm['description'] ?? '' }}" required>
<label for="quantity">Cantidad</label><input id="quantity" name="quantity" value="{{ $boletaForm['quantity'] ?? '1' }}" required>
<label for="unit_price">Valor unitario PEN</label><input id="unit_price" name="unit_price" value="{{ $boletaForm['unit_price'] ?? '100.00' }}" required>
<label for="emission_date">Fecha de emisión del ensayo</label><input id="emission_date" type="date" name="emission_date" value="{{ $boletaForm['emission_date'] ?? now('America/Lima')->toDateString() }}" required>
<button type="submit">Preparar boleta ficticia</button>
</form>
<h2>Buscar boletas y RC</h2>
<form method="get" action="{{ route('boletas.index') }}">
<label for="filter_date">Fecha de emisión de boletas</label><input id="filter_date" type="date" name="boleta_date" value="{{ $filters['boleta_date'] ?? '' }}">
<label for="filter_number">Número de boleta B000</label><input id="filter_number" type="number" name="boleta_number" min="1" value="{{ $filters['boleta_number'] ?? '' }}">
<label for="rc_name">Nombre exacto del RC</label><input id="rc_name" name="rc_name" maxlength="80" value="{{ $filters['rc_name'] ?? '' }}">
<button>Buscar</button> <a href="{{ route('boletas.index') }}">Limpiar filtros</a>
</form>
<h2>Seleccionar boletas para un RC</h2>
<p>Selecciona hasta 100 boletas propias de una misma fecha. Las reservas anteriores se conservan incluso ante rechazo o incertidumbre.</p>
<form method="post" action="{{ route('summaries.review') }}">
@csrf
<input type="hidden" name="idempotency_key" value="{{ $summaryForm['idempotency_key'] ?? \Illuminate\Support\Str::uuid() }}">
@forelse ($options as $boleta)
<label><input style="width:auto" type="checkbox" name="members[]" value="{{ $boleta->id }}" @checked(in_array($boleta->id, $selected, true)) @disabled($reasons[$boleta->id])>
B000-{{ $boleta->number }} · {{ $boleta->emission_date }} · S/ {{ \App\Invoices\InvoiceInput::decimal($boleta->snapshot['total_cents']) }}</label>
@if($reasons[$boleta->id] && in_array($boleta->id, $selected, true))<input type="hidden" name="members[]" value="{{ $boleta->id }}">@endif
@if($reasons[$boleta->id])<small>{{ $reasons[$boleta->id] }}</small>@endif
<a href="{{ route('boletas.show', $boleta->id) }}">Ver boleta B000-{{ $boleta->number }}</a>
@empty<p>No hay boletas para estos filtros.</p>@endforelse
<button type="submit">Revisar RC seleccionado</button>
</form>
@if($boletas->hasPages())<nav aria-label="Páginas de boletas">
@if($boletas->previousPageUrl())<a rel="prev" href="{{ $boletas->previousPageUrl() }}">boletas anteriores</a>@endif
@if($boletas->nextPageUrl())<a rel="next" href="{{ $boletas->nextPageUrl() }}">Más boletas</a>@endif
</nav>@endif
<h2>Historial de RC</h2>
@forelse ($summaries as $summary)
<p><a href="{{ route('summaries.show', $summary->id) }}">{{ $summary->name }}</a> · Fecha informada: {{ $summary->reference_date }} · {{ $summary->state }} (simulado)</p>
@empty<p>No hay RC para estos filtros.</p>@endforelse
@if($summaries->hasPages())<nav aria-label="Páginas de RC">
@if($summaries->previousPageUrl())<a rel="prev" href="{{ $summaries->previousPageUrl() }}">RC anteriores</a>@endif
@if($summaries->nextPageUrl())<a rel="next" href="{{ $summaries->nextPageUrl() }}">Más RC</a>@endif
</nav>@endif
@endsection
