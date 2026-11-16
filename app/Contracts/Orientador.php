<?php

namespace App\Contracts;

use App\Models\CasoClinico;
use App\Services\Ia\Orientacion;

/** Única pieza que cambiará cuando exista la IA real (Ollama + n8n): ver config/ia.php. */
interface Orientador
{
    public function orientar(CasoClinico $caso): Orientacion;
}
