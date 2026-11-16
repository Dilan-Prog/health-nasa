<?php

/*
 | FUENTE ÚNICA de los sistemas corporales. Todo lo demás se deriva de aquí:
 |   - config/indicadores.php  -> lista `sistemas` (pantalla Indicador) y el índice `sys` de cada marcador.
 |   - config/umbrales.php     -> campo `sistema` de cada indicador y lista `sistemas` (IndiceSistemaCorporal).
 |   - app/Livewire/Sistemas.php (pantalla /sistemas) lee `grupos` directamente.
 | Para mover un indicador de sistema, o añadir uno, se cambia SOLO este archivo.
 |
 |  clave       Identificador estable (se guarda en `sistema` de umbrales; no lo cambies a la ligera)
 |  titulo/ic   Nombre mostrado e icono Material Symbols
 |  origen      'documento' = una de las 9 secciones de "Salud de los astronautas" (cap. 1);
 |              'proyecto'  = grupo propio del proyecto que el documento no lista como sistema del organismo
 |  seccion     Sección del documento (1.1 ... 1.9), o null
 |  marcadores  Claves de config/indicadores.php (los 14 del catálogo). Cada una en exactamente un grupo.
 |  extra       Indicadores del motor (config/umbrales.php) que no tienen tarjeta en el catálogo, p. ej. `animo`.
 |  pendiente   Solo si el grupo no tiene indicador: qué falta medir (se muestra como "sin cobertura todavía").
 */

return [
    'grupos' => [
        ['clave' => 'cardiovascular', 'titulo' => 'Cardiovascular', 'ic' => 'cardiology', 'origen' => 'documento', 'seccion' => '1.1',
            'marcadores' => ['presion_arterial', 'frecuencia_cardiaca']],
        ['clave' => 'respiratorio', 'titulo' => 'Respiratory', 'ic' => 'pulmonology', 'origen' => 'documento', 'seccion' => '1.6',
            'marcadores' => ['saturacion_o2']],
        ['clave' => 'musculoesqueletico', 'titulo' => 'Musculoskeletal', 'ic' => 'skeleton', 'origen' => 'documento', 'seccion' => '1.2',
            'marcadores' => ['perdida_osea', 'masa_muscular']],
        ['clave' => 'sensorial', 'titulo' => 'Sensory & vestibular', 'ic' => 'neurology', 'origen' => 'documento', 'seccion' => '1.3',
            'marcadores' => [],
            'pendiente' => 'Space motion sickness, hand-eye coordination and spatial orientation.'],
        ['clave' => 'ocular', 'titulo' => 'Ocular (SANS)', 'ic' => 'visibility', 'origen' => 'documento', 'seccion' => '1.4',
            'marcadores' => ['vision_sans']],
        ['clave' => 'inmunologico', 'titulo' => 'Immune', 'ic' => 'immunology', 'origen' => 'documento', 'seccion' => '1.5',
            'marcadores' => [],
            'pendiente' => 'White blood cell activity, T lymphocytes and NK cells, and reactivation of latent viruses.'],
        ['clave' => 'hematologico', 'titulo' => 'Hematologic', 'ic' => 'hematology', 'origen' => 'documento', 'seccion' => '1.7',
            'marcadores' => [],
            'pendiente' => 'Plasma volume, red blood cell mass and hemoglobin.'],
        ['clave' => 'salud_mental', 'titulo' => 'Mental health', 'ic' => 'psychology', 'origen' => 'documento', 'seccion' => '1.8',
            'marcadores' => ['estres_cognitivo'], 'extra' => ['animo']],
        ['clave' => 'sueno', 'titulo' => 'Sleep & circadian rhythm', 'ic' => 'bedtime', 'origen' => 'documento', 'seccion' => '1.9',
            'marcadores' => ['sueno']],
        ['clave' => 'metabolico', 'titulo' => 'Metabolism', 'ic' => 'nutrition', 'origen' => 'proyecto', 'seccion' => null,
            'marcadores' => ['glucosa', 'temperatura', 'imc', 'hidratacion']],
        ['clave' => 'radiacion', 'titulo' => 'Radiation', 'ic' => 'flare', 'origen' => 'proyecto', 'seccion' => null,
            'marcadores' => ['radiacion_diaria', 'dosis_acumulada']],
    ],
];
