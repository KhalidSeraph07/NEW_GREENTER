@extends('layout')
@section('content')
<h1>Cuentas locales</h1>
<form method="post" action="{{ route('accounts.store') }}">@csrf
<label for="name">Nombre</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="120">
<label for="email">Correo</label><input id="email" type="email" name="email" value="{{ old('email') }}" required>
<label for="role">Rol fijo</label><select id="role" name="role"><option>OPERADOR</option><option>ADMIN</option></select>
<label for="password">Contraseña inicial (mínimo 16 caracteres, mayúsculas, minúsculas y números)</label><input id="password" type="password" name="password" autocomplete="new-password" required>
<label for="password_confirmation">Confirmar contraseña</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
<button>Crear cuenta</button></form>
<table><thead><tr><th>Cuenta</th><th>Rol</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@foreach($users as $user)<tr><td>{{ $user->name }} · {{ $user->email }}</td><td>{{ $user->role }}</td><td>{{ $user->is_active ? 'Activa' : 'Desactivada' }}</td><td>
@if($user->is_active)<form method="post" action="{{ route('accounts.deactivate',$user->id) }}">@csrf<button>Desactivar {{ $user->email }}</button></form>@endif
</td></tr>@endforeach</tbody></table>{{ $users->links() }}
@endsection
