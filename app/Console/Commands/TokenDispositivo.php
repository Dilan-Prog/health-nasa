<?php

namespace App\Console\Commands;

use App\Models\Astronauta;
use App\Services\Reloj\TokenReloj;
use Illuminate\Console\Command;

class TokenDispositivo extends Command
{
    protected $signature = 'reloj:token {astronauta : ID del tripulante} {--dias= : Días de vigencia (por defecto mision.reloj_token_dias)}';

    protected $description = 'Genera (o regenera) el token del reloj y su secreto de firma. Se muestran una sola vez; el token anterior sigue válido durante la gracia.';

    public function handle(TokenReloj $tokens): int
    {
        $astronauta = Astronauta::find($this->argument('astronauta'));
        if (! $astronauta) {
            $this->error('No existe ese tripulante.');

            return self::FAILURE;
        }

        $dias = $this->option('dias');
        if ($dias !== null && (! ctype_digit((string) $dias) || (int) $dias < 1)) {
            $this->error('--dias debe ser un entero positivo.');

            return self::FAILURE;
        }

        $r = $tokens->emitir($astronauta, $dias !== null ? (int) $dias : null);

        $this->info("Token para {$astronauta->nombre} (vence el {$r['vence_en']->format('Y-m-d H:i')}):");
        $this->line($r['token']);
        $this->info('Secreto de firma (X-Firma). Guárdalo ahora: no se vuelve a mostrar:');
        $this->line($r['secreto']);
        if ($r['gracia_hasta']) {
            $this->comment('El token anterior sigue válido hasta '.$r['gracia_hasta']->format('Y-m-d H:i').' (gracia).');
        }

        return self::SUCCESS;
    }
}
