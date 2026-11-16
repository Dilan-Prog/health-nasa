<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Mision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la pantalla "Historial": gráficas SVG (viewBox 600x172) de un marcador entre dos días de misión.
 *
 * - Serie real: un punto por día de misión con medición (la última del día UTC); los días sin dato se omiten.
 * - Marcadores de pérdida (loss): se grafican en magnitud (valor absoluto), se muestran con signo menos y
 *   MÍN/MÁX van invertidos; su banda normal va de 0 a |lo|.
 * - La dosis acumulada no tiene línea base. La presión arterial grafica la sistólica.
 */
class DatosHistorial
{
    public const PRESETS = [['7', 'Últimos 7 días'], ['14', '14 días'], ['30', '30 días'], ['90', '90 días'], ['all', 'Toda la misión'], ['custom', 'Personalizado']];

    public const TIPOS = [['line', 'show_chart', 'Línea'], ['bar', 'bar_chart', 'Barras'], ['area', 'area_chart', 'Área'], ['dots', 'scatter_plot', 'Puntos']];

    private const MENOS = "\u{2212}";
    private const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];
    private const W = 600;
    private const H = 172;

    /** Día de misión actual (1 = inicio, UTC); sin misión, 30 (igual que DatosIndicador). */
    public static function diasMision(Astronauta $a): int
    {
        $hoy = Carbon::now('UTC')->startOfDay();
        $mision = $a->mision_id ? Mision::find($a->mision_id) : null;

        return $mision?->inicio_en
            ? max(1, min((int) ($mision->duracion_dias ?: 180), (int) $mision->inicio_en->copy()->utc()->startOfDay()->diffInDays($hoy) + 1))
            : 30;
    }

    /** @return array{desde:int,hasta:int,invalido:bool,mensaje:string,icono:string} */
    public static function rango(string $preset, int $totalDias, ?int $desde, ?int $hasta): array
    {
        $n = max(1, $totalDias);
        $invalido = false;
        if ($preset === 'all') {
            [$d, $h] = [1, $n];
        } elseif ($preset === 'custom') {
            if ($desde !== null && $hasta !== null && $desde >= 1 && $hasta <= $n && $desde < $hasta) {
                [$d, $h] = [$desde, $hasta];
            } else {
                $invalido = true;
                [$d, $h] = [max(1, $n - 29), $n];
            }
        } else {
            $k = max(1, (int) $preset ?: 30);
            [$d, $h] = [max(1, $n - $k + 1), $n];
        }

        return [
            'desde' => $d,
            'hasta' => $h,
            'invalido' => $invalido,
            'mensaje' => $invalido
                ? "Rango no válido: el día inicial debe ser menor que el final (1–{$n})"
                : "Mostrando días {$d}–{$h} de la misión · ".($h - $d + 1).' días',
            'icono' => $invalido ? 'error' : 'date_range',
        ];
    }

    /** @return array<int, array{id:string,label:string}> */
    public static function indicadores(): array
    {
        $out = [];
        foreach (config('indicadores.marcadores') as $id => $cfg) {
            $out[] = ['id' => $id, 'label' => $cfg['label']];
        }

        return $out;
    }

    public static function grafica(Astronauta $a, string $indicadorId, string $tipo, int $desde, int $hasta): array
    {
        $cfg = config('indicadores.marcadores')[$indicadorId] ?? throw new \InvalidArgumentException("Indicador desconocido: {$indicadorId}");
        $tipoDef = collect(self::TIPOS)->first(fn ($t) => $t[0] === $tipo) ?? self::TIPOS[0];
        $tipo = $tipoDef[0];
        $loss = (bool) ($cfg['loss'] ?? false);
        $dec = (int) $cfg['dec'];
        $total = self::diasMision($a);
        $hasta = max(1, min($hasta, $total));
        $desde = max(1, min($desde, $hasta));

        $puntos = self::puntos($a, $indicadorId, $total, $desde, $hasta);
        $n = count($puntos);

        $base = [
            'id' => $indicadorId, 'ic' => $cfg['ic'], 'label' => $cfg['label'], 'unit' => $cfg['unit'],
            'aria' => "{$cfg['label']}, días {$desde} a {$hasta}, gráfica de ".mb_strtolower($tipoDef[2]),
            'rangeTxt' => self::rangoNormalTxt($cfg),
            'n' => $n, 'vacio' => $n === 0,
            // Dato sospechoso: si algún punto del periodo lo es, se avisa (con el motivo del más reciente). El estado no se oculta.
            'sospechosos' => count(array_filter($puntos, fn ($p) => $p['sospechoso'] !== null)),
            'sospechosoMotivo' => collect($puntos)->pluck('sospechoso')->filter()->last(),
        ];
        if ($n === 0) {
            return $base + [
                'estado' => 'ok', 'stats' => [], 'yMax' => '', 'yMin' => '', 'bandY' => 0.0, 'bandH' => 0.0, 'baseY' => -10.0,
                'linePts' => '', 'areaD' => '', 'bars' => [], 'dotsD' => '',
                'trendIc' => 'trending_flat', 'trendTxt' => '', 'trendEstado' => 'ok',
            ];
        }

        $v = array_column($puntos, 'v');
        $lo = $loss ? 0.0 : (float) $cfg['lo'];
        $hiv = $loss ? abs((float) $cfg['lo']) : (float) $cfg['hi'];
        $basel = $indicadorId === 'dosis_acumulada' ? null : ($loss ? abs((float) $cfg['base']) : (float) $cfg['base']);
        $todos = array_merge($v, [$lo, $hiv], $basel !== null ? [$basel] : []);
        $mn = min($todos);
        $mx = max($todos);
        $pad = (($mx - $mn) ?: 1) * 0.08;
        $mn -= $pad;
        $mx += $pad;

        $Y = fn (float $x): float => round(self::H - ($x - $mn) / ($mx - $mn) * 164, 1);
        $X = fn (int $j): float => round($n > 1 ? $j * self::W / ($n - 1) : 300, 1);
        $fmt = fn (float $x): string => ($loss ? self::MENOS : '').number_format($x, $dec, '.', '');

        $pts = [];
        foreach ($v as $j => $x) {
            $pts[] = $X($j).','.$Y($x);
        }

        $avg = array_sum($v) / $n;
        $ultimo = $v[$n - 1];
        $diff = $ultimo - $v[0];
        $estado = $puntos[$n - 1]['estado'];
        $unit = $cfg['unit'];

        $bars = [];
        if ($tipo === 'bar') {
            $bw = max(1, self::W / $n * 0.7);
            foreach ($puntos as $j => $p) {
                $y = $Y($p['v']);
                $bars[] = ['x' => round(max(0, $X($j) - $bw / 2), 1), 'w' => round($bw, 1), 'y' => $y, 'h' => max(1.0, self::H - $y), 'estado' => $p['estado']];
            }
        }
        $dots = [];
        if ($tipo === 'dots') {
            foreach ($v as $j => $x) {
                $dots[] = 'M'.$X($j).' '.$Y($x).' l0 0';
            }
        }

        return $base + [
            'estado' => $estado,
            'stats' => [
                ['k' => 'ÚLTIMO', 'v' => $fmt($ultimo).' '.$unit, 'estado' => $estado],
                ['k' => 'PROMEDIO', 'v' => $fmt($avg).' '.$unit, 'estado' => null],
                ['k' => 'MÍN', 'v' => $fmt($loss ? max($v) : min($v)).' '.$unit, 'estado' => null],
                ['k' => 'MÁX', 'v' => $fmt($loss ? min($v) : max($v)).' '.$unit, 'estado' => null],
            ],
            'yMax' => ($loss ? self::MENOS : '').number_format($mx, $dec, '.', ''),
            'yMin' => ($loss && $mn > 0 ? self::MENOS : '').number_format(max(0, $mn), $dec, '.', ''),
            'bandY' => $Y($hiv),
            'bandH' => max(0.0, round($Y($lo) - $Y($hiv), 1)),
            'baseY' => $basel === null ? -10.0 : $Y($basel),
            'linePts' => $tipo === 'line' || $tipo === 'area' ? implode(' ', $pts) : '',
            'areaD' => $tipo === 'area' ? 'M0,172 L'.implode(' L', $pts).' L600,172 Z' : '',
            'bars' => $bars,
            'dotsD' => implode(' ', $dots),
            'trendIc' => abs($diff) < ($hiv - $lo) * 0.02 ? 'trending_flat' : ($diff > 0 ? 'trending_up' : 'trending_down'),
            'trendTxt' => ($diff >= 0 ? '+' : self::MENOS).number_format(abs($diff), $dec, '.', '')." {$unit} en el periodo",
            'trendEstado' => $estado,
        ];
    }

    /** "90–120" (negativos con signo menos tipográfico). */
    private static function rangoNormalTxt(array $cfg): string
    {
        $f = fn ($x) => ((float) $x < 0 ? self::MENOS : '').number_format(abs((float) $x), (int) $cfg['dec'], '.', '');

        return $f($cfg['lo']).'–'.$f($cfg['hi']);
    }

    /**
     * Un punto por día de misión con dato (último del día UTC) entre $desde y $hasta.
     *
     * @return array<int, array{v: float, estado: string, sospechoso: ?string}>
     */
    private static function puntos(Astronauta $a, string $id, int $total, int $desde, int $hasta): array
    {
        $hoy = Carbon::now('UTC')->startOfDay();
        $dia1 = $hoy->copy()->subDays($total - 1);
        $ini = $dia1->copy()->addDays($desde - 1);
        $fin = $dia1->copy()->addDays($hasta); // exclusivo

        $filas = DB::table('mediciones')
            ->select('id', 'valores', 'medido_en', 'calidad', 'calidad_motivo')
            ->where('astronauta_id', $a->id)->where('indicador', $id)
            ->where('medido_en', '>=', $ini->toDateTimeString())->where('medido_en', '<', $fin->toDateTimeString())
            ->orderBy('medido_en')->orderBy('id')
            ->get();

        $porDia = [];
        foreach ($filas as $f) {
            $porDia[Carbon::parse($f->medido_en, 'UTC')->toDateString()] = $f; // la última del día gana
        }
        ksort($porDia);

        $evaluaciones = $porDia === []
            ? []
            : Evaluacion::whereIn('medicion_id', array_map(fn ($f) => $f->id, $porDia))->pluck('estado', 'medicion_id')->all();
        $evaluador = new EvaluadorEstado(config('umbrales'));
        $loss = (bool) (config('indicadores.marcadores')[$id]['loss'] ?? false);

        $out = [];
        foreach ($porDia as $f) {
            $valores = json_decode($f->valores, true);
            $v = (float) ($valores['valor'] ?? $valores['sistolica']);
            $e = $evaluaciones[$f->id] ?? $evaluador->evaluar($id, $valores)->value;
            $out[] = ['v' => $loss ? abs($v) : $v, 'estado' => self::ESTADO_UI[$e] ?? 'ok',
                'sospechoso' => ($f->calidad ?? 'ok') === 'sospechosa' ? ($f->calidad_motivo ?: 'Lectura dudosa del sensor.') : null];
        }

        return $out;
    }
}
