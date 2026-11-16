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
        ['nombre' => 'VO₂max and aerobic capacity', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Muscle strength (isometric and dynamic)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Bone mineral density (hip, spine, femoral neck)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        // Fila "Neuro-ocular (SANS)" del documento: PIO y capa de fibras nerviosas de la retina.
        ['nombre' => 'Retinal thickness and intraocular pressure', 'sistema' => 'ocular', 'fuente' => $dm],
        // Sin sistema propio: se asocia al hueso (metabolismo del calcio); es decisión del proyecto.
        ['nombre' => 'Vitamin D', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Urinary calcium and electrolytes', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        // Hormona de estrés: grupo de salud mental (el documento no la asigna a un sistema concreto).
        ['nombre' => 'Salivary cortisol', 'sistema' => 'salud_mental', 'fuente' => $dm],
        // Test de vigilancia (lapsos de reacción): relacionado con sueño y fatiga.
        ['nombre' => 'PVT (reaction lapses)', 'sistema' => 'sueno', 'fuente' => $dm],
        ['nombre' => 'Caloric intake', 'sistema' => 'metabolico', 'fuente' => $dm],
        // Atmósfera de cabina: el sistema afectado es el respiratorio (el documento los agrupa con radiación y cabina).
        ['nombre' => 'Cabin pCO₂, pO₂ and humidity', 'sistema' => 'respiratorio', 'fuente' => $dm],
        ['nombre' => 'ECG and jugular flow', 'sistema' => 'cardiovascular', 'fuente' => $dm],
        // Telemetría de máquinas de ejercicio: fila musculoesquelética del documento.
        ['nombre' => 'ARED, T2 and CEVIS telemetry', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => 'Per-organ radiation and solar event projection', 'sistema' => 'radiacion', 'fuente' => 'docs/fuentes/variacion_dosis_efectiva.txt'],
        // Módulo de Intervención y Contramedidas (ejercicio): se asocia al sistema que protege.
        ['nombre' => 'Dynamic exercise prescription', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
        ['nombre' => '45-day post-flight (rehabilitation)', 'sistema' => 'musculoesqueletico', 'fuente' => $dm],
    ],

    // Plataforma: piezas de infraestructura (no son biomarcadores, por eso no llevan `sistema` ni `fuente`).
    // Solo se muestran en la Guía como "Próximamente"; sin fechas comprometidas.
    'plataforma' => [
        ['nombre' => 'Local AI with Ollama', 'descripcion' => 'Coming soon: a language model that runs on the device itself, without sending data out. Today the Flight Surgeon uses simulated responses.', 'estado' => 'proximamente'],
        ['nombre' => 'Orchestration with n8n', 'descripcion' => 'Coming soon: automated workflows that coordinate alerts and tasks across services. Today no automation is connected.', 'estado' => 'proximamente'],
    ],
];
