<?php

namespace App\Services\Salud;

/**
 * Acción concreta por estado. La herramienta orienta, NO diagnostica.
 * Los textos son ejemplos pendientes de revisión médica.
 */
class CatalogoRecomendaciones
{
    private const GENERALES = [
        'normal' => 'Valor dentro de lo esperado. Continúa con tu rutina de mediciones.',
        'precaucion' => 'Repite la medición en unos minutos, descansa, hidrátate y registra cómo te sientes.',
        'critico' => 'Repite la medición de inmediato. Si se confirma, avisa a un compañero y contacta al flight surgeon.',
    ];

    private const ESPECIFICAS = [
        'frecuencia_cardiaca' => [
            'precaucion' => 'Suspende el ejercicio intenso, respira con calma y repite la medición en reposo.',
        ],
        'glucosa' => [
            'precaucion' => 'Revisa tu última comida y repite la medición; evita azúcares simples hasta confirmar.',
        ],
        'temperatura' => [
            'precaucion' => 'Revisa la temperatura del módulo, hidrátate y vigila síntomas de infección.',
        ],
        'sueno' => [
            'precaucion' => 'Ajusta tu horario de descanso y reduce la luz y el ruido antes de dormir.',
        ],
        'animo' => [
            'precaucion' => 'Habla con un compañero o programa una llamada de apoyo; cuida tu rutina social.',
        ],
    ];

    public function para(string $clave, Estado $estado): string
    {
        return self::ESPECIFICAS[$clave][$estado->value] ?? self::GENERALES[$estado->value];
    }
}
