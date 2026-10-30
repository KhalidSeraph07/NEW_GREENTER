@extends('layout')
@section('content')
<h1>Revisión de factura ficticia</h1>
<p>Emisor y receptor: 00000000000 · F000 · PEN · Contado. Sin validez fiscal.</p>
<p>{{ $values['description'] }}</p>
<dl class="totals"><dt>Cantidad</dt><dd>{{ App\Invoices\InvoiceInput::decimal($values['quantity_milli'], 3) }}</dd>
<dt>Valor unitario sin IGV</dt><dd>{{ App\Invoices\InvoiceInput::decimal($values['unit_price_cents']) }} PEN</dd>
<dt>Subtotal</dt><dd>{{ App\Invoices\InvoiceInput::decimal($values['subtotal_cents']) }} PEN</dd>
<dt>IGV del ensayo (18%)</dt><dd>{{ App\Invoices\InvoiceInput::decimal($values['tax_cents']) }} PEN</dd>
<dt>Total calculado por el servidor</dt><dd><strong>{{ App\Invoices\InvoiceInput::decimal($values['total_cents']) }} PEN</strong></dd></dl>
<p>Aún no se ha creado una factura, firmado un XML ni reservado un correlativo.</p>
<form method="post" action="{{ route('invoices.store') }}">@csrf
@foreach($form as $name=>$value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
<button>Confirmar y preparar factura ficticia</button>
<button formaction="{{ route('invoices.edit-review') }}">Corregir datos</button>
</form>
@endsection
