<?php

namespace Database\Seeders;

use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\ReglasEscalamiento;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Historial completo D1..D(hoy) de los 14 marcadores de la pantalla Indicador para los 5 tripulantes de
 * "Expedición 74", más las 24 h previas a la última medición de cada marcador (una por hora).
 *
 * - Serie diaria = función `daily` de la maqueta: de la base al valor actual con ruido determinista por
 *   marcador + tripulante (dosis acumulada: crece linealmente de 0 al valor actual). Un valor por día a las 23:50 UTC.
 * - El "valor actual" de los 10 marcadores del panel es el de ExpedicionSeeder (se lee de su última medición y no se
 *   toca ni se duplica); el de los 4 nuevos (hidratación, sueño, estrés, SANS) sale de ACTUALES_EXTRA (Dilan = MK de la maqueta).
 * - Todas las filas históricas son anteriores a la última medición real del marcador (ancla), así que el Panel, el
 *   selector y `medido_hoy` no cambian. Origen 'historial'.
 * - Idempotente: borra solo las mediciones 'historial' de estos 5 tripulantes y las regenera (ExpedicionSeeder
 *   debe haberse ejecutado antes; si no hay misión, lo ejecuta). No genera alertas a tierra ni emite eventos.
 * - Inserción masiva en bloques (mediciones y evaluaciones con EvaluadorEstado, sin ajuste de línea base).
 */
class HistorialExpedicionSeeder extends Seeder
{
    public const ORIGEN = 'historial';

    /** Valor actual de los marcadores que ExpedicionSeeder no cubre, por tripulante (mismo orden que TRIPULACION). */
    public const ACTUALES_EXTRA = [
        // [hidratación, sueño, estrés cognitivo, visión SANS]
        [2.1, 5.4, 82, 0.6],   // Dilan (MK): warn, crit, ok, warn
        [2.7, 7.4, 88, 0.2],   // Elena
        [2.4, 6.2, 71, 0.3],   // Kenji
        [2.8, 8.0, 91, 0.1],   // Amara
        [2.6, 6.8, 79, 0.45],  // Lucas
    ];

    public const CLAVES_EXTRA = ['hidratacion', 'sueno', 'estres_cognitivo', 'vision_sans'];

    private const CHUNK = 500;

    public function run(): void
    {
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->first();
        if (! $mision) {
            $this->call(ExpedicionSeeder::class);
            $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        }

        $catalogo = config('indicadores.marcadores');
        $umbrales = config('umbrales');
        $evaluador = new EvaluadorEstado($umbrales);
        $reglas = new ReglasEscalamiento();
        $recomendaciones = new CatalogoRecomendaciones();
        $ahora = now()->toDateTimeString();
        $hoy = Carbon::now('UTC')->startOfDay();
        $dias = max(1, min((int) ($mision->duracion_dias ?: 180), (int) $mision->inicio_en->copy()->utc()->startOfDay()->diffInDays($hoy) + 1));

        foreach (ExpedicionSeeder::TRIPULACION as $ci => $t) {
            $astronauta = Astronauta::where('mision_id', $mision->id)->where('nombre', $t['nombre'])->first();
            if (! $astronauta) {
                continue;
            }

            // Última medición real (no histórica) de cada marcador: ancla temporal y valor actual.
            $anclas = [];
            $reales = DB::table('mediciones')->select('indicador', 'valores', 'medido_en')
                ->where('astronauta_id', $astronauta->id)->where('origen', '!=', self::ORIGEN)
                ->whereIn('indicador', array_keys($catalogo))
                ->orderBy('medido_en')->orderBy('id')->get();
            foreach ($reales as $r) {
                $anclas[$r->indicador] = ['en' => Carbon::parse($r->medido_en, 'UTC'), 'valores' => json_decode($r->valores, true)];
            }

            $filas = [];
            foreach ($catalogo as $clave => $cfg) {
                $actual = $anclas[$clave]['valores'] ?? $this->valoresActuales($clave, $t, $ci);
                $en = $anclas[$clave]['en'] ?? $hoy->copy()->setTime(...$this->hora($t['hora'] ?? $t['hora_previa']));
                $nDia = $dias - (int) $en->copy()->startOfDay()->diffInDays($hoy); // día de misión de la ancla
                if (! isset($anclas[$clave])) {
                    $filas[] = [$clave, $actual, $en];
                }
                $this->generar($filas, $clave, $cfg, $actual, $en, $nDia, $ci, $umbrales['indicadores'][$clave] ?? null);
            }

            DB::transaction(function () use ($astronauta, $filas, $evaluador, $reglas, $recomendaciones, $ahora) {
                DB::table('mediciones')->where('astronauta_id', $astronauta->id)->where('origen', self::ORIGEN)->delete(); // cascada: evaluaciones

                foreach (array_chunk($filas, self::CHUNK) as $bloque) {
                    DB::table('mediciones')->insert(array_map(fn ($f) => [
                        'astronauta_id' => $astronauta->id, 'indicador' => $f[0], 'valores' => json_encode($f[1]),
                        'medido_en' => $f[2]->toDateTimeString(), 'origen' => self::ORIGEN, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ], $bloque));
                }

                // Evaluación de cada fila insertada (el id se recupera por indicador + instante).
                $ids = [];
                foreach (DB::table('mediciones')->select('id', 'indicador', 'medido_en')
                    ->where('astronauta_id', $astronauta->id)->where('origen', self::ORIGEN)->orderBy('id')->cursor() as $m) {
                    $ids[$m->indicador.'|'.$m->medido_en] = $m->id;
                }
                $evaluaciones = [];
                foreach ($filas as $f) {
                    $estado = $evaluador->evaluar($f[0], $f[1]);
                    $id = $ids[$f[0].'|'.$f[2]->toDateTimeString()] ?? null;
                    if ($id === null) {
                        continue;
                    }
                    $evaluaciones[] = [
                        'medicion_id' => $id, 'estado' => $estado->value, 'tendencia' => 'estable',
                        'accion' => $reglas->decidir($estado)['accion'], 'recomendacion' => $recomendaciones->para($f[0], $estado),
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ];
                }
                foreach (array_chunk($evaluaciones, self::CHUNK) as $bloque) {
                    DB::table('evaluaciones')->insert($bloque);
                }
            });
        }
    }

    /** @return array{0:int,1:int} */
    private function hora(string $hhmm): array
    {
        return array_map('intval', explode(':', $hhmm));
    }

    /** Valores de diseño de un marcador para un tripulante (panel: TRIPULACION[].m; extras: ACTUALES_EXTRA). */
    private function valoresActuales(string $clave, array $t, int $ci): array
    {
        $k = array_search($clave, ExpedicionSeeder::CLAVES, true);
        if ($k !== false) {
            $m = $t['m'][$k];

            return $clave === 'presion_arterial' ? ['sistolica' => $m[0], 'diastolica' => $m[1]] : ['valor' => $m[0]];
        }

        return ['valor' => self::ACTUALES_EXTRA[$ci][array_search($clave, self::CLAVES_EXTRA, true)]];
    }

    /**
     * Añade a $filas las mediciones diarias (D1..anclaDía-1, a las 23:50 UTC) y horarias (24 h previas a la ancla).
     *
     * @param array<int, array{0:string,1:array,2:Carbon}> $filas
     */
    private function generar(array &$filas, string $clave, array $cfg, array $actual, Carbon $ancla, int $nDia, int $ci, ?array $umbral): void
    {
        $esPA = $clave === 'presion_arterial';
        $componentes = $esPA ? ['sistolica', 'diastolica'] : ['valor'];
        $bases = $esPA ? [$cfg['base'], 76] : [$cfg['base']];
        $los = $esPA ? [$cfg['lo'], 60] : [$cfg['lo']];
        $his = $esPA ? [$cfg['hi'], 80] : [$cfg['hi']];
        $dec = $esPA ? 0 : $cfg['dec'] + 1;
        $cum = ! empty($cfg['cum']);

        $diarios = []; // por componente: valores de D1..D(nDia-1)
        $horarios = [];
        foreach ($componentes as $c => $comp) {
            $val = (float) $actual[$comp];
            $base = (float) $bases[$c];
            $semilla = $clave.($esPA ? $comp[0] : '').$ci;

            $r = $this->rng($semilla);
            $amp = max(abs($val - $base), ($his[$c] - $los[$c]) * 0.1) * 0.3;
            $n = max($nDia, 1);
            $diarios[$c] = [];
            for ($i = 0; $i < $n - 1; $i++) {
                $t = $n > 1 ? $i / ($n - 1) : 1;
                $diarios[$c][] = $cum ? round($val * $t, 2) : $base + ($val - $base) * ($t ** 1.5) + $r() * $amp;
            }

            $rh = $this->rng($semilla.'h');
            $ampH = ($his[$c] - $los[$c]) * 0.06;
            $horarios[$c] = [];
            for ($i = 0; $i < 23; $i++) {
                $horarios[$c][] = $cum
                    ? $val - (23 - $i) * ($val / max($n, 1) / 24)
                    : $val + $rh() * $ampH + sin($i / 24 * 6.28) * $ampH * 0.6;
            }
        }

        $valoresDe = function (array $porComp, int $i) use ($componentes, $umbral, $dec, $esPA): array {
            $out = [];
            foreach ($componentes as $c => $comp) {
                $rango = $umbral['componentes'][$comp]['valido'] ?? [-INF, INF];
                $v = min(max($porComp[$c][$i], $rango[0]), $rango[1]);
                $out[$comp] = $esPA ? (int) round($v) : round($v, $dec);
            }
            if ($esPA && $out['diastolica'] >= $out['sistolica']) {
                $out['diastolica'] = $out['sistolica'] - 20;
            }

            return $out;
        };

        $inicioDia = $ancla->copy()->startOfDay();
        foreach (array_keys($diarios[0]) as $i) { // i = 0 -> D1
            $dia = $inicioDia->copy()->subDays($nDia - 1 - $i);
            $filas[] = [$clave, $valoresDe($diarios, $i), $dia->setTime(23, 50)];
        }
        foreach (array_keys($horarios[0]) as $i) { // i = 0 -> hace 23 h
            $filas[] = [$clave, $valoresDe($horarios, $i), $ancla->copy()->subHours(23 - $i)];
        }
    }

    /** Generador congruencial de la maqueta (rng): devuelve valores en [-0.5, 0.5). */
    private function rng(string $semilla): \Closure
    {
        $x = 17;
        foreach (str_split($semilla) as $ch) {
            $x += ord($ch) * 31;
        }

        return function () use (&$x): float {
            $x = ($x * 9301 + 49297) % 233280;

            return $x / 233280 - 0.5;
        };
    }
}
