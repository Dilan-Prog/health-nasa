<?php

namespace App\Livewire;

use App\Models\Astronauta;
use App\Services\Salud\DatosIndicador;
use App\Services\Tripulacion\AltaTripulante;
use App\Support\TripulanteActual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha de expediente (solo lectura) de un tripulante: cabecera, línea base personal frente al último valor medido,
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

    public function mount(Astronauta $astronauta): void
    {
        abort_unless(TripulanteActual::tripulacion()->contains('id', $astronauta->id), 404);
        $this->astronautaId = $astronauta->id;
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
