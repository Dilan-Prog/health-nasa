<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Mision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payload de la pantalla "Indicador" para un tripulante (lo consume el cliente: modelo.js).
 *
 * {
 *   hoy: "2026-11-15", dias: 142,                     // día de misión actual (D1 = inicio) y su fecha (UTC)
 *   sistemas: [[nombre, icono] x5],
 *   marcadores: [ {id,label,ic,unit,sys,val,disp,lo,hi,base,dec,rec,estado,loss,cum,signo} x14 ],
 *   diaria:  { id: [dias valores, D1..D<dias>] },     // último valor de cada día UTC; huecos interpolados
 *   horaria: { id: [24 valores] }                     // 24 h que terminan en la última medición del marcador
 * }
 *
 * Reglas:
 *  - Solo 3 consultas (misión, mediciones de la misión de los 14 marcadores y estado de las últimas), más 1 por cada
 *    marcador con medición y métrica de línea base asociada (config linea_base.indicador_metrica; hoy solo temperatura).
 *  - `desv` (opcional, solo si hay medición y base): {metrica, base, pct}; pct = (val − base) / base × 100, 1 decimal.
 *  - Huecos interpolados linealmente; los extremos sin dato toman el vecino conocido más cercano.
 *    Un marcador sin ningún dato usa su base (estado 'ok').
 *  - Dosis acumulada: la serie nunca decrece (máximo acumulado).
 *  - Horaria anclada en la última medición del marcador (no en "ahora") para que la demo no dependa de la hora;
 *    el último valor coincide con `val`.
 *  - `val` = último valor (sistólica en la presión), igual a diaria[id][último] y a horaria[id][23].
 */
class DatosIndicador
{
    public const MENOS = "\u{2212}";

    private const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];

    /** @var array<string, array> */
    private array $marcadores;
    private array $sistemas;
    private EvaluadorEstado $evaluador;

    public function __construct(?array $catalogo = null, ?array $umbrales = null)
    {
        $catalogo ??= config('indicadores');
        $this->marcadores = $catalogo['marcadores'];
        $this->sistemas = $catalogo['sistemas'];
        $this->evaluador = new EvaluadorEstado($umbrales ?? config('umbrales'));
    }

    public static function make(): self
    {
        return new self();
    }

    public static function para(Astronauta $astronauta): array
    {
        return self::make()->generar($astronauta);
    }

    public function generar(Astronauta $astronauta): array
    {
        $hoy = Carbon::now('UTC')->startOfDay();
        $mision = $astronauta->mision_id ? Mision::find($astronauta->mision_id) : null;
        $dias = $mision?->inicio_en
            ? max(1, min((int) ($mision->duracion_dias ?: 180), (int) $mision->inicio_en->copy()->utc()->startOfDay()->diffInDays($hoy) + 1))
            : 30;
        $desde = $hoy->copy()->subDays($dias - 1);

        $filas = DB::table('mediciones')
            ->select('id', 'indicador', 'valores', 'medido_en')
            ->where('astronauta_id', $astronauta->id)
            ->whereIn('indicador', array_keys($this->marcadores))
            ->where('medido_en', '>=', $desde->toDateTimeString())
            ->orderBy('medido_en')->orderBy('id')
            ->get()
            ->groupBy('indicador');

        $ultimaId = [];
        foreach ($filas as $clave => $lista) {
            $ultimaId[$clave] = $lista->last()->id;
        }
        $estados = $ultimaId === [] ? [] : Evaluacion::whereIn('medicion_id', array_values($ultimaId))->pluck('estado', 'medicion_id')->all();

        $lista = [];
        $diaria = [];
        $horaria = [];
        foreach ($this->marcadores as $clave => $cfg) {
            $mediciones = $filas->get($clave, collect());
            [$dia, $hora] = $this->series($clave, $cfg, $mediciones, $hoy, $dias);
            $val = $dia[$dias - 1];

            $estado = 'ok';
            if ($mediciones->isNotEmpty()) {
                $ultima = $mediciones->last();
                $e = $estados[$ultima->id] ?? $this->evaluador->evaluar($clave, json_decode($ultima->valores, true))->value;
                $estado = self::ESTADO_UI[$e];
            }

            $lista[] = [
                'id' => $clave, 'label' => $cfg['label'], 'ic' => $cfg['ic'], 'unit' => $cfg['unit'], 'sys' => $cfg['sys'],
                'val' => $val, 'disp' => $this->disp($clave, $cfg, $mediciones->isNotEmpty() ? json_decode($mediciones->last()->valores, true) : null, $val),
                'lo' => $cfg['lo'], 'hi' => $cfg['hi'], 'base' => $cfg['base'], 'dec' => $cfg['dec'], 'rec' => $cfg['rec'],
                'estado' => $estado,
                'loss' => (bool) ($cfg['loss'] ?? false), 'cum' => (bool) ($cfg['cum'] ?? false), 'signo' => (bool) ($cfg['signo'] ?? false),
            ];
            // Desviación frente a la línea base pre-vuelo: solo con medición real y base (si no, la clave no aparece).
            if ($mediciones->isNotEmpty() && ($desv = DesviacionBase::paraIndicador($astronauta, $clave, (float) $val)) !== null) {
                $lista[array_key_last($lista)]['desv'] = $desv;
            }
            $diaria[$clave] = $dia;
            $horaria[$clave] = $hora;
        }

        return [
            'hoy' => $hoy->toDateString(),
            'dias' => $dias,
            'sistemas' => $this->sistemas,
            'marcadores' => $lista,
            'diaria' => $diaria,
            'horaria' => $horaria,
        ];
    }

    /**
     * @return array{0: float[], 1: float[]} [diaria (dias valores), horaria (24 valores)]
     */
    private function series(string $clave, array $cfg, $mediciones, Carbon $hoy, int $dias): array
    {
        $base = (float) $cfg['base'];
        if ($mediciones->isEmpty()) {
            return [array_fill(0, $dias, $base), array_fill(0, 24, $base)];
        }

        $porDia = [];
        $porHora = [];
        $ultimoTs = 0;
        foreach ($mediciones as $m) { // ordenadas: la última de cada tramo gana
            $ts = Carbon::parse($m->medido_en, 'UTC')->getTimestamp();
            $v = $this->principal(json_decode($m->valores, true));
            $dia = $dias - 1 - intdiv($hoy->getTimestamp() - ($ts - $ts % 86400), 86400);
            if ($dia >= 0 && $dia < $dias) {
                $porDia[$dia] = $v;
            }
            $porHora[$ts] = $v;
            $ultimoTs = max($ultimoTs, $ts);
        }

        $diaria = $this->rellenar($porDia, $dias);
        if (! empty($cfg['cum'])) {
            $max = -INF;
            $diaria = array_map(function ($v) use (&$max) {
                return $max = max($max, $v);
            }, $diaria);
        }

        $ventana = [];
        foreach ($porHora as $ts => $v) {
            $k = 23 - intdiv($ultimoTs - $ts, 3600);
            if ($k >= 0) {
                $ventana[$k] = $v; // $porHora viene ordenado: el último de cada hora gana
            }
        }
        $horaria = $this->rellenar($ventana, 24);

        return [array_map(fn ($v) => round($v, 3), $diaria), array_map(fn ($v) => round($v, 3), $horaria)];
    }

    /**
     * Serie de $n puntos a partir de los conocidos (índice => valor): interpola huecos y extiende los extremos.
     *
     * @param array<int, float> $conocidos
     * @return float[]
     */
    private function rellenar(array $conocidos, int $n): array
    {
        ksort($conocidos);
        $idx = array_keys($conocidos);
        $serie = [];
        $p = 0; // posición en $idx del primer conocido >= i
        for ($i = 0; $i < $n; $i++) {
            while ($p < count($idx) && $idx[$p] < $i) {
                $p++;
            }
            if (isset($conocidos[$i])) {
                $serie[] = (float) $conocidos[$i];
            } elseif ($p === 0) {
                $serie[] = (float) $conocidos[$idx[0]];
            } elseif ($p >= count($idx)) {
                $serie[] = (float) $conocidos[$idx[count($idx) - 1]];
            } else {
                $a = $idx[$p - 1];
                $b = $idx[$p];
                $serie[] = $conocidos[$a] + ($conocidos[$b] - $conocidos[$a]) * ($i - $a) / ($b - $a);
            }
        }

        return $serie;
    }

    private function principal(array $valores): float
    {
        return (float) ($valores['valor'] ?? $valores['sistolica']);
    }

    /** "128/84", "66", "−1.6", "+0.6" (signo menos tipográfico U+2212). */
    private function disp(string $clave, array $cfg, ?array $valores, float $val): string
    {
        if ($valores !== null && isset($valores['sistolica'], $valores['diastolica'])) {
            return (int) round($valores['sistolica']).'/'.(int) round($valores['diastolica']);
        }
        if ($clave === 'presion_arterial') {
            return (int) round($val).'/'.(int) round($val * 0.65);
        }
        $dec = $cfg['dec'];
        $v = round($val, $dec);
        $txt = number_format(abs($v), $dec, '.', '');
        if ($v < 0) {
            return self::MENOS.$txt;
        }

        return (! empty($cfg['signo']) && $v > 0 ? '+' : '').$txt;
    }
}
