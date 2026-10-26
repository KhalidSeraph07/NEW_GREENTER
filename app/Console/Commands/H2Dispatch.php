<?php

namespace App\Console\Commands;

use App\Invoices\SimulatedTransport;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class H2Dispatch extends Command
{
    protected $signature = 'h2:dispatch {--limit=10} {--scenario=accepted}';
    protected $description = 'Consume outbox solo con respuestas ficticias locales; no conecta a SUNAT';

    public function handle(SimulatedTransport $transport): int
    {
        $limit = $this->option('limit');
        if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100) {
            throw new InvalidArgumentException('Límite técnico entre 1 y 100 trabajos.');
        }
        $processed = 0;
        while ($processed < (int) $limit && $transport->dispatch(null, $this->option('scenario'))) {
            $processed++;
        }
        $this->info("Simulaciones procesadas: $processed. Ningún envío a SUNAT.");
        return self::SUCCESS;
    }
}
