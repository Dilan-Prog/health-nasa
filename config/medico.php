<?php

/*
 | Pantalla "Médico de vuelo": librería de orientación y catálogo de salvaguardas.
 | Fuente: diseño "Médico de vuelo" (constantes LIB y SAFE). CONTENIDO DE EJEMPLO: orienta, no diagnostica.
 |
 |  - Las claves de 'libreria' son las de config/panel.php ('sueno' es la de config/indicadores.php).
 |  - 'finding' lleva el marcador {v} (valor formateado, p. ej. "−1.6").
 |  - 'safe': verif (verificado contra botiquín) | escal (escalado a humano) | rule | none.
 |  - 'pend': paso con fármacos, sin redactar hasta su revisión médica. Nunca se incluyen medicamentos ni dosis.
 |  - 'src': fuentes consultables [l = rótulo, t = texto, ic = icono].
 */

return [
    // Regla fija de emergencia (sin IA): SpO₂ por debajo del umbral.
    'emergencia' => [
        'indicador' => 'saturacion_o2',
        'umbral' => 90,
        'regla' => 'R-02',
        'finding' => 'Saturación de oxígeno en {v} %: por debajo de 90 %.',
        'steps' => [
            'Deja lo que estés haciendo y sujétate en una posición estable.',
            'Colócate la mascarilla de oxígeno del kit de emergencia (ficha EM-02 verificada).',
            'Avisa a un compañero y contacta a tierra ahora.',
        ],
        'src' => [
            ['l' => 'Regla R-02 · SpO₂ < 90 %', 't' => 'Regla fija de emergencia, sin IA. Se activa cuando la saturación baja de 90 %.', 'ic' => 'rule'],
            ['l' => 'Ficha EM-02', 't' => 'Ficha de emergencia verificada por el equipo médico. Uso del oxígeno del kit.', 'ic' => 'medical_services'],
        ],
    ],

    'safe' => [
        'verif' => ['l' => 'Verificado contra botiquín', 'ic' => 'verified_user'],
        'escal' => ['l' => 'Escalado a humano', 'ic' => 'supervisor_account'],
        'rule' => ['l' => 'Protocolo fijo verificado · sin IA', 'ic' => 'verified_user'],
        'none' => ['l' => 'Sin orientación de IA', 'ic' => 'rule'],
    ],

    'libreria' => [
        'perdida_osea' => [
            'finding' => 'La pérdida ósea de cadera va más rápida de lo esperado ({v} %/mes).',
            'conf' => 78, 'safe' => 'escal',
            'steps' => [
                'Haz hoy las dos sesiones de carga axial en ARED, también la de la tarde.',
                'Anota el dolor de cadera de 0 a 10 antes y después de cada sesión.',
                'Pide a la Dra. Lucero revisar el plan en la próxima ventana.',
            ],
            'pend' => 'Suplementación o fármacos: [pendiente de revisión médica]',
            'src' => [
                ['l' => 'Protocolo: pérdida ósea', 't' => 'Protocolo médico de a bordo · v3.2 · §4 «Carga axial». Recomienda 2 sesiones diarias cuando la pérdida supera −1 %/mes.', 'ic' => 'description'],
                ['l' => 'Tu tendencia 15 días', 't' => 'Pasó de −1.1 a −1.6 %/mes en 15 días. Línea base en Tierra: −0.2 %/mes.', 'ic' => 'show_chart'],
            ],
        ],
        'sueno' => [
            'finding' => 'Llevas 3 noches durmiendo menos de 6 h ({v} h anoche).',
            'conf' => 84, 'safe' => 'verif',
            'steps' => [
                'Pide reprogramar la tarea de las 22:30 UTC para proteger 8 h de descanso.',
                'Activa la iluminación en fase «sueño» una hora antes de acostarte.',
                'Deja las pantallas fuera del saco de dormir.',
            ],
            'pend' => 'Ayuda farmacológica para dormir: [pendiente de revisión médica]',
            'src' => [
                ['l' => 'Protocolo: higiene del sueño', 't' => 'Protocolo médico de a bordo · v3.2 · §7. Prioriza cambios de horario y de luz antes que cualquier medicación.', 'ic' => 'description'],
                ['l' => 'Actigrafía 15 días', 't' => 'Media de 5.9 h; las 3 últimas noches, menos de 6 h. Línea base: 7.6 h.', 'ic' => 'show_chart'],
            ],
        ],
        'masa_muscular' => [
            'finding' => 'La masa muscular baja algo más de lo previsto ({v} % en la misión).',
            'conf' => 72, 'safe' => 'verif',
            'steps' => [
                'Completa hoy la rutina de fuerza de piernas (ver Rutinas).',
                'Registra en el diario la proteína de cada comida.',
            ],
            'src' => [
                ['l' => 'Protocolo: masa muscular', 't' => 'Protocolo médico de a bordo · v3.2 · §5. Objetivo: no superar −2 % durante la misión.', 'ic' => 'description'],
                ['l' => 'Tu tendencia 15 días', 't' => 'De −3.6 % a −4.2 % en 15 días.', 'ic' => 'show_chart'],
            ],
        ],
        'radiacion_diaria' => [
            'finding' => 'La dosis diaria de radiación supera el objetivo ({v} mSv/día).',
            'conf' => 71, 'safe' => 'verif',
            'steps' => [
                'Duerme en el módulo con más blindaje durante los pasos por la Anomalía del Atlántico Sur.',
                'Comprueba que el dosímetro personal está bien colocado.',
            ],
            'src' => [
                ['l' => 'Protocolo: radiación', 't' => 'Protocolo de radiación · v2.0 · §3. Objetivo diario por debajo de 0.50 mSv.', 'ic' => 'description'],
                ['l' => 'Dosímetro personal', 't' => 'Lectura de las últimas 24 h del dosímetro personal.', 'ic' => 'sensors'],
            ],
        ],
        'presion_arterial' => [
            'finding' => 'La presión arterial está por encima de tu rango ({v} mmHg).',
            'conf' => 66, 'safe' => 'verif',
            'steps' => [
                'Reposa 5 minutos sentado y sin hablar, y repite la medición en el mismo brazo.',
                'Si sigue alta, vuelve a medir dentro de una hora.',
            ],
            'src' => [
                ['l' => 'Protocolo: presión arterial', 't' => 'Protocolo médico de a bordo · v3.2 · §2. Repetir la medición antes de valorar.', 'ic' => 'description'],
                ['l' => 'Línea base personal', 't' => 'Tu valor base en Tierra, según tu expediente.', 'ic' => 'person'],
            ],
        ],
        'dosis_acumulada' => [
            'finding' => 'La dosis acumulada se acerca al límite de la misión ({v} mSv).',
            'conf' => 61, 'safe' => 'escal',
            'steps' => ['Revisa con la oficina de radiación la planificación de las EVAs pendientes.'],
            'src' => [
                ['l' => 'Protocolo: radiación', 't' => 'Protocolo de radiación · v2.0 · §5. Límite de misión asignado: 100 mSv.', 'ic' => 'description'],
                ['l' => 'Dosímetro personal', 't' => 'Acumulado de la misión según el dosímetro.', 'ic' => 'sensors'],
            ],
        ],
        'frecuencia_cardiaca' => [
            'finding' => 'La frecuencia cardíaca en reposo está fuera de tu rango ({v} lpm).',
            'conf' => 54, 'safe' => 'verif',
            'steps' => [
                'Repite la medición después de 5 minutos sentado.',
                'Anota si notas mareo o cansancio fuera de lo normal.',
            ],
            'src' => [
                ['l' => 'Protocolo: frecuencia cardíaca', 't' => 'Protocolo médico de a bordo · v3.2 · §2. En personas entrenadas puede ser normal.', 'ic' => 'description'],
                ['l' => 'Línea base personal', 't' => 'Tu valor base en Tierra, según tu expediente.', 'ic' => 'person'],
            ],
        ],
        'glucosa' => [
            'finding' => 'La glucosa está fuera de rango ({v} mg/dL).',
            'conf' => 63, 'safe' => 'verif',
            'steps' => ['Repite la medición en ayunas mañana.', 'Anota la hora de la última comida.'],
            'src' => [['l' => 'Protocolo: glucosa', 't' => 'Protocolo médico de a bordo · v3.2 · §6.', 'ic' => 'description']],
        ],
        'saturacion_o2' => [
            'finding' => 'La saturación de oxígeno está algo baja ({v} %).',
            'conf' => 69, 'safe' => 'escal',
            'steps' => [
                'Repite la medición con el dedo caliente y la mano quieta.',
                'Si baja de 90 %, se activa la regla de emergencia.',
            ],
            'src' => [['l' => 'Protocolo: oxigenación', 't' => 'Protocolo médico de a bordo · v3.2 · §1.', 'ic' => 'description']],
        ],
        'temperatura' => [
            'finding' => 'La temperatura está fuera de rango ({v} °C).',
            'conf' => 58, 'safe' => 'verif',
            'steps' => [
                'Repite la medición dentro de 30 minutos.',
                'Bebe agua y evita el ejercicio intenso hasta entonces.',
            ],
            'src' => [['l' => 'Protocolo: temperatura', 't' => 'Protocolo médico de a bordo · v3.2 · §3.', 'ic' => 'description']],
        ],
    ],
];
