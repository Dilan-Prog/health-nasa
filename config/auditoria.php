<?php

/*
 | Auditoría de accesos (quién vio/exportó/envió qué). No guarda valores médicos.
 | Los números son SUPUESTOS del proyecto, no de una norma.
 */
return [
    // Ventana de deduplicación: la misma persona, acción y recurso dentro de estos minutos cuenta como un solo registro.
    'ventana_dedup_min' => (int) env('AUDITORIA_VENTANA_MIN', 5),

    // Días que se conservan los registros (supuesto; `auditoria:podar` borra lo anterior).
    'retencion_dias' => (int) env('AUDITORIA_RETENCION_DIAS', 90),

    'por_pagina' => 25,
];
