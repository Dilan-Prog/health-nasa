<?php

namespace App\Console\Commands;

use App\Services\Salud\EnvioATierra;
use Illuminate\Console\Command;

class EnviarATierra extends Command
{
    protected $signature = 'tierra:enviar';

    protected $description = 'Envía a tierra las alertas pendientes si hay enlace';

    public function handle(EnvioATierra $envio): int
    {
        if (! $envio->enlaceActivo()) {
            $this->warn("Sin enlace con tierra. Pendientes: {$envio->pendientes()}");

            return self::SUCCESS;
        }
        $this->info("Enviadas: {$envio->enviarPendientes()}. Pendientes: {$envio->pendientes()}");

        return self::SUCCESS;
    }
}
