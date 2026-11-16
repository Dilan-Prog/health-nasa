<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use Illuminate\Support\Facades\DB;

/**
 * Desviación del valor actual respecto a la línea base PRE-VUELO del tripulante (tabla linea_base).
 * Si no hay fila, usa el valor del expediente (config linea_base.expediente_campos). No interpreta ni diagnostica.
 * Complementa (no reemplaza) la base móvil de LineaBasePersonal.
 */
class DesviacionBase
{
    /** Valor base de la métrica; null si no hay ni fila en linea_base ni campo equivalente en el expediente. */
    public static function valorBase(Astronauta $a, string $metrica): ?float
    {
        $q = DB::table('linea_base')->select('valor', DB::raw('0 as prio'))
            ->where('astronauta_id', $a->id)->where('metrica', $metrica);

        $col = config('linea_base.expediente_campos.'.$metrica);
        if (is_string($col) && $col !== '') {
            $q->unionAll(
                DB::table('expedientes_medicos')->select($col.' as valor', DB::raw('1 as prio'))
                    ->where('astronauta_id', $a->id)->whereNotNull($col)
            ); // una sola consulta; la fila de linea_base (prio 0) gana
        }

        $fila = $q->get()->sortBy('prio')->first();

        return $fila && $fila->valor !== null ? (float) $fila->valor : null;
    }

    /** (actual − base) / base × 100; null sin base o con base 0. */
    public static function porcentaje(Astronauta $a, string $metrica, float $actual): ?float
    {
        return self::calcular(self::valorBase($a, $metrica), $actual);
    }

    /** Cálculo puro del porcentaje. */
    public static function calcular(?float $base, float $actual): ?float
    {
        if ($base === null || $base == 0.0) {
            return null;
        }

        return ($actual - $base) / $base * 100;
    }

    /**
     * Para el Indicador: ['metrica' => string, 'base' => float, 'pct' => float (1 decimal)], o null si el indicador
     * no tiene métrica asociada (config linea_base.indicador_metrica) o no hay base.
     *
     * @return array{metrica: string, base: float, pct: float}|null
     */
    public static function paraIndicador(Astronauta $a, string $claveIndicador, float $actual): ?array
    {
        $metrica = config('linea_base.indicador_metrica.'.$claveIndicador);
        if (! is_string($metrica)) {
            return null;
        }
        $base = self::valorBase($a, $metrica);
        $pct = self::calcular($base, $actual);

        return $pct === null ? null : ['metrica' => $metrica, 'base' => $base, 'pct' => round($pct, 1)];
    }

    /**
     * Regla PREPARADA (sin datos: los biomarcadores son del Nivel 3). Pura: true si está en alerta; null si la regla
     * no existe o no es calculable (base 0 en reglas por porcentaje).
     */
    public static function evaluarRegla(string $regla, float $base, float $actual): ?bool
    {
        $r = config('linea_base.reglas.'.$regla);
        if (! is_array($r)) {
            return null;
        }
        if ($r['tipo'] === 'pct_minimo') {
            return $base == 0.0 ? null : ($actual / $base * 100) < $r['umbral'];
        }
        if ($r['tipo'] === 'delta_maximo') {
            return ($actual - $base) >= $r['umbral'];
        }

        return null;
    }

    /**
     * Evalúa todas las reglas con valores directos (métrica => valor). Devuelve las reglas en alerta:
     * [['regla' => clave, 'metrica' => métrica, 'texto' => string]].
     *
     * @param array<string, float> $bases
     * @param array<string, float> $actuales
     */
    public static function evaluarReglas(array $bases, array $actuales): array
    {
        $out = [];
        foreach (config('linea_base.reglas', []) as $clave => $r) {
            foreach ($r['metricas'] as $m) {
                if (isset($bases[$m], $actuales[$m]) && self::evaluarRegla($clave, (float) $bases[$m], (float) $actuales[$m]) === true) {
                    $out[] = ['regla' => $clave, 'metrica' => $m, 'texto' => $r['texto']];
                }
            }
        }

        return $out;
    }
}
