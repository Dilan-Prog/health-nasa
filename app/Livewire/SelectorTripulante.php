<?php

namespace App\Livewire;

use App\Models\Evaluacion;
use App\Models\Medicion;
use App\Support\TripulanteActual;
use Livewire\Attributes\On;
use Livewire\Component;

/** Selector de tripulante de la barra lateral. `variante` = 'lateral' (desplegable) | 'compacta' (botón redondo que rota). */
class SelectorTripulante extends Component
{
    public string $variante = 'lateral';
    public bool $abierto = false;
    public ?int $actualId = null;

    public function mount(string $variante = 'lateral'): void
    {
        $this->variante = $variante;
        $this->actualId = TripulanteActual::obtener()?->id;
    }

    public function alternar(): void
    {
        $this->abierto = ! $this->abierto;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function elegir(int $id): void
    {
        TripulanteActual::fijar($id);
        $this->actualId = TripulanteActual::obtener()?->id;
        $this->abierto = false;
        $this->dispatch('tripulante-cambiado');
    }

    /** Pasa al siguiente tripulante (en círculo); versión estrecha. */
    public function siguiente(): void
    {
        $ids = TripulanteActual::tripulacion()->pluck('id')->values();
        if ($ids->count() < 2) {
            return;
        }
        $pos = $ids->search($this->actualId);
        $this->elegir((int) $ids[(($pos === false ? -1 : $pos) + 1) % $ids->count()]);
    }

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        $this->actualId = TripulanteActual::obtener()?->id;
    }

    /** @return array<int, array{medido: bool, hora: ?string, peor: ?string}> por id de astronauta */
    private function estados($tripulacion): array
    {
        $ids = $tripulacion->pluck('id')->all();
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['medido' => false, 'hora' => null, 'peor' => null];
        }
        if (! $ids) {
            return $out;
        }

        // TODO: cambiar por App\Services\Salud\ResumenTripulante::make()->de($a) cuando el bloque B lo entregue.
        if (class_exists(\App\Services\Salud\ResumenTripulante::class)) {
            foreach ($tripulacion as $a) {
                try {
                    $r = \App\Services\Salud\ResumenTripulante::make()->de($a);
                    $out[$a->id] = [
                        'medido' => (bool) ($r['medido_hoy'] ?? false),
                        'hora' => ($r['ultima_medicion'] ?? null)?->utc()->format('H:i'),
                        'peor' => $r['peor'] ?? null,
                    ];
                } catch (\Throwable) {
                    // se deja el estado vacío
                }
            }

            return $out;
        }

        $inicioHoy = now('UTC')->startOfDay();
        $ultimas = Medicion::query()->whereIn('astronauta_id', $ids)->where('medido_en', '>=', $inicioHoy)
            ->selectRaw('astronauta_id, max(medido_en) as ultima')->groupBy('astronauta_id')->pluck('ultima', 'astronauta_id');
        foreach ($ultimas as $id => $ultima) {
            $out[$id]['medido'] = true;
            $out[$id]['hora'] = \Illuminate\Support\Carbon::parse($ultima, 'UTC')->format('H:i');
        }
        $mapa = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];
        $orden = ['ok' => 0, 'warn' => 1, 'crit' => 2];
        $filas = Evaluacion::query()->join('mediciones', 'mediciones.id', '=', 'evaluaciones.medicion_id')
            ->whereIn('mediciones.astronauta_id', $ids)->orderBy('mediciones.medido_en')
            ->get(['mediciones.astronauta_id as aid', 'mediciones.indicador as ind', 'evaluaciones.estado as estado']);
        $ultimoPorInd = [];
        foreach ($filas as $f) {
            $ultimoPorInd[$f->aid][$f->ind] = $mapa[$f->estado] ?? null;
        }
        foreach ($ultimoPorInd as $aid => $inds) {
            $peor = null;
            foreach ($inds as $e) {
                if ($e !== null && ($peor === null || $orden[$e] > $orden[$peor])) {
                    $peor = $e;
                }
            }
            $out[$aid]['peor'] = $peor;
        }

        return $out;
    }

    public function render()
    {
        $tripulacion = TripulanteActual::tripulacion();
        $actual = $tripulacion->firstWhere('id', $this->actualId) ?? $tripulacion->first();
        $pos = $actual ? $tripulacion->search(fn ($a) => $a->id === $actual->id) + 1 : 0;

        return view('livewire.selector-tripulante', [
            'tripulacion' => $tripulacion,
            'actual' => $actual,
            'pos' => $pos,
            'total' => $tripulacion->count(),
            'estados' => $this->estados($tripulacion),
        ]);
    }
}
