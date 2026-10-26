<?php

declare(strict_types=1);

namespace App\Invoices;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class InvoiceInput
{
    public function validate(array $input): array
    {
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = trim($input['description']);
        }
        $allowed = ['idempotency_key', 'description', 'quantity', 'unit_price', '_token'];
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['invoice' => 'Solo se permiten los campos del formulario ficticio.']);
        }
        $values = Validator::make($input, [
            'idempotency_key' => ['required', 'uuid'],
            'description' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F]/u'],
            'quantity' => ['required', 'string', 'regex:/\A\d{1,3}(?:\.\d{1,3})?\z/'],
            'unit_price' => ['required', 'string', 'regex:/\A\d{1,6}(?:\.\d{1,2})?\z/'],
        ])->validate();
        $quantity = $this->scaled($values['quantity'], 3);
        $price = $this->scaled($values['unit_price'], 2);
        if ($quantity < 1 || $price < 1) {
            throw ValidationException::withMessages(['quantity' => 'Cantidad y precio deben ser positivos.']);
        }
        $subtotal = intdiv($quantity * $price + 500, 1000);
        if ($subtotal < 1) {
            throw ValidationException::withMessages(['unit_price' => 'El subtotal redondeado debe ser al menos 0.01.']);
        }
        $tax = intdiv($subtotal * 18 + 50, 100);
        return [
            'description' => trim($values['description']),
            'quantity_milli' => $quantity,
            'unit_price_cents' => $price,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
        ];
    }

    public static function decimal(int $value, int $scale = 2): string
    {
        $factor = 10 ** $scale;
        return intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    private function scaled(string $value, int $scale): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * (10 ** $scale) + (int) str_pad($fraction, $scale, '0');
    }
}
