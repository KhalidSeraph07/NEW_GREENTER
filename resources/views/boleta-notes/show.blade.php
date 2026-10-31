@extends('layout')
@section('title', 'Nota / RC ficticio · H5-B')
@section('milestone', 'H5-B')
@section('content')
<h1>{{ $rc ? $record->name : $record->series.'-'.$record->number }}</h1>
<p>Estado: <strong>{{ $record->state }}</strong>. Sin CDR oficial.</p>
@if(!$rc)
<p>Tipo {{ $record->document_type }} / motivo {{ $record->reason_code }} · {{ $record->emission_date }} · {{ App\Invoices\InvoiceInput::decimal($record->snapshot['total_cents']) }} PEN</p>
<p>Original: <a href="{{ route('boletas.show',$record->original_id) }}">B000-{{ $record->snapshot['original']['number'] }}</a>. {{ $record->snapshot['reason'] }}. Otorgamiento exclusivamente ficticio.</p>
@if($member)<p>Resultado de comunicación: <a href="{{ route('receipt-notes.show',['kind'=>'rc','id'=>$member->summary_id]) }}">Consultar RC de esta nota</a> · {{ $member->state }} (simulado). El resultado corresponde al RC, no a aceptación individual de la nota.</p>@else<p>Nota preparada; aún no incluida en RC.</p>@endif
@else
<p>Fecha de notas: {{ $rc->reference_date }}. Generación RC: {{ $rc->issue_date }}.</p>
<p>Ticket ficticio: {{ $rc->ticket ?? 'No recibido: no permite recuperar por UUID ni reenviar ante incertidumbre.' }}</p>
<ul>@foreach($rc->snapshot['members'] as $m)<li><a href="{{ route('receipt-notes.show',['kind'=>'note','id'=>$m['id']]) }}">{{ $m['document_type'] }} {{ $m['series'] }}-{{ $m['number'] }}</a> → {{ $m['original_type'] }} {{ $m['original_number'] }}</li>@endforeach</ul>
<p>Intentos de envío: {{ $job->send_attempts }} · Consultas: {{ $job->poll_attempts }} · Trabajo: {{ $job->status }}.</p>
<p><strong>Resultado del RC:</strong> @if(($rc->result['simulated'] ?? null) === true && ($rc->result['official_cdr'] ?? null) === false && (($rc->state === 'sim_accepted' && ($rc->result['outcome'] ?? null) === 'accepted') || ($rc->state === 'sim_rejected' && ($rc->result['outcome'] ?? null) === 'rejected'))){{ $rc->state === 'sim_accepted' ? 'Aceptado en simulación' : 'Rechazado en simulación' }}.@else Sin resultado definitivo confirmado. @endif</p>
@if($job->available_at && Illuminate\Support\Carbon::parse($job->available_at)->isFuture())<p>Disponible desde: {{ $job->available_at }} UTC. <a href="{{ route('receipt-notes.show',['kind'=>'rc','id'=>$rc->id]) }}">Actualizar detalle</a></p>@endif
@if($actions['send'])<form method="post" action="{{ route('receipt-notes.process',$rc->id) }}">@csrf
<label for="send_scenario">Recepción ficticia del envío</label><select id="send_scenario" name="scenario"><option value="accepted">Con ticket ficticio</option><option value="lost_ticket">Ticket perdido</option><option value="incomplete">Respuesta incompleta</option><option value="exception">Error de comunicación simulado</option></select>
<button name="action" value="send">Simular envío RC</button></form>@endif
@if($actions['poll'])<form method="post" action="{{ route('receipt-notes.process',$rc->id) }}">@csrf
<label for="poll_scenario">Respuesta ficticia de consulta</label><select id="poll_scenario" name="scenario"><option value="98">98 en proceso</option><option value="0">0 con constancia de aceptación ficticia</option><option value="99">99 con constancia de rechazo ficticia</option><option value="timeout">Timeout de consulta</option><option value="incomplete">Respuesta incompleta</option></select>
<button name="action" value="poll">Consultar ticket ficticio</button></form>@endif
@if($actions['recover'])<form method="post" action="{{ route('receipt-notes.process',$rc->id) }}">@csrf
<p>Solo rearma consultas con ticket conservado o registra incertidumbre de una reserva vencida. No reenvía ni consulta SUNAT.</p>
<button name="action" value="recover">Rearmar consulta con ticket conservado</button></form>@endif
@if($rc->state === 'uncertain')<p role="status">Resultado incierto; no se permite reenviar. {{ $rc->ticket ? 'Conserva el ticket y revisa el historial.' : 'Sin ticket no hay consulta ni recuperación del resultado.' }}</p>@endif
@if($rc->result)<pre>{{ json_encode($rc->result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
<h2>Historial de intentos y eventos</h2>
@forelse($events as $e)<details><summary>{{ $e->created_at }} UTC · {{ $e->kind }}{{ $e->attention ? ' · Requiere revisión' : '' }}</summary><p>Token: {{ $e->token ?? 'No registrado' }}</p><pre>{{ $e->payload }}</pre></details>@empty<p>Aún no hay intentos.</p>@endforelse
@if($events->hasPages())<nav aria-label="Páginas de eventos">@if($events->previousPageUrl())<a href="{{ $events->previousPageUrl() }}">Eventos anteriores</a>@endif @if($events->nextPageUrl())<a href="{{ $events->nextPageUrl() }}">Más eventos</a>@endif</nav>@endif
@endif
<p>@foreach(['xml','zip','certificate'] as $file)<a href="{{ route('receipt-notes.download',[$kind,$record->id,$file]) }}">{{ $file }}</a> · @endforeach</p>
<p><a href="{{ route('receipt-notes.index') }}">Volver a notas de boleta y RC</a></p>
@endsection
