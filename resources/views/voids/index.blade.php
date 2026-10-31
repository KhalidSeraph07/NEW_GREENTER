@extends('layout')
@section('title', 'Bajas ficticias RA / RC operación 3')
@section('milestone', 'H6-3A')
@section('content')
@php($labels=['invoice'=>'Factura','invoice_note'=>'Nota de factura','boleta'=>'Boleta','boleta_note'=>'Nota de boleta'])
<h1>Bajas ficticias RA / RC operación 3</h1>
<p>Solo documentos propios aceptados en simulación y acreditados como no otorgados. No hay comunicación ni CDR oficial SUNAT.</p>
@if(session('grant_recorded'))<p role="status">Hecho ficticio registrado: {{ session('grant_recorded') }}. Se conserva el historial anterior.</p>@endif
<form method="get" action="{{ route('voids.index') }}">
<label for="kind">Tipo de documento</label><select id="kind" name="kind"><option value="">Todos</option>@foreach(['invoice'=>'Factura F','invoice_note'=>'Nota F','boleta'=>'Boleta B','boleta_note'=>'Nota B'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['kind'] ?? '')===$key)>{{ $label }}</option>@endforeach</select>
<label for="number">Correlativo original</label><input id="number" name="number" type="number" min="1" value="{{ $filters['number'] ?? '' }}">
<label for="name">Nombre exacto de comunicación</label><input id="name" name="name" value="{{ $filters['name'] ?? '' }}"><button>Buscar</button>
</form>
<h2>Registrar otorgamiento ficticio</h2>
<form method="post" action="{{ route('voids.grant') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $grantForm['idempotency_key'] ?? Illuminate\Support\Str::uuid() }}">
<label for="document">Documento propio</label><select id="document" name="document" required><option value="">Selecciona un documento</option>
@foreach($options as $token=>$d)<option value="{{ $token }}" @selected(($grantForm['document'] ?? '')===$token) @disabled($grantReasons[$token] && ($grantForm['document'] ?? '')!==$token)>{{ $d->series }}-{{ $d->number }} · {{ $labels[$d->kind] }}{{ $grantReasons[$token]?' · '.$grantReasons[$token]:'' }}</option>@endforeach</select>
<label for="state">Hecho que declaras</label><select id="state" name="state" required><option value="">Selecciona expresamente</option><option value="not_granted" @selected(($grantForm['state'] ?? '')==='not_granted')>No otorgado (not_granted)</option><option value="granted" @selected(($grantForm['state'] ?? '')==='granted')>Otorgado (granted)</option></select>
<label for="provenance">Procedencia explícita del hecho ficticio</label><input id="provenance" name="provenance" minlength="15" maxlength="500" required value="{{ $grantForm['provenance'] ?? '' }}">
<label><input type="checkbox" name="confirmed" value="1" required @checked(($grantForm['confirmed'] ?? '')==='1')> Confirmo este hecho ficticio y su procedencia; no supone acreditación real del otorgamiento.</label>
<small>Un otorgamiento ya acreditado no puede revertirse como no otorgado. No se reconstruyen evidencias históricas.</small><button>Registrar hecho ficticio</button>
</form>
<h2>Seleccionar documentos para baja</h2>
<p>La elegibilidad es provisional. Usa una sola ruta (F → RA; B → RC operación 3) y una misma fecha de emisión.</p>
<form method="post" action="{{ route('voids.review') }}">@csrf
<input type="hidden" name="idempotency_key" value="{{ $form['idempotency_key'] ?? Illuminate\Support\Str::uuid() }}">
<label for="reason">Motivo de la baja ficticia</label><input id="reason" name="reason" minlength="3" maxlength="100" required value="{{ $form['reason'] ?? '' }}">
<table><thead><tr><th scope="col">Seleccionar / documento</th><th scope="col">Otorgamiento acreditado</th><th scope="col">Elegibilidad</th></tr></thead><tbody>
@foreach($options as $token=>$d)<tr><td><label><input type="checkbox" name="members[]" value="{{ $token }}" @checked(in_array($token,$selected,true)) @disabled($reasons[$token] && !in_array($token,$selected,true))> {{ $d->series }}-{{ $d->number }} · {{ $labels[$d->kind] }}</label></td><td>{{ $facts[$token]?->state ?? 'Desconocido' }}</td><td>{{ $reasons[$token] ?? 'Elegible provisionalmente' }}</td></tr>@endforeach
</tbody></table>
<button>Revisar baja ficticia</button></form>
@if($documents->hasPages())<nav aria-label="Páginas de documentos">@if($documents->previousPageUrl())<a href="{{ $documents->previousPageUrl() }}">Página anterior</a>@endif @if($documents->nextPageUrl())<a href="{{ $documents->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
<p>La política del ensayo exige recepción simulada acreditada D+7 y bloqueo técnico desde emisión D+7 (2957). RA exige generación y emisión coincidentes. B-01/2957 sigue pendiente de validación fiscal; desde 2027 se bloquean nuevas preparaciones/envíos hasta revisión expresa.</p>
<h2>Comunicaciones propias</h2>
<table><thead><tr><th scope="col">Comunicación</th><th scope="col">Estado del simulador</th><th scope="col">Ticket</th></tr></thead><tbody>@forelse($batches as $b)<tr><td><a href="{{ route('voids.show',$b->id) }}">{{ $b->name }}</a></td><td>{{ $b->state }}</td><td>{{ $b->ticket ?? 'Sin ticket conservado' }}</td></tr>@empty<tr><td colspan="3">Sin comunicaciones para esta búsqueda.</td></tr>@endforelse</tbody></table>
@if($batches->hasPages())<nav aria-label="Páginas de comunicaciones">@if($batches->previousPageUrl())<a href="{{ $batches->previousPageUrl() }}">Página anterior</a>@endif @if($batches->nextPageUrl())<a href="{{ $batches->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
<h2>Historial de declaraciones propias</h2>
<table><thead><tr><th scope="col">Documento</th><th scope="col">Hecho / fecha</th><th scope="col">Procedencia</th></tr></thead><tbody>@foreach($grants as $g)<tr><td><a href="{{ match($g->kind){'invoice'=>route('invoices.show',$g->document_id),'invoice_note'=>route('notes.show',$g->document_id),'boleta'=>route('boletas.show',$g->document_id),'boleta_note'=>route('receipt-notes.show',['kind'=>'note','id'=>$g->document_id])} }}">{{ $labels[$g->kind] }} · {{ json_decode($g->proof,true)['series'] }}-{{ json_decode($g->proof,true)['number'] }}</a></td><td>{{ $g->state }} · {{ $g->created_at }}</td><td>{{ $g->provenance }}</td></tr>@endforeach</tbody></table>
@if($grants->hasPages())<nav aria-label="Páginas de declaraciones">@if($grants->previousPageUrl())<a href="{{ $grants->previousPageUrl() }}">Página anterior</a>@endif @if($grants->nextPageUrl())<a href="{{ $grants->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
@endsection
