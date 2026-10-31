@extends('layout')
@section('title', 'Mis comprobantes ficticios')
@section('milestone', 'H6-4')
@section('content')
<h1>Mis comprobantes ficticios</h1>
<p>Solo aparecen autorizaciones explícitas para tu cuenta. La identidad de esta cuenta es de ensayo; no acredita la identidad de un receptor real.</p>
<table><thead><tr><th scope="col">Documento</th><th scope="col">Fecha de emisión</th><th scope="col">Total PEN</th></tr></thead><tbody>
@forelse($grants as $g)@php($d=$documents[$g->id])<tr>@if($d)<td><a href="{{ route('recipient.show',$g->id) }}">{{ $d['label'] }} {{ $d['number'] }}</a></td><td>{{ $d['date'] }}</td><td>{{ App\Invoices\InvoiceInput::decimal($d['total_cents']) }}</td>@else<td colspan="3">Documento bloqueado: su autorización, propietario o evidencia no son verificables. Contacta al emisor.</td>@endif</tr>@empty<tr><td colspan="3">No tienes comprobantes autorizados vigentes.</td></tr>@endforelse
</tbody></table>
@if($grants->hasPages())<nav aria-label="Páginas de mis comprobantes">@if($grants->previousPageUrl())<a href="{{ $grants->previousPageUrl() }}">Página anterior</a>@endif @if($grants->nextPageUrl())<a href="{{ $grants->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
@endsection
