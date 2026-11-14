<?php

namespace App\Services\Salud;

/**
 * Genera mediciones plausibles para la demo. Determinista si se pasa una semilla.
 * Cada indicador oscila alrededor del centro de su rango normal; $deriva empuja la serie
 * hacia afuera para provocar precaución o crítico (0 = sin deriva, 1 = deriva fuerte).
 */
class SimuladorMediciones
{
    public function __construct(private array $config)
    {
    }

    /** @return array<string, float> componente => valor, siempre dentro del rango válido */
    public function generar(string $clave, float $deriva = 0.0, ?int $semilla = null): array
    {
        if ($semilla !== null) {
            mt_srand($semilla);
        }
        $def = $this->config['indicadores'][$clave] ?? throw new \InvalidArgumentException("Indicador desconocido: {$clave}");

        $valores = [];
        foreach ($def['componentes'] as $componente => $r) {
            [$nMin, $nMax] = $r['normal'];
            [$vMin, $vMax] = $r['valido'];
            $centro = ($nMin + $nMax) / 2;
            $semi = ($nMax - $nMin) / 2;
            $ruido = (mt_rand() / mt_getrandmax() * 2 - 1) * $semi * 0.8;
            $v = $centro + $ruido + $deriva * $semi * 2.2;
            $v = min(max($v, $vMin), $vMax);
            $valores[$componente] = round($v, $componente === 'valor' && $nMax < 12 ? 1 : 0);
        }

        // La diastólica nunca puede igualar o superar a la sistólica.
        if (isset($valores['sistolica'], $valores['diastolica']) && $valores['diastolica'] >= $valores['sistolica']) {
            $valores['diastolica'] = $valores['sistolica'] - 20;
        }

        return $valores;
    }
}
