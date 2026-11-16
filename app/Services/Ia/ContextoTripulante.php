<?php

namespace App\Services\Ia;

use App\Models\Actividad;
use App\Models\Astronauta;
use App\Models\BotiquinItem;
use App\Models\CasoClinico;
use App\Models\ConsultaTierra;
use App\Models\Evaluacion;
use App\Models\Mision;
use App\Models\OrientacionFeedback;
use App\Models\RegistroSalud;
use App\Models\ZonaDolor;
use App\Services\Auditoria;
use App\Services\Salud\AgendaRutinas;
use App\Services\Salud\DesviacionBase;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\LineaBasePersonal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Instantánea de SOLO LECTURA de un tripulante para el orientador (contrato "Contexto" del brief del orientador).
 *
 * - Solo datos del astronauta pedido. Si el usuario actual no puede ver a ese tripulante (Astronauta::visiblesPara),
 *   todas las secciones salen vacías y se indica en 'usados'.
 * - Pocas consultas, agregadas (sin N+1). La sección de indicadores se cachea unos segundos con una clave que depende de
 *   la última medición; el expediente (cifrado) y el resto NUNCA se cachean.
 * - Cada uso del expediente se audita con Auditoria::registrar('ver', 'orientador', ...), sin valores médicos.
 * - Las mediciones 'sospechosas' no cuentan para valores, series ni base personal: solo marcan 'sospechoso'.
 * - Orienta, no diagnostica: esta clase solo reúne datos.
 */
class ContextoTripulante
{
    private const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];

    private const TIPOS_EJERCICIO = ['Ejercicio resistido', 'Cardio', 'EVA'];

    public static function de(Astronauta $a): array
    {
        return (new self())->armar($a);
    }

    public function armar(Astronauta $a): array
    {
        $vacio = $this->vacio($a);
        if (! Astronauta::visiblesPara(Auth::user())->whereKey($a->id)->exists()) {
            $vacio['usados'][] = ['etiqueta' => 'Acceso', 'detalle' => 'Sin permiso para ver los datos de este tripulante: no se usó ningún dato.'];

            return $vacio;
        }

        $usados = [];
        $mision = $a->mision_id ? Mision::find($a->mision_id) : null;
        $ahora = Carbon::now('UTC');

        $expediente = $this->expediente($a, $usados);
        $indicadores = $this->indicadores($a, $mision, $usados);
        $rutina = $this->rutina($a, $ahora, $indicadores, $usados);

        return [
            'astronauta' => ['id' => $a->id, 'nombre' => $a->nombre, 'edad' => $a->edadCalculada(), 'sexo' => $a->sexo],
            'expediente' => $expediente,
            'indicadores' => $indicadores,
            'casos' => $this->casos($a, $ahora, $usados),
            'sintomas_registrados' => $this->sintomas($a, $ahora, $usados),
            'dolores' => $this->dolores($a, $ahora, $usados),
            'consultas_previas' => $this->consultasPrevias($a, $usados),
            'rutina' => $rutina,
            'mision' => $this->mision($a, $mision, $ahora, $usados),
            'botiquin' => $this->botiquin($ahora, $usados),
            'consultas_tierra' => $this->consultasTierra($a, $usados),
            'votos' => $this->votos($a),
            'usados' => $usados,
        ];
    }

    /** Estructura completa y vacía (también para quien no tiene permiso). */
    private function vacio(Astronauta $a): array
    {
        return [
            'astronauta' => ['id' => $a->id, 'nombre' => null, 'edad' => null, 'sexo' => null],
            'expediente' => ['alergias' => [], 'condiciones' => [], 'antecedentes' => [], 'medicacion' => []],
            'indicadores' => [],
            'casos' => [],
            'sintomas_registrados' => [],
            'dolores' => [],
            'consultas_previas' => [],
            'rutina' => ['ejercicio_min_semana' => 0, 'meta_min_dia' => (int) config('rutinas.metas.ejercicio_min', 150), 'sueno_h_ultimo' => null, 'ejecuciones' => [], 'ejercicio_intenso_reciente' => false],
            'mision' => ['care_level' => null, 'fase' => null, 'dia' => null, 'entorno' => null, 'retardo_s' => null, 'eventos_proximos' => []],
            'botiquin' => [],
            'consultas_tierra' => [],
            'votos' => [],
            'usados' => [],
        ];
    }

    // --- Expediente (cifrado) ---------------------------------------------------------------------------------------

    private function expediente(Astronauta $a, array &$usados): array
    {
        // Auditoría sin valores médicos: solo quién, qué recurso y cuándo.
        Auditoria::registrar('ver', 'orientador', $a->id, $a->id);

        $alergias = $a->alergias()->get()->map(fn ($r) => ['sustancia' => (string) $r->sustancia, 'reaccion' => $r->reaccion !== null ? (string) $r->reaccion : null])->all();
        $condiciones = $a->condiciones()->get()->map(fn ($r) => ['nombre' => (string) $r->nombre, 'estado' => $r->estado])->all();
        $antecedentes = $a->antecedentes()->get()->map(fn ($r) => ['condicion' => (string) $r->condicion, 'parentesco' => $r->parentesco])->all();
        $medicacion = $a->medicamentos()->where('activo', true)->get()->map(fn ($r) => ['nombre' => (string) $r->nombre, 'motivo' => $r->motivo !== null ? (string) $r->motivo : null])->all();

        $usados[] = ['etiqueta' => 'Expediente', 'detalle' => count($alergias).' alergias, '.count($condiciones).' condiciones, '.count($antecedentes).' antecedentes familiares, '.count($medicacion).' medicamentos activos'];

        return ['alergias' => $alergias, 'condiciones' => $condiciones, 'antecedentes' => $antecedentes, 'medicacion' => $medicacion];
    }

    // --- Indicadores ------------------------------------------------------------------------------------------------

    private function indicadores(Astronauta $a, ?Mision $mision, array &$usados): array
    {
        $ttl = (int) config('contexto.cache_s', 15);
        $calcular = fn () => $this->calcularIndicadores($a, $mision);

        if ($ttl > 0) {
            $ultima = DB::table('mediciones')->where('astronauta_id', $a->id)->selectRaw('MAX(id) as i, COUNT(*) as n')->first();
            $clave = 'contexto.indicadores.'.$a->id.'.'.(int) ($ultima->i ?? 0).'.'.(int) ($ultima->n ?? 0).'.'.Carbon::now('UTC')->toDateString();
            $ind = Cache::remember($clave, $ttl, $calcular);
        } else {
            $ind = $calcular();
        }

        $sosp = count(array_filter($ind, fn ($i) => $i['sospechoso']));
        $usados[] = ['etiqueta' => 'Indicadores', 'detalle' => count($ind).' indicadores con medición (serie de 7 días, últimas 24 h, base personal)'.($sosp > 0 ? '; '.$sosp.' con lectura sospechosa, no usada en la base' : '')];

        return $ind;
    }

    private function calcularIndicadores(Astronauta $a, ?Mision $mision): array
    {
        $marcadores = (array) config('indicadores.marcadores', []);
        $claves = array_keys($marcadores);
        if ($claves === []) {
            return [];
        }
        $hoy = Carbon::now('UTC')->startOfDay();
        $dias = max(7, (int) config('mision.dias_historial', 15));
        $desde = $hoy->copy()->subDays($dias - 1)->toDateTimeString();

        // 1) Último valor válido de cada día (por indicador): una consulta con subconsulta agregada.
        $ultimoDia = DB::table('mediciones')
            ->selectRaw('indicador, DATE(medido_en) as dia, MAX(medido_en) as ultimo')
            ->where('astronauta_id', $a->id)->whereIn('indicador', $claves)->where('medido_en', '>=', $desde)
            ->where(fn ($w) => $w->whereNull('calidad')->orWhere('calidad', '!=', 'sospechosa'))
            ->groupBy('indicador', DB::raw('DATE(medido_en)'));
        $filas = DB::table('mediciones as m')
            ->joinSub($ultimoDia, 'u', fn ($j) => $j->on('m.indicador', '=', 'u.indicador')->on('m.medido_en', '=', 'u.ultimo'))
            ->where('m.astronauta_id', $a->id)
            ->where(fn ($w) => $w->whereNull('m.calidad')->orWhere('m.calidad', '!=', 'sospechosa'))
            ->select('m.id', 'm.indicador', 'm.valores', 'm.medido_en', 'u.dia')
            ->orderBy('m.medido_en')->orderBy('m.id')
            ->get()->groupBy('indicador');

        // 2) Lecturas válidas de las últimas 24 h y la lectura sospechosa más reciente, por indicador.
        $hace24 = Carbon::now('UTC')->subHours(24)->toDateTimeString();
        $agregados = DB::table('mediciones')
            ->selectRaw("indicador, SUM(CASE WHEN medido_en >= ? AND (calidad IS NULL OR calidad <> 'sospechosa') THEN 1 ELSE 0 END) as n24, MAX(CASE WHEN calidad = 'sospechosa' THEN medido_en END) as sosp", [$hace24])
            ->where('astronauta_id', $a->id)->whereIn('indicador', $claves)->where('medido_en', '>=', $desde)
            ->groupBy('indicador')->get()->keyBy('indicador');

        // 3) Evaluaciones de las últimas mediciones válidas.
        $ultimas = [];
        foreach ($filas as $clave => $lista) {
            $ultimas[$clave] = $lista->last();
        }
        $estados = $ultimas === [] ? [] : Evaluacion::whereIn('medicion_id', array_map(fn ($m) => $m->id, $ultimas))->pluck('estado', 'medicion_id')->all();

        $evaluador = new EvaluadorEstado(config('umbrales'));
        $entorno = $a->entorno ?: $mision?->entorno;
        $linea = new LineaBasePersonal();
        $salida = [];

        foreach ($marcadores as $clave => $cfg) {
            $ultima = $ultimas[$clave] ?? null;
            $ag = $agregados->get($clave);
            $sospechosa = $ag && $ag->sosp !== null && (! $ultima || Carbon::parse($ag->sosp, 'UTC')->greaterThan(Carbon::parse($ultima->medido_en, 'UTC')));

            if (! $ultima) {
                if ($sospechosa) { // solo hay lecturas dudosas: se avisa, sin valores
                    $salida[$clave] = $this->entradaSinValor($cfg);
                }

                continue;
            }

            $valores = json_decode($ultima->valores, true) ?: [];
            $valor = $this->principal($valores);
            $porDia = [];
            foreach ($filas[$clave] as $m) {
                $v = json_decode($m->valores, true) ?: [];
                $porDia[$m->dia] = $this->principal($v);
            }

            try {
                $e = $estados[$ultima->id] ?? $evaluador->evaluar($clave, $valores, ['entorno' => $entorno])->value;
            } catch (\Throwable) {
                $e = null;
            }
            $estado = $e !== null ? (self::ESTADO_UI[$e] ?? null) : null;

            [$baseTexto, $pct, $baseNum] = $this->base($a, $clave, $cfg, $valor, $porDia, $ultima->dia, $linea);

            $salida[$clave] = [
                'etiqueta' => $cfg['label'],
                'unidad' => $cfg['unit'],
                'valor_texto' => $this->texto($cfg, $valores, $valor),
                'valores' => $valores,
                'estado' => $estado,
                'medido_en' => Carbon::parse($ultima->medido_en, 'UTC')->toIso8601String(),
                'base_texto' => $baseTexto,
                'desviacion_pct' => $pct,
                'serie_7d' => $this->serie7($porDia, $hoy, $valor),
                'lecturas_24h' => (int) ($ag->n24 ?? 0),
                'sospechoso' => (bool) $sospechosa,
                'direccion' => $estado === null || $estado === 'ok' ? null : $this->direccion($clave, $valores, $baseNum),
            ];
        }

        return $salida;
    }

    private function entradaSinValor(array $cfg): array
    {
        return [
            'etiqueta' => $cfg['label'], 'unidad' => $cfg['unit'], 'valor_texto' => null, 'valores' => [], 'estado' => null, 'medido_en' => null,
            'base_texto' => null, 'desviacion_pct' => null, 'serie_7d' => [], 'lecturas_24h' => 0, 'sospechoso' => true, 'direccion' => null,
        ];
    }

    /** Valor principal (sistólica en la presión). */
    private function principal(array $valores): float
    {
        return (float) ($valores['valor'] ?? $valores['sistolica'] ?? 0);
    }

    /** "128/84", "66", "−1.6" (signo menos tipográfico, igual que el resto de pantallas). */
    private function texto(array $cfg, array $valores, float $valor): string
    {
        if (isset($valores['sistolica'], $valores['diastolica'])) {
            return (int) round($valores['sistolica']).'/'.(int) round($valores['diastolica']);
        }
        $dec = (int) ($cfg['dec'] ?? 0);
        $v = round($valor, $dec);
        $t = number_format(abs($v), $dec, '.', '');

        return $v < 0 ? "\u{2212}".$t : $t;
    }

    /**
     * Base: primero la pre-vuelo (DesviacionBase, solo indicadores con métrica); si no, la media personal de los días
     * previos con lectura válida (LineaBasePersonal::MINIMO_HISTORIAL). Nunca usa lecturas sospechosas.
     *
     * @return array{0: ?string, 1: ?float, 2: ?float} [texto, desviación %, base numérica]
     */
    private function base(Astronauta $a, string $clave, array $cfg, float $valor, array $porDia, string $diaUltima, LineaBasePersonal $linea): array
    {
        $dec = (int) ($cfg['dec'] ?? 0);
        $pre = DesviacionBase::paraIndicador($a, $clave, $valor);
        if ($pre !== null) {
            return [round($pre['base'], $dec).' '.$cfg['unit'].' (pre-vuelo)', $pre['pct'], $pre['base']];
        }

        $previos = array_values(array_filter($porDia, fn ($k) => $k !== $diaUltima, ARRAY_FILTER_USE_KEY));
        $b = $linea->calcular($previos, $valor);
        if ($b === null) {
            return [null, null, null];
        }
        $pct = DesviacionBase::calcular($b['media'], $valor);

        return [round($b['media'], $dec).' '.$cfg['unit'].' (media personal, '.count($previos).' días)', $pct === null ? null : round($pct, 1), $b['media']];
    }

    /**
     * Serie de 7 valores (hace 6 días … hoy): el último valor válido de cada día UTC; huecos interpolados y extremos
     * con el vecino conocido más cercano (misma regla que ResumenTripulante). Sin datos en la ventana: constante.
     *
     * @param array<string, float> $porDia 'Y-m-d' => valor
     * @return float[]
     */
    private function serie7(array $porDia, Carbon $hoy, float $ultimo): array
    {
        $conocidos = [];
        for ($i = 0; $i < 7; $i++) {
            $k = $hoy->copy()->subDays(6 - $i)->toDateString();
            if (isset($porDia[$k])) {
                $conocidos[$i] = $porDia[$k];
            }
        }
        if ($conocidos === []) {
            return array_fill(0, 7, round($ultimo, 3));
        }
        $idx = array_keys($conocidos);
        $serie = [];
        for ($i = 0; $i < 7; $i++) {
            if (isset($conocidos[$i])) {
                $serie[] = $conocidos[$i];

                continue;
            }
            $antes = array_filter($idx, fn ($d) => $d < $i);
            $despues = array_filter($idx, fn ($d) => $d > $i);
            if ($antes === []) {
                $serie[] = $conocidos[min($despues)];
            } elseif ($despues === []) {
                $serie[] = $conocidos[max($antes)];
            } else {
                $x = max($antes);
                $y = min($despues);
                $serie[] = $conocidos[$x] + ($conocidos[$y] - $conocidos[$x]) * ($i - $x) / ($y - $x);
            }
        }

        return array_map(fn ($v) => round($v, 3), $serie);
    }

    /**
     * Hacia dónde se sale del rango normal de config/umbrales.php ('alto'|'bajo'). Si el valor está dentro del rango
     * pero el estado no es normal (p. ej. salto sobre la base personal), se compara con la base. Sin criterio: null.
     */
    private function direccion(string $clave, array $valores, ?float $base): ?string
    {
        $comps = (array) config('umbrales.indicadores.'.$clave.'.componentes', []);
        $alto = false;
        $bajo = false;
        foreach ($comps as $nombre => $rangos) {
            if (! isset($valores[$nombre], $rangos['normal'])) {
                continue;
            }
            [$lo, $hi] = $rangos['normal'];
            $v = (float) $valores[$nombre];
            $alto = $alto || $v > $hi;
            $bajo = $bajo || $v < $lo;
        }
        if ($alto) {
            return 'alto';
        }
        if ($bajo) {
            return 'bajo';
        }
        if ($base !== null) {
            $v = $this->principal($valores);

            return $v > $base ? 'alto' : ($v < $base ? 'bajo' : null);
        }

        return null;
    }

    // --- Casos, síntomas, dolores, consultas ---------------------------------------------------------------------------

    private function casos(Astronauta $a, Carbon $ahora, array &$usados): array
    {
        $desde = $ahora->copy()->subDays((int) config('contexto.casos_recientes_dias', 3));
        $casos = CasoClinico::where('astronauta_id', $a->id)
            ->where(fn ($q) => $q->where('estado', 'abierto')->orWhere('detectado_en', '>=', $desde))
            ->orderByDesc('detectado_en')->limit((int) config('contexto.max_casos', 10))->get()
            ->map(fn (CasoClinico $c) => ['patron' => $c->patron, 'severidad' => $c->severidad, 'estado' => $c->estado, 'detectado_en' => $c->detectado_en?->toIso8601String()])->all();
        $usados[] = ['etiqueta' => 'Casos', 'detalle' => count($casos).' casos abiertos o recientes del detector de patrones'];

        return $casos;
    }

    private function sintomas(Astronauta $a, Carbon $ahora, array &$usados): array
    {
        $desde = $ahora->copy()->subDays((int) config('contexto.sintomas_dias', 7));
        $out = RegistroSalud::where('astronauta_id', $a->id)->where('registrado_en', '>=', $desde)
            ->where(fn ($q) => $q->whereNotNull('nota')->orWhereNotNull('sintomas'))
            ->orderByDesc('registrado_en')->limit((int) config('contexto.max_sintomas', 10))->get()
            ->map(function (RegistroSalud $r) {
                $texto = trim(implode(', ', (array) $r->sintomas).($r->nota ? ' · '.$r->nota : ''), ' ·');

                return ['fecha' => $r->registrado_en?->toIso8601String(), 'texto' => $texto, 'aviso_a_tierra' => (bool) $r->aviso_tierra];
            })->filter(fn ($s) => $s['texto'] !== '')->values()->all();
        $usados[] = ['etiqueta' => 'Síntomas registrados', 'detalle' => count($out).' registros de los últimos '.(int) config('contexto.sintomas_dias', 7).' días'];

        return $out;
    }

    private function dolores(Astronauta $a, Carbon $ahora, array &$usados): array
    {
        $desde = $ahora->copy()->subDays((int) config('contexto.dolores_dias', 7));
        $out = ZonaDolor::where('astronauta_id', $a->id)->where('created_at', '>=', $desde)
            ->orderByDesc('created_at')->orderByDesc('id')->limit((int) config('contexto.max_dolores', 10))->get()
            ->map(fn (ZonaDolor $z) => ['zona' => ZonaDolor::nombre($z->musculo), 'musculo' => $z->musculo, 'intensidad' => (int) $z->intensidad, 'tipo' => $z->tipo, 'desde' => $z->desde])->all();
        $usados[] = ['etiqueta' => 'Zonas de dolor', 'detalle' => count($out).' zonas marcadas en la silueta en los últimos '.(int) config('contexto.dolores_dias', 7).' días'];

        return $out;
    }

    /** La tabla `consultas_sintomas` la crea otro módulo: si no existe (o no tiene las columnas esperadas), []. */
    private function consultasPrevias(Astronauta $a, array &$usados): array
    {
        $out = [];
        if (Schema::hasTable('consultas_sintomas') && Schema::hasColumns('consultas_sintomas', ['astronauta_id', 'urgencia', 'util', 'created_at'])) {
            $out = DB::table('consultas_sintomas')->where('astronauta_id', $a->id)
                ->orderByDesc('created_at')->orderByDesc('id')->limit((int) config('contexto.max_consultas_previas', 5))
                ->get(['created_at', 'urgencia', 'util'])
                ->map(fn ($c) => [
                    'fecha' => Carbon::parse($c->created_at, 'UTC')->toIso8601String(),
                    'urgencia' => $c->urgencia,
                    'util' => $c->util === null ? null : (bool) $c->util,
                ])->all();
        }
        $usados[] = ['etiqueta' => 'Consultas previas al orientador', 'detalle' => count($out).' consultas anteriores'];

        return $out;
    }

    private function consultasTierra(Astronauta $a, array &$usados): array
    {
        $out = ConsultaTierra::where('astronauta_id', $a->id)->orderByDesc('id')->limit((int) config('contexto.max_consultas_tierra', 5))->get()
            ->map(fn (ConsultaTierra $c) => [
                'fecha' => $c->created_at?->toIso8601String(),
                'estado' => $c->fallida ? 'No llegó a enviarse' : (ConsultaTierra::ETAPAS[$c->etapa] ?? 'Enviado'),
                'indicadores' => array_values((array) $c->indicadores),
            ])->all();
        $usados[] = ['etiqueta' => 'Consultas a tierra', 'detalle' => count($out).' consultas recientes'];

        return $out;
    }

    /** Votos de utilidad previos sobre orientaciones por indicador (clave extra respecto al contrato). */
    private function votos(Astronauta $a): array
    {
        return OrientacionFeedback::where('astronauta_id', $a->id)->orderBy('indicador')->get(['indicador', 'voto'])
            ->map(fn ($f) => ['indicador' => $f->indicador, 'voto' => $f->voto])->all();
    }

    // --- Rutina ------------------------------------------------------------------------------------------------------

    private function rutina(Astronauta $a, Carbon $ahora, array $indicadores, array &$usados): array
    {
        $agenda = new AgendaRutinas();
        $lunes = $agenda->lunesDe($ahora);
        $desdeFecha = $lunes->copy()->min($ahora->copy()->startOfDay()->subDays(6));
        $horas = (int) config('contexto.ejercicio_intenso_horas', 3);
        $limiteIntenso = $ahora->copy()->subHours($horas);

        $ejec = DB::table('rutina_ejecuciones as e')->join('rutinas as r', 'r.id', '=', 'e.rutina_id')
            ->where('r.astronauta_id', $a->id)->where('e.estado', 'done')->where('e.fecha', '>=', $desdeFecha->toDateString())
            ->orderByDesc('e.fecha')->orderByDesc('e.id')
            ->get(['r.nombre', 'r.tipo', 'r.kind', 'r.cantidad', 'e.fecha', 'e.hecha_en']);

        $desdeAct = $lunes->copy()->min($ahora->copy()->subHours((int) config('contexto.sueno_horas', 36)));
        $acts = Actividad::where('astronauta_id', $a->id)->where('inicio_en', '>=', $desdeAct)
            ->whereIn('tipo', array_merge(self::TIPOS_EJERCICIO, ['Sueño']))->get();

        $min = 0;
        $intenso = false;
        foreach ($ejec as $e) {
            if ($e->kind === 'ej') {
                if ($e->fecha >= $lunes->toDateString()) {
                    $min += (int) $e->cantidad;
                }
                if ($e->hecha_en && Carbon::parse($e->hecha_en, 'UTC')->greaterThanOrEqualTo($limiteIntenso)) {
                    $intenso = true;
                }
            }
        }
        foreach ($acts as $x) {
            if (in_array($x->tipo, self::TIPOS_EJERCICIO, true) && $x->origen === 'manual') {
                if ($x->inicio_en->greaterThanOrEqualTo($lunes)) {
                    $min += (int) $x->cantidad;
                }
                if ($x->inicio_en->greaterThanOrEqualTo($limiteIntenso) && $x->intensidad !== 'Baja') {
                    $intenso = true;
                }
            }
        }

        // Sueño: último valor medido del indicador `sueno` (horas); si no hay, el último registro de actividad "Sueño".
        $sueno = null;
        if (isset($indicadores['sueno']['valores']['valor'])) {
            $sueno = round((float) $indicadores['sueno']['valores']['valor'], 1);
        } else {
            $s = $acts->where('tipo', 'Sueño')->sortByDesc('inicio_en')->first();
            $sueno = $s ? round($s->cantidad / 60, 1) : null;
        }

        $recientes = $ejec->where('fecha', '>=', $ahora->copy()->startOfDay()->subDays(6)->toDateString())
            ->take((int) config('contexto.max_ejecuciones', 15))
            ->map(fn ($e) => [
                'nombre' => $e->nombre,
                'fecha' => (string) $e->fecha,
                'tipo' => $e->tipo,
                'duracion_min' => config('rutinas.tipos.'.$e->tipo.'.unidad', 'min') === 'min' && $e->cantidad ? (int) $e->cantidad : null,
            ])->values()->all();

        $meta = (int) config('rutinas.metas.ejercicio_min', 150);
        $usados[] = ['etiqueta' => 'Rutina', 'detalle' => $min.' min de ejercicio esta semana (meta '.$meta.' min/día), '.count($recientes).' tareas hechas en 7 días'];

        return [
            'ejercicio_min_semana' => $min,
            'meta_min_dia' => $meta,
            'sueno_h_ultimo' => $sueno,
            'ejecuciones' => $recientes,
            'ejercicio_intenso_reciente' => $intenso,
        ];
    }

    // --- Misión ------------------------------------------------------------------------------------------------------

    private function mision(Astronauta $a, ?Mision $m, Carbon $ahora, array &$usados): array
    {
        $entorno = $a->entorno ?: $m?->entorno;
        $dia = $m?->inicio_en ? (int) $m->inicio_en->copy()->utc()->startOfDay()->diffInDays($ahora->copy()->startOfDay(), false) + 1 : null;

        $fase = null;
        if ($m) {
            $fase = Mision::FASES[$m->mission_phase] ?? $m->mission_phase;
            if ($m->mission_phase === 'vuelo' && $dia !== null) {
                foreach ((array) config('mision.fases', []) as $f) {
                    if (($f['dia'] ?? (int) $m->duracion_dias) <= $dia) {
                        $fase = $f['nombre'];
                    }
                }
            }
        }

        $eventos = [];
        if ($dia !== null) {
            foreach ((array) config('mision.eventos', []) as $ev) {
                if ($ev['dia'] >= $dia) {
                    $eventos[] = ['nombre' => $ev['titulo'], 'dia' => (int) $ev['dia'], 'en_dias' => (int) $ev['dia'] - $dia];
                }
            }
            usort($eventos, fn ($x, $y) => $x['dia'] <=> $y['dia']);
            $eventos = array_slice($eventos, 0, (int) config('contexto.max_eventos', 5));
        }

        $retardoMin = $entorno !== null ? config('umbrales.retardo_min.'.$entorno) : null;
        $usados[] = ['etiqueta' => 'Misión', 'detalle' => 'Nivel de atención, fase'.($dia !== null ? ', día '.$dia : '').', entorno, retardo de comunicación y '.count($eventos).' eventos próximos'];

        return [
            'care_level' => $m?->nivelAtencion(),
            'fase' => $fase,
            'dia' => $dia,
            'entorno' => $entorno,
            'retardo_s' => $retardoMin === null ? null : (int) $retardoMin * 60,
            'eventos_proximos' => $eventos,
        ];
    }

    // --- Botiquín ----------------------------------------------------------------------------------------------------

    /** Inventario común de la nave. Vigente = stock > 0 y no caducado (sin fecha de caducidad no cuenta como caducado). */
    private function botiquin(Carbon $ahora, array &$usados): array
    {
        $hoy = $ahora->copy()->startOfDay();
        $out = BotiquinItem::orderBy('grupo')->orderBy('nombre')->get()->map(function (BotiquinItem $i) use ($hoy) {
            $caducado = $i->caducidad !== null && $i->caducidad->copy()->startOfDay()->lt($hoy);

            return [
                'id' => $i->id, 'nombre' => $i->nombre, 'grupo' => $i->grupo, 'stock' => (int) $i->stock,
                'caduca' => $i->caducidad?->toDateString(),
                'vigente' => (int) $i->stock > 0 && ! $caducado,
            ];
        })->all();
        $usados[] = ['etiqueta' => 'Botiquín', 'detalle' => count($out).' artículos ('.count(array_filter($out, fn ($i) => $i['vigente'])).' con stock y sin caducar)'];

        return $out;
    }
}
