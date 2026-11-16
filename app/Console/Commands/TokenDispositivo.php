<?php

namespace App\Console\Commands;

use App\Models\Astronauta;
use App\Services\Reloj\TokenReloj;
use Illuminate\Console\Command;

class TokenDispositivo extends Command
{
    protected $signature = 'reloj:token {astronauta : Crew member ID} {--dias= : Validity in days (default mision.reloj_token_dias)}';

    protected $description = 'Generates (or regenerates) the watch token and its signing secret. They are shown only once; the previous token stays valid during the grace period.';

    public function handle(TokenReloj $tokens): int
    {
        $astronauta = Astronauta::find($this->argument('astronauta'));
        if (! $astronauta) {
            $this->error('That crew member does not exist.');

            return self::FAILURE;
        }

        $dias = $this->option('dias');
        if ($dias !== null && (! ctype_digit((string) $dias) || (int) $dias < 1)) {
            $this->error('--dias must be a positive integer.');

            return self::FAILURE;
        }

        $r = $tokens->emitir($astronauta, $dias !== null ? (int) $dias : null);

        $this->info("Token for {$astronauta->nombre} (expires {$r['vence_en']->format('Y-m-d H:i')}):");
        $this->line($r['token']);
        $this->info('Signing secret (X-Firma). Save it now: it will not be shown again:');
        $this->line($r['secreto']);
        if ($r['gracia_hasta']) {
            $this->comment('The previous token stays valid until '.$r['gracia_hasta']->format('Y-m-d H:i').' (grace period).');
        }

        return self::SUCCESS;
    }
}
