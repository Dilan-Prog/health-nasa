<?php

/*
 | Patrones del detector de la "IA local simulada" (App\Services\Ia\DetectorPatrones). SOLO DATOS.
 | Orienta, no diagnostica ni receta: cada patrón es una HIPÓTESIS que tierra o el médico de vuelo deben confirmar.
 |
 | Un patrón es una combinación de ESTADOS de indicadores ya existentes (los que calculan config/umbrales.php,
 | config/ventanas.php y el motor de reglas) dentro de una ventana de minutos. NO define umbrales numéricos nuevos.
 | La clave del patrón es la del escenario de config/simulacion.php.
 |
 |  nombre         Hipótesis mostrada (lenguaje de hipótesis, nunca diagnóstico).
 |  icono          Icono Material Symbols del patrón (para el círculo del Médico de vuelo).
 |  ficha          Slug de la ficha de conocimiento (conocimiento/<slug>.md).
 |  indicadores    Claves de indicador implicadas (para el historial).
 |  acciones_guia  Claves de marcador (config/guia.php y config/indicadores.php) de donde sale el "qué hacer".
 |  confianza      0-100, fija por regla. SUPUESTO DEL PROYECTO, NO DE LA NORMA: no es una probabilidad clínica.
 |  ventana_min    Minutos hacia atrás desde la última medición del tripulante. SUPUESTO DEL PROYECTO, NO DE LA NORMA
 |                 (se ajusta a la cadencia de cada indicador de config/simulacion.php).
 |  senales        Lista de ['indicador', 'estados' => [...], 'requerida' => bool]. Una señal coincide si la última
 |                 lectura BUENA del indicador dentro de la ventana está en uno de esos estados.
 |  minimo         Cuántas señales (las requeridas cuentan) deben coincidir. SUPUESTO DEL PROYECTO, NO DE LA NORMA.
 |
 | Claves opcionales:
 |  origen_senales 'calidad': las señales miran la calidad de la lectura (estado 'sospechosa' del Nivel 3A,
 |                 config/calidad.php) en vez del estado clínico. Es la única regla que usa lecturas sospechosas;
 |                 en el resto una lectura sospechosa no cuenta.
 |  severidad_fija Severidad del caso sin mirar los estados (un fallo de sensor es del dispositivo, no escala por sí mismo).
 |
 | Severidad del caso: la peor entre las señales requeridas que coinciden (si no hay requeridas, entre todas las que coinciden).
 | Los casos de severidad crítica escalan a tierra una sola vez por caso.
 |
 | Dónde se apoya cada hipótesis: ver la ficha del patrón (conocimiento/*.md). Todo revision_medica: pendiente.
 */

return [
    'fiebre' => [
        'nombre' => 'Posible fiebre',
        'icono' => 'thermostat',
        'ficha' => 'fiebre',
        'indicadores' => ['temperatura', 'frecuencia_cardiaca'],
        'acciones_guia' => ['temperatura', 'hidratacion'],
        'confianza' => 60,      // supuesto del proyecto, no de la norma
        'ventana_min' => 120,   // supuesto del proyecto, no de la norma
        'senales' => [
            ['indicador' => 'temperatura', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'hipoxia' => [
        'nombre' => 'Posible hipoxia',
        'icono' => 'air',
        'ficha' => 'hipoxia',
        'indicadores' => ['saturacion_o2', 'frecuencia_cardiaca', 'estres_cognitivo'],
        'acciones_guia' => ['saturacion_o2', 'frecuencia_cardiaca'],
        'confianza' => 70,      // supuesto del proyecto, no de la norma
        'ventana_min' => 30,    // supuesto del proyecto, no de la norma
        'senales' => [
            ['indicador' => 'saturacion_o2', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
            ['indicador' => 'estres_cognitivo', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'deshidratacion' => [
        'nombre' => 'Posible deshidratación',
        'icono' => 'water_drop',
        'ficha' => 'deshidratacion',
        'indicadores' => ['hidratacion', 'frecuencia_cardiaca', 'temperatura', 'presion_arterial'],
        'acciones_guia' => ['hidratacion', 'temperatura'],
        'confianza' => 50,      // supuesto del proyecto, no de la norma
        'ventana_min' => 1440,  // supuesto del proyecto, no de la norma (la hidratación se mide una vez al día)
        'senales' => [
            ['indicador' => 'hidratacion', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
            ['indicador' => 'temperatura', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
            ['indicador' => 'presion_arterial', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'estres' => [
        'nombre' => 'Posible estrés agudo',
        'icono' => 'psychology',
        'ficha' => 'estres',
        'indicadores' => ['estres_cognitivo', 'frecuencia_cardiaca', 'presion_arterial'],
        'acciones_guia' => ['estres_cognitivo', 'frecuencia_cardiaca'],
        'confianza' => 45,      // supuesto del proyecto, no de la norma
        'ventana_min' => 1440,  // supuesto del proyecto, no de la norma (el estrés cognitivo se mide una vez al día)
        'senales' => [
            ['indicador' => 'estres_cognitivo', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
            ['indicador' => 'presion_arterial', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'arritmia' => [
        'nombre' => 'Posible irregularidad del pulso (requiere ECG)',
        'icono' => 'monitor_heart',
        'ficha' => 'arritmia',
        'indicadores' => ['frecuencia_cardiaca', 'estres_cognitivo'],
        'acciones_guia' => ['frecuencia_cardiaca'],
        'confianza' => 40,      // supuesto del proyecto, no de la norma
        'ventana_min' => 30,    // supuesto del proyecto, no de la norma
        'senales' => [
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'estres_cognitivo', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'falta_sueno' => [
        'nombre' => 'Posible falta de sueño',
        'icono' => 'bedtime',
        'ficha' => 'falta_sueno',
        'indicadores' => ['sueno', 'estres_cognitivo', 'frecuencia_cardiaca'],
        'acciones_guia' => ['sueno', 'estres_cognitivo'],
        'confianza' => 65,      // supuesto del proyecto, no de la norma
        'ventana_min' => 1440,  // supuesto del proyecto, no de la norma (el sueño se mide una vez al día)
        'senales' => [
            // El estado del sueño ya incorpora la regla de ventana del Nivel 3A (config/ventanas.php, 'sueno_corto').
            ['indicador' => 'sueno', 'estados' => ['precaucion', 'critico'], 'requerida' => true],
            ['indicador' => 'estres_cognitivo', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['precaucion', 'critico'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
    'fallo_sensor' => [
        'nombre' => 'Posible fallo de sensor',
        'icono' => 'sensors',
        'ficha' => 'fallo_sensor',
        'indicadores' => ['frecuencia_cardiaca', 'saturacion_o2', 'temperatura', 'presion_arterial'],
        'acciones_guia' => ['frecuencia_cardiaca', 'saturacion_o2'],
        'confianza' => 75,      // supuesto del proyecto, no de la norma
        'ventana_min' => 60,    // supuesto del proyecto, no de la norma
        'origen_senales' => 'calidad',
        'severidad_fija' => 'precaucion',
        'senales' => [
            ['indicador' => 'frecuencia_cardiaca', 'estados' => ['sospechosa'], 'requerida' => false],
            ['indicador' => 'saturacion_o2', 'estados' => ['sospechosa'], 'requerida' => false],
            ['indicador' => 'temperatura', 'estados' => ['sospechosa'], 'requerida' => false],
            ['indicador' => 'presion_arterial', 'estados' => ['sospechosa'], 'requerida' => false],
        ],
        'minimo' => 1,          // supuesto del proyecto, no de la norma
    ],
];
