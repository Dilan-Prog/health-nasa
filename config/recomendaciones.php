<?php

/*
 | Reglas de RecomendacionesEspecificas (orientador simulado): acciones concretas de alimentación, hidratación,
 | ejercicio o descanso por indicador alterado y DIRECCIÓN del problema. Todo son datos; el servicio solo las aplica.
 | Orienta, no diagnostica ni receta: sin dosis, sin fármacos, sin cargas que no estén en las fuentes.
 |
 |  - 'reglas' => indicador => direccion ('alto' | 'bajo' | 'cualquiera') => lista de acciones.
 |      Con dirección conocida se aplican las de esa dirección + 'cualquiera'. Con dirección desconocida solo 'cualquiera'
 |      (nunca se recomienda "subir" o "bajar" sin saber hacia dónde se salió el valor), salvo 'direccion_implicita'.
 |  - Cada acción: 'id' (estable dentro del indicador), 'texto', 'fuente' (clave de 'fuentes'), y opcionales:
 |      'panel' => texto EXACTO de la acción del Panel (config/panel.php) que esta acción reitera: usa su id, así que se omite
 |                 si el Panel ya la muestra;
 |      'si'    => condición sobre la rutina real: bajo_meta | cumple_meta | sin_resistido | con_resistido | sin_rutina | sueno_bajo.
 |      Marcadores en el texto: {min_semana} {meta_semana} {meta_dia} {n_resistido} {sueno_h}.
 |  - Indicadores sin reglas (p. ej. radiación): el orientador no inventa acciones de alimentación/ejercicio/descanso para ellos.
 */

return [
    // SUPUESTOS DEL PROYECTO (no de la norma): topes de presentación.
    'max_por_indicador' => 3,
    'max_total' => 6,

    // Umbral de sueño bajo: 7 h = mínimo del rango 7 a 9 h de docs/fuentes/variacion_dosis_efectiva.txt (no es un número nuevo).
    'sueno_min_h' => 7,
    // Días mínimos de ejercicio por semana: "6 a 7 días por semana" de docs/fuentes/datos_medicos.txt (se usa el mínimo).
    'dias_ejercicio_min' => 6,
    // Meta diaria por defecto si el contexto no la trae: config/rutinas.php 'metas.ejercicio_min' (2.5 h/día).
    'meta_dia_defecto_min' => 150,
    'tipo_resistido' => 'Ejercicio resistido',

    /** Cuando el contexto no trae 'direccion', hacia dónde se sale este indicador por naturaleza (solo un lado es problema). */
    'direccion_implicita' => [
        'saturacion_o2' => 'bajo',
        'hidratacion' => 'bajo',
        'sueno' => 'bajo',
        'perdida_osea' => 'alto',
        'masa_muscular' => 'alto',
        'estres_cognitivo' => 'alto',
    ],

    'fuentes' => [
        'datos_medicos' => [
            'etiqueta' => 'Arquitectura de Salud Espacial (datos_medicos.txt)',
            'texto' => 'Basado en NASA-STD-3001 y OCHMO: prescripción de ejercicio, nutrición e hidratación, fatiga y sueño.',
            'origen' => 'documento',
        ],
        'guia' => [
            'etiqueta' => 'Guía de marcadores (config/guia.php)',
            'texto' => 'Explicación breve de cada marcador en microgravedad.',
            'origen' => 'documento',
        ],
        'sueno' => [
            'etiqueta' => 'Sueño en órbita (variacion_dosis_efectiva.txt)',
            'texto' => 'Se establecen de 7 a 9 horas de sueño; en órbita el promedio real es menor.',
            'origen' => 'documento',
        ],
        'temperatura' => [
            'etiqueta' => 'Temperatura en el espacio (variacion_dosis_efectiva.txt)',
            'texto' => 'Al entrenar en microgravedad el calor corporal sube más rápido; se vigila frente a la base personal.',
            'origen' => 'documento',
        ],
        'ejemplo' => [
            'etiqueta' => 'Ejemplo simulado (sin validar)',
            'texto' => 'Orientación general y conservadora, pendiente de revisión médica.',
            'origen' => 'ejemplo_simulado',
        ],
        'pendiente' => [
            'etiqueta' => 'Pendiente de fuente verificable',
            'texto' => 'No hay documento que lo respalde; consúltalo con el médico de vuelo.',
            'origen' => 'ejemplo_simulado',
        ],
    ],

    'reglas' => [
        'glucosa' => [
            'alto' => [
                ['id' => 'comidas_regulares', 'texto' => 'Reparte tu ingesta en comidas a horas regulares, sin saltarte ninguna, y prefiere alimentos con fibra y proteína en lugar de azúcares simples.', 'fuente' => 'ejemplo'],
                ['id' => 'mover_tras_comer', 'texto' => 'Incluye movimiento suave de tu rutina junto a las comidas: la menor actividad muscular reduce la sensibilidad a la insulina y eleva la glucosa.', 'fuente' => 'guia'],
                ['id' => 'registrar_comida', 'texto' => 'Anota qué comiste y a qué hora antes de cada medición para ver qué comidas empujan tu glucosa hacia arriba.', 'fuente' => 'ejemplo', 'panel' => 'Anota tu última comida y la hora'],
            ],
            'bajo' => [
                ['id' => 'comer_pronto', 'texto' => 'Haz una comida o un refrigerio con carbohidratos de absorción habitual y vuelve a medir pasado un rato.', 'fuente' => 'ejemplo'],
                ['id' => 'no_ayuno_ejercicio', 'texto' => 'No te saltes comidas ni entrenes con el estómago vacío hasta confirmar la medición.', 'fuente' => 'ejemplo'],
                ['id' => 'avisar_sintomas', 'texto' => 'Si notas temblor, sudor frío o confusión, avisa a un compañero y a tierra sin esperar a repetir la medición.', 'fuente' => 'ejemplo'],
            ],
            'cualquiera' => [
                ['id' => 'horarios_estables', 'texto' => 'Mantén horarios de comida estables hasta confirmar si la lectura es alta o baja.', 'fuente' => 'ejemplo'],
            ],
        ],

        'presion_arterial' => [
            'alto' => [
                ['id' => 'menos_sal', 'texto' => 'Reduce la sal añadida y prefiere alimentos frescos o poco procesados en tus próximas comidas.', 'fuente' => 'ejemplo'],
                ['id' => 'esfuerzo_suave', 'texto' => 'Evita el ejercicio de alta intensidad hasta confirmar con una medición en reposo; elige esfuerzo suave.', 'fuente' => 'ejemplo'],
                ['id' => 'descanso_presion', 'texto' => 'Cuida tu descanso: el desvelo y el estrés pueden elevar la presión de forma pasajera.', 'fuente' => 'ejemplo'],
            ],
            'bajo' => [
                ['id' => 'postura_lenta', 'texto' => 'Cambia de postura despacio, sobre todo al incorporarte o salir de la zona de dormir, para evitar mareo.', 'fuente' => 'ejemplo'],
                ['id' => 'liquido_2l', 'texto' => 'Reparte el líquido durante el día y procura no quedar por debajo de 2.0 L diarios, la referencia de alerta del documento.', 'fuente' => 'datos_medicos'],
                ['id' => 'no_saltar_comidas', 'texto' => 'No te saltes comidas y evita el esfuerzo intenso hasta que la medición en reposo vuelva a tu rango.', 'fuente' => 'ejemplo'],
            ],
        ],

        'frecuencia_cardiaca' => [
            'alto' => [
                ['id' => 'bajar_intensidad', 'texto' => 'Baja la intensidad de la sesión de hoy y alarga el enfriamiento, que forma parte de la carga diaria.', 'fuente' => 'datos_medicos'],
                ['id' => 'liquido_sesion', 'texto' => 'Bebe líquido durante y después del ejercicio y evita bebidas estimulantes hasta que el pulso en reposo se normalice.', 'fuente' => 'ejemplo'],
            ],
            'bajo' => [
                ['id' => 'postura_fc', 'texto' => 'Cambia de postura despacio y evita esfuerzos bruscos; aumenta la intensidad del ejercicio solo de forma gradual.', 'fuente' => 'ejemplo'],
            ],
            'cualquiera' => [
                ['id' => 'comparar_base_fc', 'texto' => 'Compara la lectura con tu base personal y con lo que hiciste justo antes (ejercicio, comida, postura).', 'fuente' => 'ejemplo'],
            ],
        ],

        'saturacion_o2' => [
            'bajo' => [
                ['id' => 'reducir_esfuerzo', 'texto' => 'Reduce el esfuerzo físico y pospón el ejercicio intenso hasta confirmar la lectura en reposo.', 'fuente' => 'ejemplo'],
                ['id' => 'revisar_ambiente', 'texto' => 'Revisa la ventilación y la calidad del aire de tu módulo y avisa si otros compañeros notan lo mismo.', 'fuente' => 'ejemplo'],
            ],
        ],

        'temperatura' => [
            'alto' => [
                ['id' => 'frescor_liquido', 'texto' => 'Descansa en un ambiente fresco y bebe líquido; sin convección natural el cuerpo disipa peor el calor.', 'fuente' => 'temperatura'],
                ['id' => 'evitar_intenso_temp', 'texto' => 'Evita el ejercicio intenso mientras la temperatura no vuelva a tu rango; en microgravedad el calor sube más rápido al entrenar.', 'fuente' => 'temperatura'],
                ['id' => 'vigilar_otros', 'texto' => 'Vigila otros síntomas de infección: el criterio es un aumento brusco sobre tu nueva media espacial, no el umbral terrestre.', 'fuente' => 'temperatura'],
            ],
            'bajo' => [
                ['id' => 'abrigo_tibio', 'texto' => 'Abrígate, evita zonas frías del módulo y toma líquidos tibios mientras repites la medición.', 'fuente' => 'ejemplo'],
            ],
        ],

        'imc' => [
            'alto' => [
                ['id' => 'racion_nutricion', 'texto' => 'Revisa con nutrición tus raciones y la ingesta calórica diaria frente a tu requerimiento, sin recortar comidas por tu cuenta.', 'fuente' => 'ejemplo'],
                ['id' => 'meta_ejercicio_imc', 'texto' => 'Mantén la meta diaria de ejercicio de tu rutina: {min_semana} min esta semana frente a {meta_semana} min de meta.', 'fuente' => 'datos_medicos', 'si' => 'bajo_meta'],
            ],
            'bajo' => [
                ['id' => 'cubrir_requerimiento', 'texto' => 'Registra lo que comes: si no cubres tu requerimiento diario durante varios días seguidos, pide a nutrición y al médico de vuelo ajustar tu plan de raciones.', 'fuente' => 'datos_medicos'],
                ['id' => 'comidas_completas', 'texto' => 'Prioriza comidas completas con proteína antes que picoteo, y mantén el ejercicio resistido para conservar músculo.', 'fuente' => 'ejemplo'],
            ],
        ],

        'perdida_osea' => [
            'alto' => [
                ['id' => 'completar_meta_osea', 'texto' => 'Esta semana llevas {min_semana} min de ejercicio frente a {meta_semana} min de meta ({meta_dia} min al día, 6 a 7 días por semana); completa lo que falta, empezando por la sesión de carga.', 'fuente' => 'datos_medicos', 'si' => 'bajo_meta'],
                ['id' => 'resistido_osea', 'texto' => 'No hay sesión de ejercicio resistido registrada: prioriza una con ejercicios multiarticulares (sentadillas, peso muerto, press de banca, elevación de talones), que son los que estimulan el hueso según el documento.', 'fuente' => 'datos_medicos', 'si' => 'sin_resistido'],
                ['id' => 'recalibrar_osea', 'texto' => 'Cumples la meta semanal: si la pérdida ósea sigue avanzando, pide a tierra revisar y recalibrar las cargas de tu sesión resistiva.', 'fuente' => 'datos_medicos', 'si' => 'cumple_meta'],
                ['id' => 'liquido_osea', 'texto' => 'Reparte el líquido durante el día: en el espacio la sed disminuye y beber poco aumenta el riesgo de cálculos renales por el calcio que libera el hueso.', 'fuente' => 'guia'],
            ],
        ],

        'masa_muscular' => [
            'alto' => [
                ['id' => 'completar_meta_musculo', 'texto' => 'Esta semana llevas {min_semana} min de ejercicio frente a {meta_semana} min de meta ({meta_dia} min al día, 6 a 7 días por semana); completa lo que falta antes de añadir otra cosa.', 'fuente' => 'datos_medicos', 'si' => 'bajo_meta'],
                ['id' => 'resistido_musculo', 'texto' => 'No hay sesión de ejercicio resistido registrada: prioriza una con ejercicios multiarticulares (sentadillas, peso muerto, press de banca, elevación de talones).', 'fuente' => 'datos_medicos', 'si' => 'sin_resistido'],
                ['id' => 'recalibrar_musculo', 'texto' => 'Cumples la meta semanal de ejercicio: si la masa muscular sigue bajando, pide a tierra revisar y recalibrar las cargas de tu sesión resistiva.', 'fuente' => 'datos_medicos', 'si' => 'cumple_meta'],
                ['id' => 'registrar_cargas', 'texto' => 'Registra las series y cargas de tu sesión resistiva ({n_resistido} sesiones recientes) para que el equipo pueda recalibrarlas.', 'fuente' => 'datos_medicos', 'si' => 'con_resistido', 'panel' => 'Registra series y cargas'],
                ['id' => 'registrar_rutina', 'texto' => 'Registra tus sesiones de ejercicio en Rutinas para poder ajustar tu plan con datos reales.', 'fuente' => 'ejemplo', 'si' => 'sin_rutina'],
                ['id' => 'proteina_comidas', 'texto' => 'Incluye una fuente de proteína en cada comida principal en lugar de concentrarla en una sola.', 'fuente' => 'ejemplo'],
                ['id' => 'dormir_recuperar', 'texto' => 'Dormiste {sueno_h} h; el documento fija de 7 a 9 h y la recuperación muscular depende del descanso.', 'fuente' => 'sueno', 'si' => 'sueno_bajo'],
            ],
        ],

        'hidratacion' => [
            'bajo' => [
                ['id' => 'repartir_liquido', 'texto' => 'Reparte el líquido a lo largo del día y no esperes a tener sed: en el espacio la sed disminuye.', 'fuente' => 'guia'],
                ['id' => 'liquido_comidas', 'texto' => 'Acompaña cada comida y cada sesión de ejercicio con líquido; por debajo de 2.0 L diarios el documento marca alerta.', 'fuente' => 'datos_medicos'],
            ],
        ],

        'sueno' => [
            'bajo' => [
                ['id' => 'horario_fijo', 'texto' => 'Mantén un horario de descanso fijo y reduce la luz y el ruido antes de dormir; la meta del documento es de 7 a 9 horas.', 'fuente' => 'sueno'],
                ['id' => 'ejercicio_noche', 'texto' => 'Evita el ejercicio intenso y las bebidas estimulantes justo antes de dormir.', 'fuente' => 'ejemplo'],
                ['id' => 'tareas_criticas', 'texto' => 'Si el sueño sigue corto, avisa para replanificar tareas críticas: el documento las bloquea con sueño efectivo muy corto.', 'fuente' => 'datos_medicos'],
            ],
        ],

        'estres_cognitivo' => [
            'alto' => [
                ['id' => 'pausas', 'texto' => 'Alterna las tareas exigentes con pausas breves lejos de la pantalla y cuida tus horas de descanso.', 'fuente' => 'ejemplo'],
                ['id' => 'apoyo', 'texto' => 'Comenta con un compañero o con tierra cómo te sientes; el aislamiento y la carga de trabajo afectan atención y ánimo.', 'fuente' => 'guia'],
            ],
        ],
    ],
];
