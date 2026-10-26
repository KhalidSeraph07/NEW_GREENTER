@extends('layout')
@section('title', 'Nota ficticia · H5-A')
@section('milestone', 'H5-A')
@section('content')
<h1>Nota ficticia {{ $note->series }}-{{ $note->number }}</h1>
<p>Tipo {{ $note->document_type }} / motivo {{ $note->reason_code }} · {{ $note->emission_date }} · Estado: <strong>{{ $note->state }}</strong></p>
<p>Original: <a href="{{ route('invoices.show',$note->original_id) }}">{{ $note->snapshot['original']['series'] }}-{{ $note->snapshot['original']['number'] }}</a>. Receptor: {{ $note->snapshot['original']['snapshot']['receiver_name'] }}. Moneda: PEN.</p>
<p>{{ $note->snapshot['reason'] }} · Importe {{ App\Invoices\InvoiceInput::decimal($note->snapshot['total_cents']) }} PEN.</p>
<p>Otorgamiento del original declarado ficticio. Sin CDR oficial; el documento original se conserva.</p>
<p>@foreach(['xml','zip','certificate'] as $kind)<a href="{{ route('notes.download',[$note->id,$kind]) }}">{{ $kind }}</a> · @endforeach</p>
<form method="post" action="{{ route('notes.process',$note->id) }}">@csrf
    <label for="scenario">Respuesta exclusivamente ficticia</label><select id="scenario" name="scenario">@foreach(App\Notes\LocalSimulation::SCENARIOS as $scenario)<option value="{{ $scenario }}">{{ $scenario }}</option>@endforeach</select>
    <button name="action" value="dispatch">Procesar outbox ficticio</button><button name="action" value="recover">Consultar registro del simulador</button>
</form>
@if($note->result)<pre>{{ json_encode($note->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
<h2>Historial</h2><ul>@foreach($events as $event)<li>{{ $event->created_at }} · {{ $event->kind }} @if($event->attention) · Requiere revisión @endif</li>@endforeach</ul>
@endsection
