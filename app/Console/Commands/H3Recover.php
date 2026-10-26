<?php

namespace App\Console\Commands;

use App\Invoices\SimulatedTransport;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class H3Recover extends Command
{
    protected $signature = 'h3:recover {invoice : UUID de factura ficticia}';
    protected $description = 'Consulta exclusivamente el registro del simulador local; nunca reenvia';

    public function handle(SimulatedTransport $transport): int
    {
        if (!Str::isUuid($this->argument('invoice'))) { throw new RuntimeException('UUID invalido.'); }
        $invoice = $transport->recover($this->argument('invoice'));
        $this->line(json_encode(['simulated' => true, 'invoice_id' => $invoice->id,
            'transport_status' => $invoice->transport_status, 'official_cdr' => false], JSON_THROW_ON_ERROR));
        return in_array($invoice->transport_status, ['simulated_accepted', 'simulated_rejected'], true) ? self::SUCCESS : self::FAILURE;
    }
}
