<?php

namespace App\Console\Commands;

use App\Models\Medicion;
use Illuminate\Console\Command;

/**
 * Borra mediciones en vivo antiguas. Por defecto solo SIMULA: no borra nada sin --confirmar.
 *
 * Reglas de seguridad:
 *  - Solo origen `simulado` y `reloj` (lo que crece sin parar). `historial` y `manual` son la base de
 *    Indicador/Historial: solo se tocan con --origen explícito.
 *  - Nunca borra marcadores de largo plazo (con 'loss' o 'cum' en config/indicadores.php).
 *  - Nunca borra una medición cuya alerta a tierra sigue pendiente (el borrado en cascada la perdería).
 */
class PodarMediciones extends Command
{
    protected $signature = 'mediciones:podar
        {--dias= : Days to keep (default mision.dias_historial)}
        {--origen=simulado,reloj : Origins to prune, comma-separated}
        {--confirmar : Actually deletes; without this option it only shows what would be deleted}';

    protected $description = 'Deletes live measurements older than N days (simulation by default; --confirmar to delete).';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null && $this->option('dias') !== ''
            ? (int) $this->option('dias') : (int) config('mision.dias_historial', 15);
        if ($dias < 1) {
            $this->error('--dias must be at least 1.');

            return self::FAILURE;
        }
        $origenes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('origen')))));
        if ($origenes === []) {
            $this->error('Specify at least one --origen.');

            return self::FAILURE;
        }

        $corte = now()->subDays($dias);
        $largoPlazo = collect(config('indicadores.marcadores', []))
            ->filter(fn ($m) => ! empty($m['loss']) || ! empty($m['cum']))->keys()->all();

        $consulta = fn () => Medicion::query()
            ->where('medido_en', '<', $corte)
            ->whereIn('origen', $origenes)
            ->whereNotIn('indicador', $largoPlazo)
            ->whereDoesntHave('evaluacion.alerta', fn ($q) => $q->where('estado', 'pendiente'));

        $total = $consulta()->count();
        $this->line("Cutoff: {$corte->toDateTimeString()} ({$dias} days). Origins: ".implode(', ', $origenes).'.');
        $this->line('Always kept: '.implode(', ', $largoPlazo).' and measurements with a pending ground alert.');
        $this->info("Measurements to prune: {$total}".($this->option('confirmar') ? '' : ' (simulation: nothing was deleted)'));

        if (! $this->option('confirmar') || $total === 0) {
            return self::SUCCESS;
        }

        $borradas = 0;
        do { // in batches so the table is not locked while the simulation writes
            $ids = $consulta()->orderBy('id')->limit(2000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $borradas += Medicion::whereIn('id', $ids)->delete();
        } while (true);

        $this->info("Deleted: {$borradas}");

        return self::SUCCESS;
    }
}
