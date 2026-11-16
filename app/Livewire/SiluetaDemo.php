<?php

namespace App\Livewire;

use App\Support\MusculosCuerpo;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Ejemplo de uso de <x-silueta-corporal> con datos fijos. La conexión con las reglas de salud va después. */
#[Layout('layouts.app')]
class SiluetaDemo extends Component
{
    /** Conjuntos de ejemplo: cambiar de uno a otro prueba la actualización en vivo (transición de 150 ms). */
    private const EJEMPLOS = [
        'a' => ['pectoral_mayor_izq', 'pectoral_clavicular_izq', 'deltoides_anterior_izq', 'biceps_izq', 'recto_femoral_der', 'vasto_lateral_der'],
        'b' => ['trapecio_sup_der', 'trapecio_sup_izq', 'recto_abdominal_2_der', 'recto_abdominal_2_izq', 'gemelo_medial_izq', 'tibial_anterior_izq'],
    ];

    public string $ejemplo = 'a';

    public ?string $seleccionado = null;

    #[On('musculo-seleccionado')]
    public function seleccionar(string $id): void
    {
        $this->seleccionado = $id;
    }

    public function render()
    {
        return view('livewire.silueta-demo', [
            'activos' => self::EJEMPLOS[$this->ejemplo] ?? [],
            'nombreSeleccionado' => $this->seleccionado ? MusculosCuerpo::nombreEn($this->seleccionado) : null,
        ]);
    }
}
