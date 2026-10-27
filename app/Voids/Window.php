<?php
declare(strict_types=1);
namespace App\Voids;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class Window
{
    public function check(array $member): void
    {
        $today=now('America/Lima')->toDateString();
        if ($today<'2026-01-01' || $today>='2027-01-01') {
            throw ValidationException::withMessages(['window'=>'Politica del ensayo 2026 fuera de vigencia; revisar normativa antes de preparar/enviar.']);
        }
        $received=Carbon::parse($member['acceptance']['received_at'])->timezone('America/Lima')->toDateString();
        if ($today>Carbon::parse($received)->addDays(7)->toDateString()) {
            throw ValidationException::withMessages(['window'=>'Ventana desde recepcion simulada acreditada D+7 vencida.']);
        }
        if ($today>Carbon::parse($member['emission_date'])->addDays(7)->toDateString()) {
            throw ValidationException::withMessages(['window'=>'Bloqueo tecnico conservador desde emision D+7 (2957); no equivale a vencimiento legal desde CDR.']);
        }
    }
}
