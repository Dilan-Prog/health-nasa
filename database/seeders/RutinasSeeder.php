<?php

namespace Database\Seeders;

use App\Models\Actividad;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\Rutina;
use App\Services\Salud\AgendaRutinas;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rutinas de la tripulación de "Expedición 74" con las plantillas del diseño (RUT_TPL) y el historial de HIST0.
 * Idempotente: rehace las rutinas, ejecuciones y actividades de SUS 5 tripulantes (no toca otros astronautas).
 * Requiere ExpedicionSeeder (si no hay misión, no hace nada). Fechas relativas a "hoy" (UTC).
 */
class RutinasSeeder extends Seeder
{
    /** [hora, icono, nombre, tipo, kind, cantidad, indicador que la motiva] */
    public const PLANTILLA = [
        ['06:30', 'fitness_center', 'ARED session, 45 min', 'Ejercicio resistido', 'ej', 45, 'perdida_osea'],
        ['07:30', 'monitor_heart', 'Measure blood pressure', 'Medición', 'med', 0, 'presion_arterial'],
        ['08:15', 'self_improvement', 'Stretching, 10 min', 'Otro', 'ej', 10, null],
        ['09:00', 'flare', 'Check radiation dose', 'Medición', 'med', 0, 'radiacion_diaria'],
        ['11:00', 'directions_run', 'Leg strength routine, 30 min', 'Ejercicio resistido', 'ej', 30, 'masa_muscular'],
        ['13:00', 'water_drop', 'Hydration: 500 ml', 'Hidratación', 'bien', 500, null],
        ['16:00', 'pedal_bike', 'Cycle ergometer cardio (CEVIS), 30 min', 'Cardio', 'ej', 30, null],
        ['21:00', 'mood', 'Log mood', 'Otro', 'bien', 0, null],
    ];

    public function run(): void
    {
        ExpedicionSeeder::migrarRolesAnteriores();
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->first();
        if (! $mision) {
            return;
        }
        $agenda = AgendaRutinas::make();
        $hoy = $agenda->hoy();
        $lunes = $agenda->lunesDe($hoy);

        foreach (Astronauta::where('mision_id', $mision->id)->orderBy('id')->get() as $ci => $a) {
            DB::transaction(function () use ($a, $ci, $agenda, $hoy, $lunes) {
                Actividad::where('astronauta_id', $a->id)->delete();
                Rutina::where('astronauta_id', $a->id)->delete(); // cascada: ejecuciones

                $rutinas = [];
                foreach (self::PLANTILLA as [$hora, $icono, $nombre, $tipo, $kind, $cantidad, $indicador]) {
                    $rutinas[] = Rutina::create([
                        'astronauta_id' => $a->id, 'nombre' => $nombre, 'tipo' => $tipo, 'icono' => $icono, 'kind' => $kind,
                        'hora' => $hora, 'cantidad' => $cantidad, 'dias' => [1, 2, 3, 4, 5, 6, 7], 'activa' => true, 'indicador_clave' => $indicador,
                    ]);
                }

                // Cumplimiento de la semana actual hasta hoy (patrón del diseño: hechas salvo alguna omitida).
                for ($d = 0; $d < 7; $d++) {
                    $fecha = $lunes->copy()->addDays($d);
                    if ($fecha->gt($hoy)) {
                        break;
                    }
                    foreach ($rutinas as $k => $r) {
                        if ($fecha->eq($hoy)) {
                            $estado = $k < 2 ? 'done' : ($k === 2 ? 'skipped' : null);
                        } else {
                            $estado = (($d * 3 + $k + $ci) % 7 === 0) ? 'skipped' : 'done';
                        }
                        if ($estado) {
                            $agenda->marcar($a, $r, $fecha, $estado);
                        }
                    }
                }

                // Historial manual del diseño (HIST0) + hidratación de hoy.
                $manual = [
                    ['Sueño', 'Sleep', $hoy->copy()->subDay()->setTime(22, 40), 372, null, null],
                    ['Hidratación', 'Hydration', $hoy->copy()->setTime(8, 10), 1400, null, null],
                    ['Hidratación', 'Hydration', $hoy->copy()->subDay()->setTime(19, 10), 500, null, null],
                    ['Comida', 'Meal', $hoy->copy()->subDay()->setTime(13, 5), 25, null, null],
                    ['EVA', 'EVA · solar panel maintenance', $hoy->copy()->subDays(2)->setTime(8, 0), 390, 'Media', null],
                ];
                foreach ($manual as [$tipo, $nombre, $inicio, $cantidad, $intensidad, $nota]) {
                    Actividad::create([
                        'astronauta_id' => $a->id, 'tipo' => $tipo, 'nombre' => $nombre, 'inicio_en' => $inicio, 'cantidad' => $cantidad,
                        'unidad' => config('rutinas.tipos.'.$tipo.'.unidad', 'min'), 'origen' => 'manual', 'intensidad' => $intensidad, 'nota' => $nota,
                    ]);
                }
            });
        }
    }
}
