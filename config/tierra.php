<?php

/*
 | Enlace con tierra. Es la ÚNICA conexión externa del sistema.
 | Las alertas se guardan en `alertas_salientes` y esperan hasta que haya enlace.
 */
return [
    // Estado inicial del enlace (se puede alternar en caliente desde el panel).
    'enlace' => env('TIERRA_ENLACE', false),

    // Endpoint del centro de control en tierra. Vacío = modo simulado (se marca como enviada).
    'url' => env('TIERRA_URL'),

    'timeout' => 5,
];
