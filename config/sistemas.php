<?php

/*
 | Grupos de la pantalla "Sistemas corporales" (/sistemas, vista 04 del diseño).
 | Cada grupo agrupa claves de config/indicadores.php (los ids del diseño pa, fc, spo2... se tradujeron a las
 | claves reales). Todo marcador del catálogo debe aparecer exactamente una vez.
 |
 |  titulo  Nombre del sistema   ic  Icono Material Symbols   marcadores  Claves de config/indicadores.php
 */

return [
    'grupos' => [
        ['titulo' => 'Cardiorrespiratorio', 'ic' => 'cardiology', 'marcadores' => ['presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2']],
        ['titulo' => 'Metabolismo', 'ic' => 'nutrition', 'marcadores' => ['glucosa', 'temperatura', 'imc', 'hidratacion']],
        ['titulo' => 'Músculo y hueso', 'ic' => 'skeleton', 'marcadores' => ['perdida_osea', 'masa_muscular']],
        ['titulo' => 'Radiación', 'ic' => 'flare', 'marcadores' => ['radiacion_diaria', 'dosis_acumulada']],
        ['titulo' => 'Neuro y visión', 'ic' => 'psychology', 'marcadores' => ['sueno', 'estres_cognitivo', 'vision_sans']],
    ],
];
