<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\ContactoEmergencia;
use App\Models\ExpedienteMedico;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use App\Services\Tripulacion\ReglasExpediente;
use App\Support\TripulanteActual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Asistente de 5 pasos para dar de alta un tripulante en la misión actual:
 * 1 identidad · 2 biometría y línea base · 3 historia clínica · 4 contactos · 5 revisión.
 *
 * El borrador vive en las propiedades del componente (no se guarda nada hasta `guardar()`).
 * La validación y el guardado son de App\Services\Tripulacion (ReglasExpediente / AltaTripulante).
 * Los campos escalares son string (lo que envía el formulario); `payload()` los normaliza (vacío = null).
 */
#[Layout('layouts.app')]
class NuevoTripulante extends Component
{
    public const PASOS = [
        1 => ['Identity', 'badge'],
        2 => ['Biometrics and baseline', 'monitor_weight'],
        3 => ['Clinical history', 'clinical_notes'],
        4 => ['Contacts', 'contact_phone'],
        5 => ['Review', 'fact_check'],
    ];

    /** Texto de ayuda bajo el título de cada paso. */
    public const PASOS_DESC = [
        1 => 'Personal and service details of the crew member.',
        2 => 'Measurements, habits, and resting values before the mission.',
        3 => 'Flight fitness, allergies, conditions, medications, and family history. The app provides guidance only; it does not diagnose.',
        4 => 'Whom to notify if something goes wrong. Mark exactly one as primary.',
        5 => 'Check the data before saving. Missing recommended data does not prevent saving.',
    ];

    /** Campos numéricos: se acepta coma decimal. */
    private const NUMERICOS = [
        'altura_m', 'peso_kg', 'evas', 'fc_reposo', 'fc_maxima', 'presion_basal_sis', 'presion_basal_dia', 'spo2_basal',
        'temperatura_basal', 'glucosa_basal', 'vo2max', 'grasa_corporal_pct', 'densidad_osea_tscore',
        'horas_sueno_habituales', 'actividad_fisica_semana_min', 'dosis_radiacion_previa_msv',
    ];

    // ---------- Navegación ----------

    /** Paso actual (1-5). */
    public int $paso = 1;

    /** Pasos ya validados con "Siguiente". @var int[] */
    public array $pasosValidos = [];

    // ---------- Paso 1: identidad ----------

    public string $nombre = '';
    public string $fecha_nacimiento = '';
    public string $sexo = '';
    public string $nacionalidad = '';
    public string $agencia = '';
    public string $rol = '';
    public string $fecha_ingreso = '';
    public string $estado_servicio = 'activo';

    // ---------- Paso 2: biometría, signos basales, hábitos y línea base ----------

    public string $altura_m = '';
    public string $peso_kg = '';
    public string $grupo_sanguineo = '';
    public string $evas = '0';
    public string $fc_reposo = '';
    public string $fc_maxima = '';
    public string $presion_basal_sis = '';
    public string $presion_basal_dia = '';
    public string $spo2_basal = '';
    public string $temperatura_basal = '';
    public string $glucosa_basal = '';
    public string $vo2max = '';
    public string $grasa_corporal_pct = '';
    public string $densidad_osea_tscore = '';
    public string $horas_sueno_habituales = '';
    /** '1' = sí, '0' = no. */
    public string $fumador = '0';
    public string $alcohol = '';
    public string $actividad_fisica_semana_min = '';
    public string $dosis_radiacion_previa_msv = '0';

    /** Línea base personal: clave de marcador (config/indicadores) => valor escrito. @var array<string, string> */
    public array $linea_base = [];

    // ---------- Paso 3: historia clínica ----------

    public string $aptitud_vuelo = 'pendiente';
    public string $restricciones = '';
    public string $ultimo_examen_en = '';
    public string $notas = '';

    /** @var array<int, array{sustancia:string, reaccion:string, gravedad:string}> */
    public array $alergias = [];

    /** @var array<int, array{nombre:string, estado:string, desde:string, notas:string}> */
    public array $condiciones = [];

    /** Registro de lo que toma o ha tomado; NO es una receta. @var array<int, array{nombre:string, dosis_texto:string, frecuencia:string, motivo:string, desde:string, activo:bool}> */
    public array $medicamentos = [];

    /** @var array<int, array{parentesco:string, condicion:string, edad_aparicion:string}> */
    public array $antecedentes = [];

    // ---------- Paso 4: contactos de emergencia ----------

    /** @var array<int, array{nombre:string, parentesco:string, telefono:string, principal:bool}> */
    public array $contactos = [];

    public function mount(): void
    {
        $this->linea_base = array_fill_keys(array_keys(config('indicadores.marcadores', [])), '');
    }

    // ---------- Datos derivados (para la vista) ----------

    #[Computed]
    public function mision(): ?Mision
    {
        return TripulanteActual::mision();
    }

    #[Computed]
    public function cupoLleno(): bool
    {
        return $this->mision ? AltaTripulante::cupoLleno($this->mision) : false;
    }

    /** @return array<int, array{0:string, 1:string}> número => [título, icono] */
    #[Computed]
    public function pasos(): array
    {
        return self::PASOS;
    }

    /** Edad calculada con la fecha de nacimiento (null si falta o no es válida). */
    #[Computed]
    public function edad(): ?int
    {
        $f = $this->fecha($this->fecha_nacimiento);

        return ($f && $f->isPast()) ? (int) $f->diffInYears(now()) : null;
    }

    /** IMC calculado con altura y peso (null si falta alguno o no son válidos). */
    #[Computed]
    public function imc(): ?float
    {
        $h = $this->numero($this->altura_m);
        $p = $this->numero($this->peso_kg);

        return ($h && $p && $h > 0) ? round($p / ($h * $h), 1) : null;
    }

    /** Descripción del paso actual. */
    #[Computed]
    public function descripcionPaso(): string
    {
        return self::PASOS_DESC[$this->paso] ?? '';
    }

    /** Iniciales (máx. 2) del nombre escrito; '··' si está vacío. */
    #[Computed]
    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/u', trim($this->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ini = collect($palabras)->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

        return $ini !== '' ? $ini : '··';
    }

    /** "Rol · Agencia" para el resumen. */
    #[Computed]
    public function rolResumen(): string
    {
        return collect([$this->rol, $this->agencia])->map(fn ($v) => trim($v))->filter()->implode(' · ') ?: 'Role not set';
    }

    /** Tono del IMC: estado (ok|warn|crit), etiqueta e icono; null si no hay IMC. */
    #[Computed]
    public function tonoImc(): ?array
    {
        $v = $this->imc;
        if ($v === null) {
            return null;
        }
        $estado = $v >= 30 ? 'crit' : (($v < 18.5 || $v >= 25) ? 'warn' : 'ok');

        return ['estado' => $estado] + ['ok' => ['etiqueta' => 'Normal', 'icono' => 'check_circle'],
            'warn' => ['etiqueta' => 'Caution', 'icono' => 'warning'],
            'crit' => ['etiqueta' => 'Critical', 'icono' => 'error']][$estado];
    }

    /** Pasos (1-5) que tienen errores de validación visibles. @return int[] */
    #[Computed]
    public function pasosConError(): array
    {
        return $this->pasoConErrores();
    }

    /** Estado de cada paso para el indicador: actual | actual-error | completo | pendiente. @return array<int, string> */
    #[Computed]
    public function estadosPasos(): array
    {
        $conError = $this->pasosConError;
        $estados = [];
        foreach (array_keys(self::PASOS) as $n) {
            $estados[$n] = $n === $this->paso
                ? (in_array($n, $conError, true) || ($n === 5 && $this->getErrorBag()->isNotEmpty()) ? 'actual-error' : 'actual')
                : (in_array($n, $this->pasosValidos, true) ? 'completo' : 'pendiente');
        }

        return $estados;
    }

    /**
     * Campos con error para el resumen: cada uno con el id del campo (para enlazar/enfocar), etiqueta legible y mensaje.
     *
     * @return array<int, array{clave:string, uid:string, etiqueta:string, mensaje:string}>
     */
    #[Computed]
    public function erroresResumen(): array
    {
        $atributos = ReglasExpediente::atributos();
        $items = [];
        foreach ($this->getErrorBag()->toArray() as $clave => $mensajes) {
            $generica = preg_replace('/\.\d+(?=\.|$)/', '.*', $clave);
            $nombre = $atributos[$clave] ?? $atributos[$generica] ?? str_replace(['_', '.'], ' ', $clave);
            $etiqueta = mb_strtoupper(mb_substr($nombre, 0, 1)).mb_substr($nombre, 1);
            if (preg_match('/\.(\d+)(?=\.|$)/', $clave, $m)) {
                $etiqueta .= ' (no. '.((int) $m[1] + 1).')';
            }
            $items[] = [
                'clave' => $clave,
                'uid' => 'nt-'.str_replace('.', '-', $clave),
                'etiqueta' => $etiqueta,
                'mensaje' => (string) ($mensajes[0] ?? ''),
            ];
        }

        return $items;
    }

    /** Resumen del borrador para el paso de revisión: datos normalizados y completitud estimada. */
    #[Computed]
    public function revision(): array
    {
        return ['datos' => $this->payload(), 'completitud' => $this->completitudPrevia()];
    }

    /** Número de paso al que pertenece cada campo con error (para saltar al primero). */
    protected function pasoConErrores(): array
    {
        $pasos = [];
        foreach (array_keys($this->getErrorBag()->toArray()) as $clave) {
            $raiz = explode('.', $clave)[0];
            foreach ([1, 2, 3, 4] as $p) {
                if (in_array($raiz, ReglasExpediente::camposPaso($p), true)) {
                    $pasos[$p] = true;
                }
            }
        }
        ksort($pasos);

        return array_keys($pasos);
    }

    // ---------- Navegación ----------

    public function siguiente(): void
    {
        if (! $this->mision || $this->paso >= 5) {
            return;
        }
        if (! $this->validarPaso($this->paso)) {
            $this->dispatch('nt-foco', destino: 'errores');

            return;
        }
        $this->pasosValidos = array_values(array_unique([...$this->pasosValidos, $this->paso]));
        $this->paso++;
        $this->dispatch('nt-foco', destino: 'titulo');
    }

    public function anterior(): void
    {
        if ($this->paso > 1) {
            $this->resetErrorBag();
            $this->paso--;
            $this->dispatch('nt-foco', destino: 'titulo');
        }
    }

    /** Solo a pasos ya recorridos o al siguiente a uno validado (todos los anteriores deben estar validados). */
    public function irAPaso(int $n): void
    {
        if (! isset(self::PASOS[$n]) || ! $this->mision) {
            return;
        }
        for ($p = 1; $p < $n; $p++) {
            if (! in_array($p, $this->pasosValidos, true)) {
                return;
            }
        }
        $this->resetErrorBag();
        $this->paso = $n;
        $this->dispatch('nt-foco', destino: 'titulo');
    }

    // ---------- Línea base ----------

    /** Rellena con los valores base de config/indicadores los marcadores que sigan vacíos; el usuario puede editarlos. */
    public function precargarLineaBase(): void
    {
        foreach (config('indicadores.marcadores', []) as $clave => $m) {
            if (trim((string) ($this->linea_base[$clave] ?? '')) === '' && isset($m['base'])) {
                $this->linea_base[$clave] = number_format((float) $m['base'], (int) ($m['dec'] ?? 0), '.', '');
            }
        }
    }

    public function vaciarLineaBase(): void
    {
        $this->linea_base = array_fill_keys(array_keys(config('indicadores.marcadores', [])), '');
    }

    // ---------- Listas repetibles ----------

    public function agregarAlergia(): void
    {
        $this->agregar('alergias', ['sustancia' => '', 'reaccion' => '', 'gravedad' => 'leve']);
    }

    public function quitarAlergia(int $i): void
    {
        $this->quitar('alergias', $i);
    }

    public function agregarCondicion(): void
    {
        $this->agregar('condiciones', ['nombre' => '', 'estado' => 'activa', 'desde' => '', 'notas' => '']);
    }

    public function quitarCondicion(int $i): void
    {
        $this->quitar('condiciones', $i);
    }

    public function agregarMedicamento(): void
    {
        $this->agregar('medicamentos', ['nombre' => '', 'dosis_texto' => '', 'frecuencia' => '', 'motivo' => '', 'desde' => '', 'activo' => true]);
    }

    public function quitarMedicamento(int $i): void
    {
        $this->quitar('medicamentos', $i);
    }

    public function agregarAntecedente(): void
    {
        $this->agregar('antecedentes', ['parentesco' => '', 'condicion' => '', 'edad_aparicion' => '']);
    }

    public function quitarAntecedente(int $i): void
    {
        $this->quitar('antecedentes', $i);
    }

    /** El primer contacto queda como principal. */
    public function agregarContacto(): void
    {
        $primero = $this->contactos === [];
        $this->agregar('contactos', ['nombre' => '', 'parentesco' => '', 'telefono' => '', 'principal' => $primero]);
    }

    /** Si se quita el principal y quedan otros, el primero pasa a ser el principal. */
    public function quitarContacto(int $i): void
    {
        $this->quitar('contactos', $i);
        if ($this->contactos !== [] && ! collect($this->contactos)->contains('principal', true)) {
            $this->contactos[0]['principal'] = true;
        }
    }

    /** Solo un contacto puede ser el principal: marcar uno desmarca los demás. */
    public function marcarPrincipal(int $i): void
    {
        if (! isset($this->contactos[$i])) {
            return;
        }
        foreach (array_keys($this->contactos) as $k) {
            $this->contactos[$k]['principal'] = ($k === $i);
        }
    }

    // ---------- Guardar / cancelar ----------

    public function guardar(): void
    {
        $mision = $this->mision;
        if (! $mision || $this->cupoLleno) {
            return;
        }

        // Valida cada paso en orden; al primer fallo se vuelve a ese paso con sus errores.
        foreach ([1, 2, 3, 4] as $p) {
            if (! $this->validarPaso($p)) {
                $this->paso = $p;
                $this->dispatch('nt-foco', destino: 'errores');

                return;
            }
        }
        $this->resetErrorBag();

        try {
            $astronauta = app(AltaTripulante::class)->registrar($this->payload(), $mision);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->paso = $this->pasoConErrores()[0] ?? 5;
            $this->dispatch('nt-foco', destino: 'errores');

            return;
        }

        TripulanteActual::fijar($astronauta->id);
        session()->flash('tripulante_creado', $astronauta->nombre);
        $this->dispatch('tripulante-cambiado');
        $this->redirect(route('tripulacion'), navigate: false);
    }

    public function cancelar(): void
    {
        $this->redirect(route('tripulacion'), navigate: false);
    }

    // ---------- Internos ----------

    /** Valida un paso con ReglasExpediente; deja los errores en el error bag. */
    protected function validarPaso(int $paso): bool
    {
        $v = Validator::make(
            $this->payload(),
            ReglasExpediente::reglasPaso($paso),
            ReglasExpediente::mensajes(),
            ReglasExpediente::atributos()
        );
        $this->resetErrorBag();
        if ($v->fails()) {
            $this->setErrorBag($v->errors());

            return false;
        }

        return true;
    }

    /** Borrador normalizado con las claves exactas que espera AltaTripulante::registrar(). */
    protected function payload(): array
    {
        $texto = fn ($v) => ($t = trim((string) $v)) === '' ? null : $t;
        $num = fn ($v) => ($t = str_replace(',', '.', trim((string) $v))) === '' ? null : $t;

        $datos = [];
        foreach (['nombre', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'agencia', 'rol', 'fecha_ingreso', 'estado_servicio',
            'grupo_sanguineo', 'alcohol', 'aptitud_vuelo', 'restricciones', 'ultimo_examen_en', 'notas'] as $c) {
            $datos[$c] = $texto($this->{$c});
        }
        foreach (self::NUMERICOS as $c) {
            $datos[$c] = $num($this->{$c});
        }
        $datos['fumador'] = $this->fumador === '1';

        $datos['linea_base'] = collect($this->linea_base)
            ->map($num)->filter(fn ($v) => $v !== null)->all();

        $filas = fn (array $lista, array $textos, array $numeros = [], array $bools = []) => collect($lista)->map(function ($f) use ($textos, $numeros, $bools, $texto, $num) {
            $fila = [];
            foreach ($textos as $c) {
                $fila[$c] = $texto($f[$c] ?? null);
            }
            foreach ($numeros as $c) {
                $fila[$c] = $num($f[$c] ?? null);
            }
            foreach ($bools as $c) {
                $fila[$c] = (bool) ($f[$c] ?? false);
            }

            return $fila;
        })->values()->all();

        $datos['alergias'] = $filas($this->alergias, ['sustancia', 'reaccion', 'gravedad']);
        $datos['condiciones'] = $filas($this->condiciones, ['nombre', 'estado', 'desde', 'notas']);
        $datos['medicamentos'] = $filas($this->medicamentos, ['nombre', 'dosis_texto', 'frecuencia', 'motivo', 'desde'], [], ['activo']);
        $datos['antecedentes'] = $filas($this->antecedentes, ['parentesco', 'condicion'], ['edad_aparicion']);
        $datos['contactos'] = $filas($this->contactos, ['nombre', 'parentesco', 'telefono'], [], ['principal']);

        return $datos;
    }

    /** Completitud estimada sobre un Astronauta sin guardar (mismos criterios que AltaTripulante::completitud). */
    protected function completitudPrevia(): ?array
    {
        try {
            $d = $this->payload();
            $a = new Astronauta(collect($d)->only(['fecha_nacimiento', 'nacionalidad', 'agencia', 'altura_m', 'peso_kg', 'grupo_sanguineo'])->all());
            $e = new ExpedienteMedico(collect($d)->only((new ExpedienteMedico)->getFillable())->except('astronauta_id')->all());
            $e->aptitud_vuelo = $d['aptitud_vuelo'] ?? 'pendiente';
            $a->setRelation('expediente', $e);
            foreach (['alergias', 'condiciones', 'medicamentos', 'antecedentes'] as $r) {
                $a->setRelation($r, collect());
            }
            $a->setRelation('contactos', collect($d['contactos'])->map(fn ($c) => new ContactoEmergencia($c)));

            return app(AltaTripulante::class)->completitud($a);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function agregar(string $lista, array $fila): void
    {
        $max = (int) config("expediente.max_items.$lista", 30);
        if (count($this->{$lista}) < $max) {
            $this->{$lista}[] = $fila;
        }
    }

    protected function quitar(string $lista, int $i): void
    {
        if (isset($this->{$lista}[$i])) {
            unset($this->{$lista}[$i]);
            $this->{$lista} = array_values($this->{$lista});
            $this->resetErrorBag();
        }
    }

    protected function fecha(string $v): ?Carbon
    {
        try {
            return trim($v) === '' ? null : Carbon::createFromFormat('Y-m-d', trim($v))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function numero(string $v): ?float
    {
        $v = str_replace(',', '.', trim($v));

        return is_numeric($v) ? (float) $v : null;
    }

    public function render()
    {
        return view('livewire.nuevo-tripulante');
    }
}
