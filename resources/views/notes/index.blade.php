@extends('layout')
@section('title', 'Notas de factura ficticias · H5-A')
@section('milestone', 'H5-A')
@section('content')
<h1>Notas de factura ficticias</h1>
<form method="post" action="{{ route('notes.store') }}">@csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
    <label for="original_id">Factura propia aceptada simulada, sin notas anteriores</label>
    <select id="original_id" name="original_id" required>@foreach($invoices as $invoice)
        <option value="{{ $invoice->id }}">F000-{{ $invoice->number }} · {{ App\Invoices\InvoiceInput::decimal($invoice->total_cents) }} PEN</option>
    @endforeach</select>
    <label for="operation">Nota</label><select id="operation" name="operation"><option value="NC01">NC 07 / 01 · Anulación completa</option><option value="ND02">ND 08 / 02 · Aumento de valor</option></select>
    <label for="reason">Sustento ficticio de anulación o incremento</label><input id="reason" name="reason" value="{{ old('reason') }}" required maxlength="250">
    <label for="increment_unit_price">Incremento unitario sin IGV (solo ND; dejar vacío para NC)</label><input id="increment_unit_price" name="increment_unit_price" value="{{ old('increment_unit_price') }}" inputmode="decimal">
    <label for="emission_date">Fecha de emisión de la nota</label><input id="emission_date" name="emission_date" type="date" value="{{ old('emission_date', now('America/Lima')->toDateString()) }}" required>
    <label><input style="width:auto" type="checkbox" name="original_granted_test" value="1" required> Declaro otorgamiento exclusivamente ficticio del original. No acredita entrega real.</label>
    <button type="submit">Preparar nota ficticia</button>
</form>
<h2>Historial reciente</h2><table><thead><tr><th>Nota</th><th>Factura</th><th>Estado simulado</th></tr></thead><tbody>
@foreach($notes as $note)<tr><td><a href="{{ route('notes.show',$note->id) }}">{{ $note->document_type }} {{ $note->series }}-{{ $note->number }}</a></td><td>{{ $note->snapshot['original']['series'] }}-{{ $note->snapshot['original']['number'] }}</td><td>{{ $note->state }}</td></tr>@endforeach
</tbody></table>
@endsection
