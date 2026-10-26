@extends('layout')
@section('title', 'Evidencia ficticia · H4')
@section('milestone', 'H4')
@section('content')
<h1>{{ $kind === 'rc' ? $record->name : 'Boleta ficticia B000-'.$record->number }}</h1>
<p>No enviado a SUNAT. Sin CDR oficial.</p>
@if ($kind === 'boleta')
<p>{{ $record->snapshot['description'] }} · {{ $record->emission_date }} · S/ {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['total_cents']) }}</p>
@if ($membership)<p>Información por RC: {{ $membership->state }} (simulado). <a href="{{ route('summaries.show', $membership->summary_id) }}">Ver RC ficticio</a>.</p>@endif
@else
<p>Estado del ensayo: <strong>{{ $record->state }}</strong></p>
<p>Ticket ficticio: {{ $record->ticket ?? 'No recibido' }}</p>
<p>Referencia: {{ $record->reference_date }} · Generación: {{ $record->issue_date }}</p>
<ul>@foreach ($record->snapshot['members'] as $member)<li>B000-{{ $member['number'] }} · {{ $member['id'] }}</li>@endforeach</ul>
<form method="post" action="{{ route('summaries.simulate', $record->id) }}">
@csrf
<label for="action">Acción local</label><select id="action" name="action"><option value="send">Simular recepción y ticket</option><option value="poll">Consultar ticket ficticio</option><option value="recover">Rearmar consulta con ticket conservado</option></select>
<label for="scenario">Escenario ficticio</label><select id="scenario" name="scenario"><option value="">Predeterminado de la acción</option><option value="lost_ticket">Ticket perdido</option><option value="98">98 en proceso</option><option value="0">0 con constancia de aceptación ficticia</option><option value="99">99 con constancia de rechazo ficticia</option><option value="incomplete">Respuesta incompleta</option></select>
<button type="submit">Ejecutar simulación local</button>
</form>
<h2>Historial</h2><ul>@foreach ($events as $event)<li>{{ $event->created_at }} UTC · {{ $event->kind }}{{ $event->attention ? ' · Requiere atención' : '' }}</li>@endforeach</ul>
@endif
<p>Evidencias privadas: @foreach (['xml', 'zip', 'certificate'] as $file)<a href="{{ route('h4.download', [$kind, $record->id, $file]) }}">{{ $file }}</a> @endforeach</p>
@endsection
