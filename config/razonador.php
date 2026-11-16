<?php

/*
 | Reglas del RazonadorSimulado (IA local SIMULADA). Todo son datos: el razonador no lleva cifras ni listas propias.
 | Los números y listas de este archivo son SUPUESTOS DEL PROYECTO, no de la norma ni de un criterio clínico;
 | existen para que la simulación sea determinista y auditable. Las fichas (conocimiento/sintomas/*.md) llevan el
 | contenido de orientación; aquí solo se decide cómo se combinan.
 */

return [
    // De menor a mayor. "Subir un nivel" avanza una posición.
    'niveles' => ['observacion', 'autocuidado', 'urgente', 'emergencia'],

    /*
     | Banderas rojas (mandan sobre cualquier otra regla). Además de las que trae el análisis o la ficha:
     |  - claves de síntoma que siempre son emergencia,
     |  - dolor o presión con alguna de estas regiones (el dolor punzante en el pecho izquierdo SIEMPRE es emergencia).
     */
    'bandera_roja' => [
        'sintomas' => ['presion_pecho', 'falta_aire', 'desmayo', 'confusion', 'vision', 'convulsion', 'hemorragia'],
        'sintomas_con_region' => ['dolor', 'presion_pecho'],
        'regiones_toracicas' => ['pecho', 'torax', 'toracico', 'toracica', 'precordial', 'esternon', 'corazon'],
    ],

    /*
     | Alias entre las claves de bandera del analizador y las de las fichas: ambas formas son la misma bandera.
     | Se normaliza a la forma de la ficha. 'banderas_directas' son emergencia aunque ninguna ficha las liste.
     */
    'bandera_alias' => [
        'falta_aire' => 'dificultad_respirar',
        'debilidad_lado' => 'debilidad_lateral',
        'dolor_cabeza_subito_intenso' => 'dolor_cabeza_subito',
        'hemorragia_importante' => 'hemorragia',
    ],
    'banderas_directas' => [
        'dolor_toracico', 'dificultad_respirar', 'desmayo', 'dolor_cabeza_subito', 'confusion', 'debilidad_lateral',
        'entumecimiento', 'perdida_vision', 'hemorragia', 'vomito_persistente', 'convulsion',
    ],

    'medidas_inmediatas' => [
        'Detén la actividad que estés haciendo ahora.',
        'Siéntate o reposa en una posición cómoda.',
        'Vigila tu pulso y tu saturación de oxígeno.',
        'No te autoatiendas ni uses el botiquín: avisa a tierra ahora.',
    ],

    'escalar_si_emergencia' => ['Ya es una situación de emergencia: avisa a tierra ahora.'],

    // Siempre se añade, para que cualquier orientación diga cuándo escalar.
    'escalar_si_general' => [
        'Si aparece dolor o presión en el pecho, dificultad para respirar, desmayo, confusión, debilidad de un lado del cuerpo, pérdida de visión, hemorragia importante o convulsión, avisa a tierra ahora.',
    ],

    // Supuesto del proyecto: síntomas en varias regiones a la vez con mareo suben a 'urgente'.
    'multiples_regiones' => [
        'sintoma_clave' => 'mareo',
        'min_regiones' => 2,
        'urgencia_minima' => 'urgente',
    ],

    // Supuesto del proyecto: indicador relacionado en crítico -> al menos 'urgente'; en precaución -> al menos 'autocuidado'.
    'urgencia_por_indicador' => ['crit' => 'urgente', 'warn' => 'autocuidado'],

    // Supuesto del proyecto: un antecedente o condición relacionada sube la urgencia un nivel (tope 'urgente').
    'antecedente_sube' => 1,
    'tope_por_ajustes' => 'urgente',

    // Supuesto del proyecto: ventana (días) en que una EVA o actividad crítica próxima se señala.
    'eventos' => [
        'palabras' => ['eva', 'extravehicular', 'acoplamiento', 'maniobra', 'reentrada', 'aterrizaje'],
        'ventana_dias' => 7,
    ],

    // Supuestos del proyecto para la confianza simulada (determinista).
    'confianza' => [
        'base' => 20,
        'factor_sintomas' => 0.6,   // x promedio de confianza del analizador
        'antecedente' => 8,
        'indicador_alterado' => 6,
        'caso_relacionado' => 4,
        'ejercicio_intenso' => -20, // pulso alto con ejercicio intenso reciente: baja la causa patológica
        'sin_ficha' => -15,
        'min' => 5,
        'max' => 90,
        'indicador' => 60,
    ],

    // Indicador del contexto que corrobora cada síntoma (clave de config/panel.php).
    'indicadores_relacionados' => [
        'palpitaciones' => ['frecuencia_cardiaca'],
        'falta_aire' => ['saturacion_o2', 'frecuencia_cardiaca'],
        'fiebre' => ['temperatura'],
        'escalofrios' => ['temperatura'],
        'fatiga' => ['sueno', 'estres_cognitivo'],
        'mareo' => ['presion_arterial', 'glucosa', 'hidratacion'],
        'nauseas' => ['hidratacion'],
        'vomito' => ['hidratacion'],
        'dolor_cabeza' => ['estres_cognitivo', 'presion_arterial'],
        'presion_pecho' => ['frecuencia_cardiaca', 'saturacion_o2'],
        'dolor' => [],
    ],

    // Caso detectado (patrón) que se cita como respaldo de cada síntoma.
    'casos_relacionados' => [
        'palpitaciones' => ['arritmia'],
        'falta_aire' => ['hipoxia'],
        'fiebre' => ['fiebre'],
        'escalofrios' => ['fiebre'],
        'fatiga' => ['falta_sueno', 'estres'],
        'mareo' => ['deshidratacion', 'hipoxia'],
        'nauseas' => ['deshidratacion'],
    ],

    // Palabras (sin tildes) que hacen relacionado un antecedente o condición del expediente con un síntoma.
    'antecedentes_palabras' => [
        'palpitaciones' => ['cardi', 'arritm', 'hipertens', 'corazon', 'taquicard'],
        'presion_pecho' => ['cardi', 'arritm', 'hipertens', 'corazon', 'coronar', 'infarto'],
        'dolor' => ['artr', 'lumbar', 'hernia', 'columna', 'muscul', 'tendin', 'lesion'],
        'falta_aire' => ['asma', 'epoc', 'pulmon', 'respirat', 'bronqu', 'apnea'],
        'mareo' => ['hipotens', 'vertigo', 'vestibul', 'anemia', 'diabet', 'migran'],
        'nauseas' => ['gastr', 'ulcer', 'reflujo', 'migran'],
        'vomito' => ['gastr', 'ulcer', 'reflujo'],
        'fiebre' => ['infecc', 'inmun'],
        'escalofrios' => ['infecc', 'inmun'],
        'fatiga' => ['anemia', 'tiroid', 'depres', 'apnea', 'insomnio'],
        'dolor_cabeza' => ['migran', 'cefale', 'hipertens'],
    ],

    // Palabras del texto del tripulante que lo ponen "en alcance" cuando se refiere a un indicador (sin síntomas).
    'palabras_indicador' => [
        'glucosa' => ['glucosa', 'azucar'],
        'frecuencia_cardiaca' => ['pulso', 'frecuencia cardiaca', 'latidos'],
        'saturacion_o2' => ['saturacion', 'oxigeno', 'spo2'],
        'temperatura' => ['temperatura'],
        'presion_arterial' => ['presion arterial', 'tension'],
        'hidratacion' => ['hidratacion', 'deshidrat'],
        'sueno' => ['sueno', 'dormi'],
        'estres_cognitivo' => ['estres', 'carga cognitiva'],
    ],

    'mensajes' => [
        'fuera_alcance' => 'Solo oriento sobre síntomas e indicadores de salud. Cuéntame cómo te sientes o qué indicador te preocupa, y te ayudo.',
        'sin_ficha' => 'Sin ficha verificable para este síntoma: Pendiente de fuente verificable. Confírmalo con el médico de vuelo.',
    ],

    'origen_ejemplo' => 'Ejemplo simulado (sin validar)',
];
