<?php

namespace App\Services\Salud;

use Carbon\CarbonInterface;

/**
 * Una medición lista para ProcesadorMediciones::registrar(), venga del simulador o del reloj real
 * (POST /api/mediciones: {indicador, valor, medido_en}). Cambiar de fuente no cambia lo que consume el motor de reglas.
 */
final class MedicionEntrante
{
    /** @param array<string, float|int> $valores {"valor":72} o {"sistolica":120,"diastolica":80} */
    public function __construct(
        public readonly string $indicador,
        public readonly array $valores,
        public readonly CarbonInterface $medidoEn,
    ) {
    }

    /** @return array{indicador: string, valor?: float|int, sistolica?: float|int, diastolica?: float|int, medido_en: string} */
    public function aPayload(): array
    {
        return ['indicador' => $this->indicador, ...$this->valores, 'medido_en' => $this->medidoEn->toIso8601String()];
    }
}
