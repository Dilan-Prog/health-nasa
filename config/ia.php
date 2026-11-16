<?php

/*
 | IA local SIMULADA del Médico de vuelo. Orienta, no diagnostica ni receta.
 |
 |  modo   'simulado' (por defecto): OrientadorSimulado, determinista y por plantillas, sin modelo de lenguaje.
 |         'ia_local': NO implementado. OrientadorProximamente lanza «Próximamente»; no hay llamadas a Ollama ni a n8n.
 |         Un valor desconocido se trata como 'simulado' (modo seguro).
 |  aviso  Texto fijo que acompaña a toda orientación.
 */

return [
    'modo' => env('IA_MODO', 'simulado'),

    'modos' => ['simulado', 'ia_local'],

    'aviso' => 'Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes.',

    'etiquetas' => [
        'proximamente' => 'IA local y n8n: Próximamente',
    ],

    // Etiqueta corta (mono, mayúsculas) de cada indicador para la línea de evidencia del flotante.
    'etiquetas_cortas' => [
        'saturacion_o2' => 'SpO₂',
        'frecuencia_cardiaca' => 'FC',
        'temperatura' => 'TEMP',
        'presion_arterial' => 'PA',
        'hidratacion' => 'HIDRAT.',
        'estres_cognitivo' => 'ESTRÉS',
        'sueno' => 'SUEÑO',
    ],

    /*
     | Palabras que hacen "pertinente" un dato del expediente para cada patrón (se comparan sin tildes ni mayúsculas
     | contra condiciones, antecedentes familiares y alergias). Es un supuesto del proyecto, no de la norma: no es un
     | criterio clínico; solo decide qué mostrar como contexto. Sin coincidencias: «Sin antecedentes relacionados».
     */
    'expediente_palabras' => [
        'fiebre' => ['infecc', 'inmun', 'fiebre', 'sepsis'],
        'hipoxia' => ['asma', 'epoc', 'pulmon', 'respirat', 'bronqu', 'apnea', 'neumon'],
        'deshidratacion' => ['renal', 'rinon', 'calcul', 'litiasis', 'diabet', 'deshidrat'],
        'estres' => ['ansied', 'depres', 'estres', 'panico', 'insomnio'],
        'arritmia' => ['cardi', 'arritm', 'hipertens', 'corazon', 'coronar', 'infarto', 'taquicard', 'soplo'],
        'falta_sueno' => ['insomnio', 'apnea', 'sueno', 'ansied', 'depres'],
        'fallo_sensor' => [],
    ],
];
