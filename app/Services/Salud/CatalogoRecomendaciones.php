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
        'presion_arterial' => [
            'precaucion' => 'Reposa 5 min sentado, repite la medición en el mismo brazo y bebe agua.',
        ],
        'saturacion_o2' => [
            'precaucion' => 'Respira profundo y despacio, revisa el sensor y la postura del dedo, y repite la medición.',
        ],
        'imc' => [
            'precaucion' => 'Revisa tu registro de comidas de la semana y coméntalo en tu próxima consulta de nutrición.',
        ],
        'perdida_osea' => [
            'precaucion' => 'Completa hoy tu sesión de carga en el ARED y registra los minutos de ejercicio resistido.',
        ],
        'masa_muscular' => [
            'precaucion' => 'Completa la rutina de fuerza de piernas, registra series y cargas y revisa tu dieta con nutrición.',
        ],
        'radiacion_diaria' => [
            'precaucion' => 'Reduce hoy el tiempo en módulos poco blindados y duerme en la zona blindada.',
        ],
        'dosis_acumulada' => [
            'precaucion' => 'Revisa con tierra el plan de EVAs restantes y prioriza las zonas blindadas en tu horario.',
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
