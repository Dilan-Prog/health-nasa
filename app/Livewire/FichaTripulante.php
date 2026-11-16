<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Models\ExamenAscan;
use App\Models\LineaBase;
use App\Models\Mision;
use App\Services\Salud\DatosIndicador;
use App\Services\Salud\DesviacionBase;
use App\Services\Tripulacion\AltaTripulante;
use App\Support\TripulanteActual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha de expediente de un tripulante (lectura; solo se editan línea base pre-vuelo y ficha ASCAN): cabecera, línea base personal frente al último valor medido,
 * biometría y listas del expediente. La edición aún no está disponible. Orienta, no diagnostica.
 * El tripulante debe pertenecer a la misión actual (si no, 404).
 */
#[Layout('layouts.app')]
class FichaTripulante extends Component
{
    public const ESTADOS = [
        'ok' => ['Normal', 'check_circle'],
        'warn' => ['Precaución', 'warning'],
        'crit' => ['Crítico', 'error'],
        'neu' => ['Sin dato', 'remove'],
    ];

    private const APTITUD_TONO = ['apto' => 'ok', 'apto_restricciones' => 'warn', 'no_apto' => 'crit', 'pendiente' => 'neu'];

    private const GRAVEDAD_TONO = ['leve' => 'ok', 'moderada' => 'warn', 'grave' => 'crit'];

    private const MENOS = "\u{2212}";

    public int $astronautaId;

    /** Formulario de línea base pre-vuelo: métrica => [valor, unidad, desde, hasta, fuente] (texto). */
    public array $lb = [];

    /** Formulario ASCAN: clave de prueba => [resultado, fecha, estado]. */
    public array $asc = [];

    public ?string $okLb = null;

    public ?string $okAsc = null;

    public function mount(Astronauta $astronauta): void
    {
        abort_unless(TripulanteActual::tripulacion()->contains('id', $astronauta->id), 404);
        $this->astronautaId = $astronauta->id;
        $this->cargarFormularios($astronauta);
    }

    private function cargarFormularios(Astronauta $a): void
    {
        $inicio = $a->mision_id ? Mision::find($a->mision_id)?->inicio_en : null;
        $v = config('linea_base.ventana');
        $desde = $inicio?->copy()->subDays($v['desde_dias'])->toDateString() ?? '';
        $hasta = $inicio?->copy()->subDays($v['hasta_dias'])->toDateString() ?? '';

        $filas = LineaBase::where('astronauta_id', $a->id)->get()->keyBy('metrica');
        $this->lb = [];
        foreach (config('linea_base.metricas') as $k => $cfg) {
            $f = $filas->get($k);
            $this->lb[$k] = [
                'valor' => $f ? (string) (float) $f->valor : '',
                'unidad' => $f ? (string) $f->unidad : $cfg['unidad'],
                'desde' => $f?->ventana_desde?->toDateString() ?? $desde,
                'hasta' => $f?->ventana_hasta?->toDateString() ?? $hasta,
                'fuente' => $f ? (string) $f->fuente : '',
            ];
        }

        $examenes = ExamenAscan::where('astronauta_id', $a->id)->get()->keyBy('prueba');
        $this->asc = [];
        foreach (array_keys($this->pruebasAscan()) as $k) {
            $e = $examenes->get($k);
            $this->asc[$k] = [
                'resultado' => $e ? (string) $e->resultado : '',
                'fecha' => $e?->fecha?->toDateString() ?? '',
                'estado' => $e->estado ?? 'pendiente',
            ];
        }
    }

    /** @return array<string, string> clave => nombre */
    private function pruebasAscan(): array
    {
        return collect(config('ascan.grupos'))->flatMap(fn ($g) => $g['pruebas'])->all();
    }

    /** Guarda la línea base pre-vuelo. Valor vacío = sin base para esa métrica (se elimina la fila si existía). */
    public function guardarLineaBase(): void
    {
        $this->okLb = null;
        $metricas = config('linea_base.metricas');
        $reglas = [];
        foreach (array_keys($metricas) as $k) {
            $this->lb[$k]['valor'] = str_replace(',', '.', trim((string) ($this->lb[$k]['valor'] ?? '')));
            $reglas["lb.$k.valor"] = 'nullable|numeric|min:0|max:99999';
            $reglas["lb.$k.unidad"] = 'nullable|string|max:20';
            $reglas["lb.$k.fuente"] = 'nullable|string|max:120';
            $reglas["lb.$k.desde"] = 'nullable|date';
            $reglas["lb.$k.hasta"] = "nullable|date|after_or_equal:lb.$k.desde";
        }
        $this->validate($reglas, [
            'lb.*.valor.numeric' => 'El valor debe ser un número.',
            'lb.*.valor.min' => 'El valor no puede ser negativo.',
            'lb.*.valor.max' => 'El valor es demasiado grande.',
            'lb.*.unidad.max' => 'La unidad admite hasta 20 caracteres.',
            'lb.*.fuente.max' => 'La fuente admite hasta 120 caracteres.',
            'lb.*.desde.date' => 'Fecha no válida.',
            'lb.*.hasta.date' => 'Fecha no válida.',
            'lb.*.hasta.after_or_equal' => 'La ventana debe terminar el mismo día o después de empezar.',
        ]);

        $a = $this->tripulante();
        foreach (array_keys($metricas) as $k) {
            $f = $this->lb[$k];
            if ($f['valor'] === '') {
                LineaBase::where('astronauta_id', $a->id)->where('metrica', $k)->delete();

                continue;
            }
            LineaBase::updateOrCreate(['astronauta_id' => $a->id, 'metrica' => $k], [
                'valor' => (float) $f['valor'],
                'unidad' => filled($f['unidad']) ? $f['unidad'] : null,
                'ventana_desde' => filled($f['desde']) ? $f['desde'] : null,
                'ventana_hasta' => filled($f['hasta']) ? $f['hasta'] : null,
                'fuente' => filled($f['fuente']) ? $f['fuente'] : null,
            ]);
        }
        $this->okLb = 'Línea base guardada.';
    }

    /** Guarda la ficha ASCAN. Solo registra: sin cálculo ni interpretación. */
    public function guardarAscan(): void
    {
        $this->okAsc = null;
        $reglas = [];
        foreach (array_keys($this->pruebasAscan()) as $k) {
            $reglas["asc.$k.resultado"] = 'nullable|string|max:255';
            $reglas["asc.$k.fecha"] = 'nullable|date';
            $reglas["asc.$k.estado"] = 'required|in:'.implode(',', ExamenAscan::ESTADOS);
        }
        $this->validate($reglas, [
            'asc.*.resultado.max' => 'El resultado admite hasta 255 caracteres.',
            'asc.*.fecha.date' => 'Fecha no válida.',
            'asc.*.estado.in' => 'Estado no válido.',
            'asc.*.estado.required' => 'Elige un estado.',
        ]);

        $a = $this->tripulante();
        foreach (array_keys($this->pruebasAscan()) as $k) {
            $f = $this->asc[$k];
            if (blank($f['resultado']) && blank($f['fecha']) && $f['estado'] === 'pendiente') {
                ExamenAscan::where('astronauta_id', $a->id)->where('prueba', $k)->delete();

                continue;
            }
            ExamenAscan::updateOrCreate(['astronauta_id' => $a->id, 'prueba' => $k], [
                'resultado' => filled($f['resultado']) ? $f['resultado'] : null,
                'fecha' => filled($f['fecha']) ? $f['fecha'] : null,
                'estado' => $f['estado'],
            ]);
        }
        $this->okAsc = 'Ficha ASCAN guardada.';
    }

    private function tripulante(): Astronauta
    {
        $a = Astronauta::findOrFail($this->astronautaId);
        abort_unless(TripulanteActual::tripulacion()->contains('id', $a->id), 404);

        return $a;
    }

    public function render()
    {
        $a = Astronauta::with(['expediente', 'alergias', 'condiciones', 'medicamentos', 'antecedentes', 'contactos'])
            ->findOrFail($this->astronautaId);
        $e = $a->expediente;
        $edad = $a->edadCalculada();

        return view('livewire.ficha-tripulante', [
            'a' => $a,
            'iniciales' => $this->iniciales($a->nombre),
            'subtitulo' => collect([$a->rol, $edad !== null ? $edad.' años' : null, $a->agencia, $a->nacionalidad])->filter()->implode(' · '),
            'pildoras' => $this->pildoras($a),
            'restriccion' => $e && $e->aptitud_vuelo === 'apto_restricciones' && filled($e->restricciones) ? $e->restricciones : null,
            'comp' => app(AltaTripulante::class)->completitud($a),
            'linea' => $this->lineaBase($a),
            'lbMetricas' => $this->metricasBase($a),
            'ascGrupos' => config('ascan.grupos'),
            'ascEstados' => ['pendiente' => 'Pendiente', 'normal' => 'Normal', 'anormal' => 'Anormal'],
            'bio' => $this->biometria($a),
            'listas' => $this->listas($a),
        ]);
    }

    private function iniciales(?string $n): string
    {
        $p = preg_split('/\s+/u', trim((string) $n), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $p === [] ? '··' : mb_strtoupper(collect($p)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }

    /** @return array<int, array{l:string, ic:string, tono:string}> */
    private function pildoras(Astronauta $a): array
    {
        $out = [];
        $apt = $a->expediente?->aptitud_vuelo;
        if ($apt && isset(config('expediente.aptitudes')[$apt])) {
            $t = self::APTITUD_TONO[$apt] ?? 'neu';
            $out[] = ['l' => config('expediente.aptitudes')[$apt], 'ic' => self::ESTADOS[$t][1], 'tono' => $t];
        }
        foreach ($a->alergias->where('gravedad', 'grave') as $al) {
            $out[] = ['l' => 'Alergia grave: '.$al->sustancia, 'ic' => 'error', 'tono' => 'crit'];
        }
        if (filled($a->grupo_sanguineo)) {
            $out[] = ['l' => 'Grupo '.str_replace('-', self::MENOS, $a->grupo_sanguineo), 'ic' => 'bloodtype', 'tono' => 'neu'];
        }

        return $out;
    }

    /**
     * Línea base del expediente frente al último valor medido real. Sin medición en la ventana de DatosIndicador
     * no se muestra valor ni estado (DatosIndicador rellenaría con la base de ejemplo del catálogo).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineaBase(Astronauta $a): array
    {
        $base = $a->expediente?->linea_base ?? [];
        try {
            $d = DatosIndicador::para($a);
            $desde = Carbon::parse($d['hoy'], 'UTC')->subDays($d['dias'] - 1)->toDateTimeString();
            $conDato = DB::table('mediciones')->where('astronauta_id', $a->id)->where('medido_en', '>=', $desde)
                ->distinct()->pluck('indicador')->all();
            $vivos = collect($d['marcadores'])->keyBy('id');
        } catch (\Throwable $ex) {
            report($ex);
            $conDato = [];
            $vivos = collect();
        }

        $filas = [];
        foreach (config('indicadores.marcadores', []) as $clave => $cfg) {
            $dec = (int) ($cfg['dec'] ?? 0);
            $b = $base[$clave] ?? null;
            $m = in_array($clave, $conDato, true) ? $vivos->get($clave) : null;
            $delta = null;
            if ($b !== null && $m) {
                $x = round((float) $m['val'] - (float) $b, 2);
                $txt = rtrim(rtrim(number_format(abs($x), 2, '.', ''), '0'), '.');
                $delta = $x == 0.0 ? '±0' : ($x > 0 ? '+' : self::MENOS).$txt;
            }
            $estado = $m['estado'] ?? 'neu';
            $filas[] = [
                'clave' => $clave, 'label' => $cfg['label'], 'ic' => $cfg['ic'], 'unit' => $cfg['unit'],
                'base' => $b !== null ? $this->num((float) $b, $dec).($clave === 'presion_arterial' ? ' (sist.)' : '') : null,
                'ultimo' => $m['disp'] ?? null,
                'delta' => $delta,
                'estado' => $estado, 'estado_txt' => self::ESTADOS[$estado][0], 'estado_ic' => self::ESTADOS[$estado][1],
            ];
        }

        return $filas;
    }

    /**
     * Valor actual y desviación % de cada métrica de línea base con indicador asociado y medición real.
     *
     * @return array<string, array{actual:?string, desv:?string, base:?string}>
     */
    private function metricasBase(Astronauta $a): array
    {
        $porMetrica = array_flip(config('linea_base.indicador_metrica', []));
        $out = [];
        foreach (array_keys(config('linea_base.metricas')) as $k) {
            $out[$k] = ['actual' => null, 'desv' => null, 'base' => null];
            $ind = $porMetrica[$k] ?? null;
            if ($ind === null) {
                continue;
            }
            $m = DB::table('mediciones')->where('astronauta_id', $a->id)->where('indicador', $ind)->orderByDesc('medido_en')->orderByDesc('id')->first();
            $actual = $m ? (json_decode($m->valores, true)['valor'] ?? null) : null;
            if ($actual === null) {
                continue;
            }
            $dec = (int) config("indicadores.marcadores.$ind.dec", 1);
            $out[$k]['actual'] = $this->num((float) $actual, $dec).' '.config("indicadores.marcadores.$ind.unit", '');
            $pct = DesviacionBase::porcentaje($a, $k, (float) $actual);
            if ($pct !== null) {
                $r = round($pct, 1);
                $out[$k]['desv'] = ($r > 0 ? '+' : ($r < 0 ? self::MENOS : '')).number_format(abs($r), 1, '.', '').' %';
            }
        }

        return $out;
    }

    private function num(float $v, int $dec): string
    {
        $t = number_format(abs($v), $dec, '.', '');

        return ($v < 0 && (float) $t != 0.0 ? self::MENOS : '').$t;
    }

    /** @return array<int, array{l:string, v:string, vacio:bool, tono:?string}> */
    private function biometria(Astronauta $a): array
    {
        $e = $a->expediente;
        $imc = $a->imc();
        $tonoImc = $imc === null ? null : ($imc >= 30 ? 'crit' : ($imc < 18.5 || $imc >= 25 ? 'warn' : 'ok'));
        $n = fn ($v, $suf = '') => $v !== null && $v !== '' ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.').$suf : null;

        $items = [
            ['Altura', $a->altura_m ? number_format($a->altura_m, 2, '.', '').' m' : null, null],
            ['Peso', $n($a->peso_kg, ' kg'), null],
            ['IMC', $imc !== null ? number_format($imc, 1, '.', '') : null, $tonoImc],
            ['EVAs previas', $a->evas !== null ? (string) $a->evas : null, null],
            ['FC reposo', $n($e?->fc_reposo, ' lpm'), null],
            ['FC máxima', $n($e?->fc_maxima, ' lpm'), null],
            ['Presión basal', $e?->presion_basal_sis && $e?->presion_basal_dia ? $e->presion_basal_sis.'/'.$e->presion_basal_dia.' mmHg' : null, null],
            ['SpO₂ basal', $n($e?->spo2_basal, ' %'), null],
            ['VO₂ máx', $n($e?->vo2max), null],
            ['Grasa corporal', $n($e?->grasa_corporal_pct, ' %'), null],
            ['Densidad ósea (T)', $n($e?->densidad_osea_tscore), null],
            ['Sueño habitual', $n($e?->horas_sueno_habituales, ' h'), null],
            ['Fumador', $e ? ($e->fumador ? 'Sí' : 'No') : null, null],
            ['Alcohol', $e && $e->alcohol ? (config('expediente.alcohol')[$e->alcohol] ?? $e->alcohol) : null, null],
            ['Último examen médico', $e?->ultimo_examen_en?->format('d/m/Y'), null],
        ];

        return array_map(fn ($i) => ['l' => $i[0], 'v' => $i[1] ?? 'Sin dato', 'vacio' => $i[1] === null, 'tono' => $i[2]], $items);
    }

    /** @return array<int, array<string, mixed>> */
    private function listas(Astronauta $a): array
    {
        $grav = config('expediente.gravedades');
        $fecha = fn ($d) => $d ? Carbon::parse($d)->format('d/m/Y') : null;
        $unir = fn (array $p) => collect($p)->filter(fn ($x) => filled($x))->implode(' · ');

        return [
            [
                'id' => 'alergias', 'titulo' => 'Alergias', 'ic' => 'allergy', 'aviso' => null, 'vacio' => 'Sin alergias registradas.',
                'filas' => $a->alergias->map(function ($r) use ($grav) {
                    $t = self::GRAVEDAD_TONO[$r->gravedad] ?? 'neu';

                    return [
                        'main' => $r->sustancia, 'sub' => 'Reacción: '.($r->reaccion ?: 'sin dato'), 'resalta' => $r->gravedad === 'grave',
                        'pill' => $r->gravedad ? ['l' => $grav[$r->gravedad] ?? $r->gravedad, 'ic' => self::ESTADOS[$t][1], 'tono' => $t] : null,
                    ];
                })->all(),
            ],
            [
                'id' => 'condiciones', 'titulo' => 'Condiciones médicas', 'ic' => 'medical_information', 'aviso' => null, 'vacio' => 'Sin condiciones registradas.',
                'filas' => $a->condiciones->map(fn ($r) => [
                    'main' => $r->nombre, 'resalta' => false, 'pill' => null,
                    'sub' => $unir([config('expediente.estados_condicion')[$r->estado] ?? $r->estado, $r->desde ? 'desde '.$fecha($r->desde) : null, $r->notas]),
                ])->all(),
            ],
            [
                'id' => 'medicamentos', 'titulo' => 'Medicamentos actuales', 'ic' => 'medication', 'vacio' => 'Sin medicamentos registrados.',
                'aviso' => 'Registro de lo que ya toma. La app no receta ni cambia dosis.',
                'filas' => $a->medicamentos->map(fn ($r) => [
                    'main' => trim($r->nombre.' '.$r->dosis_texto), 'sub' => $unir([$r->frecuencia, $r->motivo]), 'resalta' => false,
                    'pill' => $r->activo ? null : ['l' => 'Ya no lo toma', 'ic' => 'pause_circle', 'tono' => 'neu'],
                ])->all(),
            ],
            [
                'id' => 'antecedentes', 'titulo' => 'Antecedentes familiares', 'ic' => 'family_restroom', 'aviso' => null, 'vacio' => 'Sin antecedentes registrados.',
                'filas' => $a->antecedentes->map(fn ($r) => [
                    'main' => $r->condicion, 'resalta' => false, 'pill' => null,
                    'sub' => $unir([config('expediente.parentescos')[$r->parentesco] ?? $r->parentesco, $r->edad_aparicion !== null ? 'a los '.$r->edad_aparicion.' años' : null]),
                ])->all(),
            ],
            [
                'id' => 'contactos', 'titulo' => 'Contactos de emergencia', 'ic' => 'contact_phone', 'aviso' => null, 'vacio' => 'Sin contactos registrados.',
                'filas' => $a->contactos->sortByDesc('principal')->values()->map(fn ($r) => [
                    'main' => $r->nombre, 'resalta' => false,
                    'sub' => $unir([config('expediente.parentescos')[$r->parentesco] ?? $r->parentesco, $r->telefono]),
                    'pill' => $r->principal ? ['l' => 'Principal', 'ic' => 'star', 'tono' => 'acc'] : null,
                ])->all(),
            ],
        ];
    }
}
