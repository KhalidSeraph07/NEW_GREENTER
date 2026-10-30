@extends('layout')
@section('content')
<h1>Configuración ficticia · solo lectura</h1>
<dl><dt>Ambiente fiscal</dt><dd>Simulador local. Sin conexión SUNAT ni CDR oficial.</dd>
<dt>Emisor ficticio</dt><dd>{{ config('h2.issuer_name') }} · {{ config('h2.issuer_ruc') }}</dd>
<dt>Moneda / IGV del ensayo</dt><dd>{{ config('h2.currency') }} / {{ config('h2.tax_percent') }}%</dd>
<dt>Serie de factura ficticia</dt><dd>{{ config('h2.series') }}</dd>
<dt>Propiedad documental</dt><dd>Cada cuenta accede únicamente a sus documentos. ADMIN no obtiene acceso global.</dd>
</dl><p>Sin edición de identidad, series, correlativos, certificados o credenciales fiscales.</p>
@endsection
