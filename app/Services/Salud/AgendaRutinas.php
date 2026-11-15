<?php

namespace App\Services\Salud;

use App\Models\Actividad;
use App\Models\Astronauta;
use App\Models\Rutina;
use App\Models\RutinaEjecucion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agenda de rutinas de un tripulante: semana (lunes-domingo, UTC), estado de cada tarea, marcado y progreso del día.
 *
 * Estado de una tarea: `done`/`skipped` se guardan en rutina_ejecuciones; el resto se deriva:
 * `late` si ya pasó su hora (hoy) o su día, `pending` si aún no toca.
 * Marcar hecha crea una Actividad (origen "recordatorio") para el historial; deshacer/omitir la retira.
 */
class AgendaRutinas
{
    public static function make(): self
    {
        return new self();
    }

    public function hoy(): Carbon
    {
        return Carbon::now('UTC')->startOfDay();
    }

    public function lunesDe(?Carbon $ref = null): Carbon
    {
        return ($ref ?? Carbon::now('UTC'))->copy()->utc()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * @return array<int, array{fecha: Carbon, tareas: array<int, array>}> 7 días (0 = lunes)
     */
    public function semana(Astronauta $a, Carbon $lunes): array
    {
        $rutinas = Rutina::where('astronauta_id', $a->id)->where('activa', true)->orderBy('hora')->orderBy('id')->get();
        $ejec = RutinaEjecucion::whereIn('rutina_id', $rutinas->pluck('id'))
            ->whereBetween('fecha', [$lunes->toDateString(), $lunes->copy()->addDays(6)->toDateString()])
            ->get()
            ->keyBy(fn ($e) => $e->rutina_id.'|'.$e->fecha->toDateString());

        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $fecha = $lunes->copy()->addDays($i);
            $dias[] = ['fecha' => $fecha, 'tareas' => $this->tareas($rutinas, $ejec, $fecha)];
        }

        return $dias;
    }

    /** @return array<int, array> tareas del día ordenadas por hora */
    private function tareas(Collection $rutinas, Collection $ejec, Carbon $fecha): array
    {
        $ahora = Carbon::now('UTC');
        $hoy = $ahora->copy()->startOfDay();
        $out = [];
        foreach ($rutinas as $r) {
            if (! $r->aplicaEn($fecha->isoWeekday())) {
                continue;
            }
            $e = $ejec->get($r->id.'|'.$fecha->toDateString());
            if ($e) {
                $estado = $e->estado;
            } elseif ($fecha->lt($hoy)) {
                $estado = 'late';
            } elseif ($fecha->eq($hoy)) {
                $estado = $r->hora < $ahora->format('H:i') ? 'late' : 'pending';
            } else {
                $estado = 'pending';
            }
            $out[] = [
                'id' => $r->id, 'hora' => $r->hora, 'icono' => $r->icono, 'nombre' => $r->nombre, 'tipo' => $r->tipo,
                'kind' => $r->kind, 'cantidad' => $r->cantidad, 'unidad' => config('rutinas.tipos.'.$r->tipo.'.unidad', 'min'),
                'indicador_clave' => $r->indicador_clave, 'estado' => $estado,
            ];
        }
        usort($out, fn ($x, $y) => [$x['hora'], $x['id']] <=> [$y['hora'], $y['id']]);

        return $out;
    }

    /** Marca la rutina como hecha u omitida en ese día. Solo hoy o días pasados. Devuelve false si no procede. */
    public function marcar(Astronauta $a, Rutina $r, Carbon $fecha, string $estado): bool
    {
        if (! in_array($estado, ['done', 'skipped'], true) || $r->astronauta_id !== $a->id || ! $r->aplicaEn($fecha->isoWeekday())
            || $fecha->copy()->startOfDay()->gt($this->hoy())) {
            return false;
        }
        RutinaEjecucion::updateOrCreate(
            ['rutina_id' => $r->id, 'fecha' => $fecha->toDateString()],
            ['estado' => $estado, 'hecha_en' => $estado === 'done' ? Carbon::now('UTC') : null],
        );
        $this->quitarActividad($r, $fecha);
        // Las mediciones y las rutinas sin cantidad no son "actividad" del diario: solo cuentan en su tarea.
        if ($estado === 'done' && $r->kind !== 'med' && $r->cantidad > 0) {
            $cfg = config('rutinas.tipos.'.$r->tipo, []);
            Actividad::create([
                'astronauta_id' => $a->id, 'rutina_id' => $r->id, 'tipo' => $r->tipo, 'nombre' => $r->nombre,
                'inicio_en' => $fecha->copy()->setTimeFromTimeString($r->hora), 'cantidad' => $r->cantidad,
                'unidad' => $cfg['unidad'] ?? 'min', 'origen' => 'recordatorio',
            ]);
        }

        return true;
    }

    /** Quita la marca (vuelve a pendiente/atrasada). */
    public function deshacer(Astronauta $a, Rutina $r, Carbon $fecha): bool
    {
        if ($r->astronauta_id !== $a->id) {
            return false;
        }
        RutinaEjecucion::where('rutina_id', $r->id)->whereDate('fecha', $fecha->toDateString())->delete();
        $this->quitarActividad($r, $fecha);

        return true;
    }

    private function quitarActividad(Rutina $r, Carbon $fecha): void
    {
        Actividad::where('rutina_id', $r->id)->where('origen', 'recordatorio')
            ->whereBetween('inicio_en', [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay()])->delete();
    }

    /** Registro suelto de una actividad (modal "Registrar actividad"). */
    public function registrarActividad(Astronauta $a, string $tipo, int $cantidad, Carbon $inicio, ?string $intensidad, ?string $nota): Actividad
    {
        $cfg = config('rutinas.tipos.'.$tipo);

        return Actividad::create([
            'astronauta_id' => $a->id, 'tipo' => $tipo, 'nombre' => $tipo, 'inicio_en' => $inicio, 'cantidad' => $cantidad,
            'unidad' => $cfg['unidad'] ?? 'min', 'origen' => 'manual',
            'intensidad' => ($cfg['kind'] ?? null) === 'ej' || $tipo === 'Comida' || $tipo === 'Otro' ? $intensidad : null,
            'nota' => $nota !== null && trim($nota) !== '' ? trim($nota) : null,
        ]);
    }

    /**
     * Tarjetas de progreso de HOY. Ejercicio = rutinas de ejercicio hechas + actividades manuales de ejercicio;
     * hidratación = actividades de hoy; sueño = último registro de sueño de las últimas 36 h; mediciones = tareas "med" de hoy.
     *
     * @param array<int, array> $tareasHoy
     */
    public function progreso(Astronauta $a, array $tareasHoy): array
    {
        $hoy = $this->hoy();
        $deHoy = Actividad::where('astronauta_id', $a->id)->whereBetween('inicio_en', [$hoy, $hoy->copy()->endOfDay()])->get();

        $ejMin = collect($tareasHoy)->where('kind', 'ej')->where('estado', 'done')->sum('cantidad')
            + $deHoy->where('origen', 'manual')->whereIn('tipo', ['Ejercicio resistido', 'Cardio', 'EVA'])->sum('cantidad');
        $aguaMl = $deHoy->where('tipo', 'Hidratación')->where('unidad', 'ml')->sum('cantidad');

        $sueno = Actividad::where('astronauta_id', $a->id)->where('tipo', 'Sueño')
            ->where('inicio_en', '>=', Carbon::now('UTC')->subHours(36))->orderByDesc('inicio_en')->first();

        $med = collect($tareasHoy)->where('kind', 'med');

        return [
            'ejercicio_min' => (int) $ejMin,
            'agua_ml' => (int) $aguaMl,
            'sueno_min' => $sueno?->cantidad,
            'med_hechas' => $med->where('estado', 'done')->count(),
            'med_total' => $med->count(),
        ];
    }

    /** Actividades más recientes (más nuevas primero). */
    public function historial(Astronauta $a, int $limite = 8): Collection
    {
        return Actividad::where('astronauta_id', $a->id)->where('inicio_en', '<=', Carbon::now('UTC')->endOfDay())
            ->orderByDesc('inicio_en')->orderByDesc('id')->limit($limite)->get();
    }
}
