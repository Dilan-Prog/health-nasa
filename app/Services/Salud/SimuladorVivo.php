<?php

namespace App\Services\Salud;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Simulador de la misión "viva": genera mediciones realistas al avanzar un reloj virtual.
 * Puro y determinista: el estado (JSON-serializable) lleva su propio PRNG (splitmix32), sin BD ni mt_rand.
 * Todo el comportamiento (cadencias, ruido, ciclo circadiano, ejercicio, faltantes, escenarios) sale de config/simulacion.php.
 */
class SimuladorVivo
{
    private array $config;
    private array $umbrales;

    public function __construct(?array $config = null, ?array $umbrales = null)
    {
        $this->config = $config ?? config('simulacion');
        $this->umbrales = $umbrales ?? config('umbrales');
    }

    // ------------------------------------------------------------------ API pública

    public function estadoInicial(int $semilla): array
    {
        $e = [
            'semilla' => $semilla,
            'prng' => ($semilla ^ ($semilla >> 32)) & 0xFFFFFFFF,
            'bases' => [],
            'ar' => [],
            'ultima' => [],
            'ultimo_valor' => [],
            'congelado' => [],
            'acum' => [],
            'dia_sueno' => null,
            'hueco_hasta' => 0,
            'escenario' => null,
            'escenario_hecho' => null,
        ];
        // Calentar el generador para que semillas contiguas no se parezcan.
        for ($i = 0; $i < 4; $i++) {
            $this->u($e);
        }

        $jitterDef = (float) ($this->config['jitter_base'] ?? 0.25);
        foreach (array_keys($this->config['cadencia']) as $ind) {
            $def = $this->definicion($ind);
            $jit = (float) ($this->config['ruido'][$ind]['jitter'] ?? $jitterDef);
            foreach ($def['componentes'] as $comp => $r) {
                [$nMin, $nMax] = $r['normal'];
                $centro = ($nMin + $nMax) / 2;
                $semi = ($nMax - $nMin) / 2;
                $panel = $this->config['bases'][$ind][$comp] ?? $centro;
                $base = $centro + ($panel - $centro) * 0.5 + ($this->u($e) * 2 - 1) * $semi * $jit;
                $base = min(max($base, $nMin + 0.15 * $semi), $nMax - 0.15 * $semi);
                $e['bases'][$ind][$comp] = $base;
                $e['ar'][$ind][$comp] = 0.0;
            }
            $e['ultima'][$ind] = null;
            if (isset($this->config['acumulado'][$ind])) {
                [$a, $b] = $this->config['acumulado'][$ind]['inicio'];
                $e['acum'][$ind] = $a + $this->u($e) * ($b - $a);
            }
        }

        return $e;
    }

    public function avanzar(array $estado, CarbonInterface $desde, CarbonInterface $hasta, float $velocidad, CarbonInterface $ahoraReal, ?array $escenario = null): array
    {
        $desdeT = $desde->getTimestamp();
        $hastaT = $hasta->getTimestamp();
        $velocidad = $velocidad > 0 ? $velocidad : 1.0;
        $terminado = false;

        // ---- escenario
        $esc = null; // [clave, inicioT, duraS, key]
        if ($escenario !== null && isset($this->config['escenarios'][$escenario['clave']])) {
            $inicioT = $escenario['inicio']->getTimestamp();
            $duraS = (int) $escenario['dura_s'];
            $key = $escenario['clave'].'|'.$inicioT;
            if (($estado['escenario_hecho'] ?? null) === $key) {
                $terminado = true;
            } elseif ($hastaT >= $inicioT) {
                $esc = ['clave' => $escenario['clave'], 'inicio' => $inicioT, 'dura' => $duraS];
                if (($estado['escenario']['key'] ?? null) !== $key) {
                    $estado['escenario'] = ['key' => $key, 'iniciado' => false, 'meseta' => false];
                    $estado['congelado'] = [];
                }
            }
        } elseif ($escenario === null) {
            $estado['escenario'] = null;
            $estado['congelado'] = [];
        }

        if ($hastaT <= $desdeT) {
            return ['estado' => $estado, 'mediciones' => [], 'escenario_terminado' => $terminado];
        }

        // ---- eventos: [t, orden, indicador, extra]
        $eventos = [];
        $orden = 0;
        $max = (int) ($this->config['max_lecturas_por_indicador'] ?? 600);
        foreach (array_keys($this->config['cadencia']) as $ind) {
            [$c, $off] = $this->rejilla($ind);
            $k0 = (int) floor(($desdeT - $off) / $c) + 1;
            $ult = $estado['ultima'][$ind] ?? null;
            if ($ult !== null) {
                $k0 = max($k0, (int) floor(($ult - $off) / $c) + 1);
            }
            $k1 = (int) floor(($hastaT - $off) / $c);
            $k0 = max($k0, $k1 - $max + 1);
            for ($k = $k0; $k <= $k1; $k++) {
                $eventos[] = [$k * $c + $off, $orden, $ind, false];
            }
            $orden++;
        }

        if ($esc !== null) {
            $curva = $this->curva($esc['clave']);
            $afectados = array_keys($this->config['escenarios'][$esc['clave']]['indicadores']);
            if (! $estado['escenario']['iniciado']) {
                $ti = min($hastaT, max($desdeT, $esc['inicio'] + (int) round($curva[0] * $esc['dura'] * 0.5)));
                foreach ($afectados as $ind) {
                    $eventos[] = [$ti, $this->ordenDe($ind), $ind, true];
                }
                $estado['escenario']['iniciado'] = true;
            }
            if (! $estado['escenario']['meseta']) {
                $tm = $esc['inicio'] + (int) round(($curva[0] + $curva[1] / 2) * $esc['dura']);
                if ($tm <= $hastaT) {
                    if ($tm > $desdeT) {
                        foreach ($afectados as $ind) {
                            $eventos[] = [$tm, $this->ordenDe($ind), $ind, true];
                        }
                    }
                    $estado['escenario']['meseta'] = true;
                }
            }
        }

        usort($eventos, fn ($a, $b) => [$a[0], $a[1], (int) $a[3]] <=> [$b[0], $b[1], (int) $b[3]]);

        // ---- procesar
        $f = $this->config['faltantes'] ?? [];
        $mediciones = [];
        foreach ($eventos as [$t, , $ind, $extra]) {
            if (! $extra) {
                $uHueco = $this->u($estado);
                $uPerdida = $this->u($estado);
                if ($t < $estado['hueco_hasta']) {
                    continue;
                }
                if ($uHueco < (float) ($f['prob_hueco'] ?? 0)) {
                    [$a, $b] = $f['hueco_s'] ?? [1800, 5400];
                    $estado['hueco_hasta'] = $t + (int) round($a + $this->u($estado) * ($b - $a));
                    continue;
                }
                if ($uPerdida < (float) ($f['prob_perdida'] ?? 0)) {
                    continue;
                }
                if ($ind === 'sueno' && ($estado['dia_sueno'] ?? null) === intdiv($t, 86400)) {
                    continue;
                }
            }

            $valores = $this->valorEn($estado, $ind, $t, $esc);
            if ($ind === 'sueno') {
                $estado['dia_sueno'] = intdiv($t, 86400);
            }
            $estado['ultima'][$ind] = max($estado['ultima'][$ind] ?? $t, $t);
            $estado['ultimo_valor'][$ind] = $valores;

            $real = (int) round(($hastaT - $t) / $velocidad * 1_000_000);
            $mediciones[] = new MedicionEntrante($ind, $valores, $ahoraReal->copy()->subMicroseconds($real));
        }

        if ($esc !== null && $hastaT >= $esc['inicio'] + $esc['dura']) {
            $terminado = true;
            $estado['escenario_hecho'] = $estado['escenario']['key'];
            $estado['escenario'] = null;
            $estado['congelado'] = [];
        }

        return ['estado' => $estado, 'mediciones' => $mediciones, 'escenario_terminado' => $terminado];
    }

    public function desplazamiento(string $escenario, string $indicador, float $seg, int $duraS): float
    {
        $cfg = $this->config['escenarios'][$escenario]['indicadores'][$indicador] ?? null;
        if ($cfg === null || $duraS <= 0 || $seg < 0 || $seg > $duraS) {
            return 0.0;
        }

        return (float) ($cfg['delta'] ?? 0) * $this->factorCurva($escenario, $seg, $duraS);
    }

    public function escenarios(): array
    {
        $out = [];
        foreach ($this->config['escenarios'] as $clave => $e) {
            $out[$clave] = [
                'nombre' => $e['nombre'],
                'icono' => $e['icono'],
                'descripcion' => $e['descripcion'],
                'duracion_min' => (int) $e['duracion_min'],
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ valores

    /** @return array<string, float|int> */
    private function valorEn(array &$estado, string $ind, int $t, ?array $esc): array
    {
        $def = $this->definicion($ind);
        $ruido = $this->config['ruido'][$ind] ?? ['sigma' => 0, 'phi' => 0, 'paso' => 0];
        $phi = (float) ($ruido['phi'] ?? 0);
        $paso = (float) ($ruido['paso'] ?? 0);
        $lim = (float) ($this->config['ruido_limite_sigmas'] ?? 2.5);

        // Escenario: factor de la curva, modo y parámetros de este indicador.
        $cfgEsc = null;
        $factor = 0.0;
        if ($esc !== null) {
            $cfgEsc = $this->config['escenarios'][$esc['clave']]['indicadores'][$ind] ?? null;
            if ($cfgEsc !== null) {
                $factor = $this->factorCurva($esc['clave'], $t - $esc['inicio'], $esc['dura']);
            }
        }
        $modo = $cfgEsc['modo'] ?? null;

        if ($modo === 'imposible' && $factor >= 0.5) {
            $out = [];
            foreach ($def['componentes'] as $comp => $_) {
                $out[$comp] = $cfgEsc['valor'];
            }

            return $out;
        }
        if ($modo === 'congelar' && $factor > 0) {
            if (! isset($estado['congelado'][$ind]) && isset($estado['ultimo_valor'][$ind])) {
                $estado['congelado'][$ind] = $estado['ultimo_valor'][$ind];
            }
            if (isset($estado['congelado'][$ind])) {
                return $estado['congelado'][$ind];
            }
        } elseif ($factor <= 0) {
            unset($estado['congelado'][$ind]);
        }

        $ej = $this->intensidadEjercicio($t);
        $multRuido = ($modo === 'sumar' && $factor > 0) ? 1 + (($cfgEsc['ruido_mult'] ?? 1) - 1) * $factor : 1.0;

        $out = [];
        foreach ($def['componentes'] as $comp => $r) {
            if (isset($estado['acum'][$ind])) {
                $prev = $estado['ultima'][$ind] ?? ($t - 86400);
                $estado['acum'][$ind] += (float) $this->config['acumulado'][$ind]['incremento_diario'] * max(0, $t - $prev) / 86400;
                $v = $estado['acum'][$ind];
            } else {
                $sigma = $ruido['sigma'] ?? 0;
                $sigma = (float) (is_array($sigma) ? ($sigma[$comp] ?? 0) : $sigma);
                $z = $this->gauss($estado);
                $ar = $phi * $estado['ar'][$ind][$comp] + $sigma * $multRuido * sqrt(max(0.0, 1 - $phi * $phi)) * $z;
                $tope = $lim * $sigma * $multRuido;
                $ar = min(max($ar, -$tope), $tope);
                $estado['ar'][$ind][$comp] = $ar;

                $v = $estado['bases'][$ind][$comp] + $ar;
                if (isset($this->config['circadiano'][$ind])) {
                    $c = $this->config['circadiano'][$ind];
                    $prop = $c['componentes'][$comp] ?? 1;
                    $hora = ($t % 86400) / 3600;
                    $v += $prop * $c['amplitud'] * cos(2 * M_PI * ($hora - $c['hora_pico']) / 24);
                }
                if ($ej > 0 && isset($this->config['ejercicio']['efectos'][$ind])) {
                    [$delta, $comps] = $this->efecto($this->config['ejercicio']['efectos'][$ind]);
                    $v += $ej * $delta * ($comps[$comp] ?? 1);
                }
                if ($modo === 'sumar' && $factor > 0) {
                    $comps = $cfgEsc['componentes'] ?? [];
                    $v += $factor * (float) $cfgEsc['delta'] * ($comps[$comp] ?? 1);
                }
            }

            [$vMin, $vMax] = $r['valido'];
            $v = min(max($v, $vMin), $vMax);
            if ($paso > 0) {
                $v = round($v / $paso) * $paso;
            }
            $dec = $r['decimales'] ?? ($comp === 'valor' && $r['normal'][1] < 12 ? 1 : 0);
            $v = round($v, $dec);
            $out[$comp] = min(max($v, $vMin), $vMax);
        }

        if (isset($out['sistolica'], $out['diastolica']) && $out['diastolica'] >= $out['sistolica']) {
            $out['diastolica'] = max($def['componentes']['diastolica']['valido'][0], $out['sistolica'] - 20);
        }

        return $out;
    }

    private function efecto(mixed $e): array
    {
        return is_array($e) ? [(float) $e['delta'], $e['componentes'] ?? []] : [(float) $e, []];
    }

    /** Intensidad 0..1 del ejercicio: subida, meseta durante la sesión y recuperación gradual. */
    private function intensidadEjercicio(int $t): float
    {
        $c = $this->config['ejercicio'] ?? [];
        if (empty($c['activo'])) {
            return 0.0;
        }
        $sub = (float) ($c['subida_min'] ?? 5) * 60;
        $rec = (float) ($c['recuperacion_min'] ?? 20) * 60;
        $tod = $t % 86400;
        $mejor = 0.0;
        foreach ($c['sesiones'] ?? [] as $s) {
            $x = $tod - $this->horaS($s['hora']);
            $dur = (float) $s['duracion_min'] * 60;
            $i = match (true) {
                $x < 0 => 0.0,
                $x < $sub => $x / $sub,
                $x <= $dur => 1.0,
                $x < $dur + $rec => 1 - ($x - $dur) / $rec,
                default => 0.0,
            };
            $mejor = max($mejor, $i);
        }

        return $mejor;
    }

    // ------------------------------------------------------------------ curva

    /** @return array{0: float, 1: float, 2: float} fracciones normalizadas entrada, meseta, salida */
    private function curva(string $escenario): array
    {
        $c = $this->config['escenarios'][$escenario]['curva'];
        $s = (float) $c['entrada'] + (float) $c['meseta'] + (float) $c['salida'];
        $s = $s > 0 ? $s : 1.0;

        return [$c['entrada'] / $s, $c['meseta'] / $s, $c['salida'] / $s];
    }

    private function factorCurva(string $escenario, float $seg, int $duraS): float
    {
        if ($duraS <= 0 || $seg < 0 || $seg > $duraS) {
            return 0.0;
        }
        [$e, $m, $s] = $this->curva($escenario);
        $x = $seg / $duraS;
        if ($x < $e) {
            return $this->suave($x / $e);
        }
        if ($x <= $e + $m) {
            return 1.0;
        }

        return $s > 0 ? 1 - $this->suave(($x - $e - $m) / $s) : 0.0;
    }

    private function suave(float $x): float
    {
        $x = min(max($x, 0.0), 1.0);

        return $x * $x * (3 - 2 * $x);
    }

    // ------------------------------------------------------------------ cadencia

    /** @return array{0:int,1:int} intervalo y desfase (s) respecto de la época */
    private function rejilla(string $ind): array
    {
        $cad = $this->config['cadencia'][$ind];
        if (! isset($cad['cada_s'])) {
            $frec = $this->definicion($ind)['frecuencia'] ?? 'diaria';
            $cad += $this->config['cadencia_defecto'][$frec] ?? $this->config['cadencia_defecto']['diaria'];
        }
        $c = max(1, (int) $cad['cada_s']);
        $off = (int) ($cad['fase_s'] ?? 0);
        if (isset($cad['hora'])) {
            $off += $this->horaS($cad['hora']);
        }
        if (isset($cad['dia_semana'])) {
            // La época (1970-01-01) fue jueves = 4 (0 = domingo).
            $off += ((((int) $cad['dia_semana']) - 4 + 7) % 7) * 86400;
        }

        return [$c, $off % $c];
    }

    private function horaS(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 3600 + $m * 60;
    }

    private function ordenDe(string $ind): int
    {
        return (int) array_search($ind, array_keys($this->config['cadencia']), true);
    }

    private function definicion(string $ind): array
    {
        return $this->umbrales['indicadores'][$ind]
            ?? throw new InvalidArgumentException("Indicador desconocido: {$ind}");
    }

    // ------------------------------------------------------------------ PRNG (splitmix32, estado entero serializable)

    private function u(array &$e): float
    {
        $e['prng'] = ($e['prng'] + 0x9E3779B9) & 0xFFFFFFFF;
        $z = $e['prng'];
        $z = $this->mul32($z ^ ($z >> 16), 0x85EBCA6B);
        $z = $this->mul32($z ^ ($z >> 13), 0xC2B2AE35);
        $z ^= $z >> 16;

        return ($z & 0xFFFFFFFF) / 4294967296.0;
    }

    private function mul32(int $a, int $b): int
    {
        return (($a & 0xFFFF) * $b + (((($a >> 16) & 0xFFFF) * $b & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    private function gauss(array &$e): float
    {
        $u1 = max($this->u($e), 1e-12);
        $u2 = $this->u($e);

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
