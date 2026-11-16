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

    // Si es true se exige inicio de sesión y se aplican los roles (tripulante / cmo / flight_surgeon). Por defecto false: demo sin login.
    'exigir_login' => env('MISION_EXIGIR_LOGIN', false),

    'limite_dosis_msv' => 250,

    // Días de mediciones en vivo que se conservan (la poda `mediciones:podar` borra lo más antiguo).
    'dias_historial' => (int) env('MISION_DIAS_HISTORIAL', 15),

    // SOLO PRUEBAS en entorno local: ID del tripulante al que se asignan las mediciones del reloj sin token.
    'reloj_sin_token_astronauta' => env('RELOJ_SIN_TOKEN_ASTRONAUTA'),

    /*
     | API del reloj (SUPUESTOS DEL PROYECTO, no de la norma; ajustables por .env).
     */
    // Peticiones por minuto y por token (no por IP). La simulación envía ~56 mediciones/min en lotes: 120 peticiones es holgado.
    'reloj_limite_por_minuto' => (int) env('RELOJ_LIMITE_POR_MINUTO', 120),
    // Minutos que el token anterior sigue válido al regenerar, para no cortar al dispositivo mientras se actualiza.
    'reloj_gracia_minutos' => (int) env('RELOJ_GRACIA_MINUTOS', 60),
    // Días de vigencia por defecto de un token nuevo (`reloj:token --dias=`).
    'reloj_token_dias' => (int) env('RELOJ_TOKEN_DIAS', 30),
    // Si es true, todo lote debe llevar X-Firma y X-Marca-Tiempo válidas (falso por defecto para no romper el APK actual).
    'reloj_exigir_firma' => (bool) env('RELOJ_EXIGIR_FIRMA', false),
    // Antigüedad máxima (segundos) de la marca de tiempo firmada.
    'reloj_firma_tolerancia_s' => (int) env('RELOJ_FIRMA_TOLERANCIA_S', 300),
    // Por debajo de este % de batería la ficha muestra "batería baja".
    'reloj_bateria_baja_pct' => (int) env('RELOJ_BATERIA_BAJA_PCT', 20),
];
