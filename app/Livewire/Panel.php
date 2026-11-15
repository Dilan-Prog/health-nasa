<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\Estado;
use App\Services\Salud\IndiceSistemaCorporal;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\SimuladorMediciones;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Panel extends Component
{
    /** 0 = astronautas sin misión asignada (datos anteriores a las misiones). */
    public int $misionId = 0;
    public ?int $astronautaId = null;
    public string $indicador = 'frecuencia_cardiaca';
    public string $valor = '';
    public string $sistolica = '';
    public string $diastolica = '';
    public ?string $aviso = null;
    public array $errores = [];

    public function mount(): void
    {
        $this->misionId = (int) (Mision::orderBy('id')->value('id') ?? 0);
        $this->astronautaId = $this->tripulantesQuery()->orderBy('id')->value('id');
    }

    private function tripulantesQuery()
    {
        return Astronauta::query()->when(
            $this->misionId > 0,
            fn ($q) => $q->where('mision_id', $this->misionId),
            fn ($q) => $q->whereNull('mision_id'),
        );
    }

    public function updatedMisionId(): void
    {
        $this->reset('aviso', 'errores');
        $this->astronautaId = $this->tripulantesQuery()->orderBy('id')->value('id');
    }

    public function seleccionar(int $id): void
    {
        if ($this->tripulantesQuery()->whereKey($id)->exists()) {
            $this->astronautaId = $id;
            $this->reset('aviso', 'errores');
        }
    }

    /**
     * Resumen de estado por tripulante a partir de la última evaluación de cada indicador.
     * @return array<int, array{peor: ?string, precaucion: int, critico: int, con_datos: int}>
     */
    private function resumenTripulacion($tripulantes): array
    {
        $ids = $tripulantes->pluck('id');
        $resumen = $ids->mapWithKeys(fn ($id) => [$id => ['peor' => null, 'precaucion' => 0, 'critico' => 0, 'con_datos' => 0]])->all();
        if ($ids->isEmpty()) {
            return $resumen;
        }

        $ultimas = Evaluacion::query()
            ->join('mediciones', 'mediciones.id', '=', 'evaluaciones.medicion_id')
            ->whereIn('mediciones.astronauta_id', $ids)
            ->groupBy('mediciones.astronauta_id', 'mediciones.indicador')
            ->selectRaw('max(evaluaciones.id) as id')->pluck('id');

        $rango = ['normal' => 1, 'precaucion' => 2, 'critico' => 3];
        foreach (Evaluacion::whereIn('id', $ultimas)->with('medicion:id,astronauta_id')->get() as $ev) {
            $r = &$resumen[$ev->medicion->astronauta_id];
            $r['con_datos']++;
            if ($ev->estado !== 'normal') {
                $r[$ev->estado]++;
            }
            if ($r['peor'] === null || $rango[$ev->estado] > $rango[$r['peor']]) {
                $r['peor'] = $ev->estado;
            }
            unset($r);
        }

        return $resumen;
    }

    public function updatedAstronautaId(): void
    {
        $this->reset('aviso', 'errores');
    }

    public function registrar(): void
    {
        $this->reset('aviso', 'errores');
        $astronauta = Astronauta::find($this->astronautaId);
        if (! $astronauta) {
            return;
        }

        $valores = $this->indicador === 'presion_arterial'
            ? ['sistolica' => $this->sistolica, 'diastolica' => $this->diastolica]
            : ['valor' => $this->valor];
        $valores = array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $valores);

        try {
            $ev = ProcesadorMediciones::make()->registrar($astronauta, $this->indicador, $valores);
            $this->aviso = $ev->recomendacion;
            $this->reset('valor', 'sistolica', 'diastolica');
        } catch (ValidationException $e) {
            $this->errores = collect($e->errors())->flatten()->all();
        }
    }

    /** Genera una medición simulada de cada indicador. $deriva > 0 empuja hacia precaución/crítico. */
    public function simular(float $deriva = 0.0): void
    {
        $this->reset('aviso', 'errores');
        $astronauta = Astronauta::find($this->astronautaId);
        if (! $astronauta) {
            return;
        }
        $sim = new SimuladorMediciones(config('umbrales'));
        $proc = ProcesadorMediciones::make();
        foreach (array_keys(config('umbrales.indicadores')) as $clave) {
            try {
                $proc->registrar($astronauta, $clave, $sim->generar($clave, $deriva), 'simulado');
            } catch (ValidationException) {
                // Duplicado exacto en menos de un minuto: se omite.
            }
        }
    }

    public function limpiar(): void
    {
        Medicion::where('astronauta_id', $this->astronautaId)->delete();
        $this->reset('aviso', 'errores');
    }

    public function alternarEnlace(): void
    {
        $envio = app(EnvioATierra::class);
        // Al recuperar el enlace, la cola se vacía de inmediato.
        if ($envio->alternarEnlace()) {
            $envio->enviarPendientes();
        }
    }

    public function enviarAhora(): void
    {
        app(EnvioATierra::class)->enviarPendientes();
    }

    #[On('medicion-registrada')]
    public function refrescar(): void
    {
        // Re-renderiza con los datos más recientes.
    }

    public function render()
    {
        $config = config('umbrales');
        $astronauta = $this->astronautaId ? Astronauta::find($this->astronautaId) : null;
        $ultimas = $astronauta ? ProcesadorMediciones::make()->ultimas($astronauta) : [];
        $estados = array_map(fn ($ev) => Estado::from($ev->estado), $ultimas);
        $indice = (new IndiceSistemaCorporal($config))->calcular($estados);

        $series = [];
        if ($astronauta) {
            foreach ($config['indicadores'] as $clave => $_) {
                $series[$clave] = $astronauta->mediciones()->where('indicador', $clave)
                    ->orderByDesc('medido_en')->limit(20)->get()->reverse()
                    ->map(fn ($m) => (float) ($m->valores['valor'] ?? $m->valores['sistolica']))->values()->all();
            }
        }

        $envio = app(EnvioATierra::class);
        $tripulantes = $this->tripulantesQuery()->orderBy('id')->get();

        return view('livewire.panel', [
            'config' => $config,
            'misiones' => Mision::orderBy('id')->get(),
            'hayAstronautasSinMision' => Astronauta::whereNull('mision_id')->exists(),
            'mision' => $this->misionId > 0 ? Mision::find($this->misionId) : null,
            'astronautas' => $tripulantes,
            'resumenTripulacion' => $this->resumenTripulacion($tripulantes),
            'ultimas' => $ultimas,
            'indice' => $indice,
            'series' => $series,
            'alertasPendientes' => $envio->pendientes(),
            'enlace' => $envio->enlaceActivo(),
        ]);
    }
}
