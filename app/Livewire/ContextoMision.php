<?php

namespace App\Livewire;

use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\EnvioATierra;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Pantalla Contexto de misión (vista 09 del diseño "Panel tripulación"): día de misión, entorno, órbita, enlace con
 * tierra y retardo, tripulación, línea de tiempo, dosis acumulada frente al límite de misión y próximos eventos.
 * Solo orienta: no diagnostica. Los datos salen de Mision, config/umbrales.php (retardo), config/mision.php y la última
 * medición de dosis del tripulante actual.
 */
#[Layout('layouts.app')]
class ContextoMision extends Component
{
    public int $careLevel = 3;

    public string $missionPhase = 'vuelo';

    public ?string $guardado = null;

    public function mount(): void
    {
        if ($m = TripulanteActual::mision()) {
            $this->careLevel = $m->nivelAtencion();
            $this->missionPhase = $m->mission_phase ?: 'vuelo';
        }
    }

    public function updatedCareLevel(): void
    {
        $this->guardar();
    }

    public function updatedMissionPhase(): void
    {
        $this->guardar();
    }

    /** Guarda nivel de atención y fase en la misión actual. Es una elección explícita: no se recalcula por el entorno. */
    public function guardar(): void
    {
        $this->guardado = null;
        $this->validate([
            'careLevel' => ['required', 'integer', Rule::in(array_keys(Mision::CARE_LEVELS))],
            'missionPhase' => ['required', Rule::in(array_keys(Mision::FASES))],
        ], [], ['careLevel' => 'nivel de atención', 'missionPhase' => 'fase de la misión']);

        $mision = TripulanteActual::mision();
        if (! $mision) {
            return;
        }
        $mision->update(['care_level' => $this->careLevel, 'mission_phase' => $this->missionPhase]);
        $this->guardado = 'Guardado: '.$mision->etiquetaCareLevel().' · '.Mision::FASES[$this->missionPhase].'.';
    }

    public function render()
    {
        $mision = TripulanteActual::mision();
        $astronauta = TripulanteActual::obtener();
        $datos = $mision ? $this->datos($mision, $astronauta) : null;

        return view('livewire.contexto-mision', ['mision' => $mision, 'astronauta' => $astronauta, 'd' => $datos]);
    }

    private function datos(Mision $mision, $astronauta): array
    {
        $total = $mision->duracion_dias ? (int) $mision->duracion_dias : null;
        $dia = null;
        if ($mision->inicio_en) {
            $dia = max(1, (int) $mision->inicio_en->copy()->startOfDay()->diffInDays(now('UTC')->startOfDay(), false) + 1);
            if ($total) {
                $dia = min($dia, $total);
            }
        }

        $entorno = $mision->entorno ?: 'estacion';
        $entornoTxt = config('mision.entornos.'.$entorno, ucfirst(str_replace('_', ' ', $entorno)));
        $retardo = config('umbrales.retardo_min.'.$entorno);
        $enlace = app(EnvioATierra::class)->enlaceActivo();
        $tripulacion = TripulanteActual::tripulacion();

        // Línea de tiempo (solo con duración conocida).
        $fases = [];
        if ($total && $total > 1) {
            foreach (config('mision.fases', []) as $f) {
                $d = min($total, max(1, (int) ($f['dia'] ?? $total)));
                $fases[] = ['nombre' => $f['nombre'], 'dia' => $d, 'pct' => round(($d - 1) / ($total - 1) * 100, 2), 'hecha' => $dia !== null && $d <= $dia];
            }
        }
        $avance = ($total && $total > 1 && $dia) ? round(($dia - 1) / ($total - 1) * 100, 2) : 0;

        // Dosis acumulada del tripulante actual frente al límite de misión.
        $limite = (float) config('mision.limite_dosis_msv', 250);
        $dosis = null;
        if ($astronauta) {
            $m = Medicion::where('astronauta_id', $astronauta->id)->where('indicador', 'dosis_acumulada')
                ->orderByDesc('medido_en')->orderByDesc('id')->first();
            $v = $m ? ($m->valores['valor'] ?? null) : null;
            if (is_numeric($v)) {
                $v = (float) $v;
                $c = config('umbrales.indicadores.dosis_acumulada.componentes.valor');
                $estado = $v <= $c['normal'][1] ? 'ok' : ($v <= $c['precaucion'][1] ? 'warn' : 'crit');
                $dosis = [
                    'valor' => $v,
                    'txt' => number_format($v, 1, '.', ''),
                    'pct' => $limite > 0 ? round(min(100, $v / $limite * 100), 1) : 0,
                    'estado' => $estado,
                ];
            }
        }

        $eventos = collect(config('mision.eventos', []))
            ->filter(fn ($e) => $dia === null || $e['dia'] >= $dia)
            ->sortBy('dia')->values()->all();

        return [
            'nombre' => $mision->nombre,
            'entorno' => $entorno,
            'entorno_txt' => $entornoTxt,
            'dia' => $dia,
            'total' => $total,
            'quedan' => ($dia && $total) ? max(0, $total - $dia) : null,
            'es_estacion' => $entorno === 'estacion',
            'orbita_km' => config('panel.orbita_km'),
            'velocidad' => config('mision.orbita.velocidad_kms'),
            'orbitas_dia' => config('mision.orbita.orbitas_dia'),
            'retardo' => $retardo,
            'enlace' => $enlace,
            'n_tripulantes' => $tripulacion->count(),
            'nombre_corto' => $astronauta ? mb_strtoupper(explode(' ', trim($astronauta->nombre))[0]) : null,
            'fases' => $fases,
            'avance' => $avance,
            'dosis' => $dosis,
            'limite' => $limite,
            'eventos' => $eventos,
        ];
    }
}
