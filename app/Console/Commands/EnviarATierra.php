<?php

namespace App\Console\Commands;

use App\Services\Salud\EnvioATierra;
use Illuminate\Console\Command;

class EnviarATierra extends Command
{
    protected $signature = 'tierra:enviar';

    protected $description = 'Sends pending alerts to the ground if a link is available';

    public function handle(EnvioATierra $envio): int
    {
        if (! $envio->enlaceActivo()) {
            $this->warn("No link to the ground. Pending: {$envio->pendientes()}");

            return self::SUCCESS;
        }
        $this->info("Sent: {$envio->enviarPendientes()}. Pending: {$envio->pendientes()}");

        return self::SUCCESS;
    }
}
