<?php

/*
 | Interfaz del orientador conversacional del Médico de vuelo (simulado). Valores de interfaz, no clínicos.
 | Supuesto del proyecto, no de la norma: el límite de caracteres y las listas de abajo.
 */
return [
    // Máximo de caracteres del texto libre (supuesto del proyecto).
    'max_caracteres' => 500,

    // Atajos: [etiqueta del botón, texto que rellenan]. El usuario completa y envía.
    'atajos' => [
        ['Me siento mareado', 'Me siento mareado'],
        ['Me duele…', 'Me duele '],
        ['Me falta el aire', 'Me falta el aire'],
    ],

    // Síntomas que se pueden añadir a mano en "Entendí" (clave del contrato del analizador => etiqueta).
    'sintomas' => [
        'dolor' => 'Dolor', 'mareo' => 'Mareo', 'falta_aire' => 'Falta de aire', 'nauseas' => 'Náuseas', 'fiebre' => 'Fiebre',
        'fatiga' => 'Fatiga', 'palpitaciones' => 'Palpitaciones', 'dolor_cabeza' => 'Dolor de cabeza', 'vomito' => 'Vómito',
        'desmayo' => 'Desmayo', 'confusion' => 'Confusión', 'vision' => 'Problemas de visión', 'debilidad' => 'Debilidad',
        'entumecimiento' => 'Entumecimiento', 'hemorragia' => 'Hemorragia', 'convulsion' => 'Convulsión', 'escalofrios' => 'Escalofríos',
        'presion_pecho' => 'Presión en el pecho',
    ],

    // Zonas que se pueden añadir a mano (clave => etiqueta).
    'zonas' => [
        'pecho' => 'Pecho', 'cabeza' => 'Cabeza', 'cuello' => 'Cuello', 'brazo' => 'Brazo', 'triceps' => 'Tríceps', 'mano' => 'Mano',
        'abdomen' => 'Abdomen', 'espalda' => 'Espalda', 'pierna' => 'Pierna', 'pie' => 'Pie',
    ],

    // Indicadores que se vigilan en una emergencia (pulso y saturación) si el seguimiento no indica ninguno.
    'indicadores_emergencia' => ['frecuencia_cardiaca', 'saturacion_o2'],
];
