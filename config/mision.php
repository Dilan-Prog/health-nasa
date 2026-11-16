<?php

/*
 | Contexto de misión (vista 09 del diseño "Panel tripulación"). Valores de EJEMPLO que no viven en la BD:
 | la herramienta orienta, no diagnostica ni planifica por sí sola; el calendario real lo fija el centro de control.
 |
 |  - 'entornos'  : rótulo legible por entorno de misión (claves de config/umbrales.php -> retardo_min).
 |  - 'orbita'    : datos orbitales; solo aplican al entorno 'estacion' (altitud en config/panel.php -> orbita_km).
 |  - 'fases'     : hitos de la línea de tiempo (día absoluto; 'dia' => null = último día de la misión).
 |  - 'eventos'   : próximos eventos (día absoluto de la misión). Se muestran los que aún no han pasado.
 |  - 'limite_dosis_msv': límite de misión de la dosis acumulada (referencia, no umbral de alerta).
 */
return [
    'entornos' => [
        'estacion' => 'Estación Orbital',
        'nave' => 'Nave en tránsito',
        'base_lunar' => 'Base lunar',
        'base_marciana' => 'Base marciana',
    ],

    'orbita' => [
        'velocidad_kms' => 7.66,
        'orbitas_dia' => 15.5,
    ],

    'fases' => [
        ['nombre' => 'Lanzamiento', 'dia' => 1],
        ['nombre' => 'Adaptación', 'dia' => 14],
        ['nombre' => 'Operaciones', 'dia' => 30],
        ['nombre' => 'Pre-regreso', 'dia' => 165],
        ['nombre' => 'Regreso', 'dia' => null],
    ],

    'eventos' => [
        ['dia' => 143, 'titulo' => 'OCT ocular programada', 'icono' => 'visibility'],
        ['dia' => 145, 'titulo' => 'Videoconsulta con médico de vuelo', 'icono' => 'stethoscope'],
        ['dia' => 149, 'titulo' => 'EVA 5 (sujeta a dosis)', 'icono' => 'rocket'],
        ['dia' => 152, 'titulo' => 'Llegada de carga · suplementos', 'icono' => 'inventory_2'],
        ['dia' => 165, 'titulo' => 'Inicio protocolo pre-regreso', 'icono' => 'flight_land'],
    ],

    'limite_dosis_msv' => 250,

    // Días de mediciones en vivo que se conservan (la poda `mediciones:podar` borra lo más antiguo).
    'dias_historial' => (int) env('MISION_DIAS_HISTORIAL', 15),

    // SOLO PRUEBAS en entorno local: ID del tripulante al que se asignan las mediciones del reloj sin token.
    'reloj_sin_token_astronauta' => env('RELOJ_SIN_TOKEN_ASTRONAUTA'),
];
