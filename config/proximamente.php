<?php

/*
 | HOJA DE RUTA (bloque 3B): biomarcadores que el documento de arquitectura menciona y que el proyecto NO mide todavía.
 | Solo se muestran como "Próximamente" (Guía y Sistemas). No tienen campos, lógica, umbrales ni enlaces.
 |
 |  nombre   Texto mostrado.
 |  sistema  `clave` de un grupo de config/sistemas.php (la tarjeta de Sistemas donde se lista).
 |  fuente   Archivo de docs/fuentes que lo menciona (sin cifras: nada clínico validado).
 | El mapeo a sistema es una decisión del proyecto, no de la norma; se justifica en cada comentario.
 */

$dm = 'docs/fuentes/datos_medicos.txt';

return [
    'items' => [
        // Aparece junto a DMO y fuerza en la fila "Musculoesquelético" del documento.
        ['nombre' => 'VO₂máx y capacidad aeróbica', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Fuerza muscular (isométrica y dinámica)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Densidad mineral ósea (cadera, columna, cuello femoral)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        // Fila "Neuro-ocular (SANS)" del documento: PIO y capa de fibras nerviosas de la retina.
        ['nombre' => 'Grosor de retina y presión intraocular', 'sistema' => 'ocular', 'fuente' => $dm],
        // Sin sistema propio: se asocia al hueso (metabolismo del calcio); es decisión del proyecto.
        ['nombre' => 'Vitamina D', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Calcio urinario y electrolitos', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        // Hormona de estrés: grupo de salud mental (el documento no la asigna a un sistema concreto).
        ['nombre' => 'Cortisol salival', 'sistema' => 'salud_mental', 'fuente' => $dm],
        // Test de vigilancia (lapsos de reacción): relacionado con sueño y fatiga.
        ['nombre' => 'PVT (lapsos de reacción)', 'sistema' => 'sueno', 'fuente' => $dm],
        ['nombre' => 'Ingesta calórica', 'sistema' => 'metabolico', 'fuente' => $dm],
        // Atmósfera de cabina: el sistema afectado es el respiratorio (el documento los agrupa con radiación y cabina).
        ['nombre' => 'pCO₂, pO₂ y humedad de cabina', 'sistema' => 'respiratorio', 'fuente' => $dm],
        ['nombre' => 'ECG y flujo yugular', 'sistema' => 'cardiovascular', 'fuente' => $dm],
        // Telemetría de máquinas de ejercicio: fila musculoesquelética del documento.
        ['nombre' => 'Telemetría de ARED, T2 y CEVIS', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Radiación por órgano y proyección de evento solar', 'sistema' => 'radiacion', 'fuente' => 'docs/fuentes/variacion_dosis_efectiva.txt'],
        // Módulo de Intervención y Contramedidas (ejercicio): se asocia al sistema que protege.
        ['nombre' => 'Prescripción dinámica de ejercicio', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Posvuelo de 45 días (rehabilitación)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
    ],

    // Plataforma: piezas de infraestructura (no son biomarcadores, por eso no llevan `sistema` ni `fuente`).
    // Solo se muestran en la Guía como "Próximamente"; sin fechas comprometidas.
    'plataforma' => [
        ['nombre' => 'IA local con Ollama', 'descripcion' => 'Próximamente: un modelo de lenguaje que se ejecute en el propio equipo, sin enviar datos fuera. Hoy el médico de vuelo usa respuestas simuladas.', 'estado' => 'proximamente'],
        ['nombre' => 'Orquestación con n8n', 'descripcion' => 'Próximamente: flujos automáticos que coordinen alertas y tareas entre servicios. Hoy no hay ninguna automatización conectada.', 'estado' => 'proximamente'],
    ],
];
