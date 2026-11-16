<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use App\Models\Medicion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resumen de salud de un tripulante para las pantallas Panel y Tripulación.
 *
 * Combina config/panel.php (textos, zonas, acciones) con el último estado de cada indicador y su
 * tendencia de 7 días. Consulta en bloque (no hay N+1 por indicador ni por astronauta):
 * una consulta para la ventana de 7 días (+ evaluaciones) y otra para los indicadores sin dato reciente.
 */
class ResumenTripulante
{
    public const MENOS = "\u{2212}"; // signo menos tipográfico

    private const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];

    /** @var array<string, array> */
    private array $panel;
    private EvaluadorEstado $evaluador;

    public function __construct(?array $panel = null, ?array $umbrales = null)
    {
        $this->panel = $panel ?? config('panel.indicadores');
        $this->evaluador = new EvaluadorEstado($umbrales ?? config('umbrales'));
    }

    public static function make(): self
    {
        return new self();
    }

    public function de(Astronauta $astronauta): array
    {
        return $this->deVarios(collect([$astronauta]))[$astronauta->id];
    }

    /**
     * @param Collection<int, Astronauta> $astronautas
     * @return array<int, array> id de astronauta => resumen (misma forma que de())
     */
    public function deVarios(Collection $astronautas): array
    {
        $ids = $astronautas->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $claves = array_keys($this->panel);
        $hoy = Carbon::now('UTC')->startOfDay();
        $desde = $hoy->copy()->subDays(6);

        $ventana = Medicion::whereIn('astronauta_id', $ids)
            ->whereIn('indicador', $claves)
            ->where('medido_en', '>=', $desde)
            ->with('evaluacion')
            ->orderBy('medido_en')->orderBy('id')
            ->get()
            ->groupBy('astronauta_id');

        // Último dato de cada (astronauta, indicador): de la ventana si lo hay; si no, de un historial más antiguo.
        $ultimas = [];
        foreach ($ventana as $aid => $lista) {
            foreach ($lista as $m) {
                $ultimas[$aid][$m->indicador] = $m;
            }
        }
        $faltan = false;
        foreach ($ids as $aid) {
            if (count($ultimas[$aid] ?? []) < count($claves)) {
                $faltan = true;
                break;
            }
        }
        if ($faltan) {
            foreach ($this->ultimasAnteriores($ids, $claves, $desde) as $m) {
                $ultimas[$m->astronauta_id][$m->indicador] ??= $m;
            }
        }

        $salida = [];
        foreach ($astronautas as $a) {
            $salida[$a->id] = $this->armar($ventana->get($a->id, collect()), $ultimas[$a->id] ?? [], $hoy);
        }

        return $salida;
    }

    /** Última medición (anterior a la ventana) por astronauta e indicador, en una sola consulta. */
    private function ultimasAnteriores(array $ids, array $claves, Carbon $desde): Collection
    {
        $maximos = DB::table('mediciones')
            ->select('astronauta_id', 'indicador', DB::raw('MAX(medido_en) as ultimo'))
            ->whereIn('astronauta_id', $ids)->whereIn('indicador', $claves)
            ->where('medido_en', '<', $desde)
            ->groupBy('astronauta_id', 'indicador');

        return Medicion::query()
            ->joinSub($maximos, 'u', fn ($j) => $j->on('mediciones.astronauta_id', '=', 'u.astronauta_id')
                ->on('mediciones.indicador', '=', 'u.indicador')
                ->on('mediciones.medido_en', '=', 'u.ultimo'))
            ->select('mediciones.*')
            ->with('evaluacion')
            ->orderBy('mediciones.id')
            ->get();
    }

    private function armar(Collection $ventana, array $ultimas, Carbon $hoy): array
    {
        $porIndicador = $ventana->groupBy('indicador');
        $indicadores = [];
        $conteo = ['crit' => 0, 'warn' => 0, 'ok' => 0];
        $ultimaMedicion = null;

        foreach ($this->panel as $clave => $cfg) {
            /** @var Medicion|null $m */
            $m = $ultimas[$clave] ?? null;
            $estado = $m ? $this->estadoDe($clave, $m) : null;
            $valor = $m ? $this->formatear($clave, $m->valores) : null;
            if ($estado) {
                $conteo[$estado]++;
            }
            if ($m && (! $ultimaMedicion || $m->medido_en->greaterThan($ultimaMedicion))) {
                $ultimaMedicion = $m->medido_en->copy();
            }

            $indicadores[] = [
                'clave' => $clave,
                'etiqueta' => $cfg['etiqueta'],
                'icono' => $cfg['icono'],
                'unidad' => $cfg['unidad'],
                'rango' => $cfg['rango'],
                'zona' => $cfg['zona'],
                'musculos' => $cfg['musculos'],
                'acciones' => $cfg['acciones'],
                'valor' => $valor,
                'sospechoso' => $m && $m->calidad === 'sospechosa' ? ($m->calidad_motivo ?: 'Questionable sensor reading.') : null,
                'estado' => $estado,
                'razon' => $valor !== null && $estado !== null && $estado !== 'ok' ? $this->razon($cfg, $valor) : null,
                'medido_en' => $m?->medido_en?->copy(),
                'tendencia' => [
                    'puntos' => $this->puntos($porIndicador->get($clave, collect()), $m, $cfg, $hoy),
                    'base' => $this->numero($cfg['base']),
                ],
                'base' => $cfg['base'],
            ];
        }

        $incidencias = array_values(array_filter($indicadores, fn ($i) => in_array($i['estado'], ['crit', 'warn'], true)));
        usort($incidencias, fn ($a, $b) => ($b['estado'] === 'crit') <=> ($a['estado'] === 'crit')); // estable en PHP 8
        $musculos = array_values(array_unique(array_merge(...array_map(fn ($i) => $i['musculos'], $incidencias) ?: [[]])));

        return [
            'indicadores' => $indicadores,
            'conteo' => $conteo,
            'peor' => $conteo['crit'] > 0 ? 'crit' : ($conteo['warn'] > 0 ? 'warn' : ($conteo['ok'] > 0 ? 'ok' : null)),
            'medido_hoy' => $ventana->contains(fn (Medicion $m) => $m->medido_en->greaterThanOrEqualTo($hoy)),
            'ultima_medicion' => $ultimaMedicion,
            'incidencias' => $incidencias,
            'musculos_activos' => $musculos,
        ];
    }

    private function estadoDe(string $clave, Medicion $m): string
    {
        $estado = $m->evaluacion?->estado
            ?? $this->evaluador->evaluar($clave, $m->valores)->value;

        return self::ESTADO_UI[$estado];
    }

    /** "128/84", "66", "−1.6" (signo menos U+2212 para negativos). */
    private function formatear(string $clave, array $valores): string
    {
        if (isset($valores['sistolica'], $valores['diastolica'])) {
            return (int) round($valores['sistolica']).'/'.(int) round($valores['diastolica']);
        }
        $dec = $this->panel[$clave]['decimales'] ?? 0;
        $v = round((float) $valores['valor'], $dec);
        $txt = number_format(abs($v), $dec, '.', '');

        return $v < 0 ? self::MENOS.$txt : $txt;
    }

    private function principal(array $valores): float
    {
        return (float) ($valores['valor'] ?? $valores['sistolica']);
    }

    private function numero(string $txt): float
    {
        return (float) str_replace(self::MENOS, '-', $txt);
    }

    /** Explicación breve de por qué el valor no está bien (lógica de reasonFor del diseño). */
    private function razon(array $cfg, string $valor): string
    {
        $lim = $cfg['limites'];
        $unidad = $cfg['unidad'];
        $n = abs($this->numero($valor));
        $max = $lim['max'];
        $maxTxt = $lim['max_txt'] ?? $max;

        if ($cfg['perdida']) {
            return $n > $max
                ? "Loss of {$n} {$unidad}: exceeds your limit of {$max} {$unidad}"
                : "Loss of {$n} {$unidad}: near your limit of {$max} {$unidad}";
        }
        if ($cfg['unidad'] === 'mSv') { // dosis acumulada: % del límite de misión
            return $valor.' mSv: '.(int) round($n / $max * 100)." % of your mission limit ({$max} mSv)";
        }
        if ($n > $max) {
            return "{$valor} {$unidad} exceeds your maximum of {$maxTxt}";
        }
        if ($n < $lim['min']) {
            return "{$valor} {$unidad} is below your minimum of ".($lim['min_txt'] ?? $lim['min']);
        }

        return "{$valor} {$unidad} is near your limit of {$maxTxt}";
    }

    /**
     * 7 valores (hace 6 días … hoy): el último de cada día UTC; huecos interpolados linealmente
     * y extremos sin dato rellenados con el vecino conocido más cercano.
     *
     * @return float[]
     */
    private function puntos(Collection $mediciones, ?Medicion $ultima, array $cfg, Carbon $hoy): array
    {
        $porDia = [];
        foreach ($mediciones as $m) { // ya ordenadas por medido_en: el último de cada día gana
            $dia = 6 - (int) $m->medido_en->copy()->utc()->startOfDay()->diffInDays($hoy, false);
            if ($dia >= 0 && $dia <= 6) {
                $porDia[$dia] = $this->principal($m->valores);
            }
        }

        if ($porDia === []) {
            $fijo = $ultima ? $this->principal($ultima->valores) : $this->numero($cfg['base']);

            return array_fill(0, 7, round($fijo, 3));
        }

        $conocidos = array_keys($porDia);
        $serie = [];
        for ($i = 0; $i < 7; $i++) {
            if (isset($porDia[$i])) {
                $serie[] = $porDia[$i];
                continue;
            }
            $antes = array_filter($conocidos, fn ($d) => $d < $i);
            $despues = array_filter($conocidos, fn ($d) => $d > $i);
            if ($antes === []) {
                $serie[] = $porDia[min($despues)];
            } elseif ($despues === []) {
                $serie[] = $porDia[max($antes)];
            } else {
                $a = max($antes);
                $b = min($despues);
                $serie[] = $porDia[$a] + ($porDia[$b] - $porDia[$a]) * ($i - $a) / ($b - $a);
            }
        }

        return array_map(fn ($v) => round($v, 3), $serie);
    }
}
