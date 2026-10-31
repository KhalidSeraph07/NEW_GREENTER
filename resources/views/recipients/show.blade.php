@extends('layout')
@section('title', 'Comprobante ficticio reservado al receptor')
@section('milestone', 'H6-4')
@section('head')
<style>@media print{nav,form,.no-print{display:none!important}body{background:white}main{margin:0;border:0;max-width:none}.notice{color:black;background:white;border:2px solid black}}</style>
@endsection
@section('content')
<h1>{{ $document['label'] }} ficticia {{ $document['number'] }}</h1>
<p><strong>SIMULACIÓN LOCAL · SIN VALIDEZ FISCAL.</strong> Sin CDR oficial. Esta vista HTML no es una representación impresa fiscalmente validada.</p>
<p>Tipo: {{ $document['type'] }} · Fecha: {{ $document['date'] }} · Moneda: {{ $document['currency'] }}.</p>
<p>Emisor conservado: {{ $document['issuer']['name'] }} · {{ $document['issuer']['id'] }}</p>
<p>Receptor conservado en el XML: {{ $document['receiver']['name'] }} · {{ $document['receiver']['id'] }}</p>
@if($document['original'])<p>Referencia original: {{ $document['original']['number'] }}.</p>@endif
@if($document['reason'])<p>Motivo conservado: {{ $document['reason_code'] }} · {{ $document['reason'] }}</p>@endif
<table><thead><tr><th scope="col">Descripción</th><th scope="col">Cantidad</th><th scope="col">Valor unitario {{ $document['currency'] }}</th></tr></thead><tbody><tr><td>{{ $document['description'] }}</td><td>{{ App\Invoices\InvoiceInput::decimal($document['quantity_milli'],3) }}</td><td>{{ App\Invoices\InvoiceInput::decimal($document['unit_price_cents']) }}</td></tr></tbody></table>
<dl><dt>Subtotal conservado</dt><dd>{{ App\Invoices\InvoiceInput::decimal($document['subtotal_cents']) }} {{ $document['currency'] }}</dd><dt>IGV del ensayo conservado</dt><dd>{{ App\Invoices\InvoiceInput::decimal($document['tax_cents']) }} {{ $document['currency'] }}</dd><dt>Total conservado</dt><dd><strong>{{ App\Invoices\InvoiceInput::decimal($document['total_cents']) }} {{ $document['currency'] }}</strong></dd></dl>
<p>Datos de la instantánea y XML conservados, sin recalcular ni modificar el comprobante. Consultar o descargar no acredita ni modifica el otorgamiento fiscal.</p>
<div class="no-print">
@if($print)<p>Usa Imprimir del navegador (Ctrl+P o ⌘P). No se genera un PDF ni se confirma que hayas impreso físicamente.</p>@else<p><a href="{{ route('recipient.print',$grant->id) }}">Abrir vista imprimible HTML</a></p>@endif
<p><a href="{{ route('recipient.download',['id'=>$grant->id,'file'=>'xml']) }}">Descargar XML</a> · <a href="{{ route('recipient.download',['id'=>$grant->id,'file'=>'zip']) }}">Descargar ZIP</a> · <a href="{{ route('recipient.index') }}">Volver a mis comprobantes</a></p>
</div>
@endsection
