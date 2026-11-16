<?php

namespace App\Livewire;

use App\Models\BotiquinItem;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Botiquín informativo. Lista por grupo; stock y caducidad editables en línea (valores de ejemplo).
 * Sin dosis, sin indicaciones de uso y sin sugerencias automáticas.
 */
#[Layout('layouts.app')]
class Botiquin extends Component
{
    public ?int $editando = null;

    public string $stock = '';

    public string $caducidad = '';

    public function editar(int $id): void
    {
        $item = BotiquinItem::findOrFail($id);
        $this->resetValidation();
        $this->editando = $item->id;
        $this->stock = (string) $item->stock;
        $this->caducidad = $item->caducidad?->format('Y-m-d') ?? '';
    }

    public function cancelar(): void
    {
        $this->resetValidation();
        $this->reset('editando', 'stock', 'caducidad');
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'caducidad' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
        ]);

        $item = BotiquinItem::findOrFail($this->editando);
        $item->update([
            'stock' => (int) $datos['stock'],
            'caducidad' => $datos['caducidad'] ?: null,
            'valor_ejemplo' => false,
        ]);
        $this->cancelar();
    }

    public function render()
    {
        $grupos = [];
        foreach (array_keys(BotiquinItem::GRUPOS) as $g) {
            $grupos[$g] = [];
        }
        foreach (BotiquinItem::orderBy('id')->get() as $item) {
            $grupos[$item->grupo][] = $item;
        }

        return view('livewire.botiquin', ['grupos' => $grupos]);
    }
}
