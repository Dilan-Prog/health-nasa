<?php

namespace App\Console\Commands;

use App\Models\Astronauta;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TokenDispositivo extends Command
{
    protected $signature = 'reloj:token {astronauta : ID del tripulante}';

    protected $description = 'Genera (o regenera) el token del reloj de un tripulante. Se muestra una sola vez.';

    public function handle(): int
    {
        $astronauta = Astronauta::find($this->argument('astronauta'));
        if (! $astronauta) {
            $this->error('No existe ese tripulante.');

            return self::FAILURE;
        }

        $token = Str::random(40);
        $astronauta->forceFill(['token_dispositivo' => hash('sha256', $token)])->save();

        $this->info("Token para {$astronauta->nombre} (cualquier token anterior queda inválido):");
        $this->line($token);

        return self::SUCCESS;
    }
}
