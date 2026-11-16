<?php

namespace App\Livewire;

use App\Events\MedicionRegistrada;
use App\Models\RegistroSalud;
use App\Models\ZonaDolor;
use App\Services\Salud\AnalisisRegistro;
use App\Services\Salud\EnvioATierra;
use App\Services\Salud\GuardadorRegistro;
use App\Support\MusculosCuerpo;
use App\Support\TripulanteActual;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Registrar (diseño "Panel tripulación"): marcado de dolor en la silueta + nueva medición.
 *
 * Columna izquierda: zonas de dolor (músculos de MusculosCuerpo; un mismo músculo en varias vistas = una sola zona).
 * Columna derecha: los 10 indicadores con validación en vivo, síntomas opcionales, nota y origen.
 * Guardar persiste todo en un registro (GuardadorRegistro) y, con valor crítico o dolor >= 8, encola un aviso a tierra.
 * La herramienta orienta, no diagnostica ni receta.
 */
#[Layout('layouts.app')]
class Registrar extends Component
{
    /** Indicador a enfocar al abrir (?indicador=clave). */
    #[Url(as: 'indicador', except: '')]
    public string $indicador = '';

    /** Para demos: "sinenlace" fuerza el aspecto sin enlace con tierra. */
    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    /** Texto escrito por indicador; la presión usa `pa_s` (sistólica) y `pa_d` (diastólica). @var array<string, string> */
    public array $campos = [];

    /** id de músculo => ['int' => 1-10, 'tipo' => string, 'desde' => string]. @var array<string, array> */
    public array $zonas = [];

    /** @var string[] */
    public array $sintomas = [];

    public string $nota = '';

    public string $origen = 'manual';

    /** Errores devueltos al guardar (p. ej. medición duplicada): clave => mensaje. @var array<string, string> */
    public array $erroresGuardado = [];

    /** Resumen de la fase "guardado" (null = editando). */
    public ?array $guardado = null;

    public function mount(): void
    {
        if ($this->indicador !== '' && ! array_key_exists($this->indicador, config('panel.indicadores', []))) {
            $this->indicador = '';
        }
        $this->vaciar();
    }

    private function vaciar(): void
    {
        $this->campos = array_fill_keys(array_merge(array_keys(config('panel.indicadores', [])), ['pa_s', 'pa_d', 'animo']), '');
        $this->zonas = [];
        $this->sintomas = [];
        $this->nota = '';
        $this->origen = 'manual';
        $this->erroresGuardado = [];
        $this->guardado = null;
    }

    // ---------- Zonas de dolor ----------

    /**
     * Clic o Enter en un músculo de la silueta: lo marca o lo desmarca. Lo invoca la vista al recibir el CustomEvent DOM
     * `musculo-seleccionado` (detail.id). No se escucha con #[On]: la silueta emite además Livewire.dispatch del mismo
     * nombre y el listener se ejecutaría dos veces (marcar y desmarcar).
     */
    public function alternarZona(string $id): void
    {
        if (! in_array($id, MusculosCuerpo::ids(), true) || $this->guardado !== null) {
            return;
        }
        if (isset($this->zonas[$id])) {
            unset($this->zonas[$id]);
        } else {
            $this->zonas[$id] = ['int' => 5, 'tipo' => ZonaDolor::TIPOS[0], 'desde' => ZonaDolor::DESDE[0]];
        }
    }

    public function fijarIntensidad(string $id, int $n): void
    {
        if (isset($this->zonas[$id]) && $n >= 1 && $n <= 10) {
            $this->zonas[$id]['int'] = $n;
        }
    }

    public function fijarTipo(string $id, string $tipo): void
    {
        if (isset($this->zonas[$id]) && in_array($tipo, ZonaDolor::TIPOS, true)) {
            $this->zonas[$id]['tipo'] = $tipo;
        }
    }

    public function fijarDesde(string $id, string $desde): void
    {
        if (isset($this->zonas[$id]) && in_array($desde, ZonaDolor::DESDE, true)) {
            $this->zonas[$id]['desde'] = $desde;
        }
    }

    public function limpiarZonas(): void
    {
        $this->zonas = [];
    }

    // ---------- Formulario ----------

    /** Activa (50 = punto medio) o quita el registro opcional del estado de ánimo (VAS 0-100). */
    public function alternarAnimo(): void
    {
        if ($this->guardado !== null) {
            return;
        }
        $this->campos['animo'] = trim((string) ($this->campos['animo'] ?? '')) === '' ? '50' : '';
        $this->erroresGuardado = [];
    }

    public function fijarOrigen(string $origen): void
    {
        if (array_key_exists($origen, RegistroSalud::ORIGENES)) {
            $this->origen = $origen;
        }
    }

    public function alternarSintoma(string $sintoma): void
    {
        if (! in_array($sintoma, RegistroSalud::SINTOMAS, true)) {
            return;
        }
        $this->sintomas = in_array($sintoma, $this->sintomas, true)
            ? array_values(array_diff($this->sintomas, [$sintoma]))
            : [...$this->sintomas, $sintoma];
    }

    public function updatedCampos(): void
    {
        $this->erroresGuardado = [];
    }

    public function cancelar()
    {
        return $this->redirectRoute('panel');
    }

    public function nuevoRegistro(): void
    {
        $this->vaciar();
    }

    #[On('tripulante-cambiado')]
    public function alCambiarTripulante(): void
    {
        $this->vaciar();
    }

    /**
     * Estado de cada indicador a partir de lo escrito.
     *
     * @return array<string, array{valores: ?array, error: ?string, estado: ?string, mensaje: ?string, pendiente: ?string}>
     */
    private function analizar(): array
    {
        $an = AnalisisRegistro::make();
        $salida = [];
        foreach (config('panel.indicadores') as $clave => $_) {
            $r = ['valores' => null, 'error' => null, 'estado' => null, 'mensaje' => null, 'pendiente' => null];
            if ($clave === 'presion_arterial') {
                $s = trim((string) ($this->campos['pa_s'] ?? ''));
                $d = trim((string) ($this->campos['pa_d'] ?? ''));
                if ($s !== '' && $d !== '') {
                    $ns = $an->numero($s);
                    $nd = $an->numero($d);
                    $valores = $ns !== null && $nd !== null ? ['sistolica' => $ns, 'diastolica' => $nd] : null;
                } elseif ($s !== '' || $d !== '') {
                    $r['pendiente'] = 'Enter systolic and diastolic';
                    $valores = false;
                } else {
                    $valores = false;
                }
                $presente = $s !== '' || $d !== '';
            } else {
                $t = trim((string) ($this->campos[$clave] ?? ''));
                $presente = $t !== '';
                $n = $presente ? $an->numero($t) : null;
                $valores = $presente ? ($n === null ? null : $an->valorSimple($clave, $n)) : false;
            }

            if ($valores === null) {
                $r['error'] = 'Enter a number';
            } elseif (is_array($valores)) {
                $e = $an->evaluar($clave, $valores);
                if ($e['error']) {
                    $r['error'] = $e['error'];
                } else {
                    $r['valores'] = $valores;
                    $r['estado'] = $e['estado'];
                    $r['mensaje'] = $an->mensaje($clave, $valores, $e['estado']);
                }
            }
            if ($presente && ($this->erroresGuardado[$clave] ?? null)) {
                $r['error'] ??= $this->erroresGuardado[$clave];
            }
            $salida[$clave] = $r;
        }
        $salida['animo'] = $this->analizarAnimo($an);

        return $salida;
    }

    /** Estado de ánimo: escala VAS 0-100 (opcional); sus umbrales viven en config/umbrales.php (valores de ejemplo). */
    private function analizarAnimo(AnalisisRegistro $an): array
    {
        $r = ['valores' => null, 'error' => null, 'estado' => null, 'mensaje' => null, 'pendiente' => null];
        $t = trim((string) ($this->campos['animo'] ?? ''));
        if ($t === '') {
            return $r;
        }
        $n = $an->numero($t);
        if ($n === null) {
            $r['error'] = 'Enter a number';
        } else {
            $valores = ['valor' => $n];
            $e = $an->evaluar('animo', $valores);
            if ($e['error']) {
                $r['error'] = $e['error'];
            } else {
                $r['valores'] = $valores;
                $r['estado'] = $e['estado'];
                $r['mensaje'] = match ($e['estado']) {
                    'ok' => 'Within the usual range',
                    'warn' => 'Below the usual range: talk to a crew mate or schedule a support call',
                    default => 'Very low: alert the ground or your support team',
                };
            }
        }
        if ($this->erroresGuardado['animo'] ?? null) {
            $r['error'] ??= $this->erroresGuardado['animo'];
        }

        return $r;
    }

    /** Resumen del formulario: mediciones válidas, errores, críticas, zonas y si hay aviso a tierra. */
    private function resumen(array $analisis): array
    {
        $hechas = array_filter($analisis, fn ($a) => $a['estado'] !== null);
        $nErr = count(array_filter($analisis, fn ($a) => $a['error'] !== null));
        $nCrit = count(array_filter($hechas, fn ($a) => $a['estado'] === 'crit'));
        $dolorAlto = collect($this->zonas)->contains(fn ($z) => $z['int'] >= ZonaDolor::INTENSIDAD_ALERTA);

        return [
            'hechas' => $hechas,
            'nHechas' => count($hechas),
            'nErr' => $nErr,
            'nCrit' => $nCrit,
            'nZonas' => count($this->zonas),
            'alerta' => $nCrit > 0 || $dolorAlto,
            'vacio' => $hechas === [] && $this->zonas === [],
        ];
    }

    public function guardar(): void
    {
        $astronauta = TripulanteActual::obtener();
        if (! $astronauta || $this->guardado !== null) {
            return;
        }
        $this->erroresGuardado = [];
        $analisis = $this->analizar();
        $r = $this->resumen($analisis);
        if ($r['nErr'] > 0 || $r['vacio']) {
            return; // el botón ya está deshabilitado; la barra explica por qué
        }

        $mediciones = array_map(fn ($a) => $a['valores'], $r['hechas']);
        try {
            $res = GuardadorRegistro::make()->guardar($astronauta, $mediciones, $this->zonas, $this->sintomas, $this->nota, $this->origen);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $clave => $mensajes) {
                $this->erroresGuardado[$clave] = $mensajes[0];
            }
            $this->dispatch('registro-foco-error');

            return;
        }

        $enlace = $this->enlaceVisible();
        if ($res['aviso'] && $enlace) {
            app(EnvioATierra::class)->enviarPendientes();
        }
        foreach ($res['estados'] as $clave => $estado) {
            // La difusión en tiempo real es accesoria: si Reverb no responde, los datos ya están guardados.
            try {
                MedicionRegistrada::dispatch($astronauta->id, $clave, $estado);
            } catch (\Throwable $e) {
                report($e);
                break; // sin Reverb cada intento espera su timeout: no se repite con el resto
            }
        }

        $filas = [];
        foreach ($r['hechas'] as $clave => $a) {
            $cfg = $clave === 'animo'
                ? ['icono' => 'mood', 'etiqueta' => 'Mood', 'unidad' => '/100 VAS']
                : config("panel.indicadores.$clave");
            $filas[] = [
                'ic' => $cfg['icono'], 'label' => $cfg['etiqueta'], 'estado' => $a['estado'],
                'v' => $this->textoValor($clave, $a['valores']).' '.$cfg['unidad'],
            ];
        }
        foreach ($this->zonas as $id => $z) {
            $filas[] = [
                'ic' => 'location_on', 'label' => $this->nombreZona($id).' · '.mb_strtolower(ZonaDolor::etiquetaTipo($z['tipo'])),
                'estado' => ZonaDolor::estado($z['int']), 'v' => $z['int'].'/10',
            ];
        }
        $this->guardado = [
            'hora' => now('UTC')->format('H:i'),
            'filas' => $filas,
            'aviso' => $res['aviso'],
            'enlace' => $enlace,
            'nMediciones' => $r['nHechas'],
            'nZonas' => $r['nZonas'],
        ];
        $this->dispatch('medicion-guardada', astronautaId: $astronauta->id);
        $this->dispatch('medicion-registrada', astronautaId: $astronauta->id);
    }

    /** "128/84", "−1.6" (signo menos tipográfico): el valor tal como se muestra en el resumen guardado. */
    private function textoValor(string $clave, array $valores): string
    {
        $f = fn (float $n) => rtrim(rtrim(number_format(abs($n), 2, '.', ''), '0'), '.');
        if (isset($valores['sistolica'])) {
            return $f($valores['sistolica']).'/'.$f($valores['diastolica']);
        }
        $v = (float) $valores['valor'];

        return ($v < 0 ? "\u{2212}" : '').$f($v);
    }

    /** English display name of a body zone (the id is what is stored and compared). */
    public function nombreZona(string $id): string
    {
        return MusculosCuerpo::nombreEn($id) ?? ZonaDolor::nombre($id);
    }

    /** English display word for a 1-10 pain intensity (ZonaDolor::palabra() is the Spanish original). */
    public function palabraDolor(int $n): string
    {
        return $n <= 3 ? 'Mild' : ($n <= 6 ? 'Moderate' : ($n <= 8 ? 'Strong' : 'Very strong'));
    }

    private function enlaceVisible(): bool
    {
        return $this->estado !== 'sinenlace' && app(EnvioATierra::class)->enlaceActivo();
    }

    public function render()
    {
        $astronauta = TripulanteActual::obtener();
        $analisis = $this->analizar();
        $r = $this->resumen($analisis);

        return view('livewire.registrar', [
            'astronauta' => $astronauta,
            'indicadores' => config('panel.indicadores'),
            'analisis' => $analisis,
            'r' => $r,
            'conEnlace' => $this->enlaceVisible(),
            'retardo' => max(1, $astronauta?->retardoMin() ?? 1),
            'ahora' => now('UTC'),
            'tipos' => ZonaDolor::TIPOS,
            'desde' => ZonaDolor::DESDE,
            'vistas' => MusculosCuerpo::VISTAS,
        ]);
    }
}
