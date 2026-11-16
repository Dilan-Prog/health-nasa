<?php

namespace App\Services\Salud;

use InvalidArgumentException;

/** Medición -> estado (normal / precaución / crítico). El compuesto toma el peor componente. */
class EvaluadorEstado
{
    public function __construct(private array $config)
    {
    }

    /**
     * @param array $contexto opcional: 'entorno' (radiación diaria), 'base_temperatura' (float, base personal),
     *                        'ejercicio_reciente' (bool). Sin contexto rigen los rangos absolutos.
     */
    public function evaluar(string $clave, array $valores, array $contexto = []): Estado
    {
        $def = $this->config['indicadores'][$clave]
            ?? throw new InvalidArgumentException("Unknown indicator: {$clave}");

        foreach (array_keys($def['componentes']) as $componente) {
            if (! isset($valores[$componente])) {
                throw new InvalidArgumentException("Missing component '{$componente}'.");
            }
        }

        switch ($clave) {
            case 'radiacion_diaria':
                return $this->contraLimite((float) $valores['valor'], $this->limiteDiario($contexto['entorno'] ?? null));
            case 'dosis_acumulada':
                return $this->contraLimite((float) $valores['valor'], (float) $this->config['radiacion']['dosis_carrera_msv']);
            case 'presion_arterial':
                return $this->evaluarPresion($def, $valores);
            case 'temperatura':
                $porSalto = $this->evaluarTemperaturaPorSalto((float) $valores['valor'], $contexto);
                if ($porSalto !== null) {
                    return $porSalto;
                }
                break;
        }

        $estado = Estado::Normal;
        foreach ($def['componentes'] as $componente => $rangos) {
            $estado = $estado->peor($this->evaluarComponente((float) $valores[$componente], $rangos));
        }

        return $estado;
    }

    /** Visible band labels keyed by the stable band key (claveBanda). */
    public const BANDAS = [
        'normal' => 'Normal',
        'elevada' => 'Elevated',
        'hipertension_1' => 'Hypertension stage 1',
        'hipertension_2' => 'Hypertension stage 2',
        'crisis' => 'Hypertensive crisis',
    ];

    /**
     * Banda de presión arterial: Normal, Elevada, Hipertensión 1, Hipertensión 2 o Crisis hipertensiva.
     * null para los indicadores que no tienen bandas.
     */
    public function banda(string $clave, array $valores): ?string
    {
        if ($clave !== 'presion_arterial' || ! isset($valores['sistolica'], $valores['diastolica'])) {
            return null;
        }

        return self::BANDAS[$this->claveBanda((float) $valores['sistolica'], (float) $valores['diastolica'])];
    }

    /** Proyección de evento solar (mSv): crítica por encima del límite, con acción de refugio; null si no lo supera. */
    public function eventoSolar(float $proyeccionMsv): ?array
    {
        $cfg = $this->config['radiacion'];
        if ($proyeccionMsv <= (float) $cfg['evento_solar_critico_msv']) {
            return null;
        }

        return ['estado' => Estado::Critico, 'accion' => $cfg['evento_solar_accion']];
    }

    /** Clave interna de la banda (normal, elevada, hipertension_1, hipertension_2, crisis); null si no aplica. */
    public function claveBandaPresion(string $clave, array $valores): ?string
    {
        if ($clave !== 'presion_arterial' || ! isset($valores['sistolica'], $valores['diastolica'])) {
            return null;
        }

        return $this->claveBanda((float) $valores['sistolica'], (float) $valores['diastolica']);
    }

    /** Tipo de límite GCR ('espacio_libre' | 'superficie') que corresponde al entorno de la misión. */
    public function tipoLimiteRadiacion(?string $entorno): string
    {
        $cfg = $this->config['radiacion'];

        return $cfg['entorno_limite'][$entorno] ?? $cfg['limite_por_defecto'];
    }

    private function claveBanda(float $sis, float $dia): string
    {
        return match (true) {
            $sis > 180 || $dia > 120 => 'crisis',
            $sis >= 140 || $dia >= 90 => 'hipertension_2',
            $sis >= 130 || $dia >= 80 => 'hipertension_1',
            $sis >= 120 => 'elevada',
            default => 'normal',
        };
    }

    private function evaluarPresion(array $def, array $valores): Estado
    {
        $sis = (float) $valores['sistolica'];
        $dia = (float) $valores['diastolica'];
        $estado = Estado::from($this->config['presion_bandas'][$this->claveBanda($sis, $dia)]);

        // Lado bajo (sin bandas definidas): se conservan los rangos anteriores.
        foreach (['sistolica' => $sis, 'diastolica' => $dia] as $comp => $v) {
            $r = $def['componentes'][$comp];
            if ($v < $r['normal'][0]) {
                $estado = $estado->peor($this->evaluarComponente($v, $r));
            }
        }

        return $estado;
    }

    private function limiteDiario(?string $entorno): float
    {
        return (float) $this->config['radiacion']['limite_msv_dia'][$this->tipoLimiteRadiacion($entorno)];
    }

    /** Crítico al llegar al límite; precaución desde límite x margen. */
    private function contraLimite(float $v, float $limite): Estado
    {
        return match (true) {
            $v >= $limite => Estado::Critico,
            $v >= round($limite * (float) $this->config['radiacion']['margen_precaucion'], 6) => Estado::Precaucion, // round: evita 0.9 x 0.8 = 0.72000000000000008
            default => Estado::Normal,
        };
    }

    /** null si no hay base personal (rige el rango absoluto). */
    private function evaluarTemperaturaPorSalto(float $v, array $contexto): ?Estado
    {
        $base = $contexto['base_temperatura'] ?? null;
        if ($base === null) {
            return null;
        }
        $salto = $v - (float) $base;
        if ($salto > 0 && ($contexto['ejercicio_reciente'] ?? false)) {
            return Estado::Normal; // temperatura alta tras ejercicio: no alerta
        }
        $cfg = $this->config['temperatura_salto'];
        $abs = round(abs($salto), 6);

        return match (true) {
            $abs >= $cfg['critico_c'] => Estado::Critico,
            $abs >= $cfg['precaucion_c'] => Estado::Precaucion,
            default => Estado::Normal,
        };
    }

    private function evaluarComponente(float $v, array $rangos): Estado
    {
        if ($v >= $rangos['normal'][0] && $v <= $rangos['normal'][1]) {
            return Estado::Normal;
        }
        if ($v >= $rangos['precaucion'][0] && $v <= $rangos['precaucion'][1]) {
            return Estado::Precaucion;
        }

        return Estado::Critico;
    }

    /**
     * Tendencia de una serie ordenada de más antigua a más reciente.
     * Compara el promedio de la segunda mitad con el de la primera.
     * El umbral es relativo (5% por defecto) para no marcar ruido como tendencia.
     *
     * @param float[] $serie
     * @return 'subiendo'|'bajando'|'estable'
     */
    public function tendencia(array $serie, float $umbralRelativo = 0.05): string
    {
        $n = count($serie);
        if ($n < 4) {
            return 'estable';
        }
        $mitad = intdiv($n, 2);
        $ini = array_sum(array_slice($serie, 0, $mitad)) / $mitad;
        $fin = array_sum(array_slice($serie, $n - $mitad)) / $mitad;
        $base = max(abs($ini), 1e-9);
        $cambio = ($fin - $ini) / $base;

        return match (true) {
            $cambio > $umbralRelativo => 'subiendo',
            $cambio < -$umbralRelativo => 'bajando',
            default => 'estable',
        };
    }
}
