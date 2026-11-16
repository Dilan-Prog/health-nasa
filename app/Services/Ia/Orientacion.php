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
     * @param array<int, string|array{etiqueta:string, texto:string, nota?:string}> $expedienteRelevante  [] si no hay nada (la vista dice «Sin antecedentes relacionados»); etiqueta = ALERGIA | CONDICIÓN MÉDICA | ANTECEDENTE FAMILIAR
     * @param array<int, string|array{etiqueta:string, texto:string, nota?:string}> $tratamientos  [] si no hay nada (solo lectura); p. ej. etiqueta «CONSULTA A TIERRA · 3 NOV»
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
        // Diseño "Médico de vuelo · flotante": datos de presentación (todos opcionales).
        public readonly string $icono = 'stethoscope',       // icono Material Symbols del patrón
        public readonly string $evidencia = '',              // "SpO₂ 89 (CRÍTICO) · FC 108 (PRECAUCIÓN) · VENTANA 10 MIN"
        public readonly bool $empeoro = false,               // la severidad subió desde que se abrió el caso
        public readonly ?string $detectadoEn = null,         // "10:42" (UTC)
        public readonly ?string $escaladoEn = null,          // "10:42" (UTC) si ya se avisó a tierra
        public readonly ?string $auditadoEn = null,          // "10:42" (UTC) del acceso al expediente registrado
        public readonly ?string $matiz = null,               // párrafo que usa el historial de tratamientos para matizar
    ) {
    }
}
