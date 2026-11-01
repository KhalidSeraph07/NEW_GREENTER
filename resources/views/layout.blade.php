<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Factura ficticia · H2')</title>
    <style>
        body{font:16px/1.5 system-ui,sans-serif;background:#f4f6f8;color:#182533;margin:0}
        main{max-width:900px;margin:32px auto;padding:24px;background:white;border:1px solid #d6dee5;border-radius:12px}
        nav{display:flex;gap:24px;margin-bottom:24px}a{color:#154a82}h1{font-size:1.6rem}
        .notice{padding:12px;background:#fff4d7;border-left:4px solid #a97400}.errors{color:#9c2525}
        label{display:block;margin:18px 0 5px;font-weight:600}input,select,button{font:inherit;padding:10px;border:1px solid #8a9caa;border-radius:5px}
        input{width:min(95%,580px)}button{background:#164c80;color:white;cursor:pointer;margin-top:20px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px 6px;border-bottom:1px solid #d6dee5}
        .totals{font-variant-numeric:tabular-nums}small{display:block;color:#526373}pre{white-space:pre-wrap;overflow-wrap:anywhere}
        @media(max-width:600px){main{margin:0;padding:16px;border-radius:0}table{font-size:14px}nav{flex-wrap:wrap}}
    </style>
    @yield('head')
</head>
<body><main>
    <p class="notice"><strong>@yield('milestone', 'H2') · Simulación local · Sin validez fiscal.</strong> No se envía a SUNAT.</p>
    @auth
    @if(in_array(auth()->user()->role, ['ADMIN','OPERADOR'], true))
    <nav><a href="{{ route('invoices.index') }}">Historial</a><a href="{{ route('invoices.create') }}">Nueva factura ficticia</a><a href="{{ route('boletas.index') }}">Boletas / RC ficticios</a><a href="{{ route('notes.index') }}">Notas de factura ficticias</a><a href="{{ route('receipt-notes.index') }}">Notas de boleta / RC ficticios</a><a href="{{ route('voids.index') }}">Bajas ficticias</a><a href="{{ route('recipient-access.index') }}">Accesos de receptor</a><a href="{{ route('settings') }}">Configuración ficticia</a>@if(auth()->user()->role === 'ADMIN')<a href="{{ route('accounts.index') }}">Cuentas</a>@endif</nav>
    @elseif(auth()->user()->role === 'RECEPTOR')<nav><a href="{{ route('recipient.index') }}">Mis comprobantes</a></nav>@endif
    <form method="post" action="{{ route('logout') }}">@csrf<button>Cerrar sesión</button></form>
    @endauth
    @if ($errors->any())
        <div class="errors" role="alert"><p>Revisa los datos:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main></body>
</html>
