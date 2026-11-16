<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\AuditoriaAcceso;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Registro de accesos, solo lectura. Con login activo, solo para flight_surgeon. No muestra valores médicos. */
#[Layout('layouts.app')]
class Auditoria extends Component
{
    use WithPagination;

    #[Url(as: 'rol', except: '')]
    public string $rol = '';

    #[Url(as: 'accion', except: '')]
    public string $accion = '';

    #[Url(as: 'recurso', except: '')]
    public string $recurso = '';

    #[Url(as: 'tripulante', except: '')]
    public string $tripulante = '';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        if (config('mision.exigir_login', false)) {
            abort_unless(auth()->user()?->esFlightSurgeon(), 403);
        }
    }

    public function updated($name): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset('rol', 'accion', 'recurso', 'tripulante', 'desde', 'hasta');
        $this->resetPage();
    }

    private function fecha(string $v): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    public function render()
    {
        // Defensa en profundidad: las acciones Livewire posteriores también exigen el rol.
        if (config('mision.exigir_login', false)) {
            abort_unless(auth()->user()?->esFlightSurgeon(), 403);
        }

        $q = AuditoriaAcceso::query()->orderByDesc('ocurrido_en')->orderByDesc('id');
        $q->when($this->rol !== '', fn ($x) => $x->where('rol', $this->rol));
        $q->when($this->accion !== '', fn ($x) => $x->where('accion', $this->accion));
        $q->when($this->recurso !== '', fn ($x) => $x->where('recurso', $this->recurso));
        $q->when($this->tripulante !== '', fn ($x) => $x->where('astronauta_id', (int) $this->tripulante));
        $q->when($this->fecha($this->desde), fn ($x, $d) => $x->where('ocurrido_en', '>=', $d.' 00:00:00'));
        $q->when($this->fecha($this->hasta), fn ($x, $d) => $x->where('ocurrido_en', '<=', $d.' 23:59:59'));

        $registros = $q->paginate((int) config('auditoria.por_pagina', 25));
        $nombres = Astronauta::whereIn('id', $registros->pluck('astronauta_id')->filter()->unique())->pluck('nombre', 'id');

        return view('livewire.auditoria', [
            'registros' => $registros,
            'nombres' => $nombres,
            'roles' => AuditoriaAcceso::query()->distinct()->orderBy('rol')->pluck('rol'),
            'acciones' => AuditoriaAcceso::query()->distinct()->orderBy('accion')->pluck('accion'),
            'recursos' => AuditoriaAcceso::query()->distinct()->orderBy('recurso')->pluck('recurso'),
            'tripulantes' => Astronauta::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }
}
