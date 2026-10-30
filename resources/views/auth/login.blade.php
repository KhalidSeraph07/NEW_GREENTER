@extends('layout')
@section('title', 'Acceso local')
@section('content')
<h1>Acceso a la instalación ficticia</h1>
<form method="post" action="{{ route('login.store') }}">@csrf
<label for="email">Correo de la cuenta local</label><input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required>
<label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button>Iniciar sesión</button>
</form>
@endsection
