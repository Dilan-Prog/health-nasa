<?php

namespace App\Services\Ia;

/**
 * Motor de razonamiento SIMULADO (determinista, sin modelo de lenguaje ni red). Combina el análisis de los síntomas
 * con el contexto del tripulante y las fichas de síntoma para producir urgencia, hipótesis con su "por qué",
 * acciones, opciones de botiquín (sin dosis), seguimiento y cuándo escalar. Las reglas son datos: config/razonador.php.
 *
 * Orden: 1) banderas rojas (dominan), 2) alcance, 3) agrupación y hipótesis, 4) ajustes por contexto,
 * 5) reevaluación, 6) acciones sin repetir el Panel, 7) filtro de seguridad sobre todo texto saliente.
 * Orienta, no diagnostica: lenguaje de hipótesis; confirma siempre con el médico de vuelo.
 */
class RazonadorSimulado
{
    public function __construct(private readonly ?FiltroSeguridad $filtro = null)
    {
    }

    /**
     * @param  array  $analisis  salida de AnalizadorSintomas::analizar()
     * @param  array  $contexto  salida de ContextoTripulante::de()
     * @param  array  $opciones  ['evolucion'=>null|'igual'|'empeoro', 'urgencia_previa'=>?string, 'acciones_panel'=>[ids]]
     */
    public function razonar(array $analisis, array $contexto, array $opciones = []): array
    {
        $evolucion = in_array($opciones['evolucion'] ?? null, ['igual', 'empeoro'], true) ? $opciones['evolucion'] : null;
        $previa = $this->nivelValido($opciones['urgencia_previa'] ?? null);
        $idsPanel = array_values(array_filter((array) ($opciones['acciones_panel'] ?? []), 'is_string'));

        $negados = (array) ($analisis['negados'] ?? []);
        $sintomas = array_values(array_filter((array) ($analisis['sintomas'] ?? []), fn ($s) => is_array($s)
            && ! in_array($s['clave'] ?? '', $negados, true)));

        $grupos = $this->agrupar($sintomas);
        $indicadoresPregunta = $this->indicadoresMencionados((string) ($analisis['texto'] ?? ''), $contexto);

        // 2) Alcance.
        if ($sintomas === [] && $indicadoresPregunta === []) {
            return $this->fueraDeAlcance($contexto);
        }

        // 1) Banderas rojas primero.
        $bandera = $this->hayBanderaRoja($analisis, $sintomas, $grupos);

        // Grupos por indicador (pregunta sobre un indicador alterado, sin síntomas que lo expliquen).
        $gruposIndicador = $this->gruposIndicador($indicadoresPregunta, $sintomas, $contexto);

        // 3) Hipótesis y ajustes por contexto.
        $antecedentes = $this->antecedentesRelacionados($sintomas, $contexto);
        $hipotesis = [];
        $urgencia = 'observacion';
        foreach ($grupos as $g) {
            [$h, $u] = $this->hipotesis($g, $contexto, $antecedentes, $analisis);
            $hipotesis[] = $h;
            $urgencia = $this->max($urgencia, $u);
        }
        foreach ($gruposIndicador as $g) {
            [$h, $u] = $this->hipotesisIndicador($g, $contexto);
            $hipotesis[] = $h;
            $urgencia = $this->max($urgencia, $u);
        }
        usort($hipotesis, fn ($a, $b) => $b['confianza'] <=> $a['confianza']);
        if ($hipotesis !== [] && ($linea = $this->lineaMedicacion($contexto)) !== null) {
            $hipotesis[0]['por_que'][] = $linea; // medicación activa mencionada (contexto del expediente, solo lectura)
        }

        $ajustes = [];
        $tope = (string) config('razonador.tope_por_ajustes', 'urgente');
        if ($antecedentes !== [] && $this->indice($urgencia) < $this->indice($tope)) {
            $urgencia = $this->subir($urgencia, (int) config('razonador.antecedente_sube', 1), $tope);
            $ajustes[] = 'antecedente';
        }
        if ($this->variasRegionesConMareo($sintomas)) {
            $urgencia = $this->max($urgencia, (string) config('razonador.multiples_regiones.urgencia_minima', 'urgente'));
            $ajustes[] = 'regiones';
        }

        // 5) Reevaluación.
        if ($evolucion !== null && $previa !== null) {
            $urgencia = $this->max($urgencia, $previa);
        }
        if ($evolucion === 'empeoro') {
            $urgencia = $this->subir($urgencia, 1, 'emergencia');
        }

        if ($bandera) {
            $urgencia = 'emergencia';
        }
        $emergencia = $urgencia === 'emergencia';

        $eva = $this->evaProxima($contexto);

        // 6) Acciones, botiquín, seguimiento, escalar.
        $fichasUsadas = $this->fichasDe($grupos);
        [$acciones, $panelVigente] = $emergencia ? [$this->accionesEmergencia(), false] : $this->acciones($fichasUsadas, $contexto, $idsPanel);
        $botiquin = $emergencia ? [] : $this->botiquin($fichasUsadas, $contexto);
        $seguimiento = $this->seguimiento($fichasUsadas, $gruposIndicador, $emergencia);
        $escalarSi = $this->escalarSi($fichasUsadas, $emergencia, $evolucion);

        $resultado = [
            'urgencia' => $urgencia,
            'alcance' => true,
            'hipotesis' => $hipotesis,
            'conclusion' => $this->conclusion($hipotesis, $urgencia, $evolucion, $previa, $contexto, $eva, $ajustes),
            'acciones' => $acciones,
            'panel_vigente' => $panelVigente,
            'botiquin_opciones' => $botiquin,
            'seguimiento' => $seguimiento,
            'escalar_si' => $escalarSi,
            'escalar_ahora' => $emergencia,
            'medidas_inmediatas' => $emergencia ? array_values((array) config('razonador.medidas_inmediatas', [])) : [],
            'eva_proxima' => $eva,
            'datos_usados' => array_values((array) ($contexto['usados'] ?? [])),
            'fuentes' => $this->fuentes($fichasUsadas),
            'aviso' => (string) config('ia.aviso'),
        ];

        // 7) Filtro de seguridad sobre todo texto saliente.
        if ($this->filtro()->revisarTodos($this->textos($resultado)) !== null) {
            return $this->porReglas($resultado);
        }

        return $resultado;
    }

    // --- Puntos de extensión (los tests los sustituyen; en producción delegan en las piezas reales) -----------------

    /** @return array<int, array> */
    protected function fichasPorSintoma(string $clave): array
    {
        try {
            return LectorFichasSintomas::porSintoma($clave);
        } catch (\Throwable) {
            return [];
        }
    }

    protected function opcionesBotiquin(array $contexto, array $grupos): array
    {
        return BotiquinOpciones::para($contexto, $grupos, false);
    }

    /** @return array{acciones: array, panel_vigente: bool} */
    protected function recomendacionesEspecificas(array $contexto, array $idsPanel): array
    {
        return RecomendacionesEspecificas::para($contexto, $idsPanel);
    }

    private function filtro(): FiltroSeguridad
    {
        return $this->filtro ?? app(FiltroSeguridad::class);
    }

    // --- 1) Banderas rojas ----------------------------------------------------------------------------------------

    private function hayBanderaRoja(array $analisis, array $sintomas, array $grupos): bool
    {
        // Cualquier bandera del analizador (en cualquiera de sus formas) es emergencia: conservador ante claves nuevas.
        if (array_values(array_filter(array_map(fn ($b) => self::canonica((string) $b), (array) ($analisis['banderas_rojas'] ?? [])))) !== []) {
            return true;
        }
        $cfg = (array) config('razonador.bandera_roja', []);
        foreach ($sintomas as $s) {
            $clave = (string) ($s['clave'] ?? '');
            if (in_array($clave, (array) ($cfg['sintomas'] ?? []), true)) {
                return true;
            }
            if (in_array($clave, (array) ($cfg['sintomas_con_region'] ?? []), true) && $this->esToracico($s)) {
                return true;
            }
        }
        foreach ($grupos as $g) {
            if (($g['ficha']['urgencia_base'] ?? null) === 'emergencia') {
                return true;
            }
        }

        return false;
    }

    /** Forma canónica (la de las fichas) de una clave de bandera roja del analizador. */
    public static function canonica(string $bandera): string
    {
        return (string) config('razonador.bandera_alias.'.$bandera, $bandera);
    }

    private function esToracico(array $s): bool
    {
        $texto = FiltroSeguridad::normalizar((string) ($s['region'] ?? '').' '.(string) ($s['etiqueta'] ?? ''));
        foreach ((array) config('razonador.bandera_roja.regiones_toracicas', []) as $r) {
            if ($texto !== '' && str_contains($texto, (string) $r)) {
                return true;
            }
        }

        return false;
    }

    // --- 2) Alcance -----------------------------------------------------------------------------------------------

    private function fueraDeAlcance(array $contexto): array
    {
        $msg = (string) config('razonador.mensajes.fuera_alcance');

        return [
            'urgencia' => 'observacion',
            'alcance' => false,
            'hipotesis' => [],
            'conclusion' => $msg,
            'acciones' => [],
            'panel_vigente' => false,
            'botiquin_opciones' => [],
            'seguimiento' => [],
            'escalar_si' => [],
            'escalar_ahora' => false,
            'medidas_inmediatas' => [],
            'eva_proxima' => null,
            'datos_usados' => [],
            'fuentes' => [],
            'aviso' => (string) config('ia.aviso'),
        ];
    }

    /** Claves de indicador que el texto menciona (en alcance aunque no haya síntomas). */
    private function indicadoresMencionados(string $texto, array $contexto): array
    {
        $t = FiltroSeguridad::normalizar($texto);
        $out = [];
        foreach ((array) config('razonador.palabras_indicador', []) as $clave => $palabras) {
            foreach ((array) $palabras as $p) {
                if ($t !== '' && str_contains($t, FiltroSeguridad::normalizar((string) $p))) {
                    $out[] = $clave;
                    break;
                }
            }
        }

        return $out;
    }

    /** Indicadores mencionados que están alterados y que ningún síntoma ya cubre. */
    private function gruposIndicador(array $mencionados, array $sintomas, array $contexto): array
    {
        $cubiertos = [];
        foreach ($sintomas as $s) {
            foreach ((array) config('razonador.indicadores_relacionados.'.($s['clave'] ?? ''), []) as $i) {
                $cubiertos[$i] = true;
            }
        }
        $out = [];
        foreach ($mencionados as $clave) {
            $ind = $contexto['indicadores'][$clave] ?? null;
            if (is_array($ind) && in_array($ind['estado'] ?? null, ['warn', 'crit'], true) && ! isset($cubiertos[$clave])) {
                $out[] = ['clave' => $clave, 'indicador' => $ind];
            }
        }

        return $out;
    }

    // --- 3) Agrupación e hipótesis --------------------------------------------------------------------------------

    /** @return array<string, array{slug:string, ficha:?array, sintomas:array}> */
    private function agrupar(array $sintomas): array
    {
        $grupos = [];
        foreach ($sintomas as $s) {
            $ficha = $this->elegirFicha($s);
            $slug = $ficha['slug'] ?? 'sin_ficha:'.($s['clave'] ?? 'desconocido');
            $grupos[$slug] ??= ['slug' => $slug, 'ficha' => $ficha, 'sintomas' => []];
            $grupos[$slug]['sintomas'][] = $s;
        }

        return $grupos;
    }

    private function elegirFicha(array $s): ?array
    {
        $fichas = $this->fichasPorSintoma((string) ($s['clave'] ?? ''));
        if ($fichas === []) {
            return null;
        }
        $region = FiltroSeguridad::normalizar((string) ($s['region'] ?? ''));
        if ($region !== '') {
            foreach ($fichas as $f) {
                foreach ((array) ($f['regiones'] ?? []) as $r) {
                    $r = FiltroSeguridad::normalizar((string) $r);
                    if ($r !== '' && (str_contains($region, $r) || str_contains($r, $region))) {
                        return $f;
                    }
                }
            }
        }

        // Sin región que case: se evita elegir una ficha de emergencia por defecto (p. ej. dolor torácico para un dolor genérico).
        $suaves = array_values(array_filter($fichas, fn ($f) => ($f['urgencia_base'] ?? null) !== 'emergencia'));

        return ($suaves[0] ?? $fichas[0]);
    }

    private function hipotesis(array $g, array $contexto, array $antecedentes, array $analisis): array
    {
        $cfg = (array) config('razonador.confianza', []);
        $ficha = $g['ficha'];
        $claves = array_values(array_unique(array_map(fn ($s) => (string) $s['clave'], $g['sintomas'])));
        $titulo = $ficha !== null
            ? $this->posible((string) ($ficha['titulo'] ?? $g['slug']))
            : 'Posible causa a descartar: '.$this->etiquetasSintomas($g['sintomas']);

        $por = [];
        $por[] = 'Describes: '.$this->etiquetasSintomas($g['sintomas']).'.';
        $confianza = (float) ($cfg['base'] ?? 20) + (float) ($cfg['factor_sintomas'] ?? 0.6) * $this->promedioConfianza($g['sintomas']);

        // Indicadores relacionados.
        $altoPulso = false;
        foreach ($this->indicadoresDe($claves) as $clave) {
            $ind = $contexto['indicadores'][$clave] ?? null;
            if (! is_array($ind)) {
                continue;
            }
            foreach ($this->citarIndicador($ind) as $linea) {
                $por[] = $linea;
            }
            if (in_array($ind['estado'] ?? null, ['warn', 'crit'], true)) {
                $confianza += (float) ($cfg['indicador_alterado'] ?? 6);
                if ($clave === 'frecuencia_cardiaca' && ($ind['direccion'] ?? null) === 'alto') {
                    $altoPulso = true;
                }
            }
        }

        // Casos detectados relacionados.
        foreach ($this->casosRelacionados($claves, $contexto) as $caso) {
            $por[] = 'Hay un caso detectado de '.str_replace('_', ' ', (string) $caso['patron']).' (severidad '.($caso['severidad'] ?? 'sin dato').').';
            $confianza += (float) ($cfg['caso_relacionado'] ?? 4);
        }

        // Dolores registrados en la misma zona.
        foreach ($this->doloresRelacionados($g['sintomas'], $contexto) as $d) {
            $por[] = 'Registraste antes un dolor en '.$d['zona'].(isset($d['intensidad']) ? ' (intensidad '.$d['intensidad'].')' : '').'.';
        }

        // Expediente.
        foreach ($antecedentes as $a) {
            if (array_intersect($claves, $a['claves']) !== []) {
                $por[] = $a['texto'];
                $confianza += (float) ($cfg['antecedente'] ?? 8);
            }
        }
        if (! empty($contexto['expediente']['alergias'])) {
            $por[] = 'Figura en tu expediente al menos una alergia; las opciones del botiquín se filtran en consecuencia.';
        }

        // Ejercicio intenso reciente: explica un pulso alto y baja la confianza de causa patológica.
        if (($contexto['rutina']['ejercicio_intenso_reciente'] ?? false) && (in_array('palpitaciones', $claves, true) || $altoPulso)) {
            $por[] = 'Registras ejercicio intenso reciente, que puede explicar un pulso alto; por eso es menos probable una causa patológica.';
            $confianza += (float) ($cfg['ejercicio_intenso'] ?? -20);
        }

        if ($ficha === null) {
            $por[] = (string) config('razonador.mensajes.sin_ficha');
            $confianza += (float) ($cfg['sin_ficha'] ?? -15);
        }

        $confianza = (int) round(max((float) ($cfg['min'] ?? 5), min((float) ($cfg['max'] ?? 90), $confianza)));
        $urgencia = $this->nivelValido($ficha['urgencia_base'] ?? null) ?? 'observacion';

        // Indicador relacionado en crítico/precaución fija un mínimo de urgencia.
        foreach ($this->indicadoresDe($claves) as $clave) {
            $estado = $contexto['indicadores'][$clave]['estado'] ?? null;
            $min = config('razonador.urgencia_por_indicador.'.$estado);
            if (is_string($min)) {
                $urgencia = $this->max($urgencia, $min);
            }
        }

        return [[
            'titulo' => $titulo,
            'confianza' => $confianza,
            'por_que' => array_values($por),
            'sintomas' => $claves,
            'ficha' => (string) ($ficha['slug'] ?? ''),
            'origen' => ($ficha['origen'] ?? null) === 'documento' ? 'documento' : 'ejemplo_simulado',
        ], $urgencia];
    }

    private function hipotesisIndicador(array $g, array $contexto): array
    {
        $ind = $g['indicador'];
        $etiqueta = mb_strtolower((string) ($ind['etiqueta'] ?? $g['clave']));
        $dir = $ind['direccion'] ?? null;
        $donde = $dir === 'alto' ? ' por encima de tu rango' : ($dir === 'bajo' ? ' por debajo de tu rango' : '');
        $por = $this->citarIndicador($ind);
        $por[] = 'La orientación de abajo respeta la dirección de la alteración: no se propone subir un valor que ya está alto ni bajar uno que ya está bajo.';
        $estado = $ind['estado'] ?? null;

        return [[
            'titulo' => 'Posible alteración de '.$etiqueta.$donde,
            'confianza' => (int) config('razonador.confianza.indicador', 60),
            'por_que' => $por,
            'sintomas' => [],
            'ficha' => '',
            'origen' => 'ejemplo_simulado',
        ], (string) (config('razonador.urgencia_por_indicador.'.$estado) ?? 'observacion')];
    }

    private function lineaMedicacion(array $contexto): ?string
    {
        $nombres = array_values(array_filter(array_map(fn ($m) => (string) ($m['nombre'] ?? ''), (array) ($contexto['expediente']['medicacion'] ?? []))));

        return $nombres === [] ? null : FiltroSeguridad::ETIQUETA_MEDICACION.' '.implode(', ', $nombres).'. Coméntalo con el médico de vuelo.';
    }

    private function posible(string $titulo): string
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            return 'Posible causa a descartar';
        }

        return 'Posible '.mb_strtolower(mb_substr($titulo, 0, 1)).mb_substr($titulo, 1);
    }

    private function etiquetasSintomas(array $sintomas): string
    {
        $et = array_map(fn ($s) => mb_strtolower((string) ($s['etiqueta'] ?? $s['clave'] ?? '')), $sintomas);

        return implode('; ', array_values(array_unique($et)));
    }

    private function promedioConfianza(array $sintomas): float
    {
        if ($sintomas === []) {
            return 0;
        }
        $v = array_map(fn ($s) => (float) ($s['confianza'] ?? 50), $sintomas);

        return array_sum($v) / count($v);
    }

    private function indicadoresDe(array $claves): array
    {
        $out = [];
        foreach ($claves as $c) {
            foreach ((array) config('razonador.indicadores_relacionados.'.$c, []) as $i) {
                $out[$i] = $i;
            }
        }

        return array_values($out);
    }

    /** Líneas que citan datos reales del indicador. */
    private function citarIndicador(array $ind): array
    {
        $et = mb_strtolower((string) ($ind['etiqueta'] ?? 'indicador'));
        $uni = trim((string) ($ind['unidad'] ?? ''));
        $out = [];
        $serie = array_values(array_filter((array) ($ind['serie_7d'] ?? []), 'is_numeric'));
        if (count($serie) >= 2 && (float) $serie[0] !== (float) end($serie)) {
            $a = (float) $serie[0];
            $b = (float) end($serie);
            $out[] = 'Tu '.$et.' '.($b > $a ? 'subió' : 'bajó').' de '.$this->num($a).' a '.$this->num($b).($uni !== '' ? ' '.$uni : '').' en los últimos 7 días.';
        } elseif (! empty($ind['valor_texto'])) {
            $estado = $ind['estado'] ?? null;
            $out[] = 'Tu '.$et.' actual es '.$ind['valor_texto']
                .(! empty($ind['base_texto']) ? ' (tu base personal: '.$ind['base_texto'].')' : '')
                .($estado === 'ok' ? ', dentro de rango.' : '.');
        }
        if (! empty($ind['sospechoso'])) {
            $out[] = 'La lectura de '.$et.' es sospechosa de fallo del sensor; conviene repetir la medición.';
        }

        return $out;
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    }

    private function casosRelacionados(array $claves, array $contexto): array
    {
        $patrones = [];
        foreach ($claves as $c) {
            foreach ((array) config('razonador.casos_relacionados.'.$c, []) as $p) {
                $patrones[$p] = true;
            }
        }
        $out = [];
        foreach ((array) ($contexto['casos'] ?? []) as $caso) {
            if (isset($patrones[$caso['patron'] ?? '']) && ! in_array($caso['estado'] ?? '', ['cerrado', 'resuelto'], true)) {
                $out[] = $caso;
            }
        }

        return $out;
    }

    private function doloresRelacionados(array $sintomas, array $contexto): array
    {
        $out = [];
        foreach ($sintomas as $s) {
            $region = FiltroSeguridad::normalizar((string) ($s['region'] ?? ''));
            if ($region === '') {
                continue;
            }
            foreach ((array) ($contexto['dolores'] ?? []) as $d) {
                $zona = FiltroSeguridad::normalizar((string) ($d['zona'] ?? '').' '.(string) ($d['musculo'] ?? ''));
                if ($zona !== '' && str_contains($zona, $region)) {
                    $out[] = $d;
                }
            }
        }

        return $out;
    }

    // --- 4) Contexto: antecedentes, regiones, EVA -----------------------------------------------------------------

    /** @return array<int, array{claves:array<int,string>, texto:string}> */
    private function antecedentesRelacionados(array $sintomas, array $contexto): array
    {
        $exp = (array) ($contexto['expediente'] ?? []);
        $registros = [];
        foreach ((array) ($exp['antecedentes'] ?? []) as $a) {
            $registros[] = [FiltroSeguridad::normalizar((string) ($a['condicion'] ?? '')), 'Figura en tu expediente un antecedente de '.($a['condicion'] ?? '').(! empty($a['parentesco']) ? ' ('.$a['parentesco'].')' : '').'.'];
        }
        foreach ((array) ($exp['condiciones'] ?? []) as $c) {
            $registros[] = [FiltroSeguridad::normalizar((string) ($c['nombre'] ?? '')), 'Registras una condición relacionada: '.($c['nombre'] ?? '').'.'];
        }

        $out = [];
        foreach ($registros as [$nombre, $texto]) {
            if ($nombre === '') {
                continue;
            }
            $claves = [];
            foreach ($sintomas as $s) {
                $clave = (string) ($s['clave'] ?? '');
                foreach ((array) config('razonador.antecedentes_palabras.'.$clave, []) as $p) {
                    if (str_contains($nombre, FiltroSeguridad::normalizar((string) $p))) {
                        $claves[] = $clave;
                        break;
                    }
                }
            }
            if ($claves !== []) {
                $out[] = ['claves' => array_values(array_unique($claves)), 'texto' => $texto];
            }
        }

        return $out;
    }

    private function variasRegionesConMareo(array $sintomas): bool
    {
        $cfg = (array) config('razonador.multiples_regiones', []);
        $claves = array_column($sintomas, 'clave');
        if (! in_array($cfg['sintoma_clave'] ?? 'mareo', $claves, true)) {
            return false;
        }
        $regiones = [];
        foreach ($sintomas as $s) {
            $r = FiltroSeguridad::normalizar((string) ($s['region'] ?? ''));
            if ($r !== '') {
                $regiones[$r] = true;
            }
        }

        return count($regiones) >= (int) ($cfg['min_regiones'] ?? 2);
    }

    private function evaProxima(array $contexto): ?string
    {
        $cfg = (array) config('razonador.eventos', []);
        foreach ((array) ($contexto['mision']['eventos_proximos'] ?? []) as $e) {
            $nombre = (string) ($e['nombre'] ?? '');
            $n = FiltroSeguridad::normalizar($nombre);
            $dias = $e['en_dias'] ?? null;
            if ($dias !== null && $dias > (int) ($cfg['ventana_dias'] ?? 7)) {
                continue;
            }
            foreach ((array) ($cfg['palabras'] ?? []) as $p) {
                if (preg_match('/(?<![a-z])'.preg_quote((string) $p, '/').'/u', $n) === 1) {
                    return 'Esto podría afectar tu '.$nombre.(isset($e['dia']) ? ' del día '.$e['dia'] : '').': coméntalo con el médico de vuelo.';
                }
            }
        }

        return null;
    }

    // --- 6) Acciones, botiquín, seguimiento, escalar --------------------------------------------------------------

    private function fichasDe(array $grupos): array
    {
        $out = [];
        foreach ($grupos as $g) {
            if ($g['ficha'] !== null) {
                $out[$g['slug']] = $g['ficha'];
            }
        }

        return array_values($out);
    }

    private function accionesEmergencia(): array
    {
        return [[
            'id' => 'avisar_tierra_ahora',
            'texto' => 'Avisa a tierra ahora con el resumen de tus síntomas y confirma con el médico de vuelo.',
            'fuente' => $this->fuenteEjemplo(),
        ]];
    }

    /** @return array{0: array, 1: bool} */
    private function acciones(array $fichas, array $contexto, array $idsPanel): array
    {
        $acciones = [];
        $vigente = false;
        $vistos = [];

        foreach ($fichas as $f) {
            foreach ((array) ($f['acciones'] ?? []) as $a) {
                $acc = $this->normalizarAccion($a, $f);
                if ($acc === null || isset($vistos[$acc['id']])) {
                    continue;
                }
                if (in_array($acc['id'], $idsPanel, true)) {
                    $vigente = true;
                    continue;
                }
                $vistos[$acc['id']] = true;
                $acciones[] = $acc;
            }
        }

        $esp = $this->recomendacionesEspecificas($contexto, $idsPanel);
        foreach ((array) ($esp['acciones'] ?? []) as $a) {
            $acc = $this->normalizarAccion($a, null);
            if ($acc === null || isset($vistos[$acc['id']]) || in_array($acc['id'], $idsPanel, true)) {
                continue;
            }
            $vistos[$acc['id']] = true;
            $acciones[] = $acc;
        }
        $vigente = $vigente || (bool) ($esp['panel_vigente'] ?? false);

        $acciones[] = [
            'id' => 'confirmar_medico_vuelo',
            'texto' => OrientadorSimulado::CONFIRMAR,
            'fuente' => $this->fuenteEjemplo(),
        ];

        return [$acciones, $vigente];
    }

    private function normalizarAccion(mixed $a, ?array $ficha): ?array
    {
        if (! is_array($a) || trim((string) ($a['texto'] ?? '')) === '') {
            return null;
        }
        // Una acción que no supera el filtro de seguridad se descarta sola (el resto de la respuesta se conserva).
        if (! $this->filtro()->permitido((string) $a['texto'])) {
            return null;
        }
        $origenFicha = ($ficha['origen'] ?? null) === 'documento' ? 'documento' : 'ejemplo_simulado';
        $fuente = $this->normalizarFuente($a['fuente'] ?? null, $origenFicha);

        return [
            'id' => (string) ($a['id'] ?? md5((string) $a['texto'])),
            'texto' => (string) $a['texto'],
            'fuente' => $fuente,
        ];
    }

    /** Siempre devuelve etiqueta, texto y origen: sin fuente = "Pendiente de fuente verificable". */
    private function normalizarFuente(mixed $f, string $origen): array
    {
        if (is_array($f)) {
            $etiqueta = (string) ($f['etiqueta'] ?? '');
            $texto = (string) ($f['texto'] ?? $f['fuente'] ?? $f['archivo'] ?? '');
            $o = in_array($f['origen'] ?? null, ['documento', 'ejemplo_simulado'], true) ? $f['origen'] : $origen;
            if ($etiqueta !== '' || $texto !== '') {
                return ['etiqueta' => $etiqueta !== '' ? $etiqueta : ($o === 'documento' ? 'Documento' : (string) config('razonador.origen_ejemplo')), 'texto' => $texto, 'origen' => $o];
            }
        } elseif (is_string($f) && trim($f) !== '') {
            return ['etiqueta' => $origen === 'documento' ? 'Documento' : (string) config('razonador.origen_ejemplo'), 'texto' => trim($f), 'origen' => $origen];
        }

        return ['etiqueta' => OrientadorSimulado::PENDIENTE, 'texto' => 'Confirma con el médico de vuelo.', 'origen' => 'ejemplo_simulado'];
    }

    private function fuenteEjemplo(): array
    {
        return ['etiqueta' => (string) config('razonador.origen_ejemplo'), 'texto' => 'Orientación general y conservadora de la simulación.', 'origen' => 'ejemplo_simulado'];
    }

    private function botiquin(array $fichas, array $contexto): array
    {
        $grupos = [];
        foreach ($fichas as $f) {
            foreach ((array) ($f['grupos_botiquin_permitidos'] ?? []) as $g) {
                $grupos[$g] = $g;
            }
        }
        if ($grupos === []) {
            return [];
        }
        try {
            $opciones = $this->opcionesBotiquin($contexto, array_values($grupos));
        } catch (\Throwable) {
            return [];
        }

        $opciones = array_slice(array_values((array) $opciones), 0, 3);
        // Validación de las opciones por el filtro (grupo, stock, alergia, texto sin dosis); si falla, ninguna.
        if ($this->filtro()->revisarOpcionesBotiquin($opciones, $contexto, array_values($grupos), false) !== null) {
            return [];
        }

        return $opciones;
    }

    private function seguimiento(array $fichas, array $gruposIndicador, bool $emergencia): array
    {
        if ($emergencia) {
            return [
                ['texto' => 'Vigila tu pulso.', 'indicador' => 'frecuencia_cardiaca'],
                ['texto' => 'Vigila tu saturación de oxígeno.', 'indicador' => 'saturacion_o2'],
            ];
        }
        $out = [];
        $vistos = [];
        foreach ($fichas as $f) {
            foreach ((array) ($f['seguimiento'] ?? []) as $s) {
                $texto = trim((string) (is_array($s) ? ($s['texto'] ?? '') : $s));
                if ($texto === '' || isset($vistos[$texto])) {
                    continue;
                }
                $vistos[$texto] = true;
                $out[] = ['texto' => $texto, 'indicador' => is_array($s) ? ($s['indicador'] ?? null) : null];
            }
        }
        foreach ($gruposIndicador as $g) {
            $texto = 'Registra una nueva medición de '.mb_strtolower((string) ($g['indicador']['etiqueta'] ?? $g['clave'])).'.';
            if (! isset($vistos[$texto])) {
                $out[] = ['texto' => $texto, 'indicador' => $g['clave']];
            }
        }

        return $out;
    }

    private function escalarSi(array $fichas, bool $emergencia, ?string $evolucion): array
    {
        if ($emergencia) {
            return array_values((array) config('razonador.escalar_si_emergencia', []));
        }
        $out = [];
        foreach ($fichas as $f) {
            foreach ((array) ($f['escalar_si'] ?? []) as $e) {
                if (is_string($e) && trim($e) !== '') {
                    $out[] = trim($e);
                }
            }
        }
        if ($evolucion === 'igual') {
            $out[] = 'Como los síntomas siguen igual, si persisten avisa a tierra y confirma con el médico de vuelo.';
        }
        foreach ((array) config('razonador.escalar_si_general', []) as $e) {
            $out[] = $e;
        }

        return array_values(array_unique($out));
    }

    private function fuentes(array $fichas): array
    {
        $out = [];
        foreach ($fichas as $f) {
            $origen = ($f['origen'] ?? null) === 'documento' ? 'documento' : 'ejemplo_simulado';
            $fuentes = (array) ($f['fuentes'] ?? []);
            if ($fuentes === []) {
                $out[] = $origen === 'documento'
                    ? ['etiqueta' => 'Ficha de conocimiento', 'texto' => (string) ($f['titulo'] ?? $f['slug'] ?? ''), 'origen' => 'documento']
                    : $this->fuenteEjemplo();
            }
            foreach ($fuentes as $fu) {
                $out[] = $this->normalizarFuente($fu, $origen);
            }
        }
        if ($out === []) {
            $out[] = ['etiqueta' => OrientadorSimulado::PENDIENTE, 'texto' => 'Confirma con el médico de vuelo.', 'origen' => 'ejemplo_simulado'];
        }
        $vistos = [];

        return array_values(array_filter($out, function ($x) use (&$vistos) {
            $k = $x['etiqueta'].'|'.$x['texto'];

            return isset($vistos[$k]) ? false : ($vistos[$k] = true);
        }));
    }

    // --- Conclusión -----------------------------------------------------------------------------------------------

    private function conclusion(array $hipotesis, string $urgencia, ?string $evolucion, ?string $previa, array $contexto, ?string $eva, array $ajustes): string
    {
        $titulos = array_map(fn ($h) => mb_strtolower((string) $h['titulo']), $hipotesis);
        $t = count($titulos) > 1
            ? 'Con lo que cuentas y tus datos, veo '.count($titulos).' grupos de síntomas a valorar: '.implode('; ', $titulos).'.'
            : 'Con lo que cuentas y tus datos, la hipótesis principal es: '.($titulos[0] ?? 'sin hipótesis clara').'.';

        $t .= ' Urgencia estimada: '.$urgencia.'.';
        if ($urgencia === 'emergencia') {
            $t .= ' No es momento de autoatenderte: aplica las medidas inmediatas y avisa a tierra ahora.';
        }
        if (in_array('regiones', $ajustes, true)) {
            $t .= ' Síntomas en varias regiones junto con mareo suben la urgencia (supuesto del proyecto).';
        }
        if (in_array('antecedente', $ajustes, true)) {
            $t .= ' Un antecedente relacionado en tu expediente sube la urgencia un nivel.';
        }
        if ($evolucion === 'empeoro') {
            $t .= ' Indicas que empeoró: reevalúo y subo la urgencia'.($previa !== null ? ' respecto a la anterior ('.$previa.')' : '').'.';
        } elseif ($evolucion === 'igual') {
            $t .= ' Indicas que sigue igual: mantengo la urgencia y, si persiste, avisa a tierra.';
        }
        if (! empty($contexto['expediente']['medicacion'])) {
            $t .= ' Hay medicación activa registrada: coméntala con el médico de vuelo.';
        }
        if ($eva !== null) {
            $t .= ' '.$eva;
        }

        return $t.' '.OrientadorSimulado::CONFIRMAR;
    }

    // --- 7) Filtro de seguridad -----------------------------------------------------------------------------------

    /** @return array<int, string> */
    private function textos(array $r): array
    {
        $t = [$r['conclusion']];
        foreach ($r['hipotesis'] as $h) {
            $t[] = $h['titulo'];
            array_push($t, ...array_values(array_map('strval', $h['por_que'])));
        }
        foreach ($r['acciones'] as $a) {
            $t[] = $a['texto'];
        }
        foreach ($r['seguimiento'] as $s) {
            $t[] = $s['texto'];
        }
        array_push($t, ...$r['escalar_si'], ...$r['medidas_inmediatas']);
        if ($r['eva_proxima'] !== null) {
            $t[] = $r['eva_proxima'];
        }

        return $t;
    }

    /** Texto por reglas seguro: conserva urgencia y escalado, descarta lo que no superó el filtro. */
    private function porReglas(array $r): array
    {
        $r['hipotesis'] = array_map(fn ($h) => [
            'titulo' => 'Hipótesis a descartar con el médico de vuelo',
            'confianza' => $h['confianza'],
            'por_que' => [],
            'sintomas' => $h['sintomas'],
            'ficha' => $h['ficha'],
            'origen' => 'ejemplo_simulado',
        ], $r['hipotesis']);
        $r['conclusion'] = 'Urgencia estimada: '.$r['urgencia'].'. La redacción ampliada no superó el filtro de seguridad; se muestra el texto por reglas. Aviso: '
            .config('ia.aviso').' '.OrientadorSimulado::CONFIRMAR;
        $r['acciones'] = $r['urgencia'] === 'emergencia'
            ? $this->accionesEmergencia()
            : [['id' => 'confirmar_medico_vuelo', 'texto' => OrientadorSimulado::CONFIRMAR, 'fuente' => $this->fuenteEjemplo()]];
        $r['botiquin_opciones'] = [];
        $r['seguimiento'] = [];
        $r['escalar_si'] = $r['escalar_ahora'] ? array_values((array) config('razonador.escalar_si_emergencia', [])) : array_values((array) config('razonador.escalar_si_general', []));
        $r['eva_proxima'] = null;

        return $r;
    }

    // --- Niveles de urgencia --------------------------------------------------------------------------------------

    private function nivelValido(mixed $n): ?string
    {
        return is_string($n) && in_array($n, (array) config('razonador.niveles'), true) ? $n : null;
    }

    private function indice(string $n): int
    {
        $i = array_search($n, (array) config('razonador.niveles'), true);

        return $i === false ? 0 : (int) $i;
    }

    private function max(string $a, string $b): string
    {
        return $this->indice($b) > $this->indice($a) ? $b : $a;
    }

    private function subir(string $n, int $pasos, string $tope): string
    {
        $niveles = (array) config('razonador.niveles');
        $i = min($this->indice($n) + $pasos, $this->indice($tope));

        return $niveles[max($i, $this->indice($n))] ?? $n;
    }
}
