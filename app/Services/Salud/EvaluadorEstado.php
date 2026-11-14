<?php

namespace App\Services\Salud;

use InvalidArgumentException;

/** Medición -> estado (normal / precaución / crítico). El compuesto toma el peor componente. */
class EvaluadorEstado
{
    public function __construct(private array $config)
    {
    }

    public function evaluar(string $clave, array $valores): Estado
    {
        $def = $this->config['indicadores'][$clave]
            ?? throw new InvalidArgumentException("Indicador desconocido: {$clave}");

        $estado = Estado::Normal;
        foreach ($def['componentes'] as $componente => $rangos) {
            if (! isset($valores[$componente])) {
                throw new InvalidArgumentException("Falta el componente '{$componente}'.");
            }
            $estado = $estado->peor($this->evaluarComponente((float) $valores[$componente], $rangos));
        }

        return $estado;
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
