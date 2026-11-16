<?php

namespace Tests\Unit;

use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\Estado;
use App\Services\Salud\SimuladorVivo;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SimuladorVivoTest extends TestCase
{
    private array $config;
    private array $umbrales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require __DIR__.'/../../config/simulacion.php';
        $this->umbrales = require __DIR__.'/../../config/umbrales.php';
    }

    private function sim(?array $config = null): SimuladorVivo
    {
        return new SimuladorVivo($config ?? $this->config, $this->umbrales);
    }

    private function t(string $s): Carbon
    {
        return Carbon::parse($s, 'UTC');
    }

    private function resumen(array $r): array
    {
        return array_map(fn ($m) => [$m->indicador, $m->valores, $m->medidoEn->format('Y-m-d H:i:s.u')], $r['mediciones']);
    }

    public function test_determinista_con_semilla(): void
    {
        $desde = $this->t('2026-11-16 00:00:00');
        $hasta = $this->t('2026-11-17 00:00:00');
        $real = $this->t('2026-11-16 12:00:00');

        $a = $this->sim();
        $b = $this->sim();
        $ra = $a->avanzar($a->estadoInicial(42), $desde, $hasta, 360, $real);
        $rb = $b->avanzar($b->estadoInicial(42), $desde, $hasta, 360, $real);

        $this->assertNotEmpty($ra['mediciones']);
        $this->assertSame($this->resumen($ra), $this->resumen($rb));
        $this->assertSame($ra['estado'], $rb['estado']);
        $this->assertEquals($ra['estado'], json_decode(json_encode($ra['estado']), true));
    }

    public function test_semillas_distintas_dan_bases_distintas(): void
    {
        $s = $this->sim();
        $this->assertNotSame($s->estadoInicial(1)['bases'], $s->estadoInicial(2)['bases']);
        $this->assertSame($s->estadoInicial(7), $s->estadoInicial(7));
    }

    public function test_curva_del_escenario(): void
    {
        $s = $this->sim();
        $dura = 3600;
        $c = $this->config['escenarios']['fiebre']['curva'];
        $delta = $this->config['escenarios']['fiebre']['indicadores']['temperatura']['delta'];

        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', -1, $dura));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', $dura + 1, $dura));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', 0, $dura));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'inexistente', 100, $dura));

        $ent = $c['entrada'] * $dura;
        $this->assertLessThan($s->desplazamiento('fiebre', 'temperatura', $ent * 0.5, $dura), $s->desplazamiento('fiebre', 'temperatura', $ent * 0.2, $dura));
        $this->assertLessThan($s->desplazamiento('fiebre', 'temperatura', $ent * 0.9, $dura), $s->desplazamiento('fiebre', 'temperatura', $ent * 0.5, $dura));

        $m1 = $s->desplazamiento('fiebre', 'temperatura', ($c['entrada'] + 0.1) * $dura, $dura);
        $m2 = $s->desplazamiento('fiebre', 'temperatura', ($c['entrada'] + $c['meseta'] - 0.1) * $dura, $dura);
        $this->assertEqualsWithDelta($delta, $m1, 1e-9);
        $this->assertEqualsWithDelta($delta, $m2, 1e-9);

        $sal0 = ($c['entrada'] + $c['meseta']) * $dura;
        $this->assertGreaterThan($s->desplazamiento('fiebre', 'temperatura', $sal0 + 0.5 * $c['salida'] * $dura, $dura), $s->desplazamiento('fiebre', 'temperatura', $sal0 + 0.2 * $c['salida'] * $dura, $dura));
        $this->assertGreaterThan($s->desplazamiento('fiebre', 'temperatura', $dura - 1, $dura), $s->desplazamiento('fiebre', 'temperatura', $sal0 + 0.5 * $c['salida'] * $dura, $dura));
    }

    public function test_las_curvas_suman_uno(): void
    {
        foreach ($this->config['escenarios'] as $clave => $e) {
            $this->assertEqualsWithDelta(1.0, array_sum($e['curva']), 1e-9, $clave);
        }
        $this->assertCount(7, $this->sim()->escenarios());
    }

    public function test_datos_faltantes(): void
    {
        $desde = $this->t('2026-11-16 10:00:00');
        $hasta = $this->t('2026-11-16 22:00:00');
        $real = $this->t('2026-11-16 12:00:00');
        $contar = function (float $perdida) use ($desde, $hasta, $real) {
            $cfg = $this->config;
            $cfg['faltantes'] = ['prob_perdida' => $perdida, 'prob_hueco' => 0.0, 'hueco_s' => [600, 600]];
            $s = $this->sim($cfg);
            $r = $s->avanzar($s->estadoInicial(5), $desde, $hasta, 1, $real);

            return count(array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca'));
        };

        $this->assertSame(12 * 12, $contar(0.0));   // una lectura cada 5 min durante 12 h
        $this->assertSame(0, $contar(1.0));
        $this->assertLessThan($contar(0.0), $contar(0.5));
    }

    public function test_huecos_largos_reducen_lecturas(): void
    {
        $cfg = $this->config;
        $cfg['faltantes'] = ['prob_perdida' => 0.0, 'prob_hueco' => 0.05, 'hueco_s' => [3600, 3600]];
        $s = $this->sim($cfg);
        $r = $s->avanzar($s->estadoInicial(3), $this->t('2026-11-16 00:00:00'), $this->t('2026-11-17 00:00:00'), 60, $this->t('2026-11-16 12:00:00'));
        $fc = array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca');
        $this->assertLessThan(288, count($fc));
    }

    public function test_medido_en_nunca_futura_y_ordenada(): void
    {
        $s = $this->sim();
        $desde = $this->t('2026-11-16 00:00:00');
        $hasta = $this->t('2026-11-16 06:00:00');
        $real = $this->t('2026-11-16 12:00:00');
        $vel = 360.0;
        $r = $s->avanzar($s->estadoInicial(9), $desde, $hasta, $vel, $real, [
            'clave' => 'hipoxia', 'inicio' => $this->t('2026-11-16 01:00:00'), 'dura_s' => 7200,
        ]);
        $minimo = $real->copy()->subSeconds((int) ceil(($hasta->getTimestamp() - $desde->getTimestamp()) / $vel));

        $this->assertNotEmpty($r['mediciones']);
        $prev = null;
        foreach ($r['mediciones'] as $m) {
            $this->assertLessThanOrEqual($real->getTimestamp(), $m->medidoEn->getTimestamp());
            $this->assertGreaterThanOrEqual($minimo->getTimestamp(), $m->medidoEn->getTimestamp());
            if ($prev !== null) {
                $this->assertGreaterThanOrEqual($prev, $m->medidoEn->getTimestamp());
            }
            $prev = $m->medidoEn->getTimestamp();
        }
    }

    public function test_valores_dentro_de_rango_valido(): void
    {
        $s = $this->sim();
        $r = $s->avanzar($s->estadoInicial(11), $this->t('2026-11-16 00:00:00'), $this->t('2026-11-20 00:00:00'), 360, $this->t('2026-11-20 00:00:00'));
        $this->assertNotEmpty($r['mediciones']);
        foreach ($r['mediciones'] as $m) {
            foreach ($m->valores as $comp => $v) {
                [$lo, $hi] = $this->umbrales['indicadores'][$m->indicador]['componentes'][$comp]['valido'];
                $this->assertGreaterThanOrEqual($lo, $v, $m->indicador);
                $this->assertLessThanOrEqual($hi, $v, $m->indicador);
            }
        }
    }

    /** @return array<string, Estado> peor estado por indicador durante el escenario */
    private function peorEstado(string $clave, int $semilla = 21): array
    {
        $s = $this->sim();
        $ev = new EvaluadorEstado($this->umbrales);
        $inicio = $this->t('2026-11-16 02:00:00');
        $dura = (int) $this->config['escenarios'][$clave]['duracion_min'] * 60;
        $est = $s->estadoInicial($semilla);
        $r = $s->avanzar($est, $this->t('2026-11-16 00:00:00'), $inicio->copy()->addSeconds($dura + 3600), 1, $this->t('2026-11-17 00:00:00'), [
            'clave' => $clave, 'inicio' => $inicio, 'dura_s' => $dura,
        ]);
        $peor = [];
        foreach ($r['mediciones'] as $m) {
            $e = $ev->evaluar($m->indicador, $m->valores);
            $peor[$m->indicador] = isset($peor[$m->indicador]) ? $peor[$m->indicador]->peor($e) : $e;
        }
        $this->assertTrue($r['escenario_terminado']);

        return $peor;
    }

    public function test_fiebre_llega_a_critico_en_temperatura(): void
    {
        $this->assertSame(Estado::Critico, $this->peorEstado('fiebre')['temperatura']);
    }

    public function test_hipoxia_llega_a_alerta_en_spo2_y_pulso(): void
    {
        $p = $this->peorEstado('hipoxia');
        $this->assertGreaterThanOrEqual(1, $p['saturacion_o2']->severidad());
        $this->assertSame(Estado::Critico, $p['saturacion_o2']);
        $this->assertGreaterThanOrEqual(1, $p['frecuencia_cardiaca']->severidad());
    }

    public function test_arritmia_llega_a_alerta_en_pulso(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->peorEstado('arritmia')['frecuencia_cardiaca']->severidad());
    }

    public function test_escenario_emite_de_inmediato_y_termina(): void
    {
        $s = $this->sim();
        $inicio = $this->t('2026-11-16 02:00:00');
        $est = $s->estadoInicial(4);
        // Primer avance de un minuto: la cadencia diaria de sueño no toca, pero el escenario fuerza lectura.
        $r = $s->avanzar($est, $inicio->copy()->subMinute(), $inicio->copy()->addMinute(), 1, $inicio, [
            'clave' => 'falta_sueno', 'inicio' => $inicio, 'dura_s' => 18000,
        ]);
        $this->assertContains('sueno', array_map(fn ($m) => $m->indicador, $r['mediciones']));
        $this->assertFalse($r['escenario_terminado']);

        $r2 = $s->avanzar($r['estado'], $inicio->copy()->addMinute(), $inicio->copy()->addSeconds(18001), 1, $inicio, [
            'clave' => 'falta_sueno', 'inicio' => $inicio, 'dura_s' => 18000,
        ]);
        $this->assertTrue($r2['escenario_terminado']);
    }

    public function test_fallo_sensor_congela_e_imposible(): void
    {
        $s = $this->sim();
        $inicio = $this->t('2026-11-16 02:00:00');
        $r = $s->avanzar($s->estadoInicial(8), $this->t('2026-11-16 01:00:00'), $this->t('2026-11-16 03:00:00'), 1, $this->t('2026-11-16 03:00:00'), [
            'clave' => 'fallo_sensor', 'inicio' => $inicio, 'dura_s' => 3600,
        ]);
        $imp = array_filter($r['mediciones'], fn ($m) => $m->indicador === 'saturacion_o2' && $m->valores['valor'] < 50);
        $this->assertNotEmpty($imp);
        $fc = array_values(array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca' && $m->medidoEn >= $this->t('2026-11-16 02:10:00')));
        $this->assertNotEmpty($fc);
        $this->assertCount(1, array_unique(array_map(fn ($m) => $m->valores['valor'], array_slice($fc, 0, 6))));
    }

    public function test_cadencia_diaria_y_semanal(): void
    {
        $s = $this->sim();
        $r = $s->avanzar($s->estadoInicial(6), $this->t('2026-11-16 00:00:00'), $this->t('2026-11-30 00:00:00'), 360, $this->t('2026-11-30 00:00:00'));
        $cuenta = array_count_values(array_map(fn ($m) => $m->indicador, $r['mediciones']));
        $this->assertLessThanOrEqual(14, $cuenta['sueno']);
        $this->assertGreaterThanOrEqual(10, $cuenta['sueno']);
        $this->assertLessThanOrEqual(2, $cuenta['masa_muscular']);
    }
}
