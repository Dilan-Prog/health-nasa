<?php

namespace App\Services\Ia;

/**
 * Mensaje de orientación para un caso. Lenguaje de hipótesis, sin dosis ni fármacos recomendados.
 * Forma estable: la IA real (futura) devuelve exactamente esto.
 */
final class Orientacion
{
    /**
     * @param array<int, array{texto:string, fuente:?array}> $acciones  fuente = salida de ResolvedorRequisito::fuente() o ['etiqueta','texto'] de la ficha/guía
     * @param array<int, array{indicador:string, etiqueta:string, unidad:string, serie:array<int,float>, actual:string, base:?string}> $historial7d
     * @param array<int, string> $expedienteRelevante  ['Sin antecedentes relacionados'] si no hay nada
     * @param array<int, string> $tratamientos         ['Sin tratamientos registrados'] si no hay nada (solo lectura)
     * @param array<int, array{etiqueta:string, texto:string}> $fuentes
     */
    public function __construct(
        public readonly int $casoId,
        public readonly int $astronautaId,
        public readonly string $patron,
        public readonly string $severidad,      // precaucion | critico
        public readonly string $titulo,         // p. ej. "Posible hipoxia"
        public readonly string $hallazgo,       // en hipótesis
        public readonly array $acciones,
        public readonly array $historial7d,
        public readonly array $expedienteRelevante,
        public readonly array $tratamientos,
        public readonly array $fuentes,
        public readonly int $confianza,         // 0-100, de la regla del detector; se muestra como simulada
        public readonly string $origen = 'simulado',
        public readonly string $revisionMedica = 'pendiente',
    ) {
    }
}
