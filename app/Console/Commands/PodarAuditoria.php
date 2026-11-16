<?php

namespace App\Console\Commands;

use App\Models\AuditoriaAcceso;
use Illuminate\Console\Command;

/** Borra registros de auditoría antiguos. Por defecto solo SIMULA; --confirmar borra de verdad. */
class PodarAuditoria extends Command
{
    protected $signature = 'auditoria:podar
        {--dias= : Días a conservar (por defecto auditoria.retencion_dias)}
        {--confirmar : Borra de verdad; sin esta opción solo muestra lo que borraría}';

    protected $description = 'Elimina registros de auditoría anteriores a N días (simulación por defecto; --confirmar para borrar).';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null && $this->option('dias') !== ''
            ? (int) $this->option('dias') : (int) config('auditoria.retencion_dias', 90);
        if ($dias < 1) {
            $this->error('--dias debe ser al menos 1.');

            return self::FAILURE;
        }

        $corte = now()->subDays($dias);
        $total = AuditoriaAcceso::where('ocurrido_en', '<', $corte)->count();
        $this->line("Corte: {$corte->toDateTimeString()} ({$dias} días).");
        $this->info("Registros a podar: {$total}".($this->option('confirmar') ? '' : ' (simulación: no se borró nada)'));

        if ($this->option('confirmar') && $total > 0) {
            $borrados = AuditoriaAcceso::where('ocurrido_en', '<', $corte)->delete();
            $this->info("Borrados: {$borrados}");
        }

        return self::SUCCESS;
    }
}
