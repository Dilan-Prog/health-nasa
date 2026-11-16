<?php

/*
 | Línea base pre-vuelo del tripulante (NASA-STD-3001 Vol. 1, Rev. C: comparar contra el propio valor de partida).
 | NADA de esto está validado clínicamente: las unidades, ventanas y umbrales son VALORES DE EJEMPLO.
 */

return [
    /** Métricas capturables (las mismas de App\Models\LineaBase::METRICAS). `unidad` es solo la sugerencia por defecto. */
    'metricas' => [
        'vo2max' => ['label' => 'VO₂ máx', 'unidad' => 'mL/kg/min'],
        'dmo_cadera' => ['label' => 'Densidad mineral ósea (cadera)', 'unidad' => 'g/cm²'],
        'dmo_columna' => ['label' => 'Densidad mineral ósea (columna)', 'unidad' => 'g/cm²'],
        'dmo_cuello_femoral' => ['label' => 'Densidad mineral ósea (cuello femoral)', 'unidad' => 'g/cm²'],
        'grosor_retina_um' => ['label' => 'Grosor de retina', 'unidad' => 'µm'],
        'kcal_objetivo' => ['label' => 'Energía objetivo', 'unidad' => 'kcal/día'],
        'temperatura_basal' => ['label' => 'Temperatura basal', 'unidad' => '°C'],
        'fuerza' => ['label' => 'Fuerza', 'unidad' => 'N'],
    ],

    /** Ventana por defecto, en días ANTES del inicio de la misión (supuesto del proyecto, no de la norma): L-180 a L-30. */
    'ventana' => ['desde_dias' => 180, 'hasta_dias' => 30],

    /** Indicador de /indicador => métrica de línea base con la que se compara su valor actual. */
    'indicador_metrica' => [
        'temperatura' => 'temperatura_basal',
    ],

    /**
     * Si no hay fila en `linea_base`, se usa esta columna de `expedientes_medicos` como valor base (métrica => columna).
     */
    'expediente_campos' => [
        'vo2max' => 'vo2max',
        'temperatura_basal' => 'temperatura_basal',
    ],

    /**
     * Reglas PREPARADAS, SIN DATOS: los biomarcadores y mediciones que las alimentan son del Nivel 3 y aún no se capturan.
     * Evaluadas por DesviacionBase::evaluarRegla() / evaluarReglas() con valores directos.
     *  pct_minimo   -> en alerta si actual < umbral % de la base
     *  delta_maximo -> en alerta si actual - base >= umbral (mismas unidades de la métrica)
     * Umbrales: supuesto del proyecto, no de la norma.
     */
    'reglas' => [
        'dmo_80' => ['metricas' => ['dmo_cadera', 'dmo_columna', 'dmo_cuello_femoral'], 'tipo' => 'pct_minimo', 'umbral' => 80, 'texto' => 'Densidad mineral ósea por debajo del 80 % de la base'],
        'kcal_95' => ['metricas' => ['kcal_objetivo'], 'tipo' => 'pct_minimo', 'umbral' => 95, 'texto' => 'Energía por debajo del 95 % del objetivo base'],
        'retina_20um' => ['metricas' => ['grosor_retina_um'], 'tipo' => 'delta_maximo', 'umbral' => 20, 'texto' => 'Grosor de retina 20 µm o más sobre la base'],
    ],
];
