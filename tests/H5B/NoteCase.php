<?php

namespace Tests\H5B;

use App\Boletas\Preparation as BoletaPreparation;
use App\Boletas\SummaryTransport as BoletaTransport;
use App\Models\Boleta;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class NoteCase extends TestCase
{
    protected function original(?string $date = null): Boleta
    {
        $b = app(BoletaPreparation::class)->issue($this->operator->id, $this->input(['emission_date' => $date ?? now('America/Lima')->toDateString()]));
        $rc = app(BoletaPreparation::class)->freeze($this->operator->id, ['members' => [$b->id], 'idempotency_key' => (string) Str::uuid()]);
        app(BoletaTransport::class)->send($rc->id); app(BoletaTransport::class)->poll($rc->id);
        return $b->fresh();
    }

    protected function noteInput(Boleta $b, array $overrides = []): array
    {
        return $overrides + ['original_id' => $b->id, 'idempotency_key' => (string) Str::uuid(), 'operation' => 'NC01',
            'reason' => 'ANULACION COMPLETA FICTICIA', 'emission_date' => now('America/Lima')->toDateString(), 'original_granted_test' => '1'];
    }

    protected function summary(array $notes, ?string $key = null)
    {
        return app(\App\BoletaNotes\SummaryPreparation::class)->freeze($this->operator->id, ['members' => $notes, 'idempotency_key' => $key ?? (string) Str::uuid()]);
    }
}
