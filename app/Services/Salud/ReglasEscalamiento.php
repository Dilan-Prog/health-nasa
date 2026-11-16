<?php

namespace App\Services\Salud;

/**
 * Decide cuándo basta una recomendación y cuándo alertar a la tripulación o a tierra.
 * Con retardo alto de comunicación, tierra no puede ser la primera respuesta:
 * la herramienta orienta a bordo y además encola el aviso.
 */
class ReglasEscalamiento
{
    public const NINGUNO = 'ninguno';
    public const RECOMENDAR = 'recomendar';
    public const ALERTAR_TRIPULACION = 'alertar_tripulacion';
    public const CONTACTAR_TIERRA = 'contactar_tierra';

    /**
     * Nivel de atención 3 = telemedicina síncrona; 4-5 = triaje autónomo local (orientación a bordo inmediata,
     * sin videoconsulta; el aviso a tierra se encola igual).
     *
     * @return array{accion: string, a_bordo: bool, encolar_tierra: bool, videoconsulta: bool}
     */
    public function decidir(Estado $estado, string $tendencia = 'estable', int $retardoMin = 0, int $careLevel = 3): array
    {
        $empeorando = $tendencia !== 'estable' && $estado !== Estado::Normal;

        $accion = match (true) {
            $estado === Estado::Critico => self::CONTACTAR_TIERRA,
            $estado === Estado::Precaucion && $empeorando => self::ALERTAR_TRIPULACION,
            $estado === Estado::Precaucion => self::RECOMENDAR,
            default => self::NINGUNO,
        };

        $autonomo = $careLevel >= 4;

        return [
            'accion' => $accion,
            // Con retardo alto, un crítico se atiende primero a bordo (tripulación) mientras la alerta viaja.
            'a_bordo' => $accion !== self::NINGUNO && ($accion !== self::CONTACTAR_TIERRA || $retardoMin >= 5 || $autonomo),
            'encolar_tierra' => $accion === self::CONTACTAR_TIERRA || $accion === self::ALERTAR_TRIPULACION,
            'videoconsulta' => ! $autonomo && $accion === self::CONTACTAR_TIERRA,
        ];
    }
}
