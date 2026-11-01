@extends('layout')
@section('title', 'Autorizaciones de lectura del receptor')
@section('milestone', 'H6-4')
@section('content')
<h1>Autorizar lectura de comprobantes propios</h1>
<p>Selecciona expresamente una cuenta RECEPTOR y un comprobante. No se asigna acceso por coincidencia de RUC, DNI o correo. La propiedad y el otorgamiento fiscal no cambian.</p>
@if(session('access_recorded'))<p role="status">Autorización registrada: {{ session('access_recorded') }}.</p>@endif
@if(session('access_revoked'))<p role="status">Autorización revocada: {{ session('access_revoked') }}. Las siguientes solicitudes quedan bloqueadas; las copias ya descargadas no se eliminan.</p>@endif
<form method="get" action="{{ route('recipient-access.index') }}">
<label for="kind">Familia</label><select id="kind" name="kind"><option value="">Todas</option>@foreach(App\Recipients\Access::LABELS as $kind=>$label)<option value="{{ $kind }}" @selected(($filters['kind'] ?? '')===$kind)>{{ $label }}</option>@endforeach</select>
<label for="number">Correlativo</label><input id="number" type="number" name="number" min="1" value="{{ $filters['number'] ?? '' }}"><button>Buscar comprobantes propios</button></form>
<form method="post" action="{{ route('recipient-access.store') }}">@csrf
<label for="document">Comprobante propio</label><select id="document" name="document" required><option value="">Selecciona un comprobante</option>@foreach($documents as $d)<option value="{{ $d->kind.':'.$d->id }}" @selected(old('document')===$d->kind.':'.$d->id)>{{ App\Recipients\Access::LABELS[$d->kind] }} · {{ $d->series }}-{{ $d->number }}</option>@endforeach</select>
<label for="recipient_id">Cuenta RECEPTOR activa</label><select id="recipient_id" name="recipient_id" required><option value="">Selecciona un receptor</option>@foreach($recipients as $recipient)<option value="{{ $recipient->id }}" @selected((string)old('recipient_id')===(string)$recipient->id)>{{ $recipient->name }} · {{ $recipient->email }}</option>@endforeach</select>
@if($recipients->isEmpty())<p>ADMIN debe crear una cuenta RECEPTOR desde Cuentas. No hay registro público.</p>@endif
<label><input type="checkbox" name="confirmed" value="1" required @checked(old('confirmed')==='1')> Confirmo expresamente el documento y la cuenta destinataria seleccionados.</label>
<button>Conceder lectura al receptor</button></form>
@if($documents->hasPages())<nav aria-label="Páginas de comprobantes">@if($documents->previousPageUrl())<a href="{{ $documents->previousPageUrl() }}">Página anterior</a>@endif @if($documents->nextPageUrl())<a href="{{ $documents->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
<h2>Autorizaciones emitidas por ti</h2>
<table><thead><tr><th scope="col">Comprobante</th><th scope="col">Cuenta receptora</th><th scope="col">Fecha / estado</th><th scope="col">Acción</th></tr></thead><tbody>
@forelse($grants as $g)<tr><td>{{ App\Recipients\Access::LABELS[$g->kind] }} · {{ $grantDocuments[$g->id] }}<small>{{ $g->id }}</small></td><td>{{ $g->recipient_user_id }}</td><td>{{ $g->created_at }} · {{ $g->revoked_at?'Revocada '.$g->revoked_at:'Vigente' }}</td><td>@if(!$g->revoked_at)<form method="post" action="{{ route('recipient-access.revoke',$g->id) }}">@csrf<button>Revocar autorización</button></form>@endif</td></tr>@empty<tr><td colspan="4">Todavía no has autorizado comprobantes.</td></tr>@endforelse
</tbody></table>
@if($grants->hasPages())<nav aria-label="Páginas de autorizaciones">@if($grants->previousPageUrl())<a href="{{ $grants->previousPageUrl() }}">Página anterior</a>@endif @if($grants->nextPageUrl())<a href="{{ $grants->nextPageUrl() }}">Página siguiente</a>@endif</nav>@endif
@endsection
