<?php

namespace App\Livewire;

use App\Services\Salud\DatosIndicador;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pantalla Sistemas corporales (vista 04 del diseño "Panel tripulación"): una tarjeta por sistema (config/sistemas.php)
 * coloreada por el estado más comprometido de sus indicadores. Cada fila enlaza a /indicador?id=<marcador>.
 * Los valores y estados son el último dato real del tripulante (DatosIndicador). Orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class Sistemas extends Component
{
    /** Estado => [texto, icono, gravedad (menor = peor)]. */
    public const ESTADOS = ['ok' => ['NORMAL', 'check_circle', 2], 'warn' => ['PRECAUCIÓN', 'warning', 1], 'crit' => ['CRÍTICO', 'error', 0]];

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        // El siguiente render recalcula los valores del nuevo tripulante.
    }

    public function render()
    {
        $a = TripulanteActual::obtener();

        return view('livewire.sistemas', [
            'astronauta' => $a,
            'tarjetas' => $a ? $this->tarjetas($a) : [],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function tarjetas($a): array
    {
        $vivos = [];
        try {
            foreach (DatosIndicador::para($a)['marcadores'] as $m) {
                $vivos[$m['id']] = $m;
            }
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $out = [];
        foreach (config('sistemas.grupos', []) as $i => $g) {
            $filas = [];
            $peor = null;
            foreach ($g['marcadores'] as $clave) {
                $m = $vivos[$clave] ?? null;
                if (! $m) {
                    continue;
                }
                $filas[] = [
                    'clave' => $clave, 'label' => $m['label'], 'valor' => $m['disp'], 'unidad' => $m['unit'], 'estado' => $m['estado'],
                    'estado_txt' => self::ESTADOS[$m['estado']][0], 'estado_ic' => self::ESTADOS[$m['estado']][1],
                    'url' => route('indicador', ['id' => $clave]),
                ];
                if ($peor === null || self::ESTADOS[$m['estado']][2] < self::ESTADOS[$peor][2]) {
                    $peor = $m['estado'];
                }
            }
            $out[] = [
                'indice' => $i, 'titulo' => $g['titulo'], 'ic' => $g['ic'], 'filas' => $filas,
                'estado' => $peor ?? 'ok',
                'estado_txt' => self::ESTADOS[$peor ?? 'ok'][0], 'estado_ic' => self::ESTADOS[$peor ?? 'ok'][1],
            ];
        }

        return $out;
    }
}
