<?php

/**
 * Pantalla Rutinas (diseño "Panel tripulación"): tipos, estilos por clase de tarea, metas y el bloque "Por qué importa".
 * Textos orientativos: no prescriben ni diagnostican; metas = valores de ejemplo pendientes de validar con HRP.
 */
return [
    /** Tipo de actividad => icono por defecto, clase (ej|med|bien) y unidad de la cantidad. `max` = tope de la cantidad. */
    'tipos' => [
        'Ejercicio resistido' => ['icono' => 'fitness_center', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'Cardio' => ['icono' => 'pedal_bike', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'EVA' => ['icono' => 'rocket_launch', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'Comida' => ['icono' => 'restaurant', 'kind' => 'bien', 'unidad' => 'min', 'max' => 720],
        'Hidratación' => ['icono' => 'water_drop', 'kind' => 'bien', 'unidad' => 'ml', 'max' => 3000],
        'Sueño' => ['icono' => 'bedtime', 'kind' => 'bien', 'unidad' => 'min', 'max' => 960],
        'Medición' => ['icono' => 'monitor_heart', 'kind' => 'med', 'unidad' => 'min', 'max' => 720],
        'Otro' => ['icono' => 'more_horiz', 'kind' => 'bien', 'unidad' => 'min', 'max' => 720],
    ],

    /** Tipos que se ofrecen al registrar una actividad suelta (como en el diseño; "Medición" solo para rutinas). */
    'tipos_actividad' => ['Ejercicio resistido', 'Cardio', 'EVA', 'Comida', 'Hidratación', 'Sueño', 'Otro'],

    /** Iconos que se pueden elegir al crear una rutina (Material Symbols). */
    'iconos' => ['fitness_center', 'pedal_bike', 'directions_run', 'self_improvement', 'monitor_heart', 'flare', 'water_drop', 'restaurant', 'bedtime', 'mood', 'rocket_launch', 'more_horiz'],

    /** Clases de tarea (leyenda del calendario). */
    'kinds' => [
        'ej' => 'Ejercicio',
        'med' => 'Medición',
        'bien' => 'Bienestar',
    ],

    'dias' => [1 => 'LUN', 2 => 'MAR', 3 => 'MIÉ', 4 => 'JUE', 5 => 'VIE', 6 => 'SÁB', 7 => 'DOM'],
    'dias_largo' => [1 => 'LUNES', 2 => 'MARTES', 3 => 'MIÉRCOLES', 4 => 'JUEVES', 5 => 'VIERNES', 6 => 'SÁBADO', 7 => 'DOMINGO'],
    'meses' => [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'],

    /** Metas diarias de ejemplo. Ejercicio: 150 min/día (2.5 h), 6 a 7 días por semana (guía interna; valor de ejemplo). */
    'metas' => ['ejercicio_min' => 150, 'ejercicio_dias_semana' => [6, 7], 'agua_ml' => 2500, 'sueno_h' => 8],

    /** Intensidad al registrar actividad. */
    'intensidades' => ['Baja', 'Media', 'Alta'],

    /**
     * "Por qué importa": indicador del panel (clave de config/panel.php) => etiqueta, icono, acción sugerida y motivo.
     * Se muestran los 2 con peor estado real del tripulante.
     */
    'why' => [
        'perdida_osea' => ['etiqueta' => 'Pérdida ósea (cadera)', 'icono' => 'skeleton', 'accion' => 'Completa hoy tu sesión de carga (ARED)', 'porque' => 'La carga sobre los huesos frena la pérdida de densidad que provoca la microgravedad.'],
        'masa_muscular' => ['etiqueta' => 'Masa muscular', 'icono' => 'fitness_center', 'accion' => 'Haz la rutina de fuerza de piernas', 'porque' => 'Las piernas y la espalda pierden masa rápido sin ejercicio de resistencia.'],
        'radiacion_diaria' => ['etiqueta' => 'Radiación diaria', 'icono' => 'flare', 'accion' => 'Revisa tu dosímetro y prioriza zonas blindadas', 'porque' => 'Conocer tu dosis del día ayuda a planificar las tareas en módulos más protegidos.'],
        'presion_arterial' => ['etiqueta' => 'Presión arterial', 'icono' => 'monitor_heart', 'accion' => 'Mide tu presión en reposo', 'porque' => 'En microgravedad los fluidos suben al tórax y pueden elevar la presión.'],
    ],
    'why_max' => 2,
];
