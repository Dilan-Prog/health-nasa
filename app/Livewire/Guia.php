<?php

namespace App\Livewire;

use App\Models\Medicion;
use App\Services\Salud\AnalisisRegistro;
use App\Services\Salud\DatosIndicador;
use App\Services\Salud\ResolvedorRequisito;
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
            'extras' => $this->extras($a),
            'proximamente' => Sistemas::proximamente(),
            'plataforma' => config('proximamente.plataforma', []),
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
                'fuentes' => $this->fuentes($clave), 'contexto' => $g['contexto'] ?? null,
                'valor' => $v ? $v['disp'] : null, 'unidad' => $c['unit'],
                'estado' => $v ? $v['estado'] : null,
                'estado_txt' => $v ? self::ESTADOS[$v['estado']] : null,
                'busca' => mb_strtolower($c['label'].' '.$g['parte'].' '.$g['texto']),
            ];
        }

        return $out;
    }

    /**
     * Fuente del indicador (config/trazabilidad.php) y, si es una proyección HRP, la referencia normativa real.
     * Si la tabla falta o está vacía devuelve [] y la tarjeta muestra «sin requisito asociado».
     *
     * @return array<int, array<string, mixed>>
     */
    private function fuentes(string $clave): array
    {
        try {
            return array_values(array_filter([
                ResolvedorRequisito::fuente(ResolvedorRequisito::para($clave)),
                ResolvedorRequisito::fuente(ResolvedorRequisito::referenciaDe($clave)),
            ]));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Tarjetas extra fuera del catálogo de 14 marcadores (config/guia.php › extras), p. ej. el ánimo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extras($a): array
    {
        $out = [];
        foreach (config('guia.extras', []) as $clave => $e) {
            $u = config('umbrales.indicadores.'.($e['umbral'] ?? $clave).'.componentes.valor', []);
            $unidad = (string) config('umbrales.indicadores.'.($e['umbral'] ?? $clave).'.unidad', '');
            $rango = isset($u['normal']) ? 'MÍN '.$u['normal'][0].' · MÁX '.$u['normal'][1].' '.$unidad : $unidad;

            $valor = $estado = null;
            if ($a) {
                try {
                    $m = Medicion::where('astronauta_id', $a->id)->where('indicador', $e['umbral'] ?? $clave)->latest('medido_en')->first();
                    $n = $m?->valores['valor'] ?? null;
                    if (is_numeric($n)) {
                        $valor = number_format((float) $n, 0, '.', '');
                        $estado = (new AnalisisRegistro)->evaluar($e['umbral'] ?? $clave, ['valor' => (float) $n])['estado'];
                    }
                } catch (\Throwable $x) {
                    report($x);
                }
            }

            $out[] = [
                'clave' => $clave, 'label' => $e['label'], 'ic' => $e['ic'], 'parte' => $e['parte'], 'rango' => $rango,
                'texto' => $e['texto'], 'rec' => $e['rec'],
                'fuentes' => $this->fuentes($clave), 'contexto' => null,
                'valor' => $valor, 'unidad' => $unidad,
                'estado' => $estado, 'estado_txt' => $estado ? self::ESTADOS[$estado] : null,
                'busca' => mb_strtolower($e['label'].' '.$e['parte'].' '.$e['texto']),
            ];
        }

        return $out;
    }
}
