<?php

namespace App\Services\Ia;

/**
 * Analizador de síntomas en texto libre (IA local SIMULADA: léxico y reglas, SIN modelo de lenguaje).
 *
 * - El texto del usuario es DATO: solo se compara contra el léxico de config/sintomas.php. Nunca se interpreta como
 *   instrucción, no se evalúa ni se usa en plantillas. Se normaliza (minúsculas, sin tildes) y se limita su longitud.
 * - Devuelve el contrato "Análisis" (texto, entendido, confianza, sintomas[], negados[], banderas_rojas[]).
 * - Las banderas rojas se calculan a partir de los síntomas NO negados, de modo que "no tengo dolor en el pecho"
 *   no activa dolor_toracico, y se detectan aunque haya más síntomas en la frase.
 * - Campos opcionales de un síntoma (solo cuando son verdaderos): 'subito', 'persistente', 'importante'.
 * - Las regiones son claves canónicas sin tildes (triceps, muneca, espalda baja...), ver config/sintomas.php.
 * - Los métodos son estáticos para que cualquier módulo los use sin instanciar.
 *
 * Todos los números (ventanas, confianzas, valores de intensidad por palabra) son SUPUESTOS DEL PROYECTO, NO DE LA NORMA.
 */
class AnalizadorSintomas
{
    /** @var array<string, array> */
    private static array $vocCache = [];

    private static ?array $cfgManual = null;

    private const CALIDAD_ETIQUETA = ['pulsatil' => 'pulsátil', 'colico' => 'tipo cólico', 'calambre' => 'tipo calambre', 'rigidez' => 'con rigidez'];

    /** Solo para pruebas: usa esta configuración en lugar de config('sintomas'). null = la normal. */
    public static function usarConfig(?array $cfg): void
    {
        self::$cfgManual = $cfg;
    }

    /** @return array<string, mixed> */
    private static function cfg(): array
    {
        if (self::$cfgManual !== null) {
            return self::$cfgManual;
        }
        $c = null;
        if (function_exists('config')) {
            try {
                $c = config('sintomas');
            } catch (\Throwable) {
                $c = null;
            }
        }

        return is_array($c) && $c !== [] ? $c : require dirname(__DIR__, 3).'/config/sintomas.php';
    }

    // ================================================================= API pública

    /** @return array{texto:string,entendido:bool,confianza:int,sintomas:array,negados:array,banderas_rojas:array} */
    public static function analizar(string $texto): array
    {
        $cfg = self::cfg();
        $par = $cfg['analizador'];
        [$original, $recortado] = self::limpiar($texto, (int) $par['max_caracteres']);

        $res = ['texto' => $original, 'entendido' => false, 'confianza' => 0, 'sintomas' => [], 'negados' => [], 'banderas_rojas' => []];
        if ($original === '') {
            return $res;
        }

        $voc = self::vocab($cfg);
        $tokens = self::tokenizar($original, $cfg);
        [$eventos, $desconocidas, $consumidas] = self::extraer($tokens, $voc, $cfg);
        $grupos = self::construirGrupos($eventos, $tokens, $cfg);
        [$sintomas, $negados] = self::entidades($grupos, $cfg);

        $res['sintomas'] = $sintomas;
        $res['negados'] = $negados;
        $res['banderas_rojas'] = self::banderas($sintomas);
        $res['entendido'] = $sintomas !== [] || $negados !== [];

        if (! $res['entendido']) {
            return $res;
        }

        $c = $par['confianza'];
        $media = $sintomas !== []
            ? array_sum(array_column($sintomas, 'confianza')) / count($sintomas)
            : (float) $c['solo_negaciones'];
        $total = $desconocidas + $consumidas;
        $penaliza = $total > 0 ? (int) round($c['penalizacion_max_no_reconocido'] * $desconocidas / $total) : 0;
        if ($recortado) {
            $penaliza += (int) $c['penalizacion_texto_recortado'];
        }
        $res['confianza'] = max(0, min(100, (int) round($media) - $penaliza));

        return $res;
    }

    /**
     * Reconstruye un análisis desde los chips editados por el usuario (añadir/quitar). Cada chip confirmado por el usuario
     * lleva la confianza 'chip_usuario'. Acepta arrays con clave/region/lado/calidad/intensidad/duracion_min (o solo la clave
     * como string) y descarta lo que no esté en el léxico.
     *
     * @param  array<int, array|string>  $sintomas
     * @param  array<int, string>  $negados  claves negadas que se conservan (opcional)
     */
    public static function desdeEntidades(array $sintomas, string $texto = '', array $negados = []): array
    {
        $cfg = self::cfg();
        $claves = $cfg['claves'];
        $out = [];

        foreach ($sintomas as $s) {
            if (is_string($s)) {
                $s = ['clave' => $s];
            }
            if (! is_array($s)) {
                continue;
            }
            $clave = is_string($s['clave'] ?? null) ? $s['clave'] : '';
            if (! isset($claves[$clave])) {
                continue;
            }
            $region = is_string($s['region'] ?? null) && isset($cfg['regiones'][$s['region']]) ? $s['region'] : null;
            $lado = self::normalizarLado($s['lado'] ?? null);
            $calidad = is_string($s['calidad'] ?? null) && isset($cfg['calidades'][$s['calidad']]) ? $s['calidad'] : null;
            $intensidad = isset($s['intensidad']) && is_numeric($s['intensidad']) ? max(1, min(10, (int) $s['intensidad'])) : null;
            $duracion = isset($s['duracion_min']) && is_numeric($s['duracion_min']) && (int) $s['duracion_min'] >= 0 ? (int) $s['duracion_min'] : null;

            // Misma normalización que el analizador.
            if ($clave === 'dolor' && $region === 'cabeza') {
                $clave = 'dolor_cabeza';
            } elseif ($clave === 'dolor_cabeza') {
                $region = 'cabeza';
            }
            if ($clave === 'presion') {
                $clave = $region === 'pecho' ? 'presion_pecho' : 'dolor';
            }
            if ($clave === 'presion_pecho') {
                $region = 'pecho';
                $calidad = 'opresivo';
            }
            if (! ($claves[$clave]['calidad'] ?? false) && $clave !== 'presion_pecho') {
                $calidad = null;
            }

            $e = [
                'clave' => $clave,
                'etiqueta' => '',
                'region' => $region,
                'lado' => $lado,
                'calidad' => $calidad,
                'intensidad' => $intensidad,
                'duracion_min' => $duracion,
                'confianza' => (int) $cfg['analizador']['confianza']['chip_usuario'],
            ];
            foreach (['subito', 'persistente', 'importante'] as $extra) {
                if (! empty($s[$extra])) {
                    $e[$extra] = true;
                }
            }
            $e['etiqueta'] = self::etiquetar($e);
            $out[] = $e;
        }

        $out = self::fusionar($out);
        $negs = [];
        foreach ($negados as $n) {
            if (is_string($n) && isset($claves[$n]) && ! in_array($n, array_column($out, 'clave'), true)) {
                $negs[$n] = $n;
            }
        }
        [$texto] = self::limpiar($texto, (int) $cfg['analizador']['max_caracteres']);

        return [
            'texto' => $texto,
            'entendido' => $out !== [] || $negs !== [],
            'confianza' => $out !== [] ? (int) $cfg['analizador']['confianza']['chip_usuario'] : ($negs !== [] ? (int) $cfg['analizador']['confianza']['chip_usuario'] : 0),
            'sintomas' => $out,
            'negados' => array_values($negs),
            'banderas_rojas' => self::banderas($out),
        ];
    }

    /** Etiqueta del chip: "Dolor punzante · pecho izquierdo". */
    public static function etiquetar(array $s): string
    {
        $cfg = self::cfg();
        $clave = (string) ($s['clave'] ?? '');
        $def = $cfg['claves'][$clave] ?? null;
        $base = $def['etiqueta'] ?? ucfirst(str_replace('_', ' ', $clave));
        $derivada = in_array($clave, $cfg['claves_derivadas'] ?? [], true);

        $calidad = $s['calidad'] ?? null;
        if ($calidad && ($def['calidad'] ?? false)) {
            $base .= ' '.(self::CALIDAD_ETIQUETA[$calidad] ?? $calidad);
        }

        $region = $s['region'] ?? null;
        $lado = $s['lado'] ?? null;
        $genero = $region ? ($cfg['regiones'][$region]['genero'] ?? 'm') : 'm';
        $ladoTxt = $lado ? ($genero === 'f' ? ($lado === 'izquierdo' ? 'izquierda' : 'derecha') : $lado) : null;

        if ($derivada) {
            return $ladoTxt ? $base.' · lado '.$lado : $base;
        }
        if ($region) {
            $nombre = $cfg['regiones'][$region]['etiqueta'] ?? $region;

            return $base.' · '.$nombre.($ladoTxt ? ' '.$ladoTxt : '');
        }

        return $lado ? $base.' · lado '.$lado : $base;
    }

    /** Catálogo para ofrecer chips al usuario (añadir un síntoma, elegir una zona). */
    public static function catalogo(): array
    {
        $cfg = self::cfg();

        return [
            'claves' => array_map(fn ($d) => $d['etiqueta'], $cfg['claves']),
            'regiones' => array_map(fn ($d) => $d['etiqueta'], $cfg['regiones']),
            'lados' => ['izquierdo', 'derecho'],
            'calidades' => array_keys($cfg['calidades']),
            'banderas' => array_map(fn ($b) => $b['descripcion'], $cfg['banderas']),
        ];
    }

    /** Banderas rojas (claves de config) que cumplen los síntomas dados (todos no negados). */
    public static function banderas(array $sintomas): array
    {
        $cfg = self::cfg();
        $out = [];
        foreach ($cfg['banderas'] as $clave => $def) {
            foreach ($def['reglas'] as $regla) {
                foreach ($sintomas as $s) {
                    if (self::cumple($s, $regla)) {
                        $out[] = $clave;
                        continue 3;
                    }
                }
            }
        }

        return $out;
    }

    private static function cumple(array $s, array $regla): bool
    {
        if (! in_array($s['clave'] ?? '', $regla['claves'], true)) {
            return false;
        }
        if (isset($regla['regiones']) && ! in_array($s['region'] ?? null, $regla['regiones'], true)) {
            return false;
        }
        if (! empty($regla['exige_lado']) && empty($s['lado']) && ($s['region'] ?? null) !== 'un_lado') {
            return false;
        }
        foreach ($regla['exige'] ?? [] as $campo) {
            if (empty($s[$campo])) {
                return false;
            }
        }
        if (isset($regla['intensidad_min']) && (($s['intensidad'] ?? null) === null || $s['intensidad'] < $regla['intensidad_min'])) {
            return false;
        }

        return true;
    }

    // ================================================================= normalización

    /** @return array{0:string,1:bool} [texto limpio y recortado, true si se recortó] */
    private static function limpiar(string $texto, int $max): array
    {
        $t = mb_scrub($texto, 'UTF-8');
        $t = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $t) ?? '';
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
        $recortado = false;
        if ($max > 0 && mb_strlen($t) > $max) {
            $t = rtrim(mb_substr($t, 0, $max));
            $recortado = true;
        }

        return [$t, $recortado];
    }

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);

        return preg_replace('/\p{Mn}/u', '', $s) ?? $s;
    }

    private static function normalizarLado(mixed $l): ?string
    {
        if (! is_string($l) || $l === '') {
            return null;
        }
        $l = self::normalizar(trim($l));

        return str_starts_with($l, 'i') ? 'izquierdo' : (str_starts_with($l, 'd') ? 'derecho' : null);
    }

    /**
     * Pasa el texto a una lista de palabras. Antes extrae la duración y la intensidad numérica y las deja como
     * marcas "#d<minutos>" y "#i<1-10>"; los signos , y . / ; ! ? quedan como separadores.
     *
     * @return array<int, string>
     */
    private static function tokenizar(string $original, array $cfg): array
    {
        $n = self::normalizar($original);
        $d = $cfg['duracion'];

        $nums = array_keys($d['numeros']);
        usort($nums, fn ($a, $b) => strlen($b) <=> strlen($a));
        $numAlt = implode('|', array_map(fn ($x) => preg_quote($x, '/'), $nums));
        $uni = array_keys($d['unidades']);
        usort($uni, fn ($a, $b) => strlen($b) <=> strlen($a));
        $uniAlt = implode('|', array_map(fn ($x) => preg_quote($x, '/'), $uni));
        $rel = array_keys($d['relativos']);
        usort($rel, fn ($a, $b) => strlen($b) <=> strlen($a));
        $relAlt = implode('|', array_map(fn ($x) => preg_quote($x, '/'), $rel));

        // "desde hace 20 minutos", "hace 2 horas", "llevo tres dias".
        $n = preg_replace_callback(
            '/\b(?:desde hace|desde|hace|llevo|lleva|llevamos|durante|ya van)\s+(?:(?:unos|unas|casi|como|mas de|menos de|apenas|aproximadamente|aprox)\s+)*(\d+(?:[.,]\d+)?|'.$numAlt.')\s*('.$uniAlt.')\b/u',
            function ($m) use ($d) {
                $num = ctype_digit(str_replace([',', '.'], '', $m[1])) ? (float) str_replace(',', '.', $m[1]) : (float) ($d['numeros'][$m[1]] ?? 0);
                $min = (int) round($num * ($d['unidades'][$m[2]] ?? 0));

                return $min > 0 ? ' #d'.min($min, 525600).' ' : ' ';
            },
            $n
        ) ?? $n;
        $n = preg_replace('/\b(?:desde hace|hace|llevo|lleva|durante|desde)\s+(?:una\s+)?media hora\b/u', ' #d'.(int) $d['media_hora'].' ', $n) ?? $n;
        $n = preg_replace_callback('/\bdesde\s+('.$relAlt.')\b/u', fn ($m) => ' #d'.(int) ($d['relativos'][$m[1]] ?? 0).' ', $n) ?? $n;

        // Intensidad numérica: "7/10", "7 de 10", "un 7 del 1 al 10", "del 1 al 10, un 7", "intensidad 7".
        $marca = fn ($m) => ((int) $m[1] >= 1 && (int) $m[1] <= 10) ? ' #i'.(int) $m[1].' ' : ' ';
        foreach ([
            '/\b(\d{1,2})\s+(?:en|de)\s+(?:una\s+)?escala(?:\s+(?:del|de)\s+(?:1|uno)\s+(?:al|a)\s+(?:10|diez))?/u',
            '/\b(\d{1,2})\s+(?:del|de)\s+(?:1|uno)\s+(?:al|a)\s+(?:10|diez)\b/u',
            '/\b(\d{1,2})\s*(?:\/|sobre|de)\s*10\b/u',
            '/\b(?:del|de)\s+(?:1|uno)\s+(?:al|a)\s+(?:10|diez)\b\D{0,25}?\b(\d{1,2})\b/u',
            '/\b(?:intensidad|nivel|puntuacion|grado)\s*(?:de|es|:)?\s*(?:un\s+)?(\d{1,2})\b/u',
        ] as $re) {
            $n = preg_replace_callback($re, $marca, $n) ?? $n;
        }

        $n = preg_replace('/[,]+/u', ' , ', $n) ?? $n;
        $n = preg_replace('/[.;:!?¡¿\n\r]+/u', ' . ', $n) ?? $n;
        $n = preg_replace('/[^a-z0-9#,.\s]/u', ' ', $n) ?? $n;
        $n = trim($n);

        return $n === '' ? [] : (preg_split('/\s+/', $n) ?: []);
    }

    // ================================================================= vocabulario

    /** @return array{exacto:array,comodin:array} */
    private static function vocab(array $cfg): array
    {
        $key = md5(serialize($cfg));
        if (isset(self::$vocCache[$key])) {
            return self::$vocCache[$key];
        }

        $entradas = [];
        $ord = 0;
        $anadir = function (string|array $frase, string $tipo, mixed $valor, array $op = []) use (&$entradas, &$ord) {
            if (is_array($frase)) {
                $op = array_diff_key($frase, [0 => 1]) + $op;
                $frase = (string) $frase[0];
            }
            $partes = preg_split('/\s+/', trim(self::normalizar($frase))) ?: [];
            $toks = [];
            $wild = [];
            foreach ($partes as $p) {
                if ($p === '') {
                    continue;
                }
                $w = str_ends_with($p, '*');
                $toks[] = $w ? rtrim($p, '*') : $p;
                $wild[] = $w;
            }
            if ($toks === []) {
                return;
            }
            $entradas[] = ['t' => $toks, 'w' => $wild, 'n' => count($toks), 'tipo' => $tipo, 'valor' => $valor, 'op' => $op, 'ord' => $ord++];
        };

        foreach ($cfg['cortes'] as $f) {
            $anadir($f, 'corte', null);
        }
        foreach ($cfg['subito'] as $f) {
            $anadir($f, 'subito', null);
        }
        foreach ($cfg['grave'] as $f) {
            $anadir($f, 'grave', null);
        }
        // "3 veces", "tres veces": vómito repetido.
        $min = (int) $cfg['analizador']['vomito_veces_min'];
        for ($i = $min; $i <= 20; $i++) {
            $anadir($i.' veces', 'grave', null);
        }
        foreach ($cfg['duracion']['numeros'] as $palabra => $valor) {
            if ($valor >= $min) {
                $anadir($palabra.' veces', 'grave', null);
            }
        }
        foreach ($cfg['negaciones'] as $f) {
            $anadir($f, 'negacion', null);
        }
        foreach ($cfg['claves'] as $clave => $def) {
            foreach ($def['frases'] as $f) {
                $anadir($f, 'sintoma', $clave);
            }
        }
        foreach ($cfg['regiones'] as $canon => $def) {
            foreach ($def['frases'] as $f) {
                $anadir($f, 'region', $canon);
            }
        }
        foreach ($cfg['lados'] as $lado => $frases) {
            foreach ($frases as $f) {
                $anadir($f, 'lado', $lado);
            }
        }
        foreach ($cfg['ambos'] as $f) {
            $anadir($f, 'ambos', null);
        }
        foreach ($cfg['calidades'] as $cal => $frases) {
            foreach ($frases as $f) {
                $anadir($f, 'calidad', $cal);
            }
        }
        foreach ($cfg['intensidad_palabras'] as $valor => $frases) {
            foreach ($frases as $f) {
                $anadir($f, 'intensidad', (int) $valor);
            }
        }

        $exacto = [];
        $comodin = [];
        foreach ($entradas as $k => $e) {
            if ($e['w'][0]) {
                $comodin[] = $k;
            } else {
                $exacto[$e['t'][0]][] = $k;
            }
        }
        $voc = ['entradas' => $entradas, 'exacto' => $exacto, 'comodin' => $comodin];
        if (count(self::$vocCache) > 8) {
            self::$vocCache = [];
        }

        return self::$vocCache[$key] = $voc;
    }

    /** Mejor (más larga; a igualdad, la primera definida) entrada del vocabulario que empieza en $i. */
    private static function mejor(array $tokens, int $i, array $voc): ?array
    {
        $tk = $tokens[$i];
        $cand = $voc['exacto'][$tk] ?? [];
        foreach ($voc['comodin'] as $k) {
            if (str_starts_with($tk, $voc['entradas'][$k]['t'][0])) {
                $cand[] = $k;
            }
        }
        $mejor = null;
        foreach ($cand as $k) {
            $e = $voc['entradas'][$k];
            $ok = true;
            for ($j = 0; $j < $e['n']; $j++) {
                $t = $tokens[$i + $j] ?? null;
                if ($t === null || ($e['w'][$j] ? ! str_starts_with($t, $e['t'][$j]) : $t !== $e['t'][$j])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok && ($mejor === null || $e['n'] > $mejor['n'] || ($e['n'] === $mejor['n'] && $e['ord'] < $mejor['ord']))) {
                $mejor = $e;
            }
        }

        return $mejor;
    }

    // ================================================================= eventos

    /** @return array{0:array,1:int,2:int} [eventos, palabras no reconocidas, palabras reconocidas] */
    private static function extraer(array $tokens, array $voc, array $cfg): array
    {
        $vacias = array_flip($cfg['analizador']['palabras_vacias']);
        $ev = [];
        $c = 0;
        $desconocidas = 0;
        $consumidas = 0;
        $n = count($tokens);
        $i = 0;

        while ($i < $n) {
            $tk = $tokens[$i];
            if ($tk === '.') {
                $ev[] = ['tipo' => 'punto', 'i' => $i, 'f' => $i, 'c' => $c];
                $c++;
                $i++;
                continue;
            }
            if ($tk === ',') {
                $ev[] = ['tipo' => 'coma', 'i' => $i, 'f' => $i, 'c' => $c];
                $i++;
                continue;
            }
            if (preg_match('/^#i(\d+)$/', $tk, $m)) {
                $ev[] = ['tipo' => 'intensidad', 'valor' => (int) $m[1], 'num' => true, 'i' => $i, 'f' => $i, 'c' => $c];
                $consumidas++;
                $i++;
                continue;
            }
            if (preg_match('/^#d(\d+)$/', $tk, $m)) {
                $ev[] = ['tipo' => 'duracion', 'valor' => (int) $m[1], 'i' => $i, 'f' => $i, 'c' => $c];
                $consumidas++;
                $i++;
                continue;
            }
            if ($tk === 'y' || $tk === 'o') {
                $ev[] = ['tipo' => 'y', 'i' => $i, 'f' => $i, 'c' => $c];
                $i++;
                continue;
            }

            $m = self::mejor($tokens, $i, $voc);
            if ($m === null) {
                if (! isset($vacias[$tk]) && preg_match('/^[a-z]{2,}$/', $tk)) {
                    $desconocidas++;
                }
                $i++;
                continue;
            }

            $f = $i + $m['n'] - 1;
            $consumidas += $m['n'];
            $e = ['tipo' => $m['tipo'], 'valor' => $m['valor'], 'op' => $m['op'], 'i' => $i, 'f' => $f, 'c' => $c];
            $ev[] = $e;
            if ($m['tipo'] === 'corte') {
                $c++;
            }
            $i = $f + 1;
        }

        return [$ev, $desconocidas, $consumidas];
    }

    // ================================================================= grupos (síntoma + atributos)

    private static function construirGrupos(array $ev, array $tokens, array $cfg): array
    {
        $par = $cfg['analizador'];
        $claves = $cfg['claves'];

        // 1) Calidad + región sin ningún síntoma que admita región en la oración ("algo punzante en el pecho"): dolor implícito.
        $porClausula = [];
        foreach ($ev as $e) {
            $porClausula[$e['c']][] = $e;
        }
        foreach ($porClausula as $lista) {
            $conGrupo = false;
            $conRegion = false;
            $q = null;
            foreach ($lista as $e) {
                if ($e['tipo'] === 'sintoma' && ($claves[$e['valor']]['region'] ?? false)) {
                    $conGrupo = true;
                } elseif ($e['tipo'] === 'region') {
                    $conRegion = true;
                } elseif ($e['tipo'] === 'calidad' && $q === null) {
                    $q = $e;
                }
            }
            if (! $conGrupo && $conRegion && $q !== null) {
                $ev[] = ['tipo' => 'sintoma', 'valor' => 'dolor', 'op' => [], 'i' => $q['i'], 'f' => $q['i'], 'c' => $q['c'], 'sintetico' => true];
            }
        }
        usort($ev, fn ($a, $b) => [$a['i'], ($a['tipo'] === 'sintoma' ? 0 : 1)] <=> [$b['i'], ($b['tipo'] === 'sintoma' ? 0 : 1)]);

        // 2) Negación y creación de grupos.
        $grupos = [];
        $negFin = null;
        $ultNeg = null;
        foreach ($ev as $e) {
            switch ($e['tipo']) {
                case 'punto':
                case 'coma':
                case 'corte':
                    $negFin = null;
                    break;
                case 'y':
                    $sig = $tokens[$e['f'] + 1] ?? '';
                    if (in_array($sig, $par['inicio_clausula'], true)) {
                        $negFin = null;
                        $ultNeg = null;
                    } elseif ($ultNeg !== null && $e['i'] - $ultNeg <= 4) {
                        $negFin = $e['i'];
                    }
                    break;
                case 'negacion':
                    $negFin = $e['f'];
                    break;
                case 'sintoma':
                    $neg = $negFin !== null && ($e['i'] - $negFin) <= (int) $par['ventana_negacion'];
                    $parcial = false;
                    if ($neg) {
                        $sig = $tokens[$e['f'] + 1] ?? '';
                        if (in_array($sig, $par['atenuadores_negacion'], true)) {
                            $neg = false;
                            $parcial = true;
                        } else {
                            $ultNeg = $e['f'];
                        }
                    }
                    $clave = $e['valor'];
                    $grupos[] = [
                        'clave' => $clave, 'i' => $e['i'], 'f' => $e['f'], 'c' => $e['c'], 'negado' => $neg, 'parcial' => $parcial, 'op' => $e['op'],
                        'acepta_region' => (bool) ($claves[$clave]['region'] ?? false),
                        'acepta_calidad' => (bool) ($claves[$clave]['calidad'] ?? false),
                        'regiones' => [], 'lado' => null, 'calidad' => null, 'calidad_explicita' => false,
                        'intensidad' => isset($e['op']['intensidad']) ? (int) $e['op']['intensidad'] : null, 'duracion' => null,
                        'subito' => ! empty($e['op']['subito']), 'grave' => false,
                    ];
                    break;
            }
        }
        if ($grupos === []) {
            return [];
        }

        $en = fn (int $c, ?callable $f = null) => array_values(array_filter(array_keys($grupos), fn ($gi) => $grupos[$gi]['c'] === $c && ($f === null || $f($grupos[$gi]))));

        $elegir = function (array $cand, array $e, bool $preModificador) use (&$grupos, $tokens) {
            $prev = null;
            $next = null;
            foreach ($cand as $gi) {
                if ($grupos[$gi]['i'] <= $e['i']) {
                    $prev = $gi;
                } elseif ($next === null) {
                    $next = $gi;
                }
            }
            if ($preModificador && $next !== null && self::adyacente($tokens, $e['f'], $grupos[$next]['i'])) {
                return $next;
            }

            return $prev ?? $next;
        };

        // 3) Regiones.
        foreach ($ev as $e) {
            if ($e['tipo'] !== 'region') {
                continue;
            }
            $cand = $en($e['c'], fn ($g) => $g['acepta_region']);
            if ($cand === []) {
                continue;
            }
            $prev = null;
            $next = null;
            foreach ($cand as $gi) {
                if ($grupos[$gi]['i'] <= $e['i']) {
                    $prev = $gi;
                } elseif ($next === null) {
                    $next = $gi;
                }
            }
            if ($prev === null) {
                $dest = $next;
            } elseif ($next === null) {
                $dest = $prev;
            } else {
                $dest = ($grupos[$next]['i'] - $e['f'] - 1 <= 3 && $grupos[$prev]['regiones'] !== []) ? $next : $prev;
            }
            $grupos[$dest]['regiones'][] = ['canon' => $e['valor'], 'i' => $e['i'], 'f' => $e['f'], 'lado' => null, 'ambos' => false];
        }

        // 4) "ambos" y lados.
        foreach ($ev as $e) {
            if ($e['tipo'] === 'ambos') {
                foreach ($en($e['c']) as $gi) {
                    foreach ($grupos[$gi]['regiones'] as $ri => $r) {
                        if ($r['i'] > $e['f'] && $r['i'] - $e['f'] - 1 <= 2) {
                            $grupos[$gi]['regiones'][$ri]['ambos'] = true;
                            break 2;
                        }
                    }
                }
            }
        }
        foreach ($ev as $e) {
            if ($e['tipo'] !== 'lado') {
                continue;
            }
            $lado = $e['valor'];
            $mejorPrev = null;
            $mejorNext = null;
            foreach ($en($e['c']) as $gi) {
                foreach ($grupos[$gi]['regiones'] as $ri => $r) {
                    if ($r['lado'] !== null) {
                        continue;
                    }
                    if ($r['f'] < $e['i'] && $e['i'] - $r['f'] <= 3 && ($mejorPrev === null || $r['f'] > $mejorPrev[2])) {
                        $mejorPrev = [$gi, $ri, $r['f']];
                    }
                    if ($r['i'] > $e['f'] && $r['i'] - $e['f'] <= 3 && ($mejorNext === null || $r['i'] < $mejorNext[2])) {
                        $mejorNext = [$gi, $ri, $r['i']];
                    }
                }
            }
            $dest = $mejorPrev ?? $mejorNext;
            if ($dest !== null) {
                $grupos[$dest[0]]['regiones'][$dest[1]]['lado'] = $lado;
                continue;
            }
            // Sin región: el lado es del síntoma ("debilidad del lado izquierdo").
            $cand = $en($e['c'], fn ($g) => $g['acepta_region'] && $g['regiones'] === [] && $g['lado'] === null);
            $gi = $elegir($cand, $e, false);
            if ($gi !== null) {
                $grupos[$gi]['lado'] = $lado;
            }
        }

        // 5) Calidad, intensidad, duración y cues.
        foreach ($ev as $e) {
            switch ($e['tipo']) {
                case 'calidad':
                    $gi = $elegir($en($e['c'], fn ($g) => $g['acepta_calidad']), $e, true);
                    if ($gi !== null && ! $grupos[$gi]['calidad_explicita']) {
                        $grupos[$gi]['calidad'] = $e['valor'];
                        $grupos[$gi]['calidad_explicita'] = true;
                    }
                    break;
                case 'intensidad':
                    $gi = $elegir($en($e['c']), $e, empty($e['num']));
                    if ($gi === null) {
                        break;
                    }
                    if (! empty($e['num'])) {
                        $grupos[$gi]['intensidad'] = $e['valor'];
                    } elseif ($grupos[$gi]['intensidad'] === null && ! $grupos[$gi]['parcial']) {
                        $grupos[$gi]['intensidad'] = $e['valor'];
                    }
                    break;
                case 'duracion':
                    $cand = $en($e['c'], fn ($g) => ! $g['negado']);
                    if ($cand === []) {
                        break;
                    }
                    $prev = array_values(array_filter($cand, fn ($gi) => $grupos[$gi]['i'] <= $e['i']));
                    if ($prev === []) {
                        // La duración va antes que los síntomas: vale para los siguientes hasta una coma.
                        foreach ($cand as $gi) {
                            if ($grupos[$gi]['duracion'] === null && ! self::hayComa($tokens, $e['i'], $grupos[$gi]['i'])) {
                                $grupos[$gi]['duracion'] = $e['valor'];
                            }
                        }
                        if ($grupos[$cand[0]]['duracion'] === null) {
                            $grupos[$cand[0]]['duracion'] = $e['valor'];
                        }
                        break;
                    }
                    $ultimo = end($prev);
                    $grupos[$ultimo]['duracion'] ??= $e['valor'];
                    foreach ($prev as $gi) {
                        if ($grupos[$gi]['duracion'] === null && ! self::hayComa($tokens, $grupos[$gi]['i'], $e['i'])) {
                            $grupos[$gi]['duracion'] = $e['valor'];
                        }
                    }
                    break;
                case 'subito':
                    foreach ($en($e['c'], fn ($g) => $g['clave'] === 'dolor') as $gi) {
                        $grupos[$gi]['subito'] = true;
                    }
                    break;
                case 'grave':
                    $claves2 = ['vomito', 'hemorragia'];
                    $hit = $en($e['c'], fn ($g) => in_array($g['clave'], $claves2, true));
                    if ($hit === []) {
                        // Si no hay en la misma oración, el más cercano anterior.
                        foreach (array_reverse(array_keys($grupos)) as $gi) {
                            if (in_array($grupos[$gi]['clave'], $claves2, true) && $grupos[$gi]['i'] < $e['i']) {
                                $hit = [$gi];
                                break;
                            }
                        }
                    }
                    foreach ($hit as $gi) {
                        $grupos[$gi]['grave'] = true;
                    }
                    break;
            }
        }

        return $grupos;
    }

    /** ¿Hay solo 0-2 palabras (sin coma, "y" ni punto) entre el final de un modificador y el inicio del síntoma? */
    private static function adyacente(array $tokens, int $fin, int $inicio): bool
    {
        if ($inicio - $fin - 1 > 2) {
            return false;
        }
        for ($k = $fin + 1; $k < $inicio; $k++) {
            if (in_array($tokens[$k] ?? '', [',', '.', 'y', 'o'], true)) {
                return false;
            }
        }

        return true;
    }

    private static function hayComa(array $tokens, int $a, int $b): bool
    {
        for ($k = min($a, $b); $k <= max($a, $b); $k++) {
            if (($tokens[$k] ?? '') === ',') {
                return true;
            }
        }

        return false;
    }

    // ================================================================= entidades

    /** @return array{0:array,1:array} [síntomas, negados] */
    private static function entidades(array $grupos, array $cfg): array
    {
        $par = $cfg['analizador'];
        $claves = $cfg['claves'];
        $sint = [];
        $negs = [];

        foreach ($grupos as $g) {
            $op = $g['op'];
            // "dolor muscular en la pierna derecha": la región genérica sobra si hay una concreta.
            if (count($g['regiones']) > 1) {
                $concretas = array_values(array_filter($g['regiones'], fn ($r) => ! in_array($r['canon'], ['musculo', 'articulacion', 'hueso'], true)));
                if ($concretas !== []) {
                    $g['regiones'] = $concretas;
                }
            }
            $canones = array_values(array_unique(array_column($g['regiones'], 'canon')));
            if (isset($op['region']) && ! in_array($op['region'], $canones, true)) {
                $g['regiones'][] = ['canon' => $op['region'], 'i' => $g['i'], 'f' => $g['f'], 'lado' => null, 'ambos' => false];
            }
            if (isset($op['region_defecto']) && $g['regiones'] === []) {
                $g['regiones'][] = ['canon' => $op['region_defecto'], 'i' => $g['i'], 'f' => $g['f'], 'lado' => null, 'ambos' => false];
            }
            if (($g['clave'] === 'presion' && $g['regiones'] === []) || (! empty($op['requiere_region']) && $g['regiones'] === [] && $g['lado'] === null)) {
                continue;
            }

            // Un elemento por región (y por lado si es bilateral).
            $combos = [];
            foreach ($g['regiones'] as $r) {
                if ($r['ambos'] && $r['lado'] === null) {
                    $combos[] = [$r['canon'], 'izquierdo'];
                    $combos[] = [$r['canon'], 'derecho'];
                } else {
                    $combos[] = [$r['canon'], $r['lado']];
                }
            }
            if ($combos === []) {
                $combos[] = [null, $g['lado']];
            }

            foreach ($combos as [$region, $lado]) {
                $clave = $g['clave'];
                $calidad = $g['calidad'] ?? ($op['calidad'] ?? null);
                if ($clave === 'dolor' && $region === 'cabeza') {
                    $clave = 'dolor_cabeza';
                } elseif ($clave === 'presion') {
                    if ($region === 'pecho') {
                        $clave = 'presion_pecho';
                        $calidad = 'opresivo';
                    } else {
                        $clave = 'dolor';
                        $calidad = $g['calidad'] ?? 'opresivo';
                    }
                }
                if (! ($claves[$clave]['calidad'] ?? false) && $clave !== 'presion_pecho') {
                    $calidad = null;
                }

                if ($g['negado']) {
                    $negs[$clave] = $clave;
                    continue;
                }

                $intensidad = $g['intensidad'] ?? ($g['parcial'] ? (int) $par['intensidad_negacion_parcial'] : null);
                $e = [
                    'clave' => $clave, 'etiqueta' => '', 'region' => $region, 'lado' => $lado, 'calidad' => $calidad,
                    'intensidad' => $intensidad, 'duracion_min' => $g['duracion'], 'confianza' => 0,
                ];
                if (str_starts_with($clave, 'dolor') && $g['subito']) {
                    $e['subito'] = true;
                }
                if ($clave === 'vomito' && $g['grave']) {
                    $e['persistente'] = true;
                }
                if ($clave === 'hemorragia') {
                    $imp = ! empty($op['importante']) || $g['grave'] || ($intensidad !== null && $intensidad >= (int) $par['intensidad_intensa']);
                    if ($intensidad !== null && $intensidad <= (int) $par['intensidad_hemorragia_leve'] && ! $g['grave']) {
                        $imp = false;
                    }
                    if (! $imp) {
                        // Sangre junto con vómito en la misma oración: se trata como importante (supuesto conservador).
                        foreach ($grupos as $o) {
                            if ($o['clave'] === 'vomito' && ! $o['negado'] && $o['c'] === $g['c']) {
                                $imp = true;
                            }
                        }
                    }
                    if ($imp) {
                        $e['importante'] = true;
                    }
                }
                $e['confianza'] = self::confianza($e, $cfg);
                $e['etiqueta'] = self::etiquetar($e);
                $sint[] = $e;
            }
        }

        $sint = self::fusionar($sint);
        $presentes = array_column($sint, 'clave');
        $negados = array_values(array_filter($negs, fn ($c) => ! in_array($c, $presentes, true)));

        return [$sint, $negados];
    }

    private static function confianza(array $e, array $cfg): int
    {
        $c = $cfg['analizador']['confianza'];
        $def = $cfg['claves'][$e['clave']] ?? [];
        $conRegion = ($def['region'] ?? false) || in_array($e['clave'], $cfg['claves_derivadas'] ?? [], true);
        $v = $conRegion ? (int) $c['base_con_region'] : (int) $c['base_sin_region'];
        if ($conRegion && ($e['region'] ?? null) !== null) {
            $v += (int) $c['suma_region'];
        }
        if ($e['lado'] ?? null) {
            $v += (int) $c['suma_lado'];
        }
        if ($e['calidad'] ?? null) {
            $v += (int) $c['suma_calidad'];
        }
        if (($e['intensidad'] ?? null) !== null) {
            $v += (int) $c['suma_intensidad'];
        }
        if (($e['duracion_min'] ?? null) !== null) {
            $v += (int) $c['suma_duracion'];
        }

        return min((int) $c['maximo'], $v);
    }

    /** Une repetidos (misma clave, región y lado) y descarta el "dolor sin región" si ya hay dolores con región. */
    private static function fusionar(array $lista): array
    {
        $out = [];
        foreach ($lista as $e) {
            $k = $e['clave'].'|'.($e['region'] ?? '').'|'.($e['lado'] ?? '');
            if (! isset($out[$k])) {
                $out[$k] = $e;
                continue;
            }
            foreach (['calidad', 'intensidad', 'duracion_min'] as $campo) {
                $out[$k][$campo] ??= $e[$campo];
            }
            foreach (['subito', 'persistente', 'importante'] as $extra) {
                if (! empty($e[$extra])) {
                    $out[$k][$extra] = true;
                }
            }
            $out[$k]['confianza'] = max($out[$k]['confianza'], $e['confianza']);
            $out[$k]['etiqueta'] = self::etiquetar($out[$k]);
        }

        $out = array_values($out);
        foreach ($out as $k => $e) {
            if (($e['region'] ?? null) === null && ($e['lado'] ?? null) === null && in_array($e['clave'], ['dolor', 'hinchazon', 'rigidez'], true)) {
                $hayEspecifico = false;
                foreach ($out as $j => $o) {
                    if ($j !== $k && $o['clave'] === $e['clave'] && $o['region'] !== null) {
                        $hayEspecifico = true;
                        foreach (['calidad', 'intensidad', 'duracion_min'] as $campo) {
                            $out[$j][$campo] ??= $e[$campo];
                        }
                        $out[$j]['etiqueta'] = self::etiquetar($out[$j]);
                    }
                }
                if ($hayEspecifico) {
                    unset($out[$k]);
                }
            }
        }

        return array_values($out);
    }
}
