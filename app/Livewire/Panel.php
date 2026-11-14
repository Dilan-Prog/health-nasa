<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\Medicion;
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
    public ?int $astronautaId = null;
    public string $indicador = 'frecuencia_cardiaca';
    public string $valor = '';
    public string $sistolica = '';
    public string $diastolica = '';
    public ?string $aviso = null;
    public array $errores = [];

    public function mount(): void
    {
        $this->astronautaId = Astronauta::orderBy('id')->value('id');
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

        return view('livewire.panel', [
            'config' => $config,
            'astronautas' => Astronauta::orderBy('id')->get(),
            'ultimas' => $ultimas,
            'indice' => $indice,
            'series' => $series,
            'alertasPendientes' => $envio->pendientes(),
            'enlace' => $envio->enlaceActivo(),
        ]);
    }
}
