@extends('layout')
@section('title', 'Evidencia ficticia · H4')
@section('milestone', 'H4')
@section('content')
<h1>{{ $kind === 'rc' ? $record->name : 'Boleta ficticia B000-'.$record->number }}</h1>
<p>No enviado a SUNAT. Sin CDR oficial.</p>
@if ($kind === 'boleta')
<p><strong>Preparación:</strong> Boleta preparada y congelada localmente.</p>
<p>{{ $record->snapshot['description'] }} · {{ $record->emission_date }}</p>
<p>Cantidad: {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['quantity_milli'], 3) }} · Valor unitario PEN: {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['unit_price_cents']) }}</p>
<p>Subtotal: {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['subtotal_cents']) }} · IGV de ejemplo: {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['tax_cents']) }} · Total: S/ {{ \App\Invoices\InvoiceInput::decimal($record->snapshot['total_cents']) }}</p>
@if ($membership)
<p><strong>Situación de la boleta:</strong> Reservada en <a href="{{ route('summaries.show', $membership->summary_id) }}">RC ficticio</a> · {{ $membership->state }} (simulado). El resultado corresponde al RC que contiene esta boleta, no a una aceptación individual oficial.</p>
@else<p><strong>Situación de la boleta:</strong> Aún no incluida en un RC.</p>@endif
@else
<p><strong>Preparación:</strong> RC congelado; miembros y evidencias conservados.</p>
<p><strong>Estado de transporte del ensayo:</strong> <strong>{{ $record->state }}</strong></p>
<p>Ticket ficticio: {{ $record->ticket ?? 'No recibido' }}</p>
<p>Fecha informada: {{ $record->reference_date }} · Generación: {{ $record->issue_date }}</p>
<p><strong>Resultado del RC:</strong>
@if(($record->result['simulated'] ?? false) === true && ($record->result['official_cdr'] ?? null) === false
    && (($record->state === 'sim_accepted' && ($record->result['outcome'] ?? null) === 'accepted') || ($record->state === 'sim_rejected' && ($record->result['outcome'] ?? null) === 'rejected')))
{{ ($record->result['outcome'] ?? '') === 'accepted' ? 'Aceptado en simulación' : 'Rechazado en simulación' }}. Solo constancia ficticia.
@else Sin resultado definitivo confirmado. @endif</p>
<p>Intentos de envío: {{ $job->send_attempts }} · Consultas: {{ $job->poll_attempts }} · Estado de trabajo: {{ $job->status }}</p>
@if($job->available_at && \Illuminate\Support\Carbon::parse($job->available_at)->isFuture())<p>Próxima consulta habilitable: {{ $job->available_at }} UTC. <a href="{{ route('summaries.show', $record->id) }}">Actualizar detalle</a></p>@endif
<ul>@foreach ($record->snapshot['members'] as $member)<li><a href="{{ route('boletas.show', $member['id']) }}">B000-{{ $member['number'] }}</a> · {{ $member['id'] }}</li>@endforeach</ul>
@if($actions['send'])
<form method="post" action="{{ route('summaries.simulate', $record->id) }}">@csrf
<input type="hidden" name="action" value="send">
<label for="send_scenario">Recepción ficticia del envío</label><select id="send_scenario" name="scenario"><option value="accepted">Con ticket ficticio</option><option value="lost_ticket">Ticket perdido</option><option value="incomplete">Respuesta incompleta</option><option value="exception">Error de comunicación simulado</option></select>
<button>Procesar envío ficticio del RC</button></form>
@endif
@if($actions['poll'])
<form method="post" action="{{ route('summaries.simulate', $record->id) }}">@csrf
<input type="hidden" name="action" value="poll">
<label for="poll_scenario">Respuesta ficticia de la consulta</label><select id="poll_scenario" name="scenario"><option value="98">98 en proceso</option><option value="0">0 con constancia de aceptación ficticia</option><option value="99">99 con constancia de rechazo ficticia</option><option value="timeout">Timeout de consulta</option><option value="incomplete">Respuesta incompleta</option></select>
<button>Consultar ticket ficticio</button></form>
@endif
@if($actions['recover'])
<form method="post" action="{{ route('summaries.simulate', $record->id) }}">@csrf
<input type="hidden" name="action" value="recover">
<p>Recuperación H4: una reserva vencida pasa a incierta. Solo un ticket ya conservado permite rearmar una consulta; nunca reenvía el RC.</p>
<button>Revisar recuperación local</button></form>
@endif
@if($record->state === 'uncertain')<p role="status">Resultado incierto. No se permite reenviar. {{ $record->ticket ? 'Conserva el ticket y revisa el historial.' : 'Sin ticket conservado no hay consulta ni recuperación del resultado.' }}</p>@endif
<h2>Historial de intentos y eventos</h2>
@forelse ($events as $event)
<details><summary>{{ $event->created_at }} UTC · {{ $event->kind }}{{ $event->attention ? ' · Requiere atención' : '' }}</summary>
<p>Token de intento: {{ $event->token ?? 'No registrado' }}</p><pre>{{ $event->payload }}</pre></details>
@empty<p>Aún no hay intentos de transporte.</p>@endforelse
@if($events->hasPages())<nav aria-label="Páginas de eventos">
@if($events->previousPageUrl())<a rel="prev" href="{{ $events->previousPageUrl() }}">Eventos anteriores</a>@endif
@if($events->nextPageUrl())<a rel="next" href="{{ $events->nextPageUrl() }}">Más eventos</a>@endif
</nav>@endif
@endif
<p>Evidencias privadas: @foreach (['xml'=>'XML firmado', 'zip'=>'ZIP', 'certificate'=>'Certificado público de prueba'] as $file=>$label)<a href="{{ route('h4.download', [$kind, $record->id, $file]) }}">{{ $label }}</a> @endforeach</p>
<p><a href="{{ route('boletas.index') }}">Volver a boletas y RC</a></p>
@endsection
