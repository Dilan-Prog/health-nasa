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
        'finding' => 'Oxygen saturation at {v} %: below 90 %.',
        'steps' => [
            'Stop what you are doing and secure yourself in a stable position.',
            'Put on the oxygen mask from the emergency kit (verified card EM-02).',
            'Alert a crewmate and contact the ground now.',
        ],
        'src' => [
            ['l' => 'Rule R-02 · SpO₂ < 90 %', 't' => 'Fixed emergency rule, no AI. It triggers when saturation drops below 90 %.', 'ic' => 'rule'],
            ['l' => 'Card EM-02', 't' => 'Emergency card verified by the medical team. Use of the kit oxygen.', 'ic' => 'medical_services'],
        ],
    ],

    'safe' => [
        'verif' => ['l' => 'Verified against medical kit', 'ic' => 'verified_user'],
        'escal' => ['l' => 'Escalated to a human', 'ic' => 'supervisor_account'],
        'rule' => ['l' => 'Verified fixed protocol · no AI', 'ic' => 'verified_user'],
        'none' => ['l' => 'No AI guidance', 'ic' => 'rule'],
    ],

    'libreria' => [
        'perdida_osea' => [
            'finding' => 'Hip bone loss is faster than expected ({v} %/month).',
            'conf' => 78, 'safe' => 'escal',
            'steps' => [
                'Do both axial loading sessions on the ARED today, including the afternoon one.',
                'Log hip pain from 0 to 10 before and after each session.',
                'Ask Dr. Lucero to review the plan in the next window.',
            ],
            'pend' => 'Supplementation or drugs: [pending medical review]',
            'src' => [
                ['l' => 'Protocol: bone loss', 't' => 'Onboard medical protocol · v3.2 · §4 "Axial loading". Recommends 2 sessions per day when loss exceeds −1 %/month.', 'ic' => 'description'],
                ['l' => 'Your 15-day trend', 't' => 'Went from −1.1 to −1.6 %/month in 15 days. Earth baseline: −0.2 %/month.', 'ic' => 'show_chart'],
            ],
        ],
        'sueno' => [
            'finding' => 'You have slept less than 6 h for 3 nights ({v} h last night).',
            'conf' => 84, 'safe' => 'verif',
            'steps' => [
                'Ask to reschedule the 22:30 UTC task to protect 8 h of rest.',
                'Switch the lighting to "sleep" phase one hour before bedtime.',
                'Keep screens out of your sleeping bag.',
            ],
            'pend' => 'Pharmacological sleep aid: [pending medical review]',
            'src' => [
                ['l' => 'Protocol: sleep hygiene', 't' => 'Onboard medical protocol · v3.2 · §7. Prioritizes schedule and light changes before any medication.', 'ic' => 'description'],
                ['l' => '15-day actigraphy', 't' => 'Average of 5.9 h; the last 3 nights, under 6 h. Baseline: 7.6 h.', 'ic' => 'show_chart'],
            ],
        ],
        'masa_muscular' => [
            'finding' => 'Muscle mass is declining somewhat more than expected ({v} % over the mission).',
            'conf' => 72, 'safe' => 'verif',
            'steps' => [
                'Complete the leg strength routine today (see Routines).',
                'Log the protein of each meal in the journal.',
            ],
            'src' => [
                ['l' => 'Protocol: muscle mass', 't' => 'Onboard medical protocol · v3.2 · §5. Target: do not exceed −2 % during the mission.', 'ic' => 'description'],
                ['l' => 'Your 15-day trend', 't' => 'From −3.6 % to −4.2 % in 15 days.', 'ic' => 'show_chart'],
            ],
        ],
        'radiacion_diaria' => [
            'finding' => 'The daily radiation dose exceeds the target ({v} mSv/day).',
            'conf' => 71, 'safe' => 'verif',
            'steps' => [
                'Sleep in the module with the most shielding during passes through the South Atlantic Anomaly.',
                'Check that the personal dosimeter is worn correctly.',
            ],
            'src' => [
                ['l' => 'Protocol: radiation', 't' => 'Radiation protocol · v2.0 · §3. Daily target below 0.50 mSv.', 'ic' => 'description'],
                ['l' => 'Personal dosimeter', 't' => 'Reading from the personal dosimeter for the last 24 h.', 'ic' => 'sensors'],
            ],
        ],
        'presion_arterial' => [
            'finding' => 'Blood pressure is above your range ({v} mmHg).',
            'conf' => 66, 'safe' => 'verif',
            'steps' => [
                'Rest seated for 5 minutes without talking, and repeat the measurement on the same arm.',
                'If it is still high, measure again within an hour.',
            ],
            'src' => [
                ['l' => 'Protocol: blood pressure', 't' => 'Onboard medical protocol · v3.2 · §2. Repeat the measurement before assessing.', 'ic' => 'description'],
                ['l' => 'Personal baseline', 't' => 'Your Earth baseline value, from your record.', 'ic' => 'person'],
            ],
        ],
        'dosis_acumulada' => [
            'finding' => 'The cumulative dose is approaching the mission limit ({v} mSv).',
            'conf' => 61, 'safe' => 'escal',
            'steps' => ['Review the planning of the pending EVAs with the radiation office.'],
            'src' => [
                ['l' => 'Protocol: radiation', 't' => 'Radiation protocol · v2.0 · §5. Assigned mission limit: 100 mSv.', 'ic' => 'description'],
                ['l' => 'Personal dosimeter', 't' => 'Mission cumulative total according to the dosimeter.', 'ic' => 'sensors'],
            ],
        ],
        'frecuencia_cardiaca' => [
            'finding' => 'Resting heart rate is outside your range ({v} bpm).',
            'conf' => 54, 'safe' => 'verif',
            'steps' => [
                'Repeat the measurement after 5 minutes seated.',
                'Log any dizziness or unusual tiredness.',
            ],
            'src' => [
                ['l' => 'Protocol: heart rate', 't' => 'Onboard medical protocol · v3.2 · §2. It can be normal in trained individuals.', 'ic' => 'description'],
                ['l' => 'Personal baseline', 't' => 'Your Earth baseline value, from your record.', 'ic' => 'person'],
            ],
        ],
        'glucosa' => [
            'finding' => 'Glucose is out of range ({v} mg/dL).',
            'conf' => 63, 'safe' => 'verif',
            'steps' => ['Repeat the measurement fasting tomorrow.', 'Log the time of the last meal.'],
            'src' => [['l' => 'Protocol: glucose', 't' => 'Onboard medical protocol · v3.2 · §6.', 'ic' => 'description']],
        ],
        'saturacion_o2' => [
            'finding' => 'Oxygen saturation is somewhat low ({v} %).',
            'conf' => 69, 'safe' => 'escal',
            'steps' => [
                'Repeat the measurement with a warm finger and a still hand.',
                'If it drops below 90 %, the emergency rule triggers.',
            ],
            'src' => [['l' => 'Protocol: oxygenation', 't' => 'Onboard medical protocol · v3.2 · §1.', 'ic' => 'description']],
        ],
        'temperatura' => [
            'finding' => 'Temperature is out of range ({v} °C).',
            'conf' => 58, 'safe' => 'verif',
            'steps' => [
                'Repeat the measurement within 30 minutes.',
                'Drink water and avoid intense exercise until then.',
            ],
            'src' => [['l' => 'Protocol: temperature', 't' => 'Onboard medical protocol · v3.2 · §3.', 'ic' => 'description']],
        ],
    ],
];
