<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Services\Salud\DatosIndicador;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Indicador (diseño "Panel tripulación"): análisis de un marcador (14) con gráfico, comparación,
 * miniaturas y resumen. El servidor solo entrega el payload JSON del tripulante actual (DatosIndicador);
 * todo el estado de la pantalla vive en el cliente (Alpine `indicador`, modelo en resources/js/indicador).
 * Orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class Indicador extends Component
{
    public const MODOS = ['ind', 'cmp', 'mini'];
    public const VISTAS = ['bars', 'lines', 'forecast', 'heat'];
    public const PERIODOS = ['24h', '7', '10', '30', 'all', 'custom'];
    public const ESTADOS = ['ok', 'carga', 'error', 'vacio'];

    /** Marcador abierto (clave real). */
    #[Url(as: 'id', except: '')]
    public string $marcador = '';

    #[Url(as: 'mode', except: '')]
    public string $modo = '';

    #[Url(as: 'view', except: '')]
    public string $vista = '';

    #[Url(as: 'pre', except: '')]
    public string $periodo = '';

    /** Marcadores a comparar, separados por coma. */
    #[Url(as: 'm', except: '')]
    public string $comparar = '';

    /** Para demos/pruebas: ok | carga | error | vacio. */
    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'norm', except: '')]
    public string $norm = '';

    #[Url(as: 'drawer', except: '')]
    public string $drawer = '';

    /** Fallo al calcular el payload (se muestra el estado de error con "Reintentar"). */
    public bool $error = false;

    public function reintentar(): void
    {
        $this->error = false;
    }

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        // El render siguiente recalcula el payload del nuevo tripulante; la plantilla se re-inicializa por wire:key.
        $this->error = false;
    }

    /** Parámetros de consulta saneados que el cliente usa como estado inicial (como la maqueta). */
    private function consulta(array $ids): array
    {
        $c = [];
        if ($this->marcador !== '' && in_array($this->marcador, $ids, true)) {
            $c['id'] = $this->marcador;
        }
        if (in_array($this->modo, self::MODOS, true)) {
            $c['mode'] = $this->modo;
        }
        if (in_array($this->vista, self::VISTAS, true)) {
            $c['view'] = $this->vista;
        }
        if (in_array($this->periodo, self::PERIODOS, true)) {
            $c['pre'] = $this->periodo;
        }
        $lista = array_values(array_filter(array_map('trim', explode(',', $this->comparar)), fn ($x) => in_array($x, $ids, true)));
        if ($lista) {
            $c['m'] = implode(',', $lista);
        }
        if (in_array($this->estado, self::ESTADOS, true) && $this->estado !== 'ok') {
            $c['estado'] = $this->estado;
        }
        if ($this->norm === '0') {
            $c['norm'] = '0';
        }
        if ($this->drawer === '1') {
            $c['drawer'] = '1';
        }

        return $c;
    }

    public function render()
    {
        /** @var Astronauta|null $a */
        $a = TripulanteActual::obtener();
        $payload = null;
        if ($a && ! $this->error) {
            try {
                $payload = DatosIndicador::para($a);
            } catch (\Throwable $e) {
                report($e);
                $this->error = true;
            }
        }
        $ids = $payload ? array_column($payload['marcadores'] ?? [], 'id') : [];

        return view('livewire.indicador', [
            'astronauta' => $a,
            'payload' => $payload,
            'consulta' => $this->consulta($ids),
            'urls' => ['registrar' => url('/registrar')],
        ]);
    }
}
