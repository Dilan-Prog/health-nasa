<?php

namespace App\Livewire;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Services\Salud\EnvioATierra;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cola a Tierra: avisos salientes (alertas_salientes) con su estado pendiente/enviada. Alertas no lista esta cola
 * (solo muestra el estado del enlace), por eso esta página es nueva y no un enlace.
 */
#[Layout('layouts.app')]
class ColaTierra extends Component
{
    public const LIMITE = 50;

    public function mount(): void
    {
        \App\Services\Auditoria::registrar('ver', 'consultas_tierra');
    }

    public function render()
    {
        $filas = AlertaSaliente::orderByDesc('id')->limit(self::LIMITE)->get();
        $nombres = Astronauta::whereIn('id', $filas->pluck('astronauta_id')->filter()->unique())->pluck('nombre', 'id');

        $avisos = $filas->map(function (AlertaSaliente $f) use ($nombres) {
            $c = is_array($f->carga) ? $f->carga : [];
            $enviada = $f->estado === 'enviada';
            $partes = array_filter([
                $c['indicador'] ?? null,
                $c['valor'] ?? null,
                $c['motivo'] ?? null,
            ], fn ($x) => is_scalar($x) && (string) $x !== '');

            return [
                'id' => $f->id,
                'enviada' => $enviada,
                'estado' => $enviada ? 'Enviada' : 'Pendiente',
                'icono' => $enviada ? 'check_circle' : 'schedule',
                'tripulante' => $nombres[$f->astronauta_id] ?? ($c['astronauta'] ?? 'Sin asignar'),
                'creada' => $f->created_at?->copy()->utc()->format('d/m H:i') ?? '—',
                'enviada_en' => $f->enviada_en?->copy()->utc()->format('d/m H:i'),
                'resumen' => $partes ? implode(' · ', array_map('strval', $partes)) : 'Sin detalle',
            ];
        })->all();

        $envio = app(EnvioATierra::class);

        return view('livewire.cola-tierra', [
            'avisos' => $avisos,
            'pendientes' => $envio->pendientes(),
            'enlace' => $envio->enlaceActivo(),
            'limite' => self::LIMITE,
        ]);
    }
}
