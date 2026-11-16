<?php

/*
 | Los 5 peligros del vuelo espacial y los 17 riesgos HRP, con la cobertura que les da ESTE sistema.
 |
 | IMPORTANTE: la cobertura es una EVALUACIÓN INTERNA, un valor de ejemplo sin validación médica ni de NASA.
 | Se verificó leyendo config/umbrales.php, config/indicadores.php, config/rutinas.php y App\Services\Salud.
 | Estados: cubierto (hay indicador + umbral + orientación), parcial (hay medición o contexto, pero incompleto),
 | sin (el sistema no mide ni orienta sobre el riesgo).
 |
 | Las claves de los peligros coinciden con 'riesgos' de config/umbrales.php, que cada indicador usa para la matriz.
 */

return [
    'nota' => 'They do not act in isolation: they can combine',
    'etiqueta_ejemplo' => 'example value / internal assessment',

    'peligros' => [
        'radiacion' => ['nombre' => 'Space Radiation', 'ic' => 'flare'],
        'aislamiento' => ['nombre' => 'Isolation and Confinement', 'ic' => 'groups'],
        'distancia' => ['nombre' => 'Distance from Earth', 'ic' => 'public'],
        'gravedad_alterada' => ['nombre' => 'Gravity Fields', 'ic' => 'swap_vert'],
        'entorno_cerrado' => ['nombre' => 'Hostile/Closed Environments', 'ic' => 'home_work'],
    ],

    'estados' => [
        'cubierto' => ['txt' => 'Covered', 'ic' => 'check_circle'],
        'parcial' => ['txt' => 'Partial', 'ic' => 'timelapse'],
        'sin' => ['txt' => 'No coverage', 'ic' => 'do_not_disturb_on'],
    ],

    'grupos' => [
        'fisiologia' => ['nombre' => 'Physiology', 'riesgos' => [
            ['Risk of Altered Immune System Responses', 'parcial', 'Only body temperature is monitored; there are no immune markers.'],
            ['Risk of Altered Sensorimotor and Vestibular Function', 'sin', 'Balance and coordination are not measured.'],
            ['Risk of Cardiovascular Adaptations', 'cubierto', 'Blood pressure, heart rate and saturation with example thresholds.'],
            ['Risk of Decompression Sickness', 'sin', 'Cabin and suit pressure are not recorded.'],
            ['Risk of Host-Microorganism Interactions', 'sin', 'There are no microbiome or infection data.'],
            ['Risk of Hypoxia', 'parcial', 'The crew member\'s SpO₂ is measured, not the module atmosphere.'],
            ['Risk of Inadequate Food and Nutrition', 'parcial', 'BMI, glucose and hydration; calories and nutrients are not recorded.'],
            ['Risk of Injury and Compromised Performance from Spacewalks (EVA)', 'parcial', 'EVA exists as an activity and mission event; injuries are not tracked.'],
            ['Risk of Reduced Cardiorespiratory and Musculoskeletal Fitness', 'parcial', 'Muscle mass is tracked; VO₂max and strength are not measured.'],
            ['Risk of Spaceflight Associated Neuro-ocular Syndrome (SANS)', 'parcial', 'Refraction is recorded in diopters; retinal thickness is not.'],
            ['Risk of Spaceflight-Induced Bone Changes', 'cubierto', 'Hip bone loss with an example threshold and a loading routine.'],
        ]],
        'conducta' => ['nombre' => 'Behavior', 'riesgos' => [
            ['Risk of Behavioral Changes and Psychiatric Disorders', 'parcial', 'Mood and stress/cognitive; no clinical assessment.'],
            ['Risk of Inadequate Teamwork', 'sin', 'Team dynamics are not measured.'],
            ['Risk of Inadequate Sleep and Irregular Schedules', 'cubierto', 'Sleep hours with an example threshold; no PVT test or schedule regularity.'],
            ['Risk of Inadequate Systems Operations Support', 'sin', 'Outside the scope of a health monitor.'],
            ['Risk of Injury from Dynamic Loads', 'sin', 'Accelerations and launch or reentry loads are not recorded.'],
        ]],
        'radiacion' => ['nombre' => 'Radiation', 'riesgos' => [
            ['Risk of Radiation-Induced Cancers', 'parcial', 'Daily and cumulative dose against an example limit; no per-organ dose or lifetime risk.'],
        ]],
    ],
];
