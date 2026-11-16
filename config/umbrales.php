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
 | Hidratación, sueño, estrés cognitivo y visión (SANS) alimentan solo la pantalla Indicador (config/indicadores.php);
 | sus rangos siguen los de la maqueta (normal = lo..hi; precaución = margen del 30 % del ancho del rango).
 */

// Radiación: derivados de 'radiacion' (más abajo) para que los rangos de referencia de las pantallas
// y del simulador (normal/precaucion) no se desalineen de la regla que usa el evaluador.
$gcrMinimo = min($radLimites = ['espacio_libre' => 1.3, 'superficie' => 0.9]);
$margenRad = 0.8;      // supuesto del proyecto, no de la norma
$dosisCarrera = 600;   // mSv de carrera (dato del encargo del Nivel 2, valor de ejemplo)

return [
    'indicadores' => [
        'presion_arterial' => [
            'nombre' => 'Presión arterial',
            'unidad' => 'mmHg',
            'sistema' => 'cardiovascular',
            'riesgos' => ['gravedad_alterada', 'radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                // El estado por encima de lo normal lo decide 'presion_bandas' (más abajo); estos rangos
                // quedan para el lado BAJO (hipotensión, sin banda definida), el simulador y los textos de pantalla.
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
                // 'valido' = rango de validez. 'normal'/'precaucion' absolutos solo rigen SIN base personal
                // (ni pre-vuelo ni base móvil); con base manda el salto de 'temperatura_salto'.
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
                // normal/precaucion: referencia (límite más estricto) para simulador y pantallas; el evaluador usa 'radiacion' por entorno.
                'valor' => ['valido' => [0, 50], 'normal' => [0.0, round($gcrMinimo * $margenRad, 2)], 'precaucion' => [0.0, $gcrMinimo], 'decimales' => 2],
            ],
        ],
        'dosis_acumulada' => [
            'nombre' => 'Dosis acumulada',
            'unidad' => 'mSv',
            'sistema' => 'inmune',
            'riesgos' => ['radiacion'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 1000], 'normal' => [0, $dosisCarrera * $margenRad], 'precaucion' => [0, $dosisCarrera], 'decimales' => 1],
            ],
        ],
        'hidratacion' => [
            'nombre' => 'Hidratación',
            'unidad' => 'L/día',
            'sistema' => 'metabolico',
            'riesgos' => ['entorno_cerrado', 'gravedad_alterada'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 10], 'normal' => [2.5, 3.0], 'precaucion' => [2.0, 3.45], 'decimales' => 1],
            ],
        ],
        'sueno' => [
            'nombre' => 'Sueño',
            'unidad' => 'h',
            'sistema' => 'mental',
            'riesgos' => ['aislamiento', 'entorno_cerrado'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 24], 'normal' => [7, 9], 'precaucion' => [5.65, 10.35], 'decimales' => 1],
            ],
        ],
        'estres_cognitivo' => [
            'nombre' => 'Estrés / cognitivo',
            'unidad' => '/100',
            'sistema' => 'mental',
            'riesgos' => ['aislamiento', 'entorno_cerrado'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 100], 'normal' => [75, 100], 'precaucion' => [60, 100]],
            ],
        ],
        'vision_sans' => [
            'nombre' => 'Visión (SANS)',
            'unidad' => 'D',
            'sistema' => 'mental',
            'riesgos' => ['gravedad_alterada'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [-5, 10], 'normal' => [0, 0.5], 'precaucion' => [0, 0.65], 'decimales' => 1],
            ],
        ],
        'animo' => [
            'nombre' => 'Estado de ánimo',
            // Escala analógica visual (VAS) 0-100, 100 = muy bien. Cortes de ejemplo (equivalen a los de la antigua escala 1-5): pendientes de revisión médica.
            'unidad' => 'VAS 0-100',
            'sistema' => 'mental',
            'riesgos' => ['aislamiento'],
            'frecuencia' => 'diaria',
            'componentes' => [
                'valor' => ['valido' => [0, 100], 'normal' => [50, 100], 'precaucion' => [25, 100]],
            ],
        ],
    ],

    /*
     | Radiación (NASA-STD-3001 Vol. 1 Rev. C, según el encargo del Nivel 2). Valores de EJEMPLO, no validados.
     | Estado: crítico cuando el valor llega al límite; precaución desde límite x margen_precaucion.
     */
    'radiacion' => [
        'limite_msv_dia' => $radLimites,   // GCR, mSv/día
        // Qué límite aplica a cada entorno de misión. supuesto del proyecto, no de la norma
        'entorno_limite' => [
            'estacion' => 'espacio_libre', 'nave' => 'espacio_libre',
            'base_lunar' => 'superficie', 'base_marciana' => 'superficie',
        ],
        // Entorno desconocido o no indicado: se usa el límite más estricto. supuesto del proyecto, no de la norma
        'limite_por_defecto' => 'superficie',
        // Fracción del límite desde la que hay precaución. supuesto del proyecto, no de la norma
        'margen_precaucion' => $margenRad,
        'dosis_carrera_msv' => $dosisCarrera,
        // Proyección de evento solar que se considera crítica (mSv) y su acción. SOLO regla y texto: aún no hay
        // entrada de evento solar en el sistema (es del Nivel 3).
        'evento_solar_critico_msv' => 250,
        'evento_solar_accion' => 'alarma y refugio blindado',
        // pendiente: límites por órgano y por año (requieren dosis por órgano, que no se registra).
    ],

    /*
     | Presión arterial por bandas (mmHg). Cortes de la banda: del encargo del Nivel 2 (valores de ejemplo).
     | El mapeo banda -> estado es supuesto del proyecto, no de la norma.
     */
    'presion_bandas' => [
        'normal' => 'normal',
        'elevada' => 'precaucion',
        'hipertension_1' => 'precaucion',
        'hipertension_2' => 'critico',
        'crisis' => 'critico',   // y acción contactar a tierra (ya implícita en todo crítico)
    ],

    /*
     | Temperatura: alerta por salto (°C) sobre la base personal (pre-vuelo o, si no hay, la móvil).
     | Sin base personal se usan los rangos absolutos del indicador.
     */
    'temperatura_salto' => [
        'precaucion_c' => 0.6,   // supuesto del proyecto, no de la norma
        'critico_c' => 1.2,      // supuesto del proyecto, no de la norma
        // Ventana tras ejercicio (rutina tipo 'ej' hecha) en que una temperatura alta no alerta.
        'ventana_ejercicio_min' => 60,   // supuesto del proyecto, no de la norma
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
