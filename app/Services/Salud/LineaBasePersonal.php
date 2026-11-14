<?php

namespace App\Services\Salud;

/** Compara una medición contra el historial del propio astronauta. */
class LineaBasePersonal
{
    /** Mínimo de mediciones previas para que la línea base sea confiable. */
    public const MINIMO_HISTORIAL = 5;

    /**
     * @param float[] $historial valores previos del astronauta (sin incluir el actual)
     * @return array{media: float, desviacion: float, z: float}|null null si hay poco historial
     */
    public function calcular(array $historial, float $valor): ?array
    {
        $n = count($historial);
        if ($n < self::MINIMO_HISTORIAL) {
            return null;
        }
        $media = array_sum($historial) / $n;
        $var = array_sum(array_map(fn ($x) => ($x - $media) ** 2, $historial)) / $n;
        $desv = sqrt($var);
        // Evita z infinitos si el historial es constante.
        $piso = max($desv, abs($media) * 0.02, 1e-9);

        return ['media' => $media, 'desviacion' => $desv, 'z' => ($valor - $media) / $piso];
    }

    /**
     * Si el valor se aleja 2 o más desviaciones de su línea base, sube un normal a precaución.
     * Nunca baja la gravedad: la comparación personal solo puede agravar.
     */
    public function ajustar(Estado $estado, ?array $base): Estado
    {
        if ($base !== null && $estado === Estado::Normal && abs($base['z']) >= 2.0) {
            return Estado::Precaucion;
        }

        return $estado;
    }
}
