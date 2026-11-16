<?php

/*
 | Los 5 peligros del vuelo espacial y los 17 riesgos HRP, con la cobertura que les da ESTE sistema.
 |
 | IMPORTANTE: la cobertura es una EVALUACIÓN INTERNA, un valor de ejemplo sin validación médica ni de NASA.
 | Se verificó leyendo config/umbrales.php, config/indicadores.php, config/rutinas.php y App\Services\Salud.
 | Estados: cubierto (hay indicador + umbral + orientación), parcial (hay medición o contexto, pero incompleto),
 | sin (el sistema no mide ni orienta sobre el riesgo).
 |
 | Las claves de los peligros coinciden con 'riesgos' de config/umbrales.php, que cada indicador usa para la matriz.
 */

return [
    'nota' => 'No actúan de forma aislada: pueden combinarse',
    'etiqueta_ejemplo' => 'valor de ejemplo / evaluación interna',

    'peligros' => [
        'radiacion' => ['nombre' => 'Radiación espacial', 'ic' => 'flare'],
        'aislamiento' => ['nombre' => 'Aislamiento y confinamiento', 'ic' => 'groups'],
        'distancia' => ['nombre' => 'Distancia de la Tierra', 'ic' => 'public'],
        'gravedad_alterada' => ['nombre' => 'Campos gravitatorios', 'ic' => 'swap_vert'],
        'entorno_cerrado' => ['nombre' => 'Ambientes cerrados u hostiles', 'ic' => 'home_work'],
    ],

    'estados' => [
        'cubierto' => ['txt' => 'Cubierto', 'ic' => 'check_circle'],
        'parcial' => ['txt' => 'Parcial', 'ic' => 'timelapse'],
        'sin' => ['txt' => 'Sin cobertura', 'ic' => 'do_not_disturb_on'],
    ],

    'grupos' => [
        'fisiologia' => ['nombre' => 'Fisiología', 'riesgos' => [
            ['Respuestas inmunitarias alteradas', 'parcial', 'Solo se vigila la temperatura corporal; no hay marcadores inmunitarios.'],
            ['Función sensoriomotora y vestibular alterada', 'sin', 'No se mide equilibrio ni coordinación.'],
            ['Adaptaciones cardiovasculares', 'cubierto', 'Presión arterial, frecuencia cardíaca y saturación con umbrales de ejemplo.'],
            ['Enfermedad por descompresión', 'sin', 'No se registra presión de cabina ni de traje.'],
            ['Interacciones huésped-microorganismo', 'sin', 'No hay datos de microbioma ni de infecciones.'],
            ['Hipoxia', 'parcial', 'Se mide la saturación de O₂ del tripulante, no la atmósfera del módulo.'],
            ['Alimentación y nutrición inadecuadas', 'parcial', 'IMC, glucosa e hidratación; no se registran calorías ni nutrientes.'],
            ['Lesión y desempeño comprometido en EVA', 'parcial', 'La EVA existe como actividad y evento de misión; sin seguimiento de lesiones.'],
            ['Menor aptitud cardiorrespiratoria y musculoesquelética', 'parcial', 'Se sigue la masa muscular; no hay VO₂máx ni fuerza medidos.'],
            ['Síndrome neuro-ocular (SANS)', 'parcial', 'Se registra la refracción en dioptrías; no el grosor retiniano.'],
            ['Cambios óseos', 'cubierto', 'Pérdida ósea de cadera con umbral de ejemplo y rutina de carga.'],
        ]],
        'conducta' => ['nombre' => 'Conducta', 'riesgos' => [
            ['Cambios conductuales y trastornos psiquiátricos', 'parcial', 'Estado de ánimo y estrés/cognitivo; sin evaluación clínica.'],
            ['Trabajo en equipo inadecuado', 'sin', 'No se mide la dinámica del equipo.'],
            ['Sueño inadecuado y horarios irregulares', 'cubierto', 'Horas de sueño con umbral de ejemplo; sin prueba PVT ni regularidad de horarios.'],
            ['Soporte de operaciones de sistemas inadecuado', 'sin', 'Fuera del alcance de un monitor de salud.'],
            ['Lesión por cargas dinámicas', 'sin', 'No se registran aceleraciones ni cargas de lanzamiento o reentrada.'],
        ]],
        'radiacion' => ['nombre' => 'Radiación', 'riesgos' => [
            ['Cánceres inducidos por radiación', 'parcial', 'Dosis diaria y acumulada frente a un límite de ejemplo; sin dosis por órgano ni riesgo de por vida.'],
        ]],
    ],
];
