<input type="hidden" name="idempotency_key" value="{{ $form['idempotency_key'] ?? (string) Illuminate\Support\Str::uuid() }}">
<input type="hidden" name="operation" value="{{ $operation }}">
<p>Tipo: <strong>{{ $operation === 'ND02' ? 'ND 08 / 02 · Aumento de valor' : 'NC 07 / 01 · Anulación completa' }}</strong></p>
<label for="original_id">{{ $originalLabel }} propia; el servidor revalida la elegibilidad al preparar</label>
<select id="original_id" name="original_id" required><option value="">Selecciona un original</option>
@foreach($options as $source)<option value="{{ $source->id }}" @selected($selected === $source->id) @disabled($reasons[$source->id] && $selected !== $source->id)>{{ $sourceSeries }}-{{ $source->number }} · {{ App\Invoices\InvoiceInput::decimal($source->snapshot['total_cents']) }} PEN{{ $reasons[$source->id] ? ' · '.$reasons[$source->id] : '' }}</option>@endforeach</select>
@if($selected && isset($reasons[$selected]) && $reasons[$selected])<p role="status">{{ $reasons[$selected] }}</p>@endif
<label for="reason">Sustento ficticio de {{ $operation === 'ND02' ? 'incremento' : 'anulación completa' }}</label><input id="reason" name="reason" value="{{ $form['reason'] ?? '' }}" required minlength="3" maxlength="250">
@if($operation === 'ND02')
<label for="increment_unit_price">Incremento unitario sin IGV, positivo, PEN</label><input id="increment_unit_price" name="increment_unit_price" value="{{ $form['increment_unit_price'] ?? '' }}" inputmode="decimal" required>
@else<p>La NC 01 anula el importe completo del original; no admite importes editables.</p>@endif
<label for="emission_date">Fecha de emisión de la nota</label><input id="emission_date" name="emission_date" type="date" value="{{ $form['emission_date'] ?? now('America/Lima')->toDateString() }}" required>
<label><input style="width:auto" type="checkbox" name="original_granted_test" value="1" @checked(($form['original_granted_test'] ?? null) === '1') required> Declaro otorgamiento exclusivamente ficticio del original. No acredita entrega real.</label>
<button type="submit">Preparar nota ficticia</button>
