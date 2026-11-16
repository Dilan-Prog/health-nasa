<?php

/*
 | Catálogos y límites del expediente del tripulante (UI + validación).
 | Los catálogos son 'clave' => 'Etiqueta en español'. Los límites son [min, max].
 | Rangos de validación de captura de datos; no son criterios clínicos.
 */

return [
    'sexos' => ['femenino' => 'Female', 'masculino' => 'Male', 'otro' => 'Other'],

    'estados_servicio' => ['activo' => 'Active', 'reserva' => 'Reserve', 'retirado' => 'Retired'],

    'aptitudes' => [
        'apto' => 'Fit',
        'apto_restricciones' => 'Fit with restrictions',
        'no_apto' => 'Not fit',
        'pendiente' => 'Pending evaluation',
    ],

    'gravedades' => ['leve' => 'Mild', 'moderada' => 'Moderate', 'grave' => 'Severe'],

    'estados_condicion' => ['activa' => 'Active', 'controlada' => 'Controlled', 'resuelta' => 'Resolved'],

    'alcohol' => [
        'nunca' => 'Never', 'ocasional' => 'Occasional', 'moderado' => 'Moderate', 'frecuente' => 'Frequent',
    ],

    'grupos_sanguineos' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],

    'parentescos' => [
        'madre' => 'Mother', 'padre' => 'Father', 'hermano' => 'Sibling', 'hijo' => 'Child',
        'pareja' => 'Partner', 'abuelo' => 'Grandparent', 'tio' => 'Aunt/uncle', 'amigo' => 'Friend', 'otro' => 'Other',
    ],

    /** Sugerencias para el campo `rol` (texto libre: se acepta cualquier valor). */
    'roles' => [
        'Commander', 'Pilot', 'Flight Engineer', 'Mission Specialist',
        'Crew Medical Officer (CMO)', 'Payload Scientist', 'EVA Specialist',
    ],

    /** Límites numéricos [min, max] por campo. */
    'limites' => [
        'edad' => [18, 80],
        'altura_m' => [1.20, 2.30],
        'peso_kg' => [35, 200],
        'evas' => [0, 500],
        'fc_reposo' => [30, 120],
        'fc_maxima' => [100, 230],
        'presion_basal_sis' => [70, 200],
        'presion_basal_dia' => [40, 130],
        'spo2_basal' => [80, 100],
        'temperatura_basal' => [34.0, 39.0],
        'glucosa_basal' => [40, 400],
        'vo2max' => [10, 90],
        'grasa_corporal_pct' => [2, 60],
        'densidad_osea_tscore' => [-5, 5],
        'horas_sueno_habituales' => [2, 14],
        'actividad_fisica_semana_min' => [0, 3000],
        'dosis_radiacion_previa_msv' => [0, 2000],
        'edad_aparicion' => [0, 110],
        'linea_base' => [-1000, 100000],
    ],

    /** Tripulantes máximos por misión. */
    'max_tripulantes' => 5,

    'max_items' => ['alergias' => 30, 'condiciones' => 30, 'medicamentos' => 30, 'antecedentes' => 30, 'contactos' => 5],
];
