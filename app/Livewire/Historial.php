<?php

namespace App\Livewire;

use App\Services\Salud\DatosHistorial;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Historial (diseño "Panel tripulación", vista 05): filtro de periodo (días de misión) y 4 gráficas
 * configurables (indicador + tipo de gráfica). Los datos los calcula DatosHistorial. Orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class Historial extends Component
{
    public const PRESET_DEFECTO = '30';

    public const CHARTS_DEFECTO = [
        ['presion_arterial', 'line'],
        ['perdida_osea', 'area'],
        ['radiacion_diaria', 'bar'],
        ['glucosa', 'dots'],
    ];

    /** Id del periodo (ver DatosHistorial::PRESETS); 'custom' habilita el rango Desde/Hasta. */
    #[Url(as: 'periodo', except: '30')]
    public string $preset = self::PRESET_DEFECTO;

    /** Rango personalizado (días de misión); llega como texto desde los inputs. */
    public $desde = null;
    public $hasta = null;

    /** @var array<int, array{0: string, 1: string}> [indicador, tipo] de cada gráfica */
    public array $charts = self::CHARTS_DEFECTO;

    public bool $error = false;

    public function mount(): void
    {
        $this->normalizar();
    }

    public function updatedPreset(): void
    {
        $this->normalizar();
        if ($this->preset === 'custom' && ($this->desde === null || $this->desde === '') && ($this->hasta === null || $this->hasta === '')) {
            // Parte del rango de 30 días para que los inputs no aparezcan vacíos.
            $a = TripulanteActual::obtener();
            if ($a) {
                $r = DatosHistorial::rango(self::PRESET_DEFECTO, DatosHistorial::diasMision($a), null, null);
                $this->desde = $r['desde'];
                $this->hasta = $r['hasta'];
            }
        }
    }

    public function elegirPreset(string $id): void
    {
        $this->preset = $id;
        $this->updatedPreset();
    }

    public function cambiarIndicador(int $i, string $id): void
    {
        if (isset($this->charts[$i]) && in_array($id, array_column(DatosHistorial::indicadores(), 'id'), true)) {
            $this->charts[$i][0] = $id;
        }
    }

    public function cambiarTipo(int $i, string $tipo): void
    {
        if (isset($this->charts[$i]) && in_array($tipo, array_column(DatosHistorial::TIPOS, 0), true)) {
            $this->charts[$i][1] = $tipo;
        }
    }

    public function reintentar(): void
    {
        $this->error = false;
    }

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        $this->error = false;
        $this->desde = $this->hasta = null;
        if ($this->preset === 'custom') {
            $this->preset = self::PRESET_DEFECTO;
        }
    }

    private function normalizar(): void
    {
        if (! in_array($this->preset, array_column(DatosHistorial::PRESETS, 0), true)) {
            $this->preset = self::PRESET_DEFECTO;
        }
    }

    private static function entero($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    public function render()
    {
        $a = TripulanteActual::obtener();
        $d = null;
        if ($a && ! $this->error) {
            try {
                $d = $this->datos($a);
            } catch (\Throwable $e) {
                report($e);
                $this->error = true;
            }
        }

        return view('livewire.historial', ['astronauta' => $a, 'd' => $d]);
    }

    private function datos($a): array
    {
        $total = DatosHistorial::diasMision($a);
        $rango = DatosHistorial::rango($this->preset, $total, self::entero($this->desde), self::entero($this->hasta));
        $opciones = DatosHistorial::indicadores();
        $ids = array_column($opciones, 'id');

        $graficas = [];
        foreach ($this->charts as $i => [$ind, $tipo]) {
            if (! in_array($ind, $ids, true)) {
                $ind = $ids[0] ?? $ind;
            }
            $graficas[] = DatosHistorial::grafica($a, $ind, $tipo, $rango['desde'], $rango['hasta']) + ['tipo' => $tipo, 'i' => $i];
        }

        return ['total' => $total, 'rango' => $rango, 'opciones' => $opciones, 'graficas' => $graficas];
    }
}
