<?php

namespace App\Livewire;

use App\Models\AlertaSaliente;
use App\Models\CasoClinico;
use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\ResumenTripulante;
use App\Support\TripulanteActual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Panel (diseño "Panel tripulación"): figura + 10 indicadores + "Qué hacer ahora".
 * El tripulante sale de TripulanteActual (selector del layout); la herramienta orienta, no diagnostica.
 */
#[Layout('layouts.app')]
class Panel extends Component
{
    public const PREGUNTAS = [
        'Do you feel dizzy or unsteady?',
        'Do you have a headache?',
        'Are you short of breath or breathing with effort?',
        'Do you notice palpitations or a racing heart?',
        'Do you have blurred vision or see spots of light?',
    ];

    public const RESULTADOS = [
        'green' => ['k' => 'ok', 'ic' => 'visibility', 'titulo' => 'Monitor', 'texto' => 'No symptoms. Continue your routine and repeat the self-check if anything changes.'],
        'orange' => ['k' => 'warn', 'ic' => 'front_hand', 'titulo' => 'Apply measure', 'texto' => 'Apply the actions on the cards and repeat the self-check in 15 min.'],
        'red' => ['k' => 'crit', 'ic' => 'emergency', 'titulo' => 'Contact ground now', 'texto' => 'Your symptoms need assessment by the Flight Surgeon. Alert ground and rest.'],
    ];

    /** Indicador seleccionado (clave de config/panel.php) o null. */
    #[Url(as: 'sel', except: '')]
    public ?string $sel = null;

    /** Para demos: "sinenlace" fuerza el aspecto sin enlace; "carga" muestra los esqueletos. */
    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    /** Respuestas del autochequeo: 'y' | 'n'. */
    public array $respuestas = [];

    /** Acciones marcadas: "<astronauta>.<clave>.<n>" => true. */
    public array $hechas = [];

    /** Avisos a tierra pedidos desde esta pantalla: "<astronauta>.<clave|chk>" => true. */
    public array $avisados = [];

    public function mount(): void
    {
        if ($this->sel !== null && ! array_key_exists($this->sel, config('panel.indicadores', []))) {
            $this->sel = null;
        }
    }

    public function seleccionar(string $clave): void
    {
        if (! array_key_exists($clave, config('panel.indicadores', []))) {
            return;
        }
        $this->sel = $this->sel === $clave ? null : $clave;
    }

    public function quitarSeleccion(): void
    {
        $this->sel = null;
    }

    public function repetirMedicion(string $clave): void
    {
        if (! array_key_exists($clave, config('panel.indicadores', []))) {
            return;
        }
        $this->sel = $clave;
        $this->dispatch('abrir-registro', indicador: $clave);
    }

    public function alternarAccion(string $clave, int $n): void
    {
        $k = $this->clave($clave.'.'.$n);
        $this->hechas[$k] = ! ($this->hechas[$k] ?? false);
    }

    public function responder(string $r): void
    {
        if (count($this->respuestas) < count(self::PREGUNTAS) && in_array($r, ['y', 'n'], true)) {
            $this->respuestas[] = $r;
        }
    }

    public function preguntaAnterior(): void
    {
        array_pop($this->respuestas);
        $this->respuestas = array_values($this->respuestas);
    }

    public function reiniciarChequeo(): void
    {
        $this->respuestas = [];
        unset($this->avisados[$this->clave('chk')]);
    }

    /** Encola (o envía, si hay enlace) un aviso real a tierra para un indicador o para el autochequeo ('chk'). */
    public function avisarATierra(string $clave): void
    {
        $astronauta = TripulanteActual::obtener();
        if (! $astronauta || ($clave !== 'chk' && ! array_key_exists($clave, config('panel.indicadores', [])))) {
            return;
        }
        $k = $this->clave($clave);
        if ($this->avisados[$k] ?? false) {
            return;
        }

        $evaluacion = Evaluacion::query()
            ->whereHas('medicion', fn ($q) => $q->where('astronauta_id', $astronauta->id)
                ->when($clave !== 'chk', fn ($q) => $q->where('indicador', $clave)))
            ->with('medicion')->latest('id')->first();
        $etiqueta = $clave === 'chk' ? 'Symptom self-check' : config('panel.indicadores.'.$clave.'.etiqueta');
        // Sin evaluación previa el aviso viaja igual (evaluacion_id es opcional): solo sin valores ni estado.
        $carga = [
            'astronauta' => $astronauta->nombre,
            'indicador' => $etiqueta,
            'valores' => $evaluacion?->medicion?->valores,
            'estado' => $evaluacion?->estado,
            'accion' => $clave === 'chk' ? 'contactar_tierra' : ($evaluacion?->accion ?? 'contactar_tierra'),
            'origen' => $clave === 'chk' ? 'autochequeo' : 'panel',
            'medido_en' => $evaluacion?->medicion?->medido_en?->toIso8601String(),
        ];
        if ($clave === 'chk') {
            $carga['respuestas'] = $this->respuestas;
        }
        AlertaSaliente::create([
            'evaluacion_id' => $evaluacion?->id,
            'astronauta_id' => $astronauta->id,
            'carga' => $carga,
        ]);
        $this->avisados[$k] = true;

        if ($this->enlaceVisible()) {
            app(EnvioATierra::class)->enviarPendientes();
        }
    }

    public function alternarEnlace(): void
    {
        $envio = app(EnvioATierra::class);
        // Al recuperar el enlace, la cola se vacía de inmediato.
        if ($envio->alternarEnlace()) {
            $envio->enviarPendientes();
        }
    }

    #[On('tripulante-cambiado')]
    public function alCambiarTripulante(): void
    {
        $this->reset('sel', 'respuestas', 'hechas', 'avisados');
    }

    #[On('medicion-registrada')]
    #[On('medicion-guardada')]
    public function refrescar(): void
    {
        // Re-renderiza con los datos más recientes (Echo/Reverb o el modal de registro).
    }

    private function clave(string $sufijo): string
    {
        return (TripulanteActual::obtener()?->id ?? 0).'.'.$sufijo;
    }

    private function enlaceVisible(): bool
    {
        return $this->estado !== 'sinenlace' && app(EnvioATierra::class)->enlaceActivo();
    }

    /** Polilínea y línea base del gráfico de tendencia (misma escala que `trend` del diseño, viewBox 280×56). */
    public static function tendenciaSvg(array $puntos, float $base): array
    {
        $lo = min(min($puntos), $base);
        $hi = max(max($puntos), $base);
        $pad = (($hi - $lo) ?: 1) * 0.18;
        $y = fn (float $x) => round(50 - ($x - $lo + $pad) / ($hi - $lo + 2 * $pad) * 44, 1);
        $n = max(count($puntos) - 1, 1);
        $pts = [];
        foreach (array_values($puntos) as $i => $x) {
            $pts[] = round($i * 280 / $n, 1).','.$y((float) $x);
        }

        return ['pts' => implode(' ', $pts), 'baseY' => $y($base)];
    }

    public function render()
    {
        $astronauta = TripulanteActual::obtener();
        $resumen = $astronauta ? ResumenTripulante::make()->de($astronauta) : null;
        $indicadores = $resumen ? collect($resumen['indicadores'])->keyBy('clave') : collect();

        $musculos = $this->sel && isset($indicadores[$this->sel])
            ? $indicadores[$this->sel]['musculos']
            : ($resumen['musculos_activos'] ?? []);

        $tarjetas = [];
        foreach ($resumen['incidencias'] ?? [] as $i) {
            $tarjetas[] = $i + [
                'svg' => self::tendenciaSvg($i['tendencia']['puntos'], (float) $i['tendencia']['base']),
                'avisado' => $this->avisados[$this->clave($i['clave'])] ?? false,
            ];
        }

        $n = count($this->respuestas);
        $si = count(array_filter($this->respuestas, fn ($r) => $r === 'y'));
        $hecho = $n >= count(self::PREGUNTAS);
        $rk = ! $hecho ? null : (($this->respuestas[2] ?? null) === 'y' || $si >= 3 ? 'red' : ($si ? 'orange' : 'green'));

        $envio = app(EnvioATierra::class);

        // Caso abierto del tripulante actual (el crítico primero): misma Orientacion cacheada que usa el flotante.
        $caso = $astronauta
            ? CasoClinico::abiertos()->where('astronauta_id', $astronauta->id)->orderByRaw("severidad = 'critico' desc")->orderByDesc('id')->first()
            : null;

        return view('livewire.panel', [
            'caso' => $caso,
            'orientacionCaso' => $caso ? MedicoFlotante::orientacionDe($caso) : null,
            'astronauta' => $astronauta,
            'resumen' => $resumen,
            'indicadores' => $indicadores->values()->all(),
            'tarjetas' => $tarjetas,
            'musculos' => $musculos,
            'seleccionado' => $this->sel ? ($indicadores[$this->sel] ?? null) : null,
            'conEnlace' => $this->enlaceVisible(),
            'enlaceReal' => $envio->enlaceActivo(),
            'cargando' => $this->estado === 'carga',
            'retardo' => max(1, $astronauta?->retardoMin() ?? 1),
            'preguntas' => self::PREGUNTAS,
            'chequeo' => [
                'n' => $n,
                'hecho' => $hecho,
                'rk' => $rk,
                'res' => $rk ? self::RESULTADOS[$rk] : null,
                'avisado' => $this->avisados[$this->clave('chk')] ?? false,
            ],
        ]);
    }
}
