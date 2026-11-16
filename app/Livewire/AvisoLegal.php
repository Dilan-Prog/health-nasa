<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** Aviso legal: prototipo educativo, valores de ejemplo, no sustituye a un profesional médico. */
#[Layout('layouts.app')]
class AvisoLegal extends Component
{
    public const FUENTES = [
        ['NASA-STD-3001 Vol. 1 Rev. C', 'Crew health and performance standard (NASA).'],
        ['NASA HRP', 'Human Research Program: research findings, for context only.'],
        ['CSA', 'Canadian Space Agency: reference material.'],
    ];

    public function render()
    {
        return view('livewire.aviso-legal', ['fuentes' => self::FUENTES]);
    }
}
