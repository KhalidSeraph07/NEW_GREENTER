@extends('layout')
@section('title', 'Notas de factura ficticias · H5-A')
@section('milestone', 'H5-A')
@section('content')
<h1>Notas de factura ficticias</h1>
<p><a href="{{ route('notes.index', ['operation'=>'NC01']) }}">NC 07 / 01 · Anulación completa</a> · <a href="{{ route('notes.index', ['operation'=>'ND02']) }}">ND 08 / 02 · Aumento de valor</a></p>
<form method="post" action="{{ route('notes.store') }}">@csrf
@include('notes.form-fields', ['originalLabel'=>'Factura','sourceSeries'=>'F000'])
</form>
<h2>Buscar originales y notas anteriores</h2>
<form method="get" action="{{ route('notes.index') }}">
<input type="hidden" name="operation" value="{{ $operation }}">
<label for="original_number">Número del original F000</label><input type="number" id="original_number" name="original_number" min="1" value="{{ $filters['original_number'] ?? '' }}">
<label for="note_number">Número de la nota</label><input type="number" id="note_number" name="note_number" min="1" value="{{ $filters['note_number'] ?? '' }}">

<button>Buscar</button> <a href="{{ route('notes.index') }}">Limpiar filtros</a>
</form>
@if($originals->hasPages())<nav aria-label="Páginas de originales">@if($originals->previousPageUrl())<a href="{{ $originals->previousPageUrl() }}">Originales anteriores</a>@endif @if($originals->nextPageUrl())<a href="{{ $originals->nextPageUrl() }}">Más originales</a>@endif</nav>@endif

<h2>Historial de notas</h2><table><thead><tr><th scope="col">Nota</th><th scope="col">Original</th><th scope="col">Estado del ensayo</th></tr></thead><tbody>
@forelse($notes as $note)<tr><td><a href="{{ route('notes.show',$note->id) }}">{{ $note->document_type }} {{ $note->series }}-{{ $note->number }}</a></td><td><a href="{{ route('invoices.show',$note->original_id) }}">F000-{{ $note->snapshot['original']['number'] }}</a></td><td>{{ $note->state }}</td></tr>@empty<tr><td colspan="3">No hay notas para estos filtros.</td></tr>@endforelse
</tbody></table>
@if($notes->hasPages())<nav aria-label="Páginas de notas">@if($notes->previousPageUrl())<a href="{{ $notes->previousPageUrl() }}">Notas anteriores</a>@endif @if($notes->nextPageUrl())<a href="{{ $notes->nextPageUrl() }}">Más notas</a>@endif</nav>@endif

@endsection
