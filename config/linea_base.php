<?php

/*
 | Línea base pre-vuelo del tripulante (NASA-STD-3001 Vol. 1, Rev. C: comparar contra el propio valor de partida).
 | NADA de esto está validado clínicamente: las unidades, ventanas y umbrales son VALORES DE EJEMPLO.
 */

return [
    /** Métricas capturables (las mismas de App\Models\LineaBase::METRICAS). `unidad` es solo la sugerencia por defecto. */
    'metricas' => [
        'vo2max' => ['label' => 'VO₂max', 'unidad' => 'mL/kg/min'],
        'dmo_cadera' => ['label' => 'Bone mineral density (hip)', 'unidad' => 'g/cm²'],
        'dmo_columna' => ['label' => 'Bone mineral density (spine)', 'unidad' => 'g/cm²'],
        'dmo_cuello_femoral' => ['label' => 'Bone mineral density (femoral neck)', 'unidad' => 'g/cm²'],
        'grosor_retina_um' => ['label' => 'Retinal thickness', 'unidad' => 'µm'],
        'kcal_objetivo' => ['label' => 'Target energy', 'unidad' => 'kcal/day'],
        'temperatura_basal' => ['label' => 'Baseline temperature', 'unidad' => '°C'],
        'fuerza' => ['label' => 'Strength', 'unidad' => 'N'],
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
        'dmo_80' => ['metricas' => ['dmo_cadera', 'dmo_columna', 'dmo_cuello_femoral'], 'tipo' => 'pct_minimo', 'umbral' => 80, 'texto' => 'Bone mineral density below 80 % of baseline'],
        'kcal_95' => ['metricas' => ['kcal_objetivo'], 'tipo' => 'pct_minimo', 'umbral' => 95, 'texto' => 'Energy below 95 % of baseline target'],
        'retina_20um' => ['metricas' => ['grosor_retina_um'], 'tipo' => 'delta_maximo', 'umbral' => 20, 'texto' => 'Retinal thickness 20 µm or more above baseline'],
    ],
];
