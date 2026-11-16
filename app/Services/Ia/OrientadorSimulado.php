<?php

namespace App\Services\Ia;

use App\Contracts\Orientador;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\ConsultaTierra;
use App\Models\OrientacionFeedback;
use App\Services\Auditoria;
use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\DatosIndicador;
use App\Services\Salud\Estado;
use App\Services\Salud\ResolvedorRequisito;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * IA local SIMULADA: determinista, por plantillas, sin modelo de lenguaje ni llamadas de red.
 * Compone la orientación a partir de la ficha del patrón, la guía, el historial de 7 días y el expediente.
 * Lenguaje de hipótesis; nunca diagnostica, ni da dosis ni recomienda fármacos. Todo pasa por FiltroSeguridad.
 */
class OrientadorSimulado implements Orientador
{
    public const SIN_ANTECEDENTES = 'No related history';
    public const SIN_TRATAMIENTOS = 'No treatments recorded';
    public const SIN_ACCESO = 'Medical record not available for your role';
    public const PENDIENTE = 'Pending verifiable source';
    public const CONFIRMAR = 'Confirm this guidance with the Flight Surgeon before deciding.';

    public function __construct(private readonly FiltroSeguridad $filtro)
    {
    }

    public function orientar(CasoClinico $caso): Orientacion
    {
        $astronauta = $caso->astronauta ?? Astronauta::findOrFail($caso->astronauta_id);
        $cfg = (array) config('patrones.'.$caso->patron, []);
        $ficha = $this->ficha((string) $caso->patron);
        $indicadores = $this->indicadores($cfg);
        $severidad = $caso->severidad === 'critico' ? 'critico' : 'precaucion';

        $fuentes = $this->fuentesFicha($ficha);
        $accionesGuia = $this->accionesGuia($cfg, $indicadores, $severidad);
        [$expediente, $meds, $auditadoEn] = $this->expediente($astronauta, (string) $caso->patron);
        $tratamientos = array_merge($this->tratamientosPrevios($astronauta, $indicadores), $meds);

        $titulo = $this->titulo($cfg, $ficha, (string) $caso->patron);
        $hallazgo = $this->hallazgo($cfg, $ficha, $indicadores, $severidad, $astronauta, (string) $caso->patron);

        $acciones = array_merge($this->accionesFicha($ficha, $fuentes), $accionesGuia);
        $acciones[] = ['texto' => self::CONFIRMAR, 'fuente' => $fuentes[0] ?? null];

        $matiz = $this->matizPresentacion($astronauta, $indicadores);
        $orientacion = $this->armar($caso, $astronauta, $titulo, $severidad, $hallazgo, $acciones, $indicadores, $expediente, $tratamientos, $fuentes, $cfg, $auditadoEn, $matiz);

        // Filtro de seguridad: si algo no pasa, se devuelve el texto por reglas del catálogo con el aviso.
        $textos = array_merge([$orientacion->titulo, $orientacion->hallazgo, (string) $orientacion->matiz], array_column($orientacion->acciones, 'texto'));
        if ($this->filtro->revisarTodos($textos) !== null) {
            return $this->porCatalogo($caso, $astronauta, $cfg, $indicadores, $severidad, $expediente, $tratamientos, $fuentes, $accionesGuia, $auditadoEn);
        }

        return $orientacion;
    }

    /** Texto por reglas del catálogo (CatalogoRecomendaciones / guía), usado cuando el filtro rechaza el texto compuesto. */
    private function porCatalogo(CasoClinico $caso, Astronauta $a, array $cfg, array $indicadores, string $sev, array $expediente, array $tratamientos, array $fuentes, array $accionesGuia, ?string $auditadoEn): Orientacion
    {
        $titulo = $this->titulo($cfg, [], (string) $caso->patron);
        $hallazgo = 'Hypothesis based on the simulated data: '.mb_strtolower($titulo).'. '
            .'The expanded wording did not pass the safety filter; the catalog rule-based text is shown. Notice: '.config('ia.aviso');
        $acciones = array_values(array_filter($accionesGuia, fn ($x) => $this->filtro->permitido($x['texto'])));
        $acciones[] = ['texto' => self::CONFIRMAR, 'fuente' => $fuentes[0] ?? null];
        $fuentes[] = ['etiqueta' => 'Notice', 'texto' => (string) config('ia.aviso')];

        return $this->armar($caso, $a, $titulo, $sev, $hallazgo, $acciones, $indicadores, $expediente, $tratamientos, $fuentes, $cfg, $auditadoEn, null);
    }

    private function armar(CasoClinico $caso, Astronauta $a, string $titulo, string $sev, string $hallazgo, array $acciones, array $indicadores, array $expediente, array $tratamientos, array $fuentes, array $cfg, ?string $auditadoEn, ?string $matiz): Orientacion
    {
        return new Orientacion(
            casoId: (int) $caso->id,
            astronautaId: (int) $a->id,
            patron: (string) $caso->patron,
            severidad: $sev,
            titulo: $titulo,
            hallazgo: $hallazgo,
            acciones: array_values($acciones),
            historial7d: $this->historial($a, $indicadores),
            expedienteRelevante: $expediente,
            tratamientos: $tratamientos,
            fuentes: $fuentes,
            confianza: max(0, min(100, (int) ($cfg['confianza'] ?? 0))),
            icono: (string) ($cfg['icono'] ?? 'stethoscope'),
            evidencia: $this->lineaEvidencia($caso),
            empeoro: (bool) ($caso->evidencia['empeoro'] ?? false),
            detectadoEn: $this->horaUtc($caso->detectado_en),
            escaladoEn: $this->horaUtc($caso->escalado_en),
            auditadoEn: $auditadoEn,
            matiz: $matiz,
        );
    }

    // --- Ficha y patrón -------------------------------------------------------------------------------------------

    private function ficha(string $patron): array
    {
        try {
            return app(LectorFichas::class)->ficha($patron);
        } catch (Throwable) {
            return []; // no verifiable sheet: will say "Pending verifiable source"
        }
    }

    /** @return array<int, string> claves de indicador del patrón (acepta lista o mapa clave => condición). */
    private function indicadores(array $cfg): array
    {
        $out = [];
        foreach ((array) ($cfg['indicadores'] ?? []) as $k => $v) {
            $out[] = is_string($k) ? $k : (string) (is_array($v) ? ($v['indicador'] ?? '') : $v);
        }

        return array_values(array_filter(array_unique($out)));
    }

    private function titulo(array $cfg, array $ficha, string $patron): string
    {
        $t = trim((string) ($cfg['nombre'] ?? $ficha['titulo'] ?? ''));

        return $t !== '' ? $t : 'Possible pattern: '.str_replace('_', ' ', $patron);
    }

    private function etiqueta(string $indicador): string
    {
        return (string) (config("indicadores.marcadores.$indicador.label") ?? config("guia.extras.$indicador.label") ?? $indicador);
    }

    private function hallazgo(array $cfg, array $ficha, array $indicadores, string $sev, Astronauta $a, string $patron): string
    {
        $etiquetas = implode(', ', array_map(fn ($i) => mb_strtolower($this->etiqueta($i)), $indicadores));
        $estado = $sev === 'critico' ? 'values in critical range' : 'values in caution range';
        $t = 'Hypothesis based on the simulated data: '.mb_strtolower($this->titulo($cfg, $ficha, $patron))
            .($etiquetas !== '' ? " ({$etiquetas}: {$estado})" : '').'.';

        $senales = $this->lineas($ficha['senales'] ?? []);
        $t .= $senales !== []
            ? ' Signs that support it according to the knowledge sheet: '.implode('; ', array_map(fn ($x) => rtrim($x, '. '), array_slice($senales, 0, 4))).'.'
            : ' '.self::PENDIENTE.' for the signs of this pattern.';
        $t .= ' This is not a diagnosis, only a hypothesis that requires confirmation by the Flight Surgeon.';

        return trim($t.' '.$this->matiz($a, $indicadores));
    }

    /** Párrafo de presentación (campo `matiz`) solo si existe el antecedente real: consulta a tierra previa + voto «me ayudó». */
    private function matizPresentacion(Astronauta $a, array $indicadores): ?string
    {
        $avisoTierra = $this->consultasRelacionadas($a, $indicadores)->contains(fn ($c) => ! $c->fallida);
        if (! $avisoTierra || ! $this->hayVotoUtil($a, $indicadores)) {
            return null;
        }
        $t = 'Last time you alerted the ground and marked it as helpful. If repeating the measurements does not improve them, do it again.';

        return $this->filtro->permitido($t) ? $t : null;
    }

    private function hayVotoUtil(Astronauta $a, array $indicadores): bool
    {
        return $indicadores !== [] && OrientacionFeedback::query()
            ->where('astronauta_id', $a->id)->whereIn('indicador', $indicadores)->where('voto', 'up')->exists();
    }

    /** "SpO₂ 89 (CRÍTICO) · FC 108 (PRECAUCIÓN) · VENTANA 10 MIN", solo con las señales reales del caso. */
    private function lineaEvidencia(CasoClinico $caso): string
    {
        $ev = (array) $caso->evidencia;
        $partes = [];
        foreach ((array) ($ev['senales'] ?? []) as $s) {
            $ind = (string) ($s['indicador'] ?? '');
            $v = (array) ($s['valores'] ?? []);
            if ($ind === '' || $v === []) {
                continue;
            }
            $corta = config("ia.etiquetas_cortas.$ind") ?? mb_strtoupper($this->etiqueta($ind));
            $valor = isset($v['sistolica']) ? $this->num($v['sistolica']).(isset($v['diastolica']) ? '/'.$this->num($v['diastolica']) : '') : $this->num($v['valor'] ?? null);
            if ($valor === '') {
                continue;
            }
            $estado = mb_strtoupper(match ((string) ($s['estado'] ?? '')) { 'critico' => 'critical', 'precaucion' => 'caution', default => (string) ($s['estado'] ?? '') });
            $partes[] = trim("$corta $valor".($estado !== '' ? " ($estado)" : ''));
        }
        if ($partes === []) {
            return '';
        }
        if (! empty($ev['ventana_min'])) {
            $partes[] = 'WINDOW '.(int) $ev['ventana_min'].' MIN';
        }

        return implode(' · ', $partes);
    }

    private function num(mixed $v): string
    {
        return is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : '';
    }

    private function horaUtc(mixed $fecha): ?string
    {
        return $fecha ? $fecha->copy()->utc()->format('H:i') : null;
    }

    private const MESES = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    private function diaMes(mixed $fecha): string
    {
        return $fecha ? (int) $fecha->format('j').' '.self::MESES[(int) $fecha->format('n') - 1] : '';
    }

    /** Matiza el texto con el historial (solo lectura): nunca recomienda un fármaco. */
    private function matiz(Astronauta $a, array $indicadores): string
    {
        $avisoTierra = $this->consultasRelacionadas($a, $indicadores)->contains(fn ($c) => ! $c->fallida);
        $util = $this->hayVotoUtil($a, $indicadores);
        if ($avisoTierra && ! $util) {
            return 'You already alerted the ground about these indicators on a previous occasion.';
        }

        return '';
    }

    // --- Acciones y fuentes ---------------------------------------------------------------------------------------

    /** @return array<int, array{etiqueta:string,texto:string}> */
    private function fuentesFicha(array $ficha): array
    {
        $out = [];
        foreach ((array) ($ficha['fuentes'] ?? []) as $f) {
            if (is_array($f)) {
                $out[] = ['etiqueta' => (string) ($f['etiqueta'] ?? 'Knowledge sheet'), 'texto' => (string) ($f['texto'] ?? $f['fuente'] ?? $f['archivo'] ?? '')];
            } elseif (is_string($f) && $f !== '') {
                $out[] = ['etiqueta' => 'Knowledge sheet', 'texto' => $f];
            }
        }

        return $out;
    }

    private function accionesFicha(array $ficha, array $fuentes): array
    {
        $fuente = $fuentes[0] ?? null;
        $acciones = [];
        foreach (['chequear', 'escalar'] as $bloque) {
            foreach ($this->lineas($ficha[$bloque] ?? []) as $texto) {
                $acciones[] = ['texto' => $texto, 'fuente' => $fuente];
            }
        }

        return $acciones ?: [['texto' => self::PENDIENTE.' para los pasos de este patrón.', 'fuente' => null]];
    }

    /**
     * Todas las acciones de la guía del patrón: una por cada indicador de `acciones_guia` (o de `indicadores`),
     * con el texto del catálogo para el estado del caso y su fuente (requisito trazable o guía de la app).
     *
     * @return array<int, array{texto:string, fuente:?array}>
     */
    private function accionesGuia(array $cfg, array $indicadores, string $sev): array
    {
        $claves = array_map(fn ($v) => is_array($v) ? (string) ($v['indicador'] ?? '') : (string) $v, (array) ($cfg['acciones_guia'] ?? []));
        $claves = array_values(array_unique(array_filter($claves))) ?: $indicadores;

        $catalogo = new CatalogoRecomendaciones();
        $estado = $sev === 'critico' ? Estado::Critico : Estado::Precaucion;
        $acciones = [];
        foreach ($claves as $clave) {
            if (! isset(config('indicadores.marcadores')[$clave]) && ! isset(config('guia.extras')[$clave])) {
                continue;
            }
            $fuente = ResolvedorRequisito::fuente(ResolvedorRequisito::para($clave))
                ?? ['etiqueta' => 'App guide (example, pending medical review)', 'texto' => 'config/guia.php · '.$this->etiqueta($clave)];
            $acciones[] = ['texto' => $catalogo->para($clave, $estado), 'fuente' => $fuente];
        }

        return $acciones;
    }

    /** @return array<int, string> */
    private function lineas(mixed $valor): array
    {
        // La ficha trae los bloques como Markdown: una viñeta «- texto» por línea (también se acepta una lista).
        if (is_string($valor)) {
            $valor = preg_split('/\R/u', $valor) ?: [];
        }
        $out = [];
        foreach ((array) $valor as $v) {
            $t = is_array($v) ? (string) ($v['texto'] ?? '') : (string) $v;
            $t = trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $t));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    // --- Historial 7 días -----------------------------------------------------------------------------------------

    private function historial(Astronauta $a, array $indicadores): array
    {
        if ($indicadores === []) {
            return [];
        }
        $datos = DatosIndicador::para($a);
        $porId = collect($datos['marcadores'])->keyBy('id');
        $out = [];
        foreach ($indicadores as $id) {
            $m = $porId->get($id);
            if (! $m) {
                continue;
            }
            $serie = array_map('floatval', array_slice($datos['diaria'][$id] ?? [], -7));
            while ($serie !== [] && count($serie) < 7) {
                array_unshift($serie, $serie[0]);
            }
            $base = $m['desv']['base'] ?? $m['base'];
            $out[] = [
                'indicador' => $id,
                'etiqueta' => (string) $m['label'],
                'unidad' => (string) $m['unit'],
                'serie' => $serie,
                'actual' => (string) $m['disp'],
                'base' => $base === null ? null : (string) round((float) $base, (int) $m['dec']),
            ];
        }

        return $out;
    }

    // --- Expediente y tratamientos --------------------------------------------------------------------------------

    /** @return array{0: array<int,array{etiqueta:string,texto:string}>, 1: array<int,array>, 2: ?string} [expediente relevante, medicación registrada, hora UTC de la auditoría] */
    private function expediente(Astronauta $a, string $patron): array
    {
        if (! Astronauta::visiblesPara(Auth::user())->whereKey($a->id)->exists()) {
            return [[['etiqueta' => 'MEDICAL RECORD', 'texto' => self::SIN_ACCESO]], [], null];
        }
        $auditoria = Auditoria::registrar('ver', 'expediente', $a->id, $a->id);

        $palabras = array_map(FiltroSeguridad::normalizar(...), (array) config('ia.expediente_palabras.'.$patron, []));
        $coincide = function (string ...$campos) use ($palabras): bool {
            $t = FiltroSeguridad::normalizar(implode(' ', $campos));
            foreach ($palabras as $p) {
                if ($p !== '' && str_contains($t, $p)) {
                    return true;
                }
            }

            return false;
        };

        $rel = [];
        foreach ($a->condiciones()->get() as $c) {
            if ($coincide((string) $c->nombre, (string) $c->notas)) {
                $rel[] = ['etiqueta' => 'MEDICAL CONDITION', 'texto' => $c->nombre.' ('.$c->estado.')'];
            }
        }
        foreach ($a->antecedentes()->get() as $f) {
            if ($coincide((string) $f->condicion)) {
                $rel[] = ['etiqueta' => 'FAMILY HISTORY', 'texto' => $f->condicion.' ('.$f->parentesco.')'];
            }
        }
        foreach ($a->alergias()->get() as $al) {
            if ($coincide((string) $al->sustancia, (string) $al->reaccion)) {
                $rel[] = ['etiqueta' => 'ALLERGY', 'texto' => $al->sustancia];
            }
        }

        // Medicación registrada: contexto de solo lectura, sin dosis. No es una recomendación.
        $meds = [];
        foreach ($a->medicamentos()->where('activo', true)->get() as $m) {
            $meds[] = ['etiqueta' => 'RECORDED MEDICATION', 'texto' => $m->nombre, 'nota' => 'Context only: the system does not recommend or modify it'];
        }

        return [$rel, $meds, $auditoria?->ocurrido_en ? $this->horaUtc($auditoria->ocurrido_en) : null];
    }

    private function consultasRelacionadas(Astronauta $a, array $indicadores): Collection
    {
        return ConsultaTierra::query()->where('astronauta_id', $a->id)->orderByDesc('id')->limit(20)->get()
            ->filter(fn (ConsultaTierra $c) => array_intersect((array) $c->indicadores, $indicadores) !== [])
            ->take(3)->values();
    }

    /** @return array<int, array{etiqueta:string,texto:string,nota?:string}> consultas a tierra previas y votos de utilidad (solo lectura). */
    private function tratamientosPrevios(Astronauta $a, array $indicadores): array
    {
        $out = [];
        $util = $this->hayVotoUtil($a, $indicadores);
        foreach ($this->consultasRelacionadas($a, $indicadores) as $c) {
            $estado = $c->fallida ? 'not sent' : (ConsultaTierra::ETAPAS[$c->etapa] ?? 'sent');
            $out[] = [
                'etiqueta' => 'GROUND CONSULTATION · '.$this->diaMes($c->created_at),
                'texto' => (string) $c->titulo,
                'nota' => $util && ! $c->fallida ? 'You marked "It helped"' : 'Status: '.mb_strtolower($estado).($c->respuesta ? ', with response' : ''),
            ];
        }
        if ($indicadores !== []) {
            foreach (OrientacionFeedback::query()->where('astronauta_id', $a->id)->whereIn('indicador', $indicadores)->orderBy('indicador')->get() as $f) {
                $out[] = [
                    'etiqueta' => 'GUIDANCE · '.$this->diaMes($f->updated_at ?? $f->created_at),
                    'texto' => 'Guidance on '.mb_strtolower($this->etiqueta($f->indicador)),
                    'nota' => $f->voto === 'up' ? 'You marked "It helped"' : 'You marked "It did not help"',
                ];
            }
        }

        return $out;
    }
}
