<?php

namespace App\Console\Commands;

use App\Models\AuditoriaAcceso;
use Illuminate\Console\Command;

/** Borra registros de auditoría antiguos. Por defecto solo SIMULA; --confirmar borra de verdad. */
class PodarAuditoria extends Command
{
    protected $signature = 'auditoria:podar
        {--dias= : Days to keep (default auditoria.retencion_dias)}
        {--confirmar : Actually deletes; without this option it only shows what would be deleted}';

    protected $description = 'Deletes audit log records older than N days (simulation by default; --confirmar to delete).';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null && $this->option('dias') !== ''
            ? (int) $this->option('dias') : (int) config('auditoria.retencion_dias', 90);
        if ($dias < 1) {
            $this->error('--dias must be at least 1.');

            return self::FAILURE;
        }

        $corte = now()->subDays($dias);
        $total = AuditoriaAcceso::where('ocurrido_en', '<', $corte)->count();
        $this->line("Cutoff: {$corte->toDateTimeString()} ({$dias} days).");
        $this->info("Records to prune: {$total}".($this->option('confirmar') ? '' : ' (simulation: nothing was deleted)'));

        if ($this->option('confirmar') && $total > 0) {
            $borrados = AuditoriaAcceso::where('ocurrido_en', '<', $corte)->delete();
            $this->info("Deleted: {$borrados}");
        }

        return self::SUCCESS;
    }
}
