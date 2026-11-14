<?php

namespace App\Services\Salud;

use InvalidArgumentException;

/**
 * Detecta valores imposibles, faltantes y duplicados.
 * $valores es un arreglo componente => número, p. ej. ['sistolica' => 120, 'diastolica' => 80]
 * o ['valor' => 72] para indicadores simples.
 */
class ValidadorMedicion
{
    public function __construct(private array $config)
    {
    }

    /** @return string[] lista de errores; vacía si la medición es válida */
    public function validar(string $clave, array $valores): array
    {
        $def = $this->definicion($clave);
        $errores = [];

        foreach ($def['componentes'] as $componente => $rangos) {
            if (! array_key_exists($componente, $valores) || $valores[$componente] === null || $valores[$componente] === '') {
                $errores[] = "Falta el componente '{$componente}'.";
                continue;
            }
            if (! is_numeric($valores[$componente])) {
                $errores[] = "El componente '{$componente}' no es numérico.";
                continue;
            }
            [$min, $max] = $rangos['valido'];
            $v = (float) $valores[$componente];
            if ($v < $min || $v > $max) {
                $errores[] = "El valor {$v} de '{$componente}' es imposible (rango válido {$min}-{$max}).";
            }
        }

        if (isset($valores['sistolica'], $valores['diastolica'])
            && is_numeric($valores['sistolica']) && is_numeric($valores['diastolica'])
            && (float) $valores['diastolica'] >= (float) $valores['sistolica']) {
            $errores[] = 'La diastólica debe ser menor que la sistólica.';
        }

        return $errores;
    }

    /**
     * Una medición es duplicada si ya existe otra del mismo indicador, con los mismos
     * valores, dentro de la ventana indicada (segundos).
     *
     * @param array $previas  lista de ['instante' => int (timestamp), 'valores' => array]
     */
    public function esDuplicada(array $valores, int $instante, array $previas, int $ventanaSegundos = 60): bool
    {
        foreach ($previas as $p) {
            if (abs($instante - $p['instante']) <= $ventanaSegundos && $p['valores'] == $valores) {
                return true;
            }
        }

        return false;
    }

    private function definicion(string $clave): array
    {
        return $this->config['indicadores'][$clave]
            ?? throw new InvalidArgumentException("Indicador desconocido: {$clave}");
    }
}
