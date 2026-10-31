@extends('layout')
@section('title', 'Notas de boleta ficticias · H5-B')
@section('milestone', 'H5-B')
@section('content')
<h1>Notas de boleta ficticias</h1>
<p><a href="{{ route('receipt-notes.index', ['operation'=>'NC01']) }}">NC 07 / 01 · Anulación completa</a> · <a href="{{ route('receipt-notes.index', ['operation'=>'ND02']) }}">ND 08 / 02 · Aumento de valor</a></p>
<form method="post" action="{{ route('receipt-notes.store') }}">@csrf
@include('notes.form-fields', ['originalLabel'=>'Boleta','sourceSeries'=>'B000'])
</form>
<h2>Buscar originales y notas anteriores</h2>
<form method="get" action="{{ route('receipt-notes.index') }}">
<input type="hidden" name="operation" value="{{ $operation }}">
<label for="original_number">Número del original B000</label><input type="number" id="original_number" name="original_number" min="1" value="{{ $filters['original_number'] ?? '' }}">
<label for="note_number">Número de la nota</label><input type="number" id="note_number" name="note_number" min="1" value="{{ $filters['note_number'] ?? '' }}">
<label for="notes_date">Fecha de emisión de notas</label><input type="date" id="notes_date" name="notes_date" value="{{ $filters['notes_date'] ?? '' }}">
<label for="rc_name">Nombre exacto del RC de notas</label><input id="rc_name" name="rc_name" maxlength="80" value="{{ $filters['rc_name'] ?? '' }}">
<button>Buscar</button> <a href="{{ route('receipt-notes.index') }}">Limpiar filtros</a>
</form>
@if($originals->hasPages())<nav aria-label="Páginas de originales">@if($originals->previousPageUrl())<a href="{{ $originals->previousPageUrl() }}">Originales anteriores</a>@endif @if($originals->nextPageUrl())<a href="{{ $originals->nextPageUrl() }}">Más originales</a>@endif</nav>@endif
<h2>Seleccionar notas para un nuevo RC</h2>
<form method="post" action="{{ route('receipt-notes.review') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $summaryForm['idempotency_key'] ?? (string) Illuminate\Support\Str::uuid() }}">
<p>Hasta 100 notas propias de una misma fecha. Las reservas anteriores se conservan.</p>
@forelse($noteOptions as $n)<label><input style="width:auto" type="checkbox" name="members[]" value="{{ $n->id }}" @checked(in_array($n->id,$members,true)) @disabled($memberReasons[$n->id])>{{ $n->document_type }} {{ $n->series }}-{{ $n->number }} · {{ $n->emission_date }} · {{ App\Invoices\InvoiceInput::decimal($n->snapshot['total_cents']) }} PEN</label>
@if($memberReasons[$n->id])<small>{{ $memberReasons[$n->id] }}</small>@if(in_array($n->id,$members,true))<input type="hidden" name="members[]" value="{{ $n->id }}">@endif @endif
@empty<p>No hay notas para seleccionar.</p>@endforelse
<button>Revisar RC de notas</button></form>
<h2>Historial de notas</h2><table><thead><tr><th scope="col">Nota</th><th scope="col">Original</th><th scope="col">Estado del ensayo</th></tr></thead><tbody>
@forelse($notes as $note)<tr><td><a href="{{ route('receipt-notes.show',['kind'=>'note','id'=>$note->id]) }}">{{ $note->document_type }} {{ $note->series }}-{{ $note->number }}</a></td><td><a href="{{ route('boletas.show',$note->original_id) }}">B000-{{ $note->snapshot['original']['number'] }}</a></td><td>{{ $note->state }} · El resultado de comunicación se consulta en su RC.</td></tr>@empty<tr><td colspan="3">No hay notas para estos filtros.</td></tr>@endforelse
</tbody></table>
@if($notes->hasPages())<nav aria-label="Páginas de notas">@if($notes->previousPageUrl())<a href="{{ $notes->previousPageUrl() }}">Notas anteriores</a>@endif @if($notes->nextPageUrl())<a href="{{ $notes->nextPageUrl() }}">Más notas</a>@endif</nav>@endif
<h2>Historial de RC de notas</h2><ul>@forelse($summaries as $s)<li><a href="{{ route('receipt-notes.show',['kind'=>'rc','id'=>$s->id]) }}">{{ $s->name }}</a> · {{ $s->reference_date }} · {{ $s->state }} (simulado)</li>@empty<li>No hay RC para estos filtros.</li>@endforelse</ul>
@if($summaries->hasPages())<nav aria-label="Páginas de RC">@if($summaries->previousPageUrl())<a href="{{ $summaries->previousPageUrl() }}">RC anteriores</a>@endif @if($summaries->nextPageUrl())<a href="{{ $summaries->nextPageUrl() }}">Más RC</a>@endif</nav>@endif
@endsection
