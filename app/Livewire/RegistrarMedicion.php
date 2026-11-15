<?php

namespace App\Livewire;

use App\Events\MedicionRegistrada;
use App\Models\Astronauta;
use App\Services\Salud\AnalisisRegistro;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ResumenTripulante;
use App\Support\TripulanteActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Diálogo modal "Registrar medición" (montado en el layout).
 *
 * Decisión de guardado: se registran TODOS los campos con valor (como el diseño: la medición de hoy
 * es un acto completo y los valores precargados son el último dato, que se confirma o se corrige).
 * Los campos vacíos se ignoran. Si algún campo es inválido no se guarda nada (todo o nada, en una transacción).
 * La alerta a tierra para valores críticos la encola ProcesadorMediciones (mecanismo existente).
 */
class RegistrarMedicion extends Component
{
    public bool $abierto = false;
    public ?int $astronautaId = null;
    /** @var array<string, string> clave de indicador => texto escrito */
    public array $campos = [];
    public ?string $enfocar = null;
    public string $hora = '';
    public ?string $exito = null;
    public ?string $errorGeneral = null;
    /** @var array<string, string> errores devueltos al guardar (p. ej. medición duplicada) */
    public array $erroresGuardado = [];

    #[On('abrir-registro')]
    public function abrir(?string $indicador = null): void
    {
        $astronauta = TripulanteActual::obtener();
        if (! $astronauta) {
            return;
        }
        $this->cargar($astronauta);
        $this->enfocar = ($indicador && isset(config('panel.indicadores')[$indicador])) ? $indicador : array_key_first(config('panel.indicadores'));
        $this->hora = now()->utc()->format('H:i');
        $this->exito = null;
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->reset('errorGeneral', 'erroresGuardado');
    }

    #[On('tripulante-cambiado')]
    public function tripulanteCambiado(): void
    {
        $this->exito = null;
        if ($this->abierto) {
            $this->cerrar();
        }
    }

    private function cargar(Astronauta $astronauta): void
    {
        $this->astronautaId = $astronauta->id;
        $this->campos = [];
        $this->reset('errorGeneral', 'erroresGuardado');
        foreach (ResumenTripulante::make()->de($astronauta)['indicadores'] as $ind) {
            $this->campos[$ind['clave']] = $ind['valor'] ?? '';
        }
    }

    public function updatedCampos(): void
    {
        $this->erroresGuardado = [];
        $this->errorGeneral = null;
    }

    /** @return array<string, array{valores: ?array, error: ?string, estado: ?string}> */
    private function analizar(): array
    {
        $analisis = AnalisisRegistro::make();
        $salida = [];
        foreach (config('panel.indicadores') as $clave => $_) {
            $p = $analisis->parsear($clave, (string) ($this->campos[$clave] ?? ''));
            $r = ['valores' => null, 'error' => $this->erroresGuardado[$clave] ?? null, 'estado' => null];
            if ($p !== null) {
                if (isset($p['formato'])) {
                    $r['error'] ??= $p['formato'];
                } else {
                    $e = $analisis->evaluar($clave, $p['valores']);
                    if ($e['error']) {
                        $r['error'] ??= $e['error'];
                    } else {
                        $r['valores'] = $p['valores'];
                        $r['estado'] = $e['estado'];
                    }
                }
            }
            $salida[$clave] = $r;
        }

        return $salida;
    }

    public function guardar(): void
    {
        $astronauta = $this->astronautaId ? Astronauta::find($this->astronautaId) : null;
        if (! $astronauta) {
            return;
        }
        $this->errorGeneral = null;
        $this->erroresGuardado = [];
        $analisis = $this->analizar();

        $lleno = array_filter($analisis, fn ($a, $clave) => trim((string) ($this->campos[$clave] ?? '')) !== '', ARRAY_FILTER_USE_BOTH);
        if ($lleno === []) {
            $this->errorGeneral = 'Escribe al menos un valor para guardar.';

            return;
        }
        if (array_filter($lleno, fn ($a) => $a['error'] !== null) !== []) {
            $this->errorGeneral = 'Revisa los campos marcados antes de guardar.';
            $this->dispatch('registro-foco-error');

            return;
        }

        $procesador = ProcesadorMediciones::make();
        $emitir = [];
        try {
            DB::transaction(function () use ($lleno, $procesador, $astronauta, &$emitir) {
                $instante = now();
                foreach ($lleno as $clave => $a) {
                    try {
                        $ev = $procesador->registrar($astronauta, $clave, $a['valores'], 'manual', $instante, emitir: false);
                    } catch (ValidationException $e) {
                        $this->erroresGuardado[$clave] = collect($e->errors())->flatten()->first();
                        throw $e;
                    }
                    $emitir[] = [$clave, $ev->estado];
                }
            });
        } catch (ValidationException) {
            $this->errorGeneral = 'Revisa los campos marcados antes de guardar.';
            $this->dispatch('registro-foco-error');

            return;
        }

        foreach ($emitir as [$clave, $estado]) {
            // La difusión en tiempo real es accesoria: si Reverb no responde, los datos ya están guardados.
            try {
                MedicionRegistrada::dispatch($astronauta->id, $clave, $estado);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $n = count($emitir);
        $this->exito = $n === 1 ? 'Medición guardada.' : "{$n} mediciones guardadas.";
        $this->abierto = false;
        $this->dispatch('medicion-guardada', astronautaId: $astronauta->id);
        $this->dispatch('medicion-registrada', astronautaId: $astronauta->id);
    }

    public function render()
    {
        $astronauta = $this->astronautaId ? Astronauta::find($this->astronautaId) : null;

        return view('livewire.registrar-medicion', [
            'nombre' => $astronauta?->nombre ?? TripulanteActual::obtener()?->nombre,
            'indicadores' => config('panel.indicadores'),
            'analisis' => $this->abierto ? $this->analizar() : [],
        ]);
    }
}
