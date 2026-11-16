<?php

/*
 | Window rules: look at the last N readings (or a period) of an indicator and can RAISE the state of the latest
 | reading. Evaluated by App\Services\Salud\EvaluadorVentanas; only indicators with an active rule here are
 | queried (the rest pay no extra query).
 |
 | PROJECT ASSUMPTIONS, NOT THE STANDARD'S: the standard does not publish sleep hours or calorie/PVT thresholds.
 | The only thing that comes from the "Medical data" guide (a guide-type row of `requisitos`, see
 | config/trazabilidad.php) is the statement "sleep under 6 h for more than 48 h". How it maps to readings is a
 | project criterion, not clinically validated. Severity is preventive only: it guides, does not diagnose or
 | prescribe (no hypnotics or drugs). Rows are referenced by their stable `clave`, not by text.
 */

return [
    'reglas' => [
        'sueno_corto' => [
            'activa' => true,
            'indicador' => 'sueno',
            'nombre' => 'Sustained short sleep',
            'componente' => 'valor',
            'operador' => '<',
            // Guía: «menor a 6 h». Mismo número que la fila de la guía; se repite aquí porque la fila es texto.
            'umbral' => 6.0,
            // SUPUESTO: «más de 48 h» con dato diario = 3 lecturas diarias consecutivas bajo el umbral
            // (2 noches cubren 48 h justas, no «más de 48 h»). Es configurable.
            'lecturas' => 3,
            // SUPUESTO: si entre dos lecturas pasan más horas que esto, falta un dato diario y la cadena se rompe
            // (un dato faltante no se interpreta como noche mala ni como buena: no se eleva).
            'max_hueco_h' => 36,
            'eleva_a' => 'precaucion', // mínimo; nunca baja un estado ya más grave
            'accion' => 'preventiva',
            'recomendacion' => 'Preventive action: avoid assigning critical tasks until sleep is recovered and reschedule what you can. Protect a rest block, reduce light and noise before sleep, and discuss the pattern with the Flight Surgeon at the next communication window.',
            // Pasos de la tarjeta del Médico de vuelo (solo medidas de conducta y de horario; nada de fármacos).
            'pasos' => [
                'Block critical tasks until sleep is recovered and ask to reschedule them if possible.',
                'Protect a rest block: fixed schedule, dim light and low noise for one hour before sleep.',
                'Discuss the pattern with the Flight Surgeon at the next communication window.',
            ],
            'texto' => 'Sleep under 6 h for {n} consecutive daily readings (guide: "for more than 48 h").',
            // Fila de `requisitos` (tipo guía) en la que se apoya; coincide con la de config/trazabilidad.php.
            'requisito' => ['tipo' => 'guia', 'clave' => 'guia.sueno.menor_6h_48h'],
        ],

        // PRÓXIMAMENTE: necesitan indicadores que aún no existen (ingesta calórica, test PVT). Desactivadas:
        // el evaluador las ignora y no se consulta nada por ellas.
        'calorias_bajas' => [
            'activa' => false,
            'proximamente' => true,
            'requiere' => 'Calorie intake indicator (does not exist yet)',
            'indicador' => 'calorias',
            'nombre' => 'Sustained low calorie intake',
            'componente' => 'valor',
            'operador' => '<',
            'umbral' => 90.0, // % del requerimiento (guía)
            'lecturas' => 3,  // «3 días seguidos» (guía)
            'max_hueco_h' => 36,
            'eleva_a' => 'precaucion',
            'accion' => 'preventiva',
            'requisito' => ['tipo' => 'guia', 'clave' => 'guia.nutricion.calorias_bajo_90'],
        ],
        'pvt_lapsos' => [
            'activa' => false,
            'proximamente' => true,
            'requiere' => 'PVT psychomotor vigilance indicator (does not exist yet)',
            'indicador' => 'pvt',
            'nombre' => 'PVT with long lapses',
            'componente' => 'valor',
            'operador' => '>',
            'umbral' => 500.0, // ms (guía)
            'lecturas' => 1,   // SUPUESTO: la guía no da ventana; sin definir hasta que exista el indicador
            'max_hueco_h' => 36,
            'eleva_a' => 'precaucion',
            'accion' => 'preventiva',
            'requisito' => ['tipo' => 'guia', 'clave' => 'guia.sueno.pvt_lapsos'],
        ],
    ],
];
