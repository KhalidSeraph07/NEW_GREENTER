<?php

namespace Tests\H5A;

use App\Invoices\SimulatedTransport;
use App\Models\Invoice;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class NoteCase extends TestCase
{
    protected function original(): Invoice
    {
        $invoice = $this->issue();
        return app(SimulatedTransport::class)->dispatch($invoice->id)->fresh();
    }

    protected function noteInput(Invoice $invoice, array $overrides = []): array
    {
        return $overrides + ['idempotency_key' => (string) Str::uuid(), 'original_id' => $invoice->id,
            'operation' => 'NC01', 'reason' => 'ANULACION COMPLETA FICTICIA ACORDADA',
            'emission_date' => now('America/Lima')->toDateString(), 'original_granted_test' => '1'];
    }
}
