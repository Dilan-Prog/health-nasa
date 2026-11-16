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
    public const SIN_ANTECEDENTES = 'Sin antecedentes relacionados';
    public const SIN_TRATAMIENTOS = 'Sin tratamientos registrados';
    public const SIN_ACCESO = 'Expediente no disponible para tu rol';
    public const PENDIENTE = 'Pendiente de fuente verificable';
    public const CONFIRMAR = 'Confirma esta orientación con el médico de vuelo antes de decidir.';

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
        $hallazgo = 'Hipótesis a partir de los datos simulados: '.mb_strtolower($titulo).'. '
            .'La redacción ampliada no superó el filtro de seguridad; se muestra el texto por reglas del catálogo. Aviso: '.config('ia.aviso');
        $acciones = array_values(array_filter($accionesGuia, fn ($x) => $this->filtro->permitido($x['texto'])));
        $acciones[] = ['texto' => self::CONFIRMAR, 'fuente' => $fuentes[0] ?? null];
        $fuentes[] = ['etiqueta' => 'Aviso', 'texto' => (string) config('ia.aviso')];

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
            return []; // sin ficha verificable: se dirá "Pendiente de fuente verificable"
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

        return $t !== '' ? $t : 'Posible patrón: '.str_replace('_', ' ', $patron);
    }

    private function etiqueta(string $indicador): string
    {
        return (string) (config("indicadores.marcadores.$indicador.label") ?? config("guia.extras.$indicador.label") ?? $indicador);
    }

    private function hallazgo(array $cfg, array $ficha, array $indicadores, string $sev, Astronauta $a, string $patron): string
    {
        $etiquetas = implode(', ', array_map(fn ($i) => mb_strtolower($this->etiqueta($i)), $indicadores));
        $estado = $sev === 'critico' ? 'valores en rango crítico' : 'valores en precaución';
        $t = 'Hipótesis a partir de los datos simulados: '.mb_strtolower($this->titulo($cfg, $ficha, $patron))
            .($etiquetas !== '' ? " ({$etiquetas}: {$estado})" : '').'.';

        $senales = $this->lineas($ficha['senales'] ?? []);
        $t .= $senales !== []
            ? ' Señales que la sostienen según la ficha: '.implode('; ', array_map(fn ($x) => rtrim($x, '. '), array_slice($senales, 0, 4))).'.'
            : ' '.self::PENDIENTE.' para las señales de este patrón.';
        $t .= ' No es un diagnóstico, solo una hipótesis que requiere confirmación del médico de vuelo.';

        return trim($t.' '.$this->matiz($a, $indicadores));
    }

    /** Párrafo de presentación (campo `matiz`) solo si existe el antecedente real: consulta a tierra previa + voto «me ayudó». */
    private function matizPresentacion(Astronauta $a, array $indicadores): ?string
    {
        $avisoTierra = $this->consultasRelacionadas($a, $indicadores)->contains(fn ($c) => ! $c->fallida);
        if (! $avisoTierra || ! $this->hayVotoUtil($a, $indicadores)) {
            return null;
        }
        $t = 'La última vez avisaste a tierra y marcaste que te ayudó. Si al repetir las mediciones no mejora, vuelve a hacerlo.';

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
            $estado = mb_strtoupper(match ((string) ($s['estado'] ?? '')) { 'critico' => 'crítico', 'precaucion' => 'precaución', default => (string) ($s['estado'] ?? '') });
            $partes[] = trim("$corta $valor".($estado !== '' ? " ($estado)" : ''));
        }
        if ($partes === []) {
            return '';
        }
        if (! empty($ev['ventana_min'])) {
            $partes[] = 'VENTANA '.(int) $ev['ventana_min'].' MIN';
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

    private const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

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
            return 'Ya avisaste a tierra por estos indicadores en una ocasión anterior.';
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
                $out[] = ['etiqueta' => (string) ($f['etiqueta'] ?? 'Ficha de conocimiento'), 'texto' => (string) ($f['texto'] ?? $f['fuente'] ?? $f['archivo'] ?? '')];
            } elseif (is_string($f) && $f !== '') {
                $out[] = ['etiqueta' => 'Ficha de conocimiento', 'texto' => $f];
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
                ?? ['etiqueta' => 'Guía de la app (ejemplo, revisión médica pendiente)', 'texto' => 'config/guia.php · '.$this->etiqueta($clave)];
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
            return [[['etiqueta' => 'EXPEDIENTE', 'texto' => self::SIN_ACCESO]], [], null];
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
                $rel[] = ['etiqueta' => 'CONDICIÓN MÉDICA', 'texto' => $c->nombre.' ('.$c->estado.')'];
            }
        }
        foreach ($a->antecedentes()->get() as $f) {
            if ($coincide((string) $f->condicion)) {
                $rel[] = ['etiqueta' => 'ANTECEDENTE FAMILIAR', 'texto' => $f->condicion.' ('.$f->parentesco.')'];
            }
        }
        foreach ($a->alergias()->get() as $al) {
            if ($coincide((string) $al->sustancia, (string) $al->reaccion)) {
                $rel[] = ['etiqueta' => 'ALERGIA', 'texto' => $al->sustancia];
            }
        }

        // Medicación registrada: contexto de solo lectura, sin dosis. No es una recomendación.
        $meds = [];
        foreach ($a->medicamentos()->where('activo', true)->get() as $m) {
            $meds[] = ['etiqueta' => 'MEDICACIÓN REGISTRADA', 'texto' => $m->nombre, 'nota' => 'Solo contexto: el sistema no la recomienda ni la modifica'];
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
            $estado = $c->fallida ? 'no llegó a enviarse' : (ConsultaTierra::ETAPAS[$c->etapa] ?? 'enviada');
            $out[] = [
                'etiqueta' => 'CONSULTA A TIERRA · '.$this->diaMes($c->created_at),
                'texto' => (string) $c->titulo,
                'nota' => $util && ! $c->fallida ? 'Marcaste «me ayudó»' : 'Estado: '.mb_strtolower($estado).($c->respuesta ? ', con respuesta' : ''),
            ];
        }
        if ($indicadores !== []) {
            foreach (OrientacionFeedback::query()->where('astronauta_id', $a->id)->whereIn('indicador', $indicadores)->orderBy('indicador')->get() as $f) {
                $out[] = [
                    'etiqueta' => 'ORIENTACIÓN · '.$this->diaMes($f->updated_at ?? $f->created_at),
                    'texto' => 'Orientación sobre '.mb_strtolower($this->etiqueta($f->indicador)),
                    'nota' => $f->voto === 'up' ? 'Marcaste «me ayudó»' : 'Marcaste «no me ayudó»',
                ];
            }
        }

        return $out;
    }
}
