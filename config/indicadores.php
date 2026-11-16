<?php

/*
 | Catálogo de los 14 marcadores de la pantalla "Indicador" (/indicador).
 | Fuente: constantes SYS y MK del diseño. Las claves son las de config/umbrales.php.
 |
 |  label  Texto mostrado          ic   Icono Material Symbols        unit  Unidad
 |  sys    (derivado de config/sistemas.php)    lo/hi  Rango normal de EJEMPLO (no validado con NASA-STD-3001/HRP)
 |  base   Valor base personal de ejemplo   dec  Decimales con que se muestra
 |  rec    Recomendación general (orienta, no diagnostica ni receta)
 |  loss   Pérdida (se muestra y guarda con signo negativo)   cum  Valor acumulado   signo  Muestra "+" si es positivo
 |
 | Para la presión arterial `val`, `lo`, `hi` y `base` son los de la sistólica.
 */

$grupos = (require __DIR__.'/sistemas.php')['grupos'];

$catalogo = [
    // Derivado de config/sistemas.php (fuente única). No editar aquí.
    'sistemas' => array_map(fn (array $g) => [$g['titulo'], $g['ic']], $grupos),

    'marcadores' => [
        'presion_arterial' => [
            'label' => 'Blood pressure', 'ic' => 'monitor_heart', 'unit' => 'mmHg',
            'lo' => 90, 'hi' => 120, 'base' => 116, 'dec' => 0,
            'rec' => 'Rest seated for 5 min and repeat the measurement on the same arm. Reduce salt in the next meals.',
        ],
        'frecuencia_cardiaca' => [
            'label' => 'Heart rate', 'ic' => 'favorite', 'unit' => 'bpm',
            'lo' => 60, 'hi' => 100, 'base' => 64, 'dec' => 0,
            'rec' => 'Stable rhythm. Keep up your aerobic exercise protocol.',
        ],
        'saturacion_o2' => [
            'label' => 'SpO₂', 'ic' => 'air', 'unit' => '%',
            'lo' => 95, 'hi' => 100, 'base' => 98, 'dec' => 0,
            'rec' => 'Oxygenation is adequate. No action required.',
        ],
        'glucosa' => [
            'label' => 'Glucose', 'ic' => 'water_drop', 'unit' => 'mg/dL',
            'lo' => 70, 'hi' => 140, 'base' => 98, 'dec' => 0,
            'rec' => 'Within range. Log your meals to see how they relate to glucose.',
        ],
        'temperatura' => [
            'label' => 'Temperature', 'ic' => 'thermostat', 'unit' => '°C',
            'lo' => 36.1, 'hi' => 37.2, 'base' => 36.7, 'dec' => 1,
            'rec' => 'Near the upper limit: check the module temperature and your hydration.',
        ],
        'imc' => [
            'label' => 'BMI', 'ic' => 'monitor_weight', 'unit' => 'kg/m²',
            'lo' => 18.5, 'hi' => 24.9, 'base' => 23.6, 'dec' => 1,
            'rec' => 'Stable index. Keep to your planned daily intake.',
        ],
        'hidratacion' => [
            'label' => 'Hydration', 'ic' => 'local_drink', 'unit' => 'L/day',
            'lo' => 2.5, 'hi' => 3.0, 'base' => 2.6, 'dec' => 1,
            'rec' => 'Drink a glass of water every waking hour to reduce the risk of kidney stones.',
        ],
        'perdida_osea' => [
            'label' => 'Bone loss (hip)', 'ic' => 'skeleton', 'unit' => '%/month',
            'lo' => -1, 'hi' => 0, 'base' => -0.6, 'dec' => 1,
            'rec' => 'Complete your loading session (ARED) today and consult the Flight Surgeon.',
            'loss' => true,
        ],
        'masa_muscular' => [
            'label' => 'Muscle mass', 'ic' => 'fitness_center', 'unit' => '% of mission',
            'lo' => -2, 'hi' => 0, 'base' => -1.2, 'dec' => 1,
            'rec' => 'Prioritize the leg strength routine and log sets and loads.',
            'loss' => true,
        ],
        'radiacion_diaria' => [
            'label' => 'Daily radiation', 'ic' => 'flare', 'unit' => 'mSv/day',
            'lo' => 0, 'hi' => 0.5, 'base' => 0.42, 'dec' => 2,
            'rec' => 'Reduce time in poorly shielded modules and sleep in the shielded area.',
        ],
        'dosis_acumulada' => [
            'label' => 'Cumulative dose', 'ic' => 'data_usage', 'unit' => 'mSv',
            'lo' => 0, 'hi' => 100, 'base' => 0, 'dec' => 1,
            'rec' => 'Review the remaining EVA plan with the ground.',
            'cum' => true,
        ],
        'sueno' => [
            'label' => 'Sleep', 'ic' => 'bedtime', 'unit' => 'h',
            'lo' => 7, 'hi' => 9, 'base' => 7.2, 'dec' => 1,
            'rec' => 'Reschedule tasks to secure 8 h and use amber light before sleep.',
        ],
        'estres_cognitivo' => [
            'label' => 'Stress / cognitive', 'ic' => 'psychology', 'unit' => '/100',
            'lo' => 75, 'hi' => 100, 'base' => 83, 'dec' => 0,
            'rec' => 'Stable test results. Keep your weekly support session.',
        ],
        'vision_sans' => [
            'label' => 'Vision (SANS)', 'ic' => 'visibility', 'unit' => 'D',
            'lo' => 0, 'hi' => 0.5, 'base' => 0.1, 'dec' => 1,
            'rec' => 'Use your corrective glasses and schedule an eye exam this week.',
            'signo' => true,
        ],
    ],
];

// `sys` = posición del grupo al que pertenece el marcador en config/sistemas.php.
foreach ($catalogo['marcadores'] as $clave => &$m) {
    $m['sys'] = null;
    foreach ($grupos as $i => $g) {
        if (in_array($clave, $g['marcadores'], true)) {
            $m['sys'] = $i;
        }
    }
}
unset($m);

return $catalogo;
