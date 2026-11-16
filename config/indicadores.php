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
            'label' => 'Presión arterial', 'ic' => 'monitor_heart', 'unit' => 'mmHg',
            'lo' => 90, 'hi' => 120, 'base' => 116, 'dec' => 0,
            'rec' => 'Reposa 5 min sentado y repite la medición en el mismo brazo. Reduce la sal en las próximas comidas.',
        ],
        'frecuencia_cardiaca' => [
            'label' => 'Frecuencia cardíaca', 'ic' => 'favorite', 'unit' => 'lpm',
            'lo' => 60, 'hi' => 100, 'base' => 64, 'dec' => 0,
            'rec' => 'Ritmo estable. Mantén tu protocolo de ejercicio aeróbico.',
        ],
        'saturacion_o2' => [
            'label' => 'Saturación O₂', 'ic' => 'air', 'unit' => '%',
            'lo' => 95, 'hi' => 100, 'base' => 98, 'dec' => 0,
            'rec' => 'Oxigenación correcta. Sin acción requerida.',
        ],
        'glucosa' => [
            'label' => 'Glucosa', 'ic' => 'water_drop', 'unit' => 'mg/dL',
            'lo' => 70, 'hi' => 140, 'base' => 98, 'dec' => 0,
            'rec' => 'Dentro de rango. Anota tus comidas para ver la relación con la glucosa.',
        ],
        'temperatura' => [
            'label' => 'Temperatura', 'ic' => 'thermostat', 'unit' => '°C',
            'lo' => 36.1, 'hi' => 37.2, 'base' => 36.7, 'dec' => 1,
            'rec' => 'Cerca del límite alto: revisa la temperatura del módulo y tu hidratación.',
        ],
        'imc' => [
            'label' => 'IMC', 'ic' => 'monitor_weight', 'unit' => 'kg/m²',
            'lo' => 18.5, 'hi' => 24.9, 'base' => 23.6, 'dec' => 1,
            'rec' => 'Índice estable. Mantén tu ingesta diaria planificada.',
        ],
        'hidratacion' => [
            'label' => 'Hidratación', 'ic' => 'local_drink', 'unit' => 'L/día',
            'lo' => 2.5, 'hi' => 3.0, 'base' => 2.6, 'dec' => 1,
            'rec' => 'Bebe un vaso de agua cada hora despierto para reducir el riesgo de cálculos renales.',
        ],
        'perdida_osea' => [
            'label' => 'Pérdida ósea (cadera)', 'ic' => 'skeleton', 'unit' => '%/mes',
            'lo' => -1, 'hi' => 0, 'base' => -0.6, 'dec' => 1,
            'rec' => 'Completa hoy tu sesión de carga (ARED) y consulta al flight surgeon.',
            'loss' => true,
        ],
        'masa_muscular' => [
            'label' => 'Masa muscular', 'ic' => 'fitness_center', 'unit' => '% misión',
            'lo' => -2, 'hi' => 0, 'base' => -1.2, 'dec' => 1,
            'rec' => 'Prioriza la rutina de fuerza de piernas y registra series y cargas.',
            'loss' => true,
        ],
        'radiacion_diaria' => [
            'label' => 'Radiación diaria', 'ic' => 'flare', 'unit' => 'mSv/día',
            'lo' => 0, 'hi' => 0.5, 'base' => 0.42, 'dec' => 2,
            'rec' => 'Reduce el tiempo en módulos poco blindados y duerme en la zona blindada.',
        ],
        'dosis_acumulada' => [
            'label' => 'Dosis acumulada', 'ic' => 'data_usage', 'unit' => 'mSv',
            'lo' => 0, 'hi' => 100, 'base' => 0, 'dec' => 1,
            'rec' => 'Revisa con tierra el plan de EVAs restantes.',
            'cum' => true,
        ],
        'sueno' => [
            'label' => 'Sueño', 'ic' => 'bedtime', 'unit' => 'h',
            'lo' => 7, 'hi' => 9, 'base' => 7.2, 'dec' => 1,
            'rec' => 'Reprograma tareas para garantizar 8 h y usa luz ámbar antes de dormir.',
        ],
        'estres_cognitivo' => [
            'label' => 'Estrés / cognitivo', 'ic' => 'psychology', 'unit' => '/100',
            'lo' => 75, 'hi' => 100, 'base' => 83, 'dec' => 0,
            'rec' => 'Pruebas estables. Mantén tu sesión semanal de apoyo.',
        ],
        'vision_sans' => [
            'label' => 'Visión (SANS)', 'ic' => 'visibility', 'unit' => 'D',
            'lo' => 0, 'hi' => 0.5, 'base' => 0.1, 'dec' => 1,
            'rec' => 'Usa tus gafas de compensación y programa una revisión ocular esta semana.',
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
