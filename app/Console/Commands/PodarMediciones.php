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
        {--dias= : Días a conservar (por defecto mision.dias_historial)}
        {--origen=simulado,reloj : Orígenes a podar, separados por coma}
        {--confirmar : Borra de verdad; sin esta opción solo muestra lo que borraría}';

    protected $description = 'Elimina mediciones en vivo anteriores a N días (simulación por defecto; --confirmar para borrar).';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null && $this->option('dias') !== ''
            ? (int) $this->option('dias') : (int) config('mision.dias_historial', 15);
        if ($dias < 1) {
            $this->error('--dias debe ser al menos 1.');

            return self::FAILURE;
        }
        $origenes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('origen')))));
        if ($origenes === []) {
            $this->error('Indica al menos un --origen.');

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
        $this->line("Corte: {$corte->toDateTimeString()} ({$dias} días). Orígenes: ".implode(', ', $origenes).'.');
        $this->line('Se conservan siempre: '.implode(', ', $largoPlazo).' y las mediciones con alerta pendiente a tierra.');
        $this->info("Mediciones a podar: {$total}".($this->option('confirmar') ? '' : ' (simulación: no se borró nada)'));

        if (! $this->option('confirmar') || $total === 0) {
            return self::SUCCESS;
        }

        $borradas = 0;
        do { // por lotes para no bloquear la tabla mientras la simulación escribe
            $ids = $consulta()->orderBy('id')->limit(2000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $borradas += Medicion::whereIn('id', $ids)->delete();
        } while (true);

        $this->info("Borradas: {$borradas}");

        return self::SUCCESS;
    }
}
