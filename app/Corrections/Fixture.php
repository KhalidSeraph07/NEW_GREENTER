<?php
declare(strict_types=1);
namespace App\Corrections;

use Illuminate\Support\Facades\DB;
use LogicException;

final class Fixture
{
    public function prepare(string $family,int $user,array $input): mixed
    {
        Family::guard();if(DB::transactionLevel()!==0){throw new LogicException('Fixture sin transaccion externa.');}
        return PackingDefect::fixture(fn()=>match($family){
            'invoice'=>app(\App\Invoices\InvoiceIssuer::class)->issue($user,$input),
            'h4'=>app(\App\Boletas\Preparation::class)->freeze($user,$input),
            'h5b'=>app(\App\BoletaNotes\SummaryPreparation::class)->freeze($user,$input),
            'void'=>app(\App\Voids\Preparation::class)->freeze($user,$input),
            default=>throw new LogicException('Fixture fuera de alcance.')});
    }
}
