@extends('layout')
@section('title', 'Comunicación de baja ficticia')
@section('milestone', 'H6-3A')
@section('content')
<p><a href="{{ route('voids.index') }}">Volver a bajas y declaraciones</a></p>
<h1>{{ $batch->name }}</h1>
<p>{{ $batch->route==='RA'?'RA':'RC operación 3' }} · Emisión de miembros: {{ $batch->reference_date }} · Generación: {{ $batch->issue_date }} · Motivo: {{ $batch->snapshot['reason'] }}</p>
<p>Preparación: comunicación congelada, XML firmado y ZIP privados. Transporte: <strong>{{ $batch->state }}</strong> · Outbox: {{ $job->status }} / {{ $job->phase }} · Envíos: {{ $job->send_attempts }} · Consultas: {{ $job->poll_attempts }}.</p>
<p>Ticket: {{ $batch->ticket ?? 'Sin ticket conservado' }}. Recibir un ticket no confirma la baja.</p>
@if($integrityIssue)<p class="errors" role="alert">{{ $integrityIssue }} Las acciones están bloqueadas; conservar evidencias.</p>@endif
@if($batch->state==='sim_accepted' && !$integrityIssue)<p><strong>Baja confirmada únicamente en simulación</strong> para los miembros correlacionados. Los documentos originales se conservan íntegros.</p>
@elseif($batch->state==='sim_rejected')<p>Comunicación rechazada en simulación: sin efecto de baja confirmado y sin liberar reservas. La interfaz de correcciones no está habilitada.</p>
@else<p>Sin efecto de baja confirmado. Un resultado incierto no autoriza reenviar; sin ticket no existe recuperación por UUID.</p>@endif
<p>Sin CDR oficial ni aceptación SUNAT. Las consultas de comunicaciones enviadas pueden continuar tras vencer el plazo; la ventana no se reinicia.</p>
<h2>Documentos relacionados</h2>
<table><thead><tr><th scope="col">Documento original</th><th scope="col">No otorgamiento acreditado</th><th scope="col">Recepción simulada</th></tr></thead><tbody>@foreach($batch->snapshot['members'] as $m)<tr><td><a href="{{ match($m['kind']){'invoice'=>route('invoices.show',$m['id']),'invoice_note'=>route('notes.show',$m['id']),'boleta'=>route('boletas.show',$m['id']),'boleta_note'=>route('receipt-notes.show',['kind'=>'note','id'=>$m['id']])} }}">{{ $m['document_type'] }} {{ $m['series'] }}-{{ $m['number'] }}</a></td><td>{{ $m['grant_id'] }}</td><td>{{ $m['acceptance']['received_at'] }}</td></tr>@endforeach</tbody></table>
@if($actions['send'])<form method="post" action="{{ route('voids.process',$batch->id) }}">@csrf<input type="hidden" name="action" value="send"><label for="send-scenario">Escenario de envío ficticio</label><select id="send-scenario" name="scenario">@foreach(['accepted'=>'Ticket ficticio','lost_ticket'=>'Ticket perdido','incomplete'=>'Respuesta incompleta','wrong_hash'=>'Huella incorrecta','duplicate'=>'Respuesta duplicada','exception'=>'Error de comunicación'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><button>Simular envío de baja</button></form>@endif
@if($actions['poll'])<form method="post" action="{{ route('voids.process',$batch->id) }}">@csrf<input type="hidden" name="action" value="poll"><label for="poll-scenario">Escenario de consulta ficticia</label><select id="poll-scenario" name="scenario">@foreach(['0'=>'0 · Aceptación ficticia','98'=>'98 · En proceso','99'=>'99 · Rechazo ficticio','timeout'=>'Timeout','incomplete'=>'Constancia incompleta','wrong_hash'=>'Huella incorrecta','wrong_member'=>'Miembro incorrecto'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><button>Consultar ticket ficticio</button></form>@endif
@if($actions['recover'])<form method="post" action="{{ route('voids.process',$batch->id) }}">@csrf<input type="hidden" name="action" value="recover"><button>Recuperación manual permitida</button><small>Solo rearma consultas con ticket conservado o acredita incertidumbre de una reserva vencida; no reenvía.</small></form>@endif
@if(!$actions['send'] && !$actions['poll'] && !$actions['recover'])<p>Sin acciones disponibles ahora. Si existe espera o un proceso activo, actualiza el detalle.</p>@endif
<p><a href="{{ route('voids.show',$batch->id) }}">Actualizar estado</a> · <a href="{{ route('voids.download',['id'=>$batch->id,'kind'=>'xml']) }}">Descargar XML privado</a> · <a href="{{ route('voids.download',['id'=>$batch->id,'kind'=>'zip']) }}">Descargar ZIP privado</a></p>
@if($batch->result)<details><summary>Resultado ficticio conservado</summary><pre>{{ json_encode($batch->result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
<h2>Historial persistido e incidencias</h2>
<table><thead><tr><th scope="col">Fecha / evento</th><th scope="col">Atención</th><th scope="col">Respuesta conservada</th></tr></thead><tbody>@foreach($events as $e)<tr><td>{{ $e->created_at }} · {{ $e->kind }}</td><td>{{ $e->attention?'Investigar':'Sin incidencia registrada' }}</td><td><details><summary>Ver evidencia del evento</summary><pre>{{ $e->payload }}</pre></details></td></tr>@endforeach</tbody></table>
@if($events->hasPages())<nav aria-label="Páginas de eventos">@if($events->previousPageUrl())<a href="{{ $events->previousPageUrl() }}">Página anterior</a>@endif @if($events->nextPageUrl())<a href="{{ $events->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
@endsection
