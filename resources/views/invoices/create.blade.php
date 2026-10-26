@extends('layout')
@section('content')
<h1>Nueva factura ficticia</h1>
<p>Emisor y receptor de prueba: RUC 00000000000 · F000 · PEN · Contado.</p>
<form action="{{ route('invoices.store') }}" method="post">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $operation) }}">
    <label for="description">Concepto de prueba</label>
    <input id="description" name="description" maxlength="120" value="{{ old('description', 'PRODUCTO FICTICIO H2') }}" required>
    <label for="quantity">Cantidad</label>
    <input id="quantity" name="quantity" inputmode="decimal" value="{{ old('quantity', '1') }}" required aria-describedby="quantity-help">
    <small id="quantity-help">Positiva, hasta 3 decimales y menor de 1000. Usa punto decimal.</small>
    <label for="unit_price">Valor unitario sin IGV, en soles</label>
    <input id="unit_price" name="unit_price" inputmode="decimal" value="{{ old('unit_price', '100.00') }}" required aria-describedby="price-help">
    <small id="price-help">Hasta 2 decimales y menor de 1 000 000. IGV de ejemplo: 18%. Totales calculados en el servidor.</small>
    <button type="submit">Preparar factura de prueba</button>
</form>
@endsection
