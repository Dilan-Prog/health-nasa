<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Aviso legal: prototipo educativo, valores de ejemplo, no sustituye a un profesional médico. */
#[Layout('layouts.app')]
class AvisoLegal extends Component
{
    public const FUENTES = [
        ['NASA-STD-3001 Vol. 1 Rev. C', 'Norma de salud y desempeño de la tripulación (NASA).'],
        ['NASA HRP', 'Human Research Program: observaciones de investigación, solo como contexto.'],
        ['CSA', 'Agencia Espacial Canadiense: material de referencia.'],
    ];

    public function render()
    {
        return view('livewire.aviso-legal', ['fuentes' => self::FUENTES]);
    }
}
