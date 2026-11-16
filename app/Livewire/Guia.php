<?php

namespace App\Livewire;

use App\Services\Salud\DatosIndicador;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pantalla Guía educativa (vista 07 del diseño "Panel tripulación"): qué mide cada indicador, por qué cambia en
 * microgravedad y qué hacer. El contenido es divulgativo (config/guia.php + config/indicadores.php); si hay un
 * tripulante seleccionado se añade su último valor y estado. El buscador filtra en el cliente (Alpine).
 * Orienta, no diagnostica ni receta.
 */
#[Layout('layouts.app')]
class Guia extends Component
{
    public const ESTADOS = ['ok' => ['NORMAL', 'check_circle'], 'warn' => ['PRECAUCIÓN', 'warning'], 'crit' => ['CRÍTICO', 'error']];

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        // El siguiente render recalcula los valores del nuevo tripulante.
    }

    public function render()
    {
        $a = TripulanteActual::obtener();

        return view('livewire.guia', [
            'astronauta' => $a,
            'tarjetas' => $this->tarjetas($a),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function tarjetas($a): array
    {
        $vivos = [];
        if ($a) {
            try {
                foreach (DatosIndicador::para($a)['marcadores'] as $m) {
                    $vivos[$m['id']] = $m;
                }
            } catch (\Throwable $e) {
                report($e); // la guía sigue siendo útil sin los valores del tripulante
            }
        }

        $menos = DatosIndicador::MENOS;
        $num = fn ($v, int $dec) => str_replace('-', $menos, number_format((float) $v, $dec, '.', ''));
        $texto = config('guia.marcadores', []);
        $out = [];
        foreach (config('indicadores.marcadores') as $clave => $c) {
            $g = $texto[$clave] ?? ['parte' => '', 'texto' => ''];
            $dec = $c['dec'] ?? 0;
            if ($clave === 'presion_arterial') {
                $rango = 'MÍN 90/60 · MÁX 120/80 '.$c['unit'];
            } elseif (! empty($c['cum'])) {
                $rango = 'MÁX '.$num($c['hi'], $dec).' '.$c['unit'];
            } else {
                $rango = 'MÍN '.$num($c['lo'], $dec).' · MÁX '.$num($c['hi'], $dec).' '.$c['unit'];
            }
            $v = $vivos[$clave] ?? null;
            $out[] = [
                'clave' => $clave, 'label' => $c['label'], 'ic' => $c['ic'], 'parte' => $g['parte'], 'rango' => $rango,
                'texto' => $g['texto'], 'rec' => $c['rec'],
                'valor' => $v ? $v['disp'] : null, 'unidad' => $c['unit'],
                'estado' => $v ? $v['estado'] : null,
                'estado_txt' => $v ? self::ESTADOS[$v['estado']] : null,
                'busca' => mb_strtolower($c['label'].' '.$g['parte'].' '.$g['texto']),
            ];
        }

        return $out;
    }
}
