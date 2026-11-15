<?php

namespace Database\Seeders;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\ProcesadorMediciones;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "Expedición 74": la tripulación de ejemplo del diseño (5 personas) con sus mediciones de hoy
 * y 6 días previos. Idempotente: cada ejecución deja a estos 5 tripulantes con los mismos datos
 * (rehace SUS mediciones; no toca otras misiones ni otros astronautas).
 *
 * Kenji y Lucas no tienen medición de hoy ("pendiente") pero sí de ayer, para tener estado.
 * Todo pasa por ProcesadorMediciones sin emitir eventos y sin ajuste de línea base personal
 * (los estados deben ser exactamente los del diseño); las alertas a tierra de días anteriores
 * se descartan para no inundar la cola (solo quedan las de hoy).
 */
class ExpedicionSeeder extends Seeder
{
    public const MISION = 'Expedición 74';

    /** Orden de los valores de TRIPULACION[i]['m'] (= orden del panel). */
    public const CLAVES = [
        'presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2', 'glucosa', 'temperatura',
        'imc', 'perdida_osea', 'masa_muscular', 'radiacion_diaria', 'dosis_acumulada',
    ];

    /** Base personal de ejemplo (QM[].base del diseño); la presión lleva sistólica y diastólica. */
    public const BASES = [
        'presion_arterial' => [116, 76], 'frecuencia_cardiaca' => 64, 'saturacion_o2' => 98, 'glucosa' => 98,
        'temperatura' => 36.6, 'imc' => 23.0, 'perdida_osea' => -0.6, 'masa_muscular' => -1.2,
        'radiacion_diaria' => 0.38, 'dosis_acumulada' => 70,
    ];

    /**
     * Valores de hoy (ayer, si la hora es null) y el estado que el diseño espera de cada uno.
     *
     * @var array<int, array{nombre:string, rol:string, edad:int, altura:float, peso:float, grupo:string, evas:int, hora:?string, hora_previa:string, m:array}>
     */
    public const TRIPULACION = [
        ['nombre' => 'Dilan García', 'rol' => 'Especialista de misión', 'edad' => 36, 'altura' => 1.78, 'peso' => 73.4, 'grupo' => 'A+', 'evas' => 4, 'hora' => '07:31', 'hora_previa' => '07:20',
            'm' => [[128, 84, 'warn'], [66, 'ok'], [97, 'ok'], [112, 'ok'], [37.1, 'ok'], [23.2, 'ok'], [-1.6, 'crit'], [-4.2, 'crit'], [0.54, 'crit'], [76.7, 'warn']]],
        ['nombre' => 'Elena Moreau', 'rol' => 'Comandante', 'edad' => 44, 'altura' => 1.69, 'peso' => 61.2, 'grupo' => 'O−', 'evas' => 7, 'hora' => '06:58', 'hora_previa' => '07:05',
            'm' => [[118, 76, 'ok'], [58, 'warn'], [98, 'ok'], [96, 'ok'], [36.7, 'ok'], [21.4, 'ok'], [-0.8, 'ok'], [-1.6, 'ok'], [0.48, 'warn'], [92.3, 'warn']]],
        ['nombre' => 'Kenji Arai', 'rol' => 'Ingeniero de vuelo', 'edad' => 39, 'altura' => 1.74, 'peso' => 70.8, 'grupo' => 'B+', 'evas' => 2, 'hora' => null, 'hora_previa' => '07:10',
            'm' => [[142, 92, 'crit'], [84, 'ok'], [96, 'ok'], [148, 'warn'], [37.0, 'ok'], [23.4, 'ok'], [-1.1, 'warn'], [-2.4, 'warn'], [0.41, 'ok'], [58.1, 'ok']]],
        ['nombre' => 'Amara Osei', 'rol' => 'Especialista médica', 'edad' => 33, 'altura' => 1.65, 'peso' => 58.9, 'grupo' => 'AB+', 'evas' => 1, 'hora' => '07:12', 'hora_previa' => '07:15',
            'm' => [[112, 72, 'ok'], [71, 'ok'], [99, 'ok'], [88, 'ok'], [36.5, 'ok'], [21.6, 'ok'], [-0.6, 'ok'], [-1.2, 'ok'], [0.39, 'ok'], [31.5, 'ok']]],
        ['nombre' => 'Lucas Ferreira', 'rol' => 'Especialista de carga útil', 'edad' => 41, 'altura' => 1.82, 'peso' => 79.6, 'grupo' => 'O+', 'evas' => 3, 'hora' => null, 'hora_previa' => '06:40',
            'm' => [[124, 82, 'warn'], [74, 'ok'], [94, 'warn'], [104, 'ok'], [37.3, 'warn'], [24.0, 'ok'], [-1.3, 'warn'], [-3.1, 'crit'], [0.46, 'ok'], [64.8, 'ok']]],
    ];

    public function run(): void
    {
        $hoy = Carbon::now('UTC')->startOfDay();

        $mision = Mision::updateOrCreate(
            ['nombre' => self::MISION],
            ['entorno' => 'estacion', 'inicio_en' => $hoy->copy()->subDays(141)->toDateString(), 'duracion_dias' => 180],
        );

        $proc = ProcesadorMediciones::make();
        $ids = [];

        foreach (self::TRIPULACION as $ci => $t) {
            $astronauta = Astronauta::updateOrCreate(
                ['nombre' => $t['nombre'], 'mision_id' => $mision->id],
                [
                    'mision' => self::MISION, 'entorno' => 'estacion', 'rol' => $t['rol'], 'edad' => $t['edad'],
                    'altura_m' => $t['altura'], 'peso_kg' => $t['peso'], 'grupo_sanguineo' => $t['grupo'], 'evas' => $t['evas'],
                ],
            );
            $ids[] = $astronauta->id;

            DB::transaction(function () use ($astronauta, $t, $ci, $hoy, $proc) {
                $astronauta->mediciones()->delete(); // cascada: evaluaciones y alertas

                $conHoy = $t['hora'] !== null;
                $ultimo = $conHoy ? 6 : 5;       // índice del valor de diseño dentro de la serie
                foreach (range(0, $ultimo) as $i) { // i = 0 es hace 6 días
                    $dia = $hoy->copy()->subDays(6 - $i);
                    $hora = $i === $ultimo && $conHoy ? $t['hora'] : $t['hora_previa'];
                    [$h, $min] = array_map('intval', explode(':', $hora));
                    $instante = $dia->copy()->setTime($h, $min);
                    $origen = $i === $ultimo && $conHoy ? 'manual' : 'simulado';

                    foreach (self::CLAVES as $k => $clave) {
                        $proc->registrar($astronauta, $clave, $this->valores($clave, $t['m'][$k], $ci, $i, $ultimo), $origen, $instante, false, false);
                    }
                }
            });
        }

        // Solo se conservan las alertas a tierra de las mediciones de hoy.
        AlertaSaliente::whereHas('evaluacion.medicion', fn ($q) => $q->whereIn('astronauta_id', $ids)->where('medido_en', '<', $hoy))->delete();
    }

    /** Valor del paso $i de la serie: interpola de la base al valor del diseño con ruido determinista. */
    private function valores(string $clave, array $diseno, int $ci, int $i, int $ultimo): array
    {
        $dec = config('panel.indicadores.'.$clave.'.decimales', 0);
        if ($clave === 'presion_arterial') {
            return [
                'sistolica' => (int) round($this->paso($clave.'s', self::BASES[$clave][0], $diseno[0], $ci, $i, $ultimo)),
                'diastolica' => (int) round($this->paso($clave.'d', self::BASES[$clave][1], $diseno[1], $ci, $i, $ultimo)),
            ];
        }

        return ['valor' => round($this->paso($clave, self::BASES[$clave], $diseno[0], $ci, $i, $ultimo), $dec)];
    }

    private function paso(string $semilla, float|int $base, float|int $fin, int $ci, int $i, int $ultimo): float
    {
        if ($i === $ultimo) {
            return (float) $fin;
        }
        $s = array_sum(array_map('ord', str_split($semilla.$ci)));
        $rnd = 0.0;
        for ($n = 0; $n <= $i; $n++) { // generador congruencial como el del diseño
            $s = ($s * 9301 + 49297) % 233280;
            $rnd = $s / 233280 - 0.5;
        }
        $ruido = $i > 0 ? $rnd * (abs($fin - $base) ?: abs($base) * 0.05) * 0.4 : 0.0;

        return $base + ($fin - $base) * (($i / $ultimo) ** 1.4) + $ruido;
    }
}
