<?php

namespace App\Livewire;

use App\Models\Rutina;
use App\Services\Salud\AgendaRutinas;
use App\Services\Salud\ResumenTripulante;
use App\Support\TripulanteActual;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla Rutinas (diseño "Panel tripulación"): semana LUN–DOM (UTC), agenda del día, progreso, plan de rutinas
 * (añadir/editar/eliminar), registro de actividades, historial y "Por qué importa" ligado a los indicadores reales.
 * Orienta, no prescribe: sin medicamentos ni dosis.
 */
#[Layout('layouts.app')]
class Rutinas extends Component
{
    public const ESTADOS = [
        'done' => ['HECHA', 'check_circle', 'ok'],
        'pending' => ['PENDIENTE', 'schedule', null],
        'late' => ['ATRASADA', 'alarm', 'warn'],
        'skipped' => ['OMITIDA', 'do_not_disturb_on', null],
    ];

    public const ESTADO3 = ['ok' => ['NORMAL', 'check_circle'], 'warn' => ['PRECAUCIÓN', 'warning'], 'crit' => ['CRÍTICO', 'error']];

    /** Día seleccionado (Y-m-d, dentro de la semana actual); vacío = hoy. */
    #[Url(as: 'dia', except: '')]
    public string $dia = '';

    /** null | actividad | rutina */
    public ?string $modal = null;

    public bool $error = false;

    // Formulario "Registrar actividad"
    public string $aTipo = 'Ejercicio resistido';
    public string $aCantidad = '';
    public string $aHora = '';
    public string $aInt = 'Media';
    public string $aNotas = '';

    // Formulario "Añadir / editar rutina"
    public ?int $rId = null;
    public string $rNombre = '';
    public string $rTipo = 'Ejercicio resistido';
    public string $rIcono = 'fitness_center';
    public string $rHora = '';
    public string $rCantidad = '';
    /** @var array<int, int|string> */
    public array $rDias = ['1', '2', '3', '4', '5', '6', '7'];
    public string $rIndicador = '';

    // --- Día ---------------------------------------------------------------------------------------------

    public function seleccionarDia(string $fecha): void
    {
        $lunes = AgendaRutinas::make()->lunesDe();
        $validas = collect(range(0, 6))->map(fn ($i) => $lunes->copy()->addDays($i)->toDateString());
        $this->dia = $validas->contains($fecha) ? $fecha : '';
    }

    // --- Tareas ------------------------------------------------------------------------------------------

    public function marcar(int $rutinaId): void
    {
        $this->cambiar($rutinaId, 'done');
    }

    public function omitir(int $rutinaId): void
    {
        $this->cambiar($rutinaId, 'skipped');
    }

    public function deshacer(int $rutinaId): void
    {
        [$a, $r] = $this->propia($rutinaId);
        if ($a && $r) {
            AgendaRutinas::make()->deshacer($a, $r, $this->fechaSeleccionada());
        }
    }

    private function cambiar(int $rutinaId, string $estado): void
    {
        [$a, $r] = $this->propia($rutinaId);
        if ($a && $r) {
            AgendaRutinas::make()->marcar($a, $r, $this->fechaSeleccionada(), $estado);
        }
    }

    /** @return array{0: ?\App\Models\Astronauta, 1: ?Rutina} la rutina solo si es del tripulante actual */
    private function propia(int $rutinaId): array
    {
        $a = TripulanteActual::obtener();

        return [$a, $a ? Rutina::where('astronauta_id', $a->id)->find($rutinaId) : null];
    }

    private function fechaSeleccionada(): Carbon
    {
        $agenda = AgendaRutinas::make();
        $lunes = $agenda->lunesDe();
        if ($this->dia && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dia)) {
            $f = Carbon::parse($this->dia, 'UTC')->startOfDay();
            if ($f->gte($lunes) && $f->lte($lunes->copy()->addDays(6))) {
                return $f;
            }
        }

        return $agenda->hoy();
    }

    // --- Modales -----------------------------------------------------------------------------------------

    public function abrirActividad(): void
    {
        $this->resetErrorBag();
        $this->aTipo = 'Ejercicio resistido';
        $this->aCantidad = '';
        $this->aHora = Carbon::now('UTC')->format('H:i');
        $this->aInt = 'Media';
        $this->aNotas = '';
        $this->modal = 'actividad';
    }

    public function abrirRutina(?int $id = null): void
    {
        $this->resetErrorBag();
        $this->rId = null;
        $this->rNombre = '';
        $this->rTipo = 'Ejercicio resistido';
        $this->rIcono = config('rutinas.tipos.Ejercicio resistido.icono');
        $this->rHora = '';
        $this->rCantidad = '';
        $this->rDias = ['1', '2', '3', '4', '5', '6', '7'];
        $this->rIndicador = '';
        if ($id) {
            [, $r] = $this->propia($id);
            if (! $r) {
                return;
            }
            $this->rId = $r->id;
            $this->rNombre = $r->nombre;
            $this->rTipo = $r->tipo;
            $this->rIcono = $r->icono;
            $this->rHora = $r->hora;
            $this->rCantidad = (string) $r->cantidad;
            $this->rDias = array_map('strval', $r->dias ?? []);
            $this->rIndicador = (string) $r->indicador_clave;
        }
        $this->modal = 'rutina';
    }

    public function cerrar(): void
    {
        $this->modal = null;
        $this->resetErrorBag();
    }

    /** Al cambiar el tipo de la rutina se propone su icono habitual. */
    public function updatedRTipo(string $tipo): void
    {
        if (isset(config('rutinas.tipos')[$tipo])) {
            $this->rIcono = config('rutinas.tipos.'.$tipo.'.icono');
        }
    }

    // --- Guardar -----------------------------------------------------------------------------------------

    public function guardarActividad(): void
    {
        $tipos = config('rutinas.tipos_actividad');
        $max = (int) config('rutinas.tipos.'.$this->aTipo.'.max', 720);
        $unidad = config('rutinas.tipos.'.$this->aTipo.'.unidad', 'min');
        $this->aCantidad = trim(str_replace(',', '.', $this->aCantidad));

        $this->validate([
            'aTipo' => ['required', 'in:'.implode(',', $tipos)],
            'aCantidad' => ['required', 'numeric', 'gt:0', 'max:'.$max],
            'aHora' => ['required', 'date_format:H:i'],
            'aInt' => ['nullable', 'in:'.implode(',', config('rutinas.intensidades'))],
            'aNotas' => ['nullable', 'string', 'max:200'],
        ], [
            'aCantidad.required' => $unidad === 'ml' ? 'Indica la cantidad' : 'Indica la duración',
            'aCantidad.numeric' => 'Debe ser un número mayor que 0',
            'aCantidad.gt' => 'Debe ser un número mayor que 0',
            'aCantidad.max' => 'Máximo '.$max.' '.$unidad,
            'aHora.required' => 'Indica la hora de la actividad',
            'aHora.date_format' => 'Indica la hora de la actividad',
            'aNotas.max' => 'Máximo 200 caracteres',
        ]);

        $a = TripulanteActual::obtener();
        if (! $a) {
            return;
        }
        $inicio = AgendaRutinas::make()->hoy()->setTimeFromTimeString($this->aHora);
        AgendaRutinas::make()->registrarActividad($a, $this->aTipo, (int) round((float) $this->aCantidad), $inicio, $this->aInt ?: null, $this->aNotas);
        $this->modal = null;
        $this->dispatch('actividad-registrada');
    }

    public function guardarRutina(): void
    {
        $tipos = array_keys(config('rutinas.tipos'));
        $max = (int) config('rutinas.tipos.'.$this->rTipo.'.max', 720);
        $unidad = config('rutinas.tipos.'.$this->rTipo.'.unidad', 'min');
        $kind = config('rutinas.tipos.'.$this->rTipo.'.kind', 'bien');
        $exigeCantidad = $kind === 'ej' && $this->rTipo !== 'Otro';
        $this->rCantidad = trim($this->rCantidad);
        $dias = array_values(array_unique(array_map('intval', array_filter($this->rDias, 'is_numeric'))));
        sort($dias);

        $this->validate([
            'rNombre' => ['required', 'string', 'max:120'],
            'rTipo' => ['required', 'in:'.implode(',', $tipos)],
            'rIcono' => ['required', 'in:'.implode(',', config('rutinas.iconos'))],
            'rHora' => ['required', 'date_format:H:i'],
            'rCantidad' => [$exigeCantidad ? 'required' : 'nullable', 'integer', $exigeCantidad ? 'min:1' : 'min:0', 'max:'.$max],
            'rDias' => ['required', 'array', 'min:1'],
            'rDias.*' => ['integer', 'between:1,7'],
            'rIndicador' => ['nullable', 'in:'.implode(',', array_keys(config('panel.indicadores', [])))],
        ], [
            'rNombre.required' => 'Pon un nombre a la rutina',
            'rNombre.max' => 'Máximo 120 caracteres',
            'rHora.required' => 'Indica la hora de la rutina',
            'rHora.date_format' => 'Indica la hora de la rutina',
            'rCantidad.required' => $unidad === 'ml' ? 'Indica la cantidad' : 'Indica la duración',
            'rCantidad.integer' => 'Debe ser un número entero',
            'rCantidad.min' => $exigeCantidad ? 'Debe ser mayor que 0' : 'No puede ser negativa',
            'rCantidad.max' => 'Máximo '.$max.' '.$unidad,
            'rDias.required' => 'Elige al menos un día',
            'rDias.min' => 'Elige al menos un día',
        ]);

        $a = TripulanteActual::obtener();
        if (! $a) {
            return;
        }
        $datos = [
            'nombre' => trim($this->rNombre), 'tipo' => $this->rTipo, 'icono' => $this->rIcono, 'kind' => $kind,
            'hora' => $this->rHora, 'cantidad' => (int) ($this->rCantidad === '' ? 0 : $this->rCantidad), 'dias' => $dias,
            'indicador_clave' => $this->rIndicador !== '' ? $this->rIndicador : null,
        ];
        if ($this->rId) {
            [, $r] = $this->propia($this->rId);
            if (! $r) {
                return;
            }
            if ($r->tipo === $this->rTipo) {
                $datos['kind'] = $r->kind; // se conserva la clase de una rutina del plan (p. ej. estiramientos = ejercicio)
            }
            $r->update($datos);
        } else {
            Rutina::create($datos + ['astronauta_id' => $a->id, 'activa' => true]);
        }
        $this->modal = null;
    }

    public function eliminarRutina(int $id): void
    {
        [, $r] = $this->propia($id);
        $r?->delete();
    }

    public function reintentar(): void
    {
        $this->error = false;
    }

    #[On('tripulante-cambiado')]
    public function refrescar(): void
    {
        $this->modal = null;
        $this->dia = '';
        $this->error = false;
    }

    // --- Render ------------------------------------------------------------------------------------------

    public function render()
    {
        $a = TripulanteActual::obtener();
        $datos = null;
        if ($a && ! $this->error) {
            try {
                $datos = $this->datos($a);
            } catch (\Throwable $e) {
                report($e);
                $this->error = true;
            }
        }

        return view('livewire.rutinas', ['astronauta' => $a, 'd' => $datos]);
    }

    private function datos($a): array
    {
        $agenda = AgendaRutinas::make();
        $hoy = $agenda->hoy();
        $ahora = Carbon::now('UTC');
        $lunes = $agenda->lunesDe($hoy);
        $semana = $agenda->semana($a, $lunes);
        $sel = $this->fechaSeleccionada();
        $idxSel = (int) $lunes->diffInDays($sel);
        $idxHoy = (int) $lunes->diffInDays($hoy);
        $cfg = config('rutinas');

        $dias = [];
        $hechasSemana = $totalSemana = 0;
        foreach ($semana as $i => $s) {
            $t = collect($s['tareas']);
            $hechas = $t->where('estado', 'done')->count();
            $hechasSemana += $hechas;
            $totalSemana += $t->count();
            $dias[] = [
                'fecha' => $s['fecha']->toDateString(), 'n' => $s['fecha']->day, 'abbr' => $i === $idxHoy ? 'HOY' : $cfg['dias'][$i + 1],
                'dia' => $cfg['dias'][$i + 1], 'mes' => $cfg['meses'][$s['fecha']->month], 'es_hoy' => $i === $idxHoy, 'sel' => $i === $idxSel,
                'kinds' => $t->pluck('kind')->unique()->values()->all(), 'hechas' => $hechas, 'total' => $t->count(),
                'frac' => $t->count() ? $hechas.'/'.$t->count() : '—',
            ];
        }
        $fin = $lunes->copy()->addDays(6);
        $semanaTxt = $lunes->day.($lunes->month !== $fin->month ? ' '.$cfg['meses'][$lunes->month] : '').'–'.$fin->day.' '.$cfg['meses'][$fin->month];

        $tareas = $semana[$idxSel]['tareas'];
        $hoyTareas = $semana[$idxHoy]['tareas'];
        $prog = $agenda->progreso($a, $hoyTareas);

        $m = TripulanteActual::mision();
        $diaMision = $m?->inicio_en ? max(1, (int) $m->inicio_en->copy()->startOfDay()->diffInDays($hoy, false) + 1) : null;
        $etiqueta = $cfg['dias_largo'][$hoy->isoWeekday()].' '.$hoy->day.' '.mb_strtoupper($cfg['meses'][$hoy->month]).' '.$hoy->year
            .($diaMision ? ' · DÍA '.$diaMision : '').' · '.$ahora->format('H:i').' UTC';

        return [
            'etiqueta_fecha' => $etiqueta,
            'hoy' => $hoy->toDateString(),
            'sel_fecha' => $sel->toDateString(),
            'sel_es_hoy' => $sel->eq($hoy),
            'sel_futuro' => $sel->gt($hoy),
            'agenda_titulo' => $sel->eq($hoy) ? 'Agenda de hoy' : 'Agenda · '.mb_strtolower($cfg['dias'][$idxSel + 1]).' '.$sel->day,
            'agenda_cuenta' => count($tareas) ? collect($tareas)->where('estado', 'done')->count().' de '.count($tareas).' hechas' : '',
            'tareas' => $tareas,
            'dias' => $dias,
            'semana_txt' => $semanaTxt,
            'semana_hechas' => $hechasSemana,
            'semana_total' => $totalSemana,
            'semana_pct' => $totalSemana ? (int) round($hechasSemana / $totalSemana * 100) : 0,
            'tarjetas' => $this->tarjetas($prog),
            'why' => $this->porQue($a, $hoyTareas),
            'historial' => $this->historial($agenda->historial($a, 8), $hoy),
            'plan' => Rutina::where('astronauta_id', $a->id)->orderBy('hora')->orderBy('id')->get(),
            'sin_rutinas' => ! Rutina::where('astronauta_id', $a->id)->exists(),
        ];
    }

    private function tarjetas(array $p): array
    {
        $metas = config('rutinas.metas');
        $ring = function (string $et, string $ic, float $v, float $meta, string $val, string $metaTxt, string $nota, string $clase) {
            $pct = max(0, min(1, $meta > 0 ? $v / $meta : 0));

            return ['label' => $et, 'ic' => $ic, 'value' => $val, 'goal' => $metaTxt, 'note' => $nota, 'clase' => $clase,
                'pct' => (int) round($pct * 100), 'deg' => (int) round($pct * 360).'deg', 'aria' => $et.': '.$val.' '.$metaTxt];
        };
        $ej = $p['ejercicio_min'];
        $agua = $p['agua_ml'] / 1000;
        $metaAgua = $metas['agua_ml'] / 1000;
        $sueno = $p['sueno_min'] !== null ? $p['sueno_min'] / 60 : null;
        $falta = $p['med_total'] - $p['med_hechas'];

        return [
            $ring('EJERCICIO', 'fitness_center', $ej, $metas['ejercicio_min'], (string) $ej, '/ '.$metas['ejercicio_min'].' min',
                $ej >= $metas['ejercicio_min'] ? 'Meta cumplida' : 'Faltan '.($metas['ejercicio_min'] - $ej).' min', 'ej'),
            $ring('HIDRATACIÓN', 'water_drop', $agua, $metaAgua, number_format($agua, 1, '.', ''), '/ '.number_format($metaAgua, 1, '.', '').' L',
                $agua >= $metaAgua ? 'Meta cumplida' : 'Faltan '.number_format($metaAgua - $agua, 1, '.', '').' L', 'med'),
            $ring('SUEÑO', 'bedtime', $sueno ?? 0, $metas['sueno_h'], $sueno !== null ? number_format($sueno, 1, '.', '') : '—', '/ '.$metas['sueno_h'].' h anoche',
                $sueno === null ? 'Sin registro de anoche' : ($sueno >= $metas['sueno_h'] ? 'Meta cumplida' : 'Por debajo de tu meta'), 'bien'),
            $ring('MEDICIONES', 'monitor_heart', $p['med_hechas'], max(1, $p['med_total']), $p['med_hechas'].'/'.$p['med_total'], 'hechas',
                $p['med_total'] === 0 ? 'Sin mediciones programadas' : ($falta > 0 ? $falta.' pendiente'.($falta > 1 ? 's' : '') : 'Todo al día'), 'ok'),
        ];
    }

    /** "Por qué importa": los indicadores con peor estado real entre los que motivan rutinas. */
    private function porQue($a, array $hoyTareas): array
    {
        $resumen = ResumenTripulante::make()->de($a);
        $porClave = collect($resumen['indicadores'])->keyBy('clave');
        $rank = ['crit' => 3, 'warn' => 2, 'ok' => 1];
        $out = [];
        foreach (config('rutinas.why') as $clave => $w) {
            $ind = $porClave->get($clave);
            if (! $ind || ! $ind['estado']) {
                continue;
            }
            $tarea = collect($hoyTareas)->firstWhere('indicador_clave', $clave);
            $out[] = [
                'clave' => $clave, 'indicador' => $w['etiqueta'], 'ic' => $w['icono'], 'accion' => $w['accion'], 'porque' => $w['porque'],
                'valor' => $ind['valor'], 'unidad' => $ind['unidad'], 'razon' => $ind['razon'], 'estado' => $ind['estado'],
                'estado_txt' => self::ESTADO3[$ind['estado']], 'rank' => $rank[$ind['estado']],
                'tarea' => $tarea,
            ];
        }
        usort($out, fn ($x, $y) => $y['rank'] <=> $x['rank']); // estable: a igual estado, orden de config

        return array_slice($out, 0, (int) config('rutinas.why_max', 2));
    }

    private function historial($actividades, Carbon $hoy): array
    {
        return $actividades->map(function ($act) use ($hoy) {
            $cfg = config('rutinas.tipos.'.$act->tipo, ['icono' => 'more_horiz', 'kind' => 'bien']);
            $sueno = $act->tipo === 'Sueño' && $act->unidad === 'min';
            $fin = $sueno ? $act->inicio_en->copy()->addMinutes($act->cantidad) : $act->inicio_en;
            $dif = (int) $fin->copy()->startOfDay()->diffInDays($hoy, false);
            $cuando = $dif === 0 ? 'Hoy' : ($dif === 1 ? 'Ayer' : ucfirst(mb_strtolower(config('rutinas.dias.'.$fin->isoWeekday()))).' '.$fin->day);
            $hora = $sueno ? $act->inicio_en->format('H:i').'→'.$fin->format('H:i') : $act->inicio_en->format('H:i');
            $cant = $act->cantidad;
            $cantTxt = $act->unidad === 'ml' ? $cant.' ml' : ($cant >= 60 ? intdiv($cant, 60).' h'.($cant % 60 ? ' '.($cant % 60).' min' : '') : $cant.' min');

            return [
                'ic' => $cfg['icono'], 'kind' => $cfg['kind'], 'nombre' => $act->nombre.($act->nota ? ' · '.$act->nota : ''),
                'cuando' => $cuando.' · '.$hora, 'cantidad' => $cantTxt, 'origen' => $act->origen === 'manual' ? 'Manual' : 'Recordatorio',
                'origen_ic' => $act->origen === 'manual' ? 'edit' : 'notifications',
            ];
        })->all();
    }
}
