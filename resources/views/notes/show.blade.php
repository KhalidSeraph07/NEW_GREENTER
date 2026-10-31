@extends('layout')
@section('title', 'Nota ficticia · H5-A')
@section('milestone', 'H5-A')
@section('content')
<h1>Nota ficticia {{ $note->series }}-{{ $note->number }}</h1>
<p>Tipo {{ $note->document_type }} / motivo {{ $note->reason_code }} · {{ $note->emission_date }} · Estado: <strong>{{ $note->state }}</strong></p>
<p>Original: <a href="{{ route('invoices.show',$note->original_id) }}">{{ $note->snapshot['original']['series'] }}-{{ $note->snapshot['original']['number'] }}</a>. Receptor: {{ $note->snapshot['original']['snapshot']['receiver_name'] }}. Moneda: PEN.</p>
<p>{{ $note->snapshot['reason'] }} · Importe {{ App\Invoices\InvoiceInput::decimal($note->snapshot['total_cents']) }} PEN.</p>
<p>Otorgamiento del original declarado ficticio. Sin CDR oficial; el documento original se conserva.</p>
<p>@foreach(['xml'=>'XML firmado','zip'=>'ZIP','certificate'=>'Certificado público de prueba'] as $kind=>$label)<a href="{{ route('notes.download',[$note->id,$kind]) }}">{{ $label }}</a> · @endforeach</p>
<p><strong>Preparación:</strong> Nota congelada localmente. La aceptación del original no confirma la nota.</p>
<p><strong>Transporte:</strong> {{ $job->status }} · Intentos: {{ $job->attempts }}.</p>
<p><strong>Resultado:</strong> @if(in_array($note->state,['sim_accepted','sim_rejected'],true) && ($note->result['simulated'] ?? null) === true && ($note->result['official_cdr'] ?? null) === false && ($note->result['outcome'] ?? null) === ($note->state === 'sim_accepted' ? 'accepted' : 'rejected')){{ $note->state === 'sim_accepted' ? 'Aceptada en simulación' : 'Rechazada en simulación' }}.@else Sin resultado definitivo confirmado. @endif</p>
@if($note->emission_date < now('America/Lima')->subDays(3)->toDateString() && in_array($note->state,['pending','retry_wait'],true))<p>Ventana D+3 del ensayo vencida; envío bloqueado.</p>@endif
@if($job->available_at)<p>Disponible desde: {{ $job->available_at }} UTC. <a href="{{ route('notes.show',$note->id) }}">Actualizar detalle</a></p>@endif
@if($actions['dispatch'])<form method="post" action="{{ route('notes.process',$note->id) }}">@csrf
<label for="scenario">Respuesta exclusivamente ficticia</label><select id="scenario" name="scenario">@foreach(App\Notes\LocalSimulation::SCENARIOS as $scenario)<option value="{{ $scenario }}">{{ match($scenario) {'accepted'=>'Aceptación simulada','rejected'=>'Rechazo simulado','uncertain'=>'Sin respuesta (incierta)','timeout'=>'Timeout tras registrar respuesta ficticia','incomplete'=>'Respuesta incompleta','wrong_reference'=>'Referencia incorrecta','wrong_hash'=>'Huella incorrecta','non_simulated'=>'Respuesta sin marca ficticia','unexpected_error'=>'Error de comunicación simulado','not_processed'=>'No procesamiento acreditado por el simulador',default=>$scenario} }}</option>@endforeach</select>
<button name="action" value="dispatch">Procesar outbox ficticio</button></form>@endif
@if($actions['recover'])<form method="post" action="{{ route('notes.process',$note->id) }}">@csrf
<p>Consulta solo el registro del simulador H5-A; puede quedar sin resolver. No demuestra recuperación oficial SUNAT ni reenvía la nota.</p>
<button name="action" value="recover">Consultar registro del simulador</button></form>@endif
@if($note->state === 'uncertain')<p role="status">Resultado incierto; no se permite reenviar automáticamente.</p>@endif
@if($note->result)<pre>{{ json_encode($note->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
<h2>Historial de intentos y eventos</h2>
@forelse($events as $event)<details><summary>{{ $event->created_at }} UTC · {{ $event->kind }}{{ $event->attention ? ' · Requiere revisión' : '' }}</summary><p>Token: {{ $event->token ?? 'No registrado' }}</p><pre>{{ $event->payload }}</pre></details>@empty<p>Aún no hay intentos.</p>@endforelse
@if($events->hasPages())<nav aria-label="Páginas de eventos">@if($events->previousPageUrl())<a href="{{ $events->previousPageUrl() }}">Eventos anteriores</a>@endif @if($events->nextPageUrl())<a href="{{ $events->nextPageUrl() }}">Más eventos</a>@endif</nav>@endif
<p><a href="{{ route('notes.index') }}">Volver a notas de factura</a></p>
@endsection
