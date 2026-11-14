<?php

/*
 | Umbrales de los indicadores de salud.
 |
 | IMPORTANTE: son valores de referencia clínica general, NO validados contra
 | NASA HRP / NASA-STD-3001. Antes de la presentación hay que citar la fuente de
 | cada umbral o marcarlo como "valor de ejemplo" en la interfaz.
 |
 | Para cada componente:
 |   valido     -> fuera de este rango la medición es imposible (error de captura)
 |   normal     -> dentro: estado normal
 |   precaucion -> dentro (y fuera de normal): precaución; fuera: crítico
 */

return [
    'indicadores' => [
        'presion_arterial' => [
            'nombre' => 'Presión arterial',
            'unidad' => 'mmHg',
            'sistema' => 'cardiovascular',
            'riesgos' => ['gravedad_alterada', 'radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'sistolica' => ['valido' => [50, 260], 'normal' => [90, 129], 'precaucion' => [80, 159]],
                'diastolica' => ['valido' => [30, 160], 'normal' => [60, 84], 'precaucion' => [50, 99]],
            ],
        ],
        'frecuencia_cardiaca' => [
            'nombre' => 'Frecuencia cardíaca',
            'unidad' => 'lpm',
            'sistema' => 'cardiovascular',
            'riesgos' => ['gravedad_alterada', 'radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [20, 250], 'normal' => [50, 100], 'precaucion' => [40, 120]],
            ],
        ],
        'glucosa' => [
            'nombre' => 'Glucosa en sangre',
            'unidad' => 'mg/dL',
            'sistema' => 'metabolico',
            'riesgos' => ['entorno_cerrado'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [20, 600], 'normal' => [70, 140], 'precaucion' => [54, 199]],
            ],
        ],
        'temperatura' => [
            'nombre' => 'Temperatura corporal',
            'unidad' => '°C',
            'sistema' => 'inmune',
            'riesgos' => ['radiacion', 'entorno_cerrado'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [30, 45], 'normal' => [36.0, 37.5], 'precaucion' => [35.0, 38.4]],
            ],
        ],
        'sueno' => [
            'nombre' => 'Horas de sueño',
            'unidad' => 'h/día',
            'sistema' => 'mental',
            'riesgos' => ['aislamiento', 'entorno_cerrado'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 24], 'normal' => [6.5, 9], 'precaucion' => [4, 10]],
            ],
        ],
        'animo' => [
            'nombre' => 'Estado de ánimo',
            'unidad' => 'escala 1-5',
            'sistema' => 'mental',
            'riesgos' => ['aislamiento'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [1, 5], 'normal' => [3, 5], 'precaucion' => [2, 5]],
            ],
        ],
    ],

    'riesgos' => [
        'radiacion' => 'Radiación espacial',
        'aislamiento' => 'Aislamiento y confinamiento',
        'distancia' => 'Distancia de la Tierra',
        'gravedad_alterada' => 'Campos gravitatorios alterados',
        'entorno_cerrado' => 'Entornos cerrados y hostiles',
    ],

    'sistemas' => ['cardiovascular', 'oseo', 'inmune', 'mental', 'metabolico'],

    // Retardo de comunicación (minutos, un trayecto) por entorno de misión.
    'retardo_min' => [
        'estacion' => 0,
        'nave' => 1,
        'base_lunar' => 2,
        'base_marciana' => 20,
    ],
];
