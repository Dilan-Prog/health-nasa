<?php

namespace App\Livewire;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\SimulacionMotor;
use App\Models\SimulacionTripulante;
use App\Services\Salud\ControlSimulacion;
use App\Services\Salud\ResumenTripulante;
use App\Services\Salud\SimuladorVivo;
use App\Support\TripulanteActual;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de simulación (solo fuera de producción): controla el motor de mediciones en vivo. El panel NO lanza procesos;
 * solo escribe interruptores en la base y muestra lo que genera `php artisan salud:simulador-vivo`.
 */
#[Layout('layouts.app')]
class Simulacion extends Component
{
    public const INTERVALOS = [2, 5, 10, 30];

    public const COMANDO = 'php artisan salud:simulador-vivo';

    public const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];

    public const ESTADOS = ['ok' => ['NORMAL', 'check_circle'], 'warn' => ['ATENCIÓN', 'warning'], 'crit' => ['GRAVE', 'error']];

    /** Elección del selector de escenario por tripulante (id => clave). */
    public array $escenarioSel = [];

    /** Mensaje de error por tripulante (id => texto). */
    public array $errores = [];

    public function mount(): void
    {
        abort_if(app()->environment('production'), 404);
    }

    #[On('medicion-registrada')]
    public function refrescar(): void
    {
        // El render recalcula todo.
    }

    public function alternarMotor(): void
    {
        $m = SimulacionMotor::actual();
        $m->update(['activa' => ! $m->activa]);
    }

    public function fijarVelocidad(int $v): void
    {
        if (in_array($v, SimulacionMotor::VELOCIDADES, true)) {
            SimulacionMotor::actual()->update(['velocidad' => $v]);
        }
    }

    public function fijarIntervalo(int $s): void
    {
        if (in_array($s, self::INTERVALOS, true)) {
            SimulacionMotor::actual()->update(['intervalo' => $s]);
        }
    }

    public function alternarTripulante(int $id): void
    {
        $a = $this->tripulante($id);
        if (! $a) {
            return;
        }
        $t = SimulacionTripulante::de($a);
        if ($t->fuente !== 'simulado') {
            $this->errores[$id] = 'Con fuente reloj no se simula.';

            return;
        }
        unset($this->errores[$id]);
        $t->update(['activa' => ! $t->activa]);
    }

    public function fijarFuente(int $id, string $fuente): void
    {
        $a = $this->tripulante($id);
        if (! $a || ! in_array($fuente, ['simulado', 'reloj'], true)) {
            return;
        }
        unset($this->errores[$id]);
        $t = SimulacionTripulante::de($a);
        $t->update($fuente === 'reloj' ? ['fuente' => 'reloj', 'activa' => false] : ['fuente' => 'simulado']);
    }

    public function lanzar(int $id): void
    {
        $a = $this->tripulante($id);
        if (! $a) {
            return;
        }
        unset($this->errores[$id]);
        try {
            ControlSimulacion::iniciar($a, (string) ($this->escenarioSel[$id] ?? ''));
        } catch (InvalidArgumentException $e) {
            $this->errores[$id] = $e->getMessage();
        }
    }

    public function cancelar(int $id): void
    {
        $a = $this->tripulante($id);
        if ($a) {
            unset($this->errores[$id]);
            ControlSimulacion::cancelar($a);
        }
    }

    private function tripulante(int $id): ?Astronauta
    {
        return TripulanteActual::tripulacion()->firstWhere('id', $id);
    }

    public function render()
    {
        $motor = SimulacionMotor::actual();
        $tripulacion = TripulanteActual::tripulacion();
        $escenarios = (new SimuladorVivo)->escenarios();
        $indicadores = config('panel.indicadores', []);

        $filas = SimulacionTripulante::whereIn('astronauta_id', $tripulacion->pluck('id'))->get()->keyBy('astronauta_id');
        foreach ($tripulacion as $a) {
            if (! $filas->has($a->id)) {
                $filas->put($a->id, SimulacionTripulante::de($a));
            }
        }

        // Última medición de cada tripulante (una sola consulta) y resumen de estado en bloque.
        $ids = $tripulacion->pluck('id')->all();
        $ultimas = $ids
            ? Medicion::whereIn('id', Medicion::selectRaw('max(id)')->whereIn('astronauta_id', $ids)->groupBy('astronauta_id'))->get()->keyBy('astronauta_id')
            : collect();
        $resumenes = $tripulacion->isNotEmpty() ? ResumenTripulante::make()->deVarios($tripulacion) : [];

        $lista = [];
        foreach ($tripulacion as $a) {
            $t = $filas[$a->id];
            $u = $ultimas->get($a->id);
            $lista[] = [
                'id' => $a->id, 'nombre' => $a->nombre, 't' => $t,
                'reloj' => $t->fuente === 'reloj', 'token' => ! empty($a->token_dispositivo),
                'escenario' => $t->escenario ? ($escenarios[$t->escenario]['nombre'] ?? $t->escenario) : null,
                'restante' => $t->escenario ? ControlSimulacion::restanteReal($t, $motor) : null,
                'ultima' => $u ? [
                    'hora' => $u->medido_en->copy()->utc()->format('H:i:s'),
                    'ind' => $indicadores[$u->indicador]['etiqueta'] ?? $u->indicador,
                    'valor' => $this->valor($u->valores, $indicadores[$u->indicador]['unidad'] ?? ''),
                ] : null,
                'estado' => $resumenes[$a->id]['peor'] ?? null,
            ];
        }

        $recientes = Medicion::with(['evaluacion', 'astronauta:id,nombre'])->where('origen', 'simulado')
            ->whereIn('astronauta_id', $ids ?: [0])->orderByDesc('medido_en')->orderByDesc('id')->limit(12)->get()
            ->map(fn ($m) => [
                'quien' => $m->astronauta?->nombre ?? '—', 'hora' => $m->medido_en->copy()->utc()->format('d/m H:i:s'),
                'ind' => $indicadores[$m->indicador]['etiqueta'] ?? $m->indicador,
                'valor' => $this->valor($m->valores, $indicadores[$m->indicador]['unidad'] ?? ''),
                'estado' => $m->evaluacion ? (self::ESTADO_UI[$m->evaluacion->estado] ?? null) : null,
            ]);

        $alertas = AlertaSaliente::whereIn('astronauta_id', $ids ?: [0])->orderByDesc('id')->limit(8)->get()
            ->map(fn ($x) => [
                'quien' => $x->carga['astronauta'] ?? '—',
                'ind' => $indicadores[$x->carga['indicador'] ?? '']['etiqueta'] ?? ($x->carga['indicador'] ?? '—'),
                'estado' => ['crit' => 'crit', 'critico' => 'crit', 'warn' => 'warn', 'precaucion' => 'warn'][$x->carga['severidad'] ?? ''] ?? null,
                'hora' => $x->created_at?->copy()->utc()->format('d/m H:i') ?? '—',
            ]);

        return view('livewire.simulacion', [
            'motor' => $motor, 'lista' => $lista, 'escenarios' => $escenarios, 'recientes' => $recientes, 'alertas' => $alertas,
            'chip' => $this->chip($motor, $lista),
            'hace' => $motor->latido_en ? max(0, (int) $motor->latido_en->diffInSeconds(now(), false)) : null,
            'corriendo' => $motor->corriendo(), 'velocidades' => SimulacionMotor::VELOCIDADES, 'intervalos' => self::INTERVALOS,
            'comando' => self::COMANDO, 'estados' => self::ESTADOS,
        ]);
    }

    /** @return array{0:string,1:string,2:string} texto, icono, tono */
    private function chip(SimulacionMotor $m, array $lista): array
    {
        return match (true) {
            $lista === [] => ['SIN TRIPULANTES', 'group_off', 'warn'],
            collect($lista)->contains(fn ($f) => $f['t']->ultimo_error) => ['ERROR', 'error', 'crit'],
            ! $m->corriendo() => ['PARADO', 'stop_circle', 'warn'],
            ! $m->activa => ['PAUSADA', 'pause_circle', 'warn'],
            collect($lista)->contains(fn ($f) => $f['t']->escenario) => ['ESCENARIO EN CURSO', 'model_training', 'warn'],
            default => ['EN MARCHA', 'play_circle', 'ok'],
        };
    }

    private function valor(array $v, string $unidad): string
    {
        $txt = isset($v['sistolica'], $v['diastolica'])
            ? (int) round($v['sistolica']).'/'.(int) round($v['diastolica'])
            : (string) round((float) ($v['valor'] ?? 0), 1);

        return trim($txt.' '.$unidad);
    }
}
