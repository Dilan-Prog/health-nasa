<?php

/*
 | Simulación viva de la misión: TODO como datos (el código de SimuladorVivo no contiene escenarios).
 | Valores de EJEMPLO para la demo; no validados contra NASA-STD-3001 / HRP.
 | Todos los tiempos son segundos o minutos VIRTUALES (reloj de la misión) salvo que se diga lo contrario;
 | las horas ('HH:MM') son UTC del reloj virtual.
 |
 |  cadencia   cada_s (intervalo), hora (hora fija diaria), dia_semana (0=domingo, solo semanales), fase_s (desfase).
 |             Si falta cada_s se deduce de 'frecuencia' de config/umbrales.php ('diaria' | 'semanal') con 'cadencia_defecto'.
 |  bases      línea base personal de ejemplo por componente (se mezcla con el centro del rango normal y la semilla).
 |  ruido      AR(1): sigma (desviación estacionaria; escalar o por componente), phi, paso (cuantización),
 |             jitter (fracción del semirango normal que la semilla desplaza la base; defecto 'jitter_base').
 |  acumulado  indicadores que solo crecen (dosis acumulada): inicio [min,max] e incremento por día.
 |  circadiano amplitud * cos(2π (hora - hora_pico) / 24).
 |  ejercicio  sesiones diarias y su efecto (delta a intensidad 1) con subida y recuperación graduales.
 |  faltantes  lecturas perdidas y huecos largos de la telemetría.
 |  escenarios curva {entrada, meseta, salida} (fracciones de la duración que suman 1) e indicadores afectados:
 |             modo 'sumar' (delta), 'congelar' (repite el último valor) o 'imposible' (valor fuera de rango válido).
 |             'componentes' reparte el delta por componente (presión); 'ruido_mult' amplifica el ruido.
 */

return [
    'jitter_base' => 0.25,
    'max_lecturas_por_indicador' => 600,   // huecos largos del reloj: solo se emiten las últimas N lecturas por indicador
    'ruido_limite_sigmas' => 2.5,

    'cadencia_defecto' => [
        'diaria' => ['cada_s' => 86400, 'hora' => '12:00'],
        'semanal' => ['cada_s' => 604800, 'dia_semana' => 1, 'hora' => '09:00'],
    ],

    'cadencia' => [
        'presion_arterial' => ['cada_s' => 3600],
        'frecuencia_cardiaca' => ['cada_s' => 300],
        'saturacion_o2' => ['cada_s' => 300, 'fase_s' => 60],
        'temperatura' => ['cada_s' => 3600, 'fase_s' => 1800],
        'glucosa' => ['cada_s' => 86400, 'hora' => '08:00'],
        'imc' => ['cada_s' => 86400, 'hora' => '06:30'],
        'hidratacion' => ['cada_s' => 86400, 'hora' => '21:00'],
        'sueno' => ['cada_s' => 86400, 'hora' => '07:00'],
        'estres_cognitivo' => ['cada_s' => 86400, 'hora' => '10:00'],
        'vision_sans' => ['cada_s' => 86400, 'hora' => '09:00'],
        'radiacion_diaria' => ['cada_s' => 86400, 'hora' => '23:00'],
        'dosis_acumulada' => ['cada_s' => 86400, 'hora' => '23:05'],
        'perdida_osea' => ['cada_s' => 604800, 'dia_semana' => 1, 'hora' => '09:00'],
        'masa_muscular' => ['cada_s' => 604800, 'dia_semana' => 3, 'hora' => '09:30'],
    ],

    'bases' => [
        'presion_arterial' => ['sistolica' => 116, 'diastolica' => 76],
        'frecuencia_cardiaca' => ['valor' => 64],
        'saturacion_o2' => ['valor' => 98],
        'glucosa' => ['valor' => 98],
        'temperatura' => ['valor' => 36.6],
        'imc' => ['valor' => 23.0],
        'hidratacion' => ['valor' => 2.6],
        'sueno' => ['valor' => 7.2],
        'estres_cognitivo' => ['valor' => 83],
        'vision_sans' => ['valor' => 0.1],
        'radiacion_diaria' => ['valor' => 0.38],
        'perdida_osea' => ['valor' => -0.6],
        'masa_muscular' => ['valor' => -1.2],
    ],

    'ruido' => [
        'presion_arterial' => ['sigma' => ['sistolica' => 2.5, 'diastolica' => 1.8], 'phi' => 0.6, 'paso' => 1],
        'frecuencia_cardiaca' => ['sigma' => 2.0, 'phi' => 0.85, 'paso' => 1],
        'saturacion_o2' => ['sigma' => 0.4, 'phi' => 0.7, 'paso' => 1],
        'temperatura' => ['sigma' => 0.04, 'phi' => 0.8, 'paso' => 0.1, 'jitter' => 0.05],
        'glucosa' => ['sigma' => 7, 'phi' => 0.4, 'paso' => 1],
        'imc' => ['sigma' => 0.08, 'phi' => 0.9, 'paso' => 0.1],
        'hidratacion' => ['sigma' => 0.08, 'phi' => 0.5, 'paso' => 0.1],
        'sueno' => ['sigma' => 0.3, 'phi' => 0.3, 'paso' => 0.1],
        'estres_cognitivo' => ['sigma' => 3, 'phi' => 0.5, 'paso' => 1],
        'vision_sans' => ['sigma' => 0.05, 'phi' => 0.8, 'paso' => 0.1],
        'radiacion_diaria' => ['sigma' => 0.03, 'phi' => 0.5, 'paso' => 0.01],
        'perdida_osea' => ['sigma' => 0.08, 'phi' => 0.6, 'paso' => 0.1],
        'masa_muscular' => ['sigma' => 0.12, 'phi' => 0.6, 'paso' => 0.1],
        'dosis_acumulada' => ['sigma' => 0.0, 'phi' => 0.0, 'paso' => 0.1],
    ],

    'acumulado' => [
        'dosis_acumulada' => ['inicio' => [15, 35], 'incremento_diario' => 0.45],
    ],

    'circadiano' => [
        'frecuencia_cardiaca' => ['amplitud' => 4.0, 'hora_pico' => 15],   // mínimo de madrugada
        'temperatura' => ['amplitud' => 0.35, 'hora_pico' => 16],          // ciclo diario ±0.35 °C
        'presion_arterial' => ['amplitud' => 3.0, 'hora_pico' => 15, 'componentes' => ['sistolica' => 1, 'diastolica' => 0.5]],
    ],

    'ejercicio' => [
        'activo' => true,
        'sesiones' => [
            ['hora' => '18:00', 'duracion_min' => 40],
        ],
        'subida_min' => 5,
        'recuperacion_min' => 25,
        'efectos' => [
            'frecuencia_cardiaca' => 18,
            'saturacion_o2' => -1,
            'temperatura' => 0.1,
            'presion_arterial' => ['delta' => 8, 'componentes' => ['sistolica' => 1, 'diastolica' => 0.3]],
        ],
    ],

    'faltantes' => [
        'prob_perdida' => 0.01,           // por lectura programada
        'prob_hueco' => 0.0008,           // por lectura programada: arranca un hueco largo de telemetría
        'hueco_s' => [1800, 5400],        // duración mínima y máxima del hueco (s virtuales)
    ],

    'escenarios' => [
        'deshidratacion' => [
            'nombre' => 'Deshidratación',
            'icono' => 'water_loss',
            'descripcion' => 'Baja la ingesta de líquidos: hidratación en descenso, pulso más alto y presión algo más baja.',
            'duracion_min' => 240,
            'curva' => ['entrada' => 0.25, 'meseta' => 0.5, 'salida' => 0.25],
            'indicadores' => [
                'hidratacion' => ['delta' => -1.1, 'modo' => 'sumar'],
                'frecuencia_cardiaca' => ['delta' => 30, 'modo' => 'sumar'],
                'temperatura' => ['delta' => 0.4, 'modo' => 'sumar'],
                'presion_arterial' => ['delta' => -22, 'modo' => 'sumar', 'componentes' => ['sistolica' => 1, 'diastolica' => 0.5]],
            ],
        ],
        'fiebre' => [
            'nombre' => 'Fiebre',
            'icono' => 'thermometer',
            'descripcion' => 'Cuadro febril: la temperatura sube a rango de alerta y el pulso la acompaña.',
            'duracion_min' => 360,
            'curva' => ['entrada' => 0.2, 'meseta' => 0.55, 'salida' => 0.25],
            'indicadores' => [
                'temperatura' => ['delta' => 2.0, 'modo' => 'sumar'],
                'frecuencia_cardiaca' => ['delta' => 18, 'modo' => 'sumar'],
            ],
        ],
        'falta_sueno' => [
            'nombre' => 'Falta de sueño',
            'icono' => 'bedtime_off',
            'descripcion' => 'Noche corta: pocas horas de sueño, peor rendimiento cognitivo y pulso algo elevado.',
            'duracion_min' => 300,
            'curva' => ['entrada' => 0.2, 'meseta' => 0.6, 'salida' => 0.2],
            'indicadores' => [
                'sueno' => ['delta' => -4.0, 'modo' => 'sumar'],
                'estres_cognitivo' => ['delta' => -20, 'modo' => 'sumar'],
                'frecuencia_cardiaca' => ['delta' => 8, 'modo' => 'sumar'],
            ],
        ],
        'arritmia' => [
            'nombre' => 'Arritmia',
            'icono' => 'ecg_heart',
            'descripcion' => 'Pulso muy elevado e irregular durante un episodio corto.',
            'duracion_min' => 90,
            'curva' => ['entrada' => 0.1, 'meseta' => 0.7, 'salida' => 0.2],
            'indicadores' => [
                'frecuencia_cardiaca' => ['delta' => 55, 'modo' => 'sumar', 'ruido_mult' => 4],
                'estres_cognitivo' => ['delta' => -8, 'modo' => 'sumar'],
            ],
        ],
        'estres' => [
            'nombre' => 'Estrés agudo',
            'icono' => 'psychology_alt',
            'descripcion' => 'Carga de estrés: el rendimiento cognitivo cae y suben pulso y presión.',
            'duracion_min' => 180,
            'curva' => ['entrada' => 0.25, 'meseta' => 0.5, 'salida' => 0.25],
            'indicadores' => [
                'estres_cognitivo' => ['delta' => -30, 'modo' => 'sumar'],
                'frecuencia_cardiaca' => ['delta' => 30, 'modo' => 'sumar'],
                'presion_arterial' => ['delta' => 14, 'modo' => 'sumar', 'componentes' => ['sistolica' => 1, 'diastolica' => 0.6]],
            ],
        ],
        'hipoxia' => [
            'nombre' => 'Hipoxia',
            'icono' => 'air',
            'descripcion' => 'Falta de oxígeno: saturación baja, pulso alto y deterioro cognitivo (el CO₂ no es un indicador).',
            'duracion_min' => 120,
            'curva' => ['entrada' => 0.2, 'meseta' => 0.55, 'salida' => 0.25],
            'indicadores' => [
                'saturacion_o2' => ['delta' => -9, 'modo' => 'sumar'],
                'frecuencia_cardiaca' => ['delta' => 32, 'modo' => 'sumar'],
                'estres_cognitivo' => ['delta' => -22, 'modo' => 'sumar'],
            ],
        ],
        'fallo_sensor' => [
            'nombre' => 'Fallo de sensor',
            'icono' => 'sensors_off',
            'descripcion' => 'El pulsómetro se congela en el último valor y el oxímetro envía lecturas imposibles.',
            'duracion_min' => 60,
            'curva' => ['entrada' => 0.05, 'meseta' => 0.9, 'salida' => 0.05],
            'indicadores' => [
                'frecuencia_cardiaca' => ['delta' => 0, 'modo' => 'congelar'],
                'saturacion_o2' => ['delta' => 0, 'modo' => 'imposible', 'valor' => 0],
            ],
        ],
    ],
];
