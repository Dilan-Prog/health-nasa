<?php

namespace App\Services\Ia;

use App\Contracts\Orientador;
use App\Models\CasoClinico;
use RuntimeException;

/** Modo `ia_local` (Ollama + n8n): todavía no existe. No hace ninguna llamada de red. */
class OrientadorProximamente implements Orientador
{
    public function orientar(CasoClinico $caso): Orientacion
    {
        throw new RuntimeException('Próximamente');
    }
}
