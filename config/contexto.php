<?php

/*
 | Parámetros de App\Services\Ia\ContextoTripulante (instantánea de solo lectura del tripulante para el orientador).
 | TODOS son SUPUESTOS DEL PROYECTO, no de la norma: solo deciden cuánto contexto se carga, no un criterio clínico.
 */
return [
    // Segundos que se cachea la sección de indicadores (datos derivados, sin expediente). 0 = sin caché.
    // La clave incluye el tripulante, la última medición y el día, así que una medición nueva la invalida sola.
    'cache_s' => (int) env('CONTEXTO_CACHE_S', 15),

    // Días hacia atrás de casos cerrados que aún se consideran "recientes" (los abiertos entran siempre).
    'casos_recientes_dias' => 3,

    // Días hacia atrás de síntomas registrados y de zonas de dolor.
    'sintomas_dias' => 7,
    'dolores_dias' => 7,

    // Máximo de elementos por lista (los más recientes).
    'max_casos' => 10,
    'max_sintomas' => 10,
    'max_dolores' => 10,
    'max_consultas_previas' => 5,
    'max_consultas_tierra' => 5,
    'max_ejecuciones' => 15,
    'max_eventos' => 5,

    // Horas hacia atrás en que un ejercicio hecho cuenta como "ejercicio intenso reciente"
    // (puede explicar un pulso alto). Cuenta una rutina de ejercicio hecha o una actividad manual de ejercicio no "Baja".
    'ejercicio_intenso_horas' => 3,

    // Horas hacia atrás en que se busca el último registro de sueño (misma ventana que el progreso de Rutinas).
    'sueno_horas' => 36,
];
