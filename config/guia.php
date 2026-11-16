<?php

/*
 | Contenido de la pantalla "Guía educativa" (/guia). Las claves son las de config/indicadores.php (14 marcadores).
 |
 |  parte   Zona del cuerpo o sistema que se muestra junto al nombre
 |  texto   Qué mide el indicador y por qué cambia en microgravedad (divulgación general; no diagnostica)
 |
 |  requisito_dominio  Dominio de la tabla `requisitos` con el que se vincula la tarjeta (null = sin requisito asociado;
 |                      la Guía muestra entonces «Umbral de ejemplo (sin requisito asociado)»). Vínculo explícito, no inferido.
 |
 | Los rangos y la recomendación "Qué hacer" salen de config/indicadores.php. Orienta, no diagnostica ni receta.
 */

return [
    'marcadores' => [
        'presion_arterial' => [
            'requisito_dominio' => 'presion_arterial',
            'parte' => 'Tórax',
            'texto' => 'En microgravedad los fluidos suben hacia la cabeza y el pecho, lo que altera la presión y la regulación del corazón.',
        ],
        'frecuencia_cardiaca' => [
            'requisito_dominio' => 'pulso',
            'parte' => 'Corazón',
            'texto' => 'El corazón trabaja menos sin gravedad y puede perder masa; el ejercicio aeróbico lo mantiene en forma.',
        ],
        'saturacion_o2' => [
            'requisito_dominio' => null,
            'parte' => 'Pulmones',
            'texto' => 'Mide cuánto oxígeno transporta la sangre. Depende de la atmósfera de la cabina y de la función pulmonar.',
        ],
        'glucosa' => [
            'requisito_dominio' => null,
            'parte' => 'Sangre',
            'texto' => 'La menor actividad muscular reduce la sensibilidad a la insulina y eleva la glucosa.',
        ],
        'temperatura' => [
            'requisito_dominio' => 'temperatura',
            'parte' => 'Cuerpo entero',
            'texto' => 'Sin convección natural, el cuerpo disipa peor el calor; la temperatura central suele subir algo en vuelo.',
        ],
        'imc' => [
            'requisito_dominio' => null,
            'parte' => 'Cuerpo entero',
            'texto' => 'Relaciona peso y altura. En misiones largas suele bajar por pérdida de músculo y hueso.',
        ],
        'hidratacion' => [
            'requisito_dominio' => 'hidratacion',
            'parte' => 'Riñones',
            'texto' => 'En el espacio la sed disminuye; beber poco aumenta el riesgo de cálculos renales por el calcio liberado del hueso.',
        ],
        'perdida_osea' => [
            'requisito_dominio' => 'hueso',
            'parte' => 'Cadera y columna',
            'texto' => 'Sin carga sobre el esqueleto se pierde en torno al 1–1.5 % de densidad ósea al mes en cadera y columna.',
        ],
        'masa_muscular' => [
            'requisito_dominio' => 'fuerza',
            'parte' => 'Piernas y espalda',
            'texto' => 'Los músculos posturales (piernas, espalda) se atrofian rápido sin ejercicio de resistencia.',
        ],
        'radiacion_diaria' => [
            'requisito_dominio' => 'radiacion',
            'parte' => 'Todo el cuerpo',
            'texto' => 'Fuera de la protección completa de la atmósfera, la radiación cósmica y solar aumenta el riesgo a largo plazo.',
        ],
        'dosis_acumulada' => [
            'requisito_dominio' => 'radiacion',
            'parte' => 'Todo el cuerpo',
            'texto' => 'Suma de toda la radiación recibida; se compara con límites de misión y de carrera.',
        ],
        'sueno' => [
            'requisito_dominio' => 'sueno',
            'parte' => 'Cerebro',
            'texto' => 'Con 16 amaneceres al día, ruido y luz artificial, el ritmo circadiano se desajusta con facilidad.',
        ],
        'estres_cognitivo' => [
            'requisito_dominio' => 'conducta',
            'parte' => 'Cerebro',
            'texto' => 'El aislamiento y la carga de trabajo afectan atención, memoria y estado de ánimo.',
        ],
        'vision_sans' => [
            'requisito_dominio' => 'vision',
            'parte' => 'Ojos',
            'texto' => 'El síndrome neuro-ocular asociado al vuelo espacial (SANS) cambia la forma del ojo y la visión de cerca.',
        ],
    ],

    /*
     | Tarjetas extra: indicadores que NO están en el catálogo de 14 marcadores (config/indicadores.php) pero sí se
     | registran (config/umbrales.php). Se muestran tras las 14, con el mismo estilo. `umbral` = clave en config/umbrales.php.
     */
    'extras' => [
        'animo' => [
            'umbral' => 'animo',
            'label' => 'Estado de ánimo',
            'ic' => 'mood',
            'parte' => 'Cerebro',
            'requisito_dominio' => null,
            'texto' => 'El aislamiento, el confinamiento y la distancia de la Tierra pueden afectar al ánimo. Se registra en una escala VAS de 0 a 100 (100 = muy bien); sirve para ver tendencias, no para diagnosticar.',
            'rec' => 'Habla con un compañero o programa una llamada de apoyo; cuida tu rutina social.',
        ],
    ],
];
