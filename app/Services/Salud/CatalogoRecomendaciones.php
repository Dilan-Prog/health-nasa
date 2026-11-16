<?php

namespace App\Services\Salud;

/**
 * Acción concreta por estado. La herramienta orienta, NO diagnostica.
 * Los textos son ejemplos pendientes de revisión médica.
 */
class CatalogoRecomendaciones
{
    private const GENERALES = [
        'normal' => 'Value within the expected range. Continue your routine measurements.',
        'precaucion' => 'Repeat the measurement in a few minutes, rest, hydrate and log how you feel.',
        'critico' => 'Repeat the measurement immediately. If confirmed, notify a crew member and contact the Flight Surgeon.',
    ];

    private const ESPECIFICAS = [
        'frecuencia_cardiaca' => [
            'precaucion' => 'Stop intense exercise, breathe calmly and repeat the measurement at rest.',
        ],
        'glucosa' => [
            'precaucion' => 'Review your last meal and repeat the measurement; avoid simple sugars until confirmed.',
        ],
        'temperatura' => [
            'precaucion' => 'Check the module temperature, hydrate and watch for signs of infection.',
        ],
        'presion_arterial' => [
            'precaucion' => 'Rest seated for 5 min, repeat the measurement on the same arm and drink water.',
        ],
        'saturacion_o2' => [
            'precaucion' => 'Breathe deeply and slowly, check the sensor and finger placement, and repeat the measurement.',
        ],
        'imc' => [
            'precaucion' => 'Review your meal log for the week and discuss it at your next nutrition consultation.',
        ],
        'perdida_osea' => [
            'precaucion' => 'Complete your ARED loading session today and log the minutes of resistive exercise.',
        ],
        'masa_muscular' => [
            'precaucion' => 'Complete the leg strength routine, log sets and loads, and review your diet with nutrition.',
        ],
        'radiacion_diaria' => [
            'precaucion' => 'Reduce your time in poorly shielded modules today and sleep in the shielded area.',
        ],
        'dosis_acumulada' => [
            'precaucion' => 'Review the remaining EVA plan with the ground and prioritize shielded areas in your schedule.',
        ],
        'sueno' => [
            'precaucion' => 'Adjust your rest schedule and reduce light and noise before sleeping.',
        ],
        'animo' => [
            'precaucion' => 'Talk with a crew member or schedule a support call; maintain your social routine.',
        ],
    ];

    /** Texto de la alarma por proyección de evento solar (solo texto; la entrada del evento es del Nivel 3). */
    public const EVENTO_SOLAR = 'Alarm: projected solar event above the limit. Go immediately to the shielded shelter and alert the ground.';

    private const CRISIS_HIPERTENSIVA = 'Hypertensive crisis: rest seated, repeat the measurement and contact the Flight Surgeon immediately. If there is chest pain, shortness of breath or confusion, ask a crew member for help now.';

    public function para(string $clave, Estado $estado, ?string $banda = null): string
    {
        if ($clave === 'presion_arterial' && ($banda === 'crisis' || $banda === EvaluadorEstado::BANDAS['crisis'])) {
            return self::CRISIS_HIPERTENSIVA;
        }

        return self::ESPECIFICAS[$clave][$estado->value] ?? self::GENERALES[$estado->value];
    }
}
