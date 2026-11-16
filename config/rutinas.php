<?php

/**
 * Pantalla Rutinas (diseño "Panel tripulación"): tipos, estilos por clase de tarea, metas y el bloque "Por qué importa".
 * Textos orientativos: no prescriben ni diagnostican; metas = valores de ejemplo pendientes de validar con HRP.
 */
return [
    /** Tipo de actividad => icono por defecto, clase (ej|med|bien) y unidad de la cantidad. `max` = tope de la cantidad. */
    'tipos' => [
        'Ejercicio resistido' => ['label' => 'Resistance exercise', 'icono' => 'fitness_center', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'Cardio' => ['label' => 'Cardio', 'icono' => 'pedal_bike', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'EVA' => ['label' => 'EVA', 'icono' => 'rocket_launch', 'kind' => 'ej', 'unidad' => 'min', 'max' => 720],
        'Comida' => ['label' => 'Meal', 'icono' => 'restaurant', 'kind' => 'bien', 'unidad' => 'min', 'max' => 720],
        'Hidratación' => ['label' => 'Hydration', 'icono' => 'water_drop', 'kind' => 'bien', 'unidad' => 'ml', 'max' => 3000],
        'Sueño' => ['label' => 'Sleep', 'icono' => 'bedtime', 'kind' => 'bien', 'unidad' => 'min', 'max' => 960],
        'Medición' => ['label' => 'Measurement', 'icono' => 'monitor_heart', 'kind' => 'med', 'unidad' => 'min', 'max' => 720],
        'Otro' => ['label' => 'Other', 'icono' => 'more_horiz', 'kind' => 'bien', 'unidad' => 'min', 'max' => 720],
    ],

    /** Tipos que se ofrecen al registrar una actividad suelta (como en el diseño; "Medición" solo para rutinas). */
    'tipos_actividad' => ['Ejercicio resistido', 'Cardio', 'EVA', 'Comida', 'Hidratación', 'Sueño', 'Otro'],

    /** Iconos que se pueden elegir al crear una rutina (Material Symbols). */
    'iconos' => ['fitness_center', 'pedal_bike', 'directions_run', 'self_improvement', 'monitor_heart', 'flare', 'water_drop', 'restaurant', 'bedtime', 'mood', 'rocket_launch', 'more_horiz'],

    /** Clases de tarea (leyenda del calendario). */
    'kinds' => [
        'ej' => 'Exercise',
        'med' => 'Measurement',
        'bien' => 'Wellness',
    ],

    'dias' => [1 => 'MON', 2 => 'TUE', 3 => 'WED', 4 => 'THU', 5 => 'FRI', 6 => 'SAT', 7 => 'SUN'],
    'dias_largo' => [1 => 'MONDAY', 2 => 'TUESDAY', 3 => 'WEDNESDAY', 4 => 'THURSDAY', 5 => 'FRIDAY', 6 => 'SATURDAY', 7 => 'SUNDAY'],
    'meses' => [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'],

    /** Metas diarias de ejemplo. Ejercicio: 150 min/día (2.5 h), 6 a 7 días por semana (guía interna; valor de ejemplo). */
    'metas' => ['ejercicio_min' => 150, 'ejercicio_dias_semana' => [6, 7], 'agua_ml' => 2500, 'sueno_h' => 8],

    /** Intensidad al registrar actividad. */
    'intensidades' => ['Baja', 'Media', 'Alta'],

    /** Display labels for the stored intensity values (the Spanish values above are persisted and compared; never print them directly). */
    'intensidades_label' => ['Baja' => 'Low', 'Media' => 'Medium', 'Alta' => 'High'],

    /**
     * "Por qué importa": indicador del panel (clave de config/panel.php) => etiqueta, icono, acción sugerida y motivo.
     * Se muestran los 2 con peor estado real del tripulante.
     */
    'why' => [
        'perdida_osea' => ['etiqueta' => 'Bone loss (hip)', 'icono' => 'skeleton', 'accion' => 'Complete your loading session (ARED) today', 'porque' => 'Loading the bones slows the loss of density caused by microgravity.'],
        'masa_muscular' => ['etiqueta' => 'Muscle mass', 'icono' => 'fitness_center', 'accion' => 'Do the leg strength routine', 'porque' => 'The legs and back lose mass quickly without resistance exercise.'],
        'radiacion_diaria' => ['etiqueta' => 'Daily radiation', 'icono' => 'flare', 'accion' => 'Check your dosimeter and prioritize shielded areas', 'porque' => 'Knowing your dose for the day helps plan tasks in more protected modules.'],
        'presion_arterial' => ['etiqueta' => 'Blood pressure', 'icono' => 'monitor_heart', 'accion' => 'Measure your blood pressure at rest', 'porque' => 'In microgravity, fluids shift toward the chest and can raise blood pressure.'],
    ],
    'why_max' => 2,
];
