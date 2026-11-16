<?php

/*
 | Reglas de ventana: miran las últimas N lecturas (o un periodo) de un indicador y pueden ELEVAR el estado de la
 | última lectura. Las evalúa App\Services\Salud\EvaluadorVentanas; solo se consultan los indicadores que tienen
 | una regla activa aquí (el resto no paga ninguna consulta extra).
 |
 | SUPUESTOS DEL PROYECTO, NO DE LA NORMA: el estándar no publica horas de sueño ni umbrales de calorías/PVT.
 | Lo único que viene de la guía «Datos médicos» (fila de `requisitos` tipo guía, ver config/trazabilidad.php) es el
 | enunciado «sueño menor a 6 h por más de 48 h». Cómo se traduce a lecturas es criterio del proyecto, sin validar
 | clínicamente. La severidad es solo preventiva: orienta, no diagnostica ni receta (sin hipnóticos ni fármacos).
 */

return [
    'reglas' => [
        'sueno_corto' => [
            'activa' => true,
            'indicador' => 'sueno',
            'nombre' => 'Sueño corto sostenido',
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
            'recomendacion' => 'Acción preventiva: evita asignar tareas críticas hasta recuperar el sueño y reprograma lo que se pueda. Protege un bloque de descanso, reduce luz y ruido antes de dormir y comenta el patrón con el médico de vuelo en la próxima ventana.',
            // Pasos de la tarjeta del Médico de vuelo (solo medidas de conducta y de horario; nada de fármacos).
            'pasos' => [
                'Bloquea las tareas críticas hasta recuperar el sueño y pide reprogramarlas si es posible.',
                'Protege un bloque de descanso: horario fijo, luz tenue y poco ruido una hora antes de dormir.',
                'Comenta el patrón con el médico de vuelo en la próxima ventana de comunicación.',
            ],
            'texto' => 'Sueño menor a 6 h durante {n} lecturas diarias seguidas (guía: «por más de 48 h»).',
            // Fila de `requisitos` (tipo guía) en la que se apoya; coincide con la de config/trazabilidad.php.
            'requisito' => ['tipo' => 'guia', 'dominio' => 'sueno', 'texto' => 'Sueño menor a 6 h por más de 48 h (severidad preventiva)'],
        ],

        // PRÓXIMAMENTE: necesitan indicadores que aún no existen (ingesta calórica, test PVT). Desactivadas:
        // el evaluador las ignora y no se consulta nada por ellas.
        'calorias_bajas' => [
            'activa' => false,
            'proximamente' => true,
            'requiere' => 'Indicador de ingesta calórica (aún no existe)',
            'indicador' => 'calorias',
            'nombre' => 'Ingesta calórica baja sostenida',
            'componente' => 'valor',
            'operador' => '<',
            'umbral' => 90.0, // % del requerimiento (guía)
            'lecturas' => 3,  // «3 días seguidos» (guía)
            'max_hueco_h' => 36,
            'eleva_a' => 'precaucion',
            'accion' => 'preventiva',
            'requisito' => ['tipo' => 'guia', 'dominio' => 'nutricion', 'texto' => 'Calorías bajo el 90 % del requerimiento durante 3 días seguidos'],
        ],
        'pvt_lapsos' => [
            'activa' => false,
            'proximamente' => true,
            'requiere' => 'Indicador de vigilancia psicomotora PVT (aún no existe)',
            'indicador' => 'pvt',
            'nombre' => 'PVT con lapsos largos',
            'componente' => 'valor',
            'operador' => '>',
            'umbral' => 500.0, // ms (guía)
            'lecturas' => 1,   // SUPUESTO: la guía no da ventana; sin definir hasta que exista el indicador
            'max_hueco_h' => 36,
            'eleva_a' => 'precaucion',
            'accion' => 'preventiva',
            'requisito' => ['tipo' => 'guia', 'dominio' => 'sueno', 'texto' => 'PVT con lapsos mayores a 500 ms (severidad preventiva)'],
        ],
    ],
];
