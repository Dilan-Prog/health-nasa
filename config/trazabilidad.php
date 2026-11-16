<?php

/*
 | Trazabilidad: de qué requisito (tabla `requisitos`, RequisitosSeeder) cuelga cada indicador.
 | Lo resuelve App\Services\Salud\ResolvedorRequisito. Cada entrada se identifica por tipo + dominio + texto EXACTO
 | del seeder (su clave única); no se inventa ninguna fila ni código. Una entrada en null, o cuya fila no exista
 | en la BD, devuelve null: el indicador queda «sin requisito asociado».
 |
 | Nada de esto está validado clínicamente: el vínculo indicador → requisito es un criterio del proyecto
 | (supuesto del proyecto, no de la norma) y los números siguen siendo valores de ejemplo.
 */

$rad = fn (string $texto) => ['tipo' => 'norma', 'dominio' => 'radiacion', 'texto' => $texto];
$pa = fn (string $texto) => ['tipo' => 'referencia', 'dominio' => 'presion_arterial', 'texto' => $texto];

$gcrLibre = $rad('GCR en espacio libre');
$gcrSuperficie = $rad('GCR en superficie planetaria (sistemas habitables de más de 60 días)');
$evento = $rad('Dosis efectiva por evento de partículas solares');
$temperatura = ['tipo' => 'referencia', 'dominio' => 'temperatura', 'texto' => 'Temperatura central en órbita: de ~37.0 a ~38.0 °C tras ~2.5 meses; > 40 °C posible en ejercicio. La alerta es un salto sobre su propia base'];

return [
    // Requisito por defecto de cada indicador (clave de config/umbrales.php).
    'indicadores' => [
        'radiacion_diaria' => $gcrLibre,
        'dosis_acumulada' => $rad('Dosis efectiva de carrera'),
        // Es una tasa observada del HRP: se cita como «Proyección (HRP)», nunca como requisito.
        'perdida_osea' => ['tipo' => 'hrp', 'dominio' => 'hueso', 'texto' => 'Pérdida ósea observada'],
        'presion_arterial' => $pa('Normal: menor a 120/80 mmHg'),
        'temperatura' => $temperatura,
        'frecuencia_cardiaca' => ['tipo' => 'referencia', 'dominio' => 'pulso', 'texto' => 'Pulso de los Apolo (rango observado)'],
        'hidratacion' => ['tipo' => 'guia', 'dominio' => 'hidratacion', 'texto' => 'Hidratación diaria baja'],
        'vision_sans' => ['tipo' => 'guia', 'dominio' => 'vision', 'texto' => 'SANS: aumento del grosor retiniano'],
        'sueno' => ['tipo' => 'guia', 'dominio' => 'sueno', 'texto' => 'Sueño menor a 6 h por más de 48 h (severidad preventiva)'],

        // SIN fila en el seeder (no se inventan): devuelven null.
        'saturacion_o2' => null,
        'glucosa' => null,
        'imc' => null,
        'masa_muscular' => null,    // el seeder solo tiene «fuerza», que es otra medida
        'estres_cognitivo' => null, // solo existe el requisito cualitativo de «conducta», sin número que enlazar
        'animo' => null,
    ],

    // Requisito "real" que acompaña a un indicador cuyo vínculo principal no lo es (tarjeta de pérdida ósea).
    'referencias' => [
        'perdida_osea' => ['tipo' => 'norma', 'dominio' => 'hueso', 'texto' => 'DMO de cadera y columna respecto al valor pre-misión'], // V1 4027
    ],

    // Entradas por contexto (reglas nuevas): indicador => campo del contexto => valor => entrada.
    // Los nombres de banda son los de la referencia de presión del seeder; si T2 usa otros, no resuelven y
    // se cae al requisito por defecto del indicador.
    'condiciones' => [
        'radiacion_diaria' => [
            'entorno' => ['espacio_libre' => $gcrLibre, 'superficie' => $gcrSuperficie],
            'condicion' => ['evento_solar' => $evento],
        ],
        'dosis_acumulada' => [
            'condicion' => ['evento_solar' => $evento],
        ],
        'presion_arterial' => [
            'banda' => [
                'normal' => $pa('Normal: menor a 120/80 mmHg'),
                'elevada' => $pa('Elevada: sistólica 120–129 y diastólica menor a 80 mmHg'),
                'hipertension_1' => $pa('Hipertensión 1: sistólica 130–139 u diastólica 80–89 mmHg'),
                'hipertension_2' => $pa('Hipertensión 2: sistólica ≥ 140 o diastólica ≥ 90 mmHg'),
                'crisis' => $pa('Crisis: mayor a 180/120 mmHg'),
            ],
        ],
        'temperatura' => [
            'condicion' => ['salto' => $temperatura],
        ],
    ],
];
