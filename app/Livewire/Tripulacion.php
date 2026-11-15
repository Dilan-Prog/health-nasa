<?php

namespace App\Livewire;

use App\Services\Salud\ResumenTripulante;
use App\Support\MusculosCuerpo;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/** Pantalla Tripulación: una tarjeta por tripulante de la misión actual; al abrirla se pasa al Panel de esa persona. */
#[Layout('layouts.app')]
class Tripulacion extends Component
{
    /** Vista de la miniatura por tripulante: [astronauta_id => frontal|lateral|lateral_izq|posterior]. */
    public array $vistas = [];

    /** Fija el tripulante y navega al Panel. */
    public function abrir(int $id): void
    {
        if (! TripulanteActual::tripulacion()->contains('id', $id)) {
            return;
        }
        TripulanteActual::fijar($id);
        $this->dispatch('tripulante-cambiado');
        $this->redirect(route('panel'), navigate: false);
    }

    /** Cambia solo la vista de la miniatura; no cambia de tripulante. */
    public function cambiarVista(int $id, string $vista): void
    {
        if (array_key_exists($vista, MusculosCuerpo::VISTAS) && TripulanteActual::tripulacion()->contains('id', $id)) {
            $this->vistas[$id] = $vista;
        }
    }

    #[On('tripulante-cambiado')]
    #[On('medicion-guardada')]
    #[On('medicion-registrada')]
    public function refrescar(): void
    {
        // Basta con volver a renderizar.
    }

    public function render()
    {
        $tripulacion = TripulanteActual::tripulacion();

        return view('livewire.tripulacion', [
            'mision' => TripulanteActual::mision(),
            'tripulacion' => $tripulacion,
            'resumenes' => ResumenTripulante::make()->deVarios($tripulacion),
        ]);
    }
}
