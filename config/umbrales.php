<?php

/*
 | Umbrales de los indicadores de salud.
 |
 | IMPORTANTE: son valores de EJEMPLO, NO validados contra NASA HRP / NASA-STD-3001.
 | Antes de la presentación hay que citar la fuente de cada umbral o marcarlo como
 | "valor de ejemplo" en la interfaz. Están ajustados para que los valores de la
 | tripulación de ejemplo (ExpedicionSeeder) den exactamente los estados del diseño.
 |
 | Para cada componente:
 |   valido     -> fuera de este rango la medición es imposible (error de captura)
 |   normal     -> dentro: estado normal
 |   precaucion -> dentro (y fuera de normal): precaución; fuera: crítico
 |   decimales  -> (opcional) decimales con que el simulador redondea el valor
 |
 | Pérdida ósea y masa muscular se guardan con signo: -1.6 = pérdida de 1.6 %.
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
                'sistolica' => ['valido' => [50, 260], 'normal' => [90, 120], 'precaucion' => [80, 135]],
                'diastolica' => ['valido' => [30, 160], 'normal' => [60, 80], 'precaucion' => [50, 90]],
            ],
        ],
        'frecuencia_cardiaca' => [
            'nombre' => 'Frecuencia cardíaca',
            'unidad' => 'lpm',
            'sistema' => 'cardiovascular',
            'riesgos' => ['gravedad_alterada', 'radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [20, 250], 'normal' => [60, 100], 'precaucion' => [50, 120]],
            ],
        ],
        'saturacion_o2' => [
            'nombre' => 'Saturación de O₂',
            'unidad' => '%',
            'sistema' => 'cardiovascular',
            'riesgos' => ['entorno_cerrado', 'gravedad_alterada'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [50, 100], 'normal' => [95, 100], 'precaucion' => [90, 100]],
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
                'valor' => ['valido' => [30, 45], 'normal' => [36.1, 37.2], 'precaucion' => [35.0, 38.0], 'decimales' => 1],
            ],
        ],
        'imc' => [
            'nombre' => 'Índice de masa corporal',
            'unidad' => 'kg/m²',
            'sistema' => 'metabolico',
            'riesgos' => ['entorno_cerrado', 'gravedad_alterada'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [10, 60], 'normal' => [18.5, 24.9], 'precaucion' => [16.0, 29.9], 'decimales' => 1],
            ],
        ],
        'perdida_osea' => [
            'nombre' => 'Pérdida ósea (cadera)',
            'unidad' => '%/mes',
            'sistema' => 'oseo',
            'riesgos' => ['gravedad_alterada'],
            'frecuencia' => 'semanal',
            'componentes' => [
                'valor' => ['valido' => [-15, 5], 'normal' => [-1.0, 0.0], 'precaucion' => [-1.3, 0.0], 'decimales' => 1],
            ],
        ],
        'masa_muscular' => [
            'nombre' => 'Masa muscular',
            'unidad' => '% misión',
            'sistema' => 'oseo',
            'riesgos' => ['gravedad_alterada'],
            'frecuencia' => 'semanal',
            'componentes' => [
                'valor' => ['valido' => [-30, 10], 'normal' => [-2.0, 0.0], 'precaucion' => [-3.0, 0.0], 'decimales' => 1],
            ],
        ],
        'radiacion_diaria' => [
            'nombre' => 'Radiación diaria',
            'unidad' => 'mSv/día',
            'sistema' => 'inmune',
            'riesgos' => ['radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 50], 'normal' => [0.0, 0.47], 'precaucion' => [0.0, 0.5], 'decimales' => 2],
            ],
        ],
        'dosis_acumulada' => [
            'nombre' => 'Dosis acumulada',
            'unidad' => 'mSv',
            'sistema' => 'inmune',
            'riesgos' => ['radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 1000], 'normal' => [0, 70], 'precaucion' => [0, 100], 'decimales' => 1],
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
