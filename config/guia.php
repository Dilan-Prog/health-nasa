<?php

/*
 | Contenido de la pantalla "Guía educativa" (/guia). Las claves son las de config/indicadores.php (14 marcadores).
 |
 |  parte   Zona del cuerpo o sistema que se muestra junto al nombre
 |  texto   Qué mide el indicador y por qué cambia en microgravedad (divulgación general; no diagnostica)
 |
 | La fuente de cada tarjeta sale de config/trazabilidad.php (ResolvedorRequisito); sin requisito la Guía muestra
 | «Umbral de ejemplo (sin requisito asociado)».
 |
 |  contexto  (opcional) Nota de contexto (referencia), solo texto: no altera ningún umbral.
 |
 | Los rangos y la recomendación "Qué hacer" salen de config/indicadores.php. Orienta, no diagnostica ni receta.
 */

return [
    'marcadores' => [
        'presion_arterial' => [
            'parte' => 'Chest',
            'texto' => 'In microgravity, fluids shift toward the head and chest, which alters blood pressure and cardiac regulation.',
        ],
        'frecuencia_cardiaca' => [
            'parte' => 'Heart',
            'texto' => 'The heart works less without gravity and can lose mass; aerobic exercise keeps it conditioned.',
            // Contexto histórico (referencia, no cambia los umbrales de la app).
            'contexto' => 'During the Apollo missions the observed pulse ranged from 66 to 144 bpm, averaging ~96 bpm on descent and 70 to 109 bpm on ascent.',
        ],
        'saturacion_o2' => [
            'parte' => 'Lungs',
            'texto' => 'Measures how much oxygen the blood carries. It depends on the cabin atmosphere and lung function.',
        ],
        'glucosa' => [
            'parte' => 'Blood',
            'texto' => 'Reduced muscle activity lowers insulin sensitivity and raises glucose.',
        ],
        'temperatura' => [
            'parte' => 'Whole body',
            'texto' => 'Without natural convection, the body dissipates heat less effectively; core temperature tends to rise slightly in flight.',
        ],
        'imc' => [
            'parte' => 'Whole body',
            'texto' => 'Relates weight to height. On long missions it tends to fall because of muscle and bone loss.',
        ],
        'hidratacion' => [
            'parte' => 'Kidneys',
            'texto' => 'In space, thirst decreases; drinking too little raises the risk of kidney stones from calcium released from bone.',
        ],
        'perdida_osea' => [
            'parte' => 'Hip and spine',
            'texto' => 'Without load on the skeleton, about 1–1.5 % of bone density is lost per month in the hip and spine.',
        ],
        'masa_muscular' => [
            'parte' => 'Legs and back',
            'texto' => 'Postural muscles (legs, back) atrophy quickly without resistance exercise.',
        ],
        'radiacion_diaria' => [
            'parte' => 'Whole body',
            'texto' => 'Outside the full protection of the atmosphere, cosmic and solar radiation increases long-term risk.',
        ],
        'dosis_acumulada' => [
            'parte' => 'Whole body',
            'texto' => 'Sum of all radiation received; it is compared with mission and career limits.',
        ],
        'sueno' => [
            'parte' => 'Brain',
            'texto' => 'With 16 sunrises per day, noise and artificial light, the circadian rhythm is easily disrupted.',
        ],
        'estres_cognitivo' => [
            'parte' => 'Brain',
            'texto' => 'Isolation and workload affect attention, memory and mood.',
        ],
        'vision_sans' => [
            'parte' => 'Eyes',
            'texto' => 'Spaceflight Associated Neuro-ocular Syndrome (SANS) changes the shape of the eye and near vision.',
        ],
    ],

    /*
     | Tarjetas extra: indicadores que NO están en el catálogo de 14 marcadores (config/indicadores.php) pero sí se
     | registran (config/umbrales.php). Se muestran tras las 14, con el mismo estilo. `umbral` = clave en config/umbrales.php.
     */
    'extras' => [
        'animo' => [
            'umbral' => 'animo',
            'label' => 'Mood',
            'ic' => 'mood',
            'parte' => 'Brain',
            'texto' => 'Isolation, confinement and distance from Earth can affect mood. It is recorded on a VAS scale from 0 to 100 (100 = very good); it is used to track trends, not to diagnose.',
            'rec' => 'Talk with a crewmate or schedule a support call; keep up your social routine.',
        ],
    ],
];
