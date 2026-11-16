<?php

/*
 | Interfaz del orientador conversacional del Médico de vuelo (simulado). Valores de interfaz, no clínicos.
 | Supuesto del proyecto, no de la norma: el límite de caracteres y las listas de abajo.
 */
return [
    // Máximo de caracteres del texto libre (supuesto del proyecto).
    'max_caracteres' => 500,

    // Atajos: [etiqueta del botón (inglés, visible), texto que rellenan]. El relleno se queda en ESPAÑOL a propósito:
    // el analizador de síntomas (config/sintomas.php) entiende español. El usuario completa y envía.
    'atajos' => [
        ['I feel dizzy', 'Me siento mareado'],
        ['It hurts…', 'Me duele '],
        ['Short of breath', 'Me falta el aire'],
    ],

    // Síntomas que se pueden añadir a mano en "Entendí" (clave del contrato del analizador => etiqueta).
    'sintomas' => [
        'dolor' => 'Pain', 'mareo' => 'Dizziness', 'falta_aire' => 'Shortness of breath', 'nauseas' => 'Nausea', 'fiebre' => 'Fever',
        'fatiga' => 'Fatigue', 'palpitaciones' => 'Palpitations', 'dolor_cabeza' => 'Headache', 'vomito' => 'Vomiting',
        'desmayo' => 'Fainting', 'confusion' => 'Confusion', 'vision' => 'Vision problems', 'debilidad' => 'Weakness',
        'entumecimiento' => 'Numbness', 'hemorragia' => 'Bleeding', 'convulsion' => 'Seizure', 'escalofrios' => 'Chills',
        'presion_pecho' => 'Chest pressure',
    ],

    // Zonas que se pueden añadir a mano (clave => etiqueta).
    'zonas' => [
        'pecho' => 'Chest', 'cabeza' => 'Head', 'cuello' => 'Neck', 'brazo' => 'Arm', 'triceps' => 'Triceps', 'mano' => 'Hand',
        'abdomen' => 'Abdomen', 'espalda' => 'Back', 'pierna' => 'Leg', 'pie' => 'Foot',
    ],

    // Indicadores que se vigilan en una emergencia (pulso y saturación) si el seguimiento no indica ninguno.
    'indicadores_emergencia' => ['frecuencia_cardiaca', 'saturacion_o2'],
];
