<?php

namespace Tests\Unit;

use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\Estado;
use App\Services\Salud\SimuladorVivo;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LiveSimulatorTest extends TestCase
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

    private function summary(array $r): array
    {
        return array_map(fn ($m) => [$m->indicador, $m->valores, $m->medidoEn->format('Y-m-d H:i:s.u')], $r['mediciones']);
    }

    public function test_is_deterministic_with_seed(): void
    {
        $from = $this->t('2026-11-16 00:00:00');
        $to = $this->t('2026-11-17 00:00:00');
        $real = $this->t('2026-11-16 12:00:00');

        $a = $this->sim();
        $b = $this->sim();
        $ra = $a->avanzar($a->estadoInicial(42), $from, $to, 360, $real);
        $rb = $b->avanzar($b->estadoInicial(42), $from, $to, 360, $real);

        $this->assertNotEmpty($ra['mediciones']);
        $this->assertSame($this->summary($ra), $this->summary($rb));
        $this->assertSame($ra['estado'], $rb['estado']);
        $this->assertEquals($ra['estado'], json_decode(json_encode($ra['estado']), true));
    }

    public function test_different_seeds_give_different_baselines(): void
    {
        $s = $this->sim();
        $this->assertNotSame($s->estadoInicial(1)['bases'], $s->estadoInicial(2)['bases']);
        $this->assertSame($s->estadoInicial(7), $s->estadoInicial(7));
    }

    public function test_scenario_curve(): void
    {
        $s = $this->sim();
        $duration = 3600;
        $c = $this->config['escenarios']['fiebre']['curva'];
        $delta = $this->config['escenarios']['fiebre']['indicadores']['temperatura']['delta'];

        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', -1, $duration));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', $duration + 1, $duration));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'temperatura', 0, $duration));
        $this->assertSame(0.0, $s->desplazamiento('fiebre', 'inexistente', 100, $duration));

        $entry = $c['entrada'] * $duration;
        $this->assertLessThan($s->desplazamiento('fiebre', 'temperatura', $entry * 0.5, $duration), $s->desplazamiento('fiebre', 'temperatura', $entry * 0.2, $duration));
        $this->assertLessThan($s->desplazamiento('fiebre', 'temperatura', $entry * 0.9, $duration), $s->desplazamiento('fiebre', 'temperatura', $entry * 0.5, $duration));

        $m1 = $s->desplazamiento('fiebre', 'temperatura', ($c['entrada'] + 0.1) * $duration, $duration);
        $m2 = $s->desplazamiento('fiebre', 'temperatura', ($c['entrada'] + $c['meseta'] - 0.1) * $duration, $duration);
        $this->assertEqualsWithDelta($delta, $m1, 1e-9);
        $this->assertEqualsWithDelta($delta, $m2, 1e-9);

        $exit0 = ($c['entrada'] + $c['meseta']) * $duration;
        $this->assertGreaterThan($s->desplazamiento('fiebre', 'temperatura', $exit0 + 0.5 * $c['salida'] * $duration, $duration), $s->desplazamiento('fiebre', 'temperatura', $exit0 + 0.2 * $c['salida'] * $duration, $duration));
        $this->assertGreaterThan($s->desplazamiento('fiebre', 'temperatura', $duration - 1, $duration), $s->desplazamiento('fiebre', 'temperatura', $exit0 + 0.5 * $c['salida'] * $duration, $duration));
    }

    public function test_curves_sum_to_one(): void
    {
        foreach ($this->config['escenarios'] as $clave => $e) {
            $this->assertEqualsWithDelta(1.0, array_sum($e['curva']), 1e-9, $clave);
        }
        $this->assertCount(7, $this->sim()->escenarios());
    }

    public function test_missing_data(): void
    {
        $from = $this->t('2026-11-16 10:00:00');
        $to = $this->t('2026-11-16 22:00:00');
        $real = $this->t('2026-11-16 12:00:00');
        $count = function (float $loss) use ($from, $to, $real) {
            $cfg = $this->config;
            $cfg['faltantes'] = ['prob_perdida' => $loss, 'prob_hueco' => 0.0, 'hueco_s' => [600, 600]];
            $s = $this->sim($cfg);
            $r = $s->avanzar($s->estadoInicial(5), $from, $to, 1, $real);

            return count(array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca'));
        };

        $this->assertSame(12 * 12, $count(0.0));   // one reading every 5 min for 12 h
        $this->assertSame(0, $count(1.0));
        $this->assertLessThan($count(0.0), $count(0.5));
    }

    public function test_long_gaps_reduce_readings(): void
    {
        $cfg = $this->config;
        $cfg['faltantes'] = ['prob_perdida' => 0.0, 'prob_hueco' => 0.05, 'hueco_s' => [3600, 3600]];
        $s = $this->sim($cfg);
        $r = $s->avanzar($s->estadoInicial(3), $this->t('2026-11-16 00:00:00'), $this->t('2026-11-17 00:00:00'), 60, $this->t('2026-11-16 12:00:00'));
        $fc = array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca');
        $this->assertLessThan(288, count($fc));
    }

    public function test_measured_at_is_never_in_the_future_and_is_ordered(): void
    {
        $s = $this->sim();
        $from = $this->t('2026-11-16 00:00:00');
        $to = $this->t('2026-11-16 06:00:00');
        $real = $this->t('2026-11-16 12:00:00');
        $speed = 360.0;
        $r = $s->avanzar($s->estadoInicial(9), $from, $to, $speed, $real, [
            'clave' => 'hipoxia', 'inicio' => $this->t('2026-11-16 01:00:00'), 'dura_s' => 7200,
        ]);
        $minimum = $real->copy()->subSeconds((int) ceil(($to->getTimestamp() - $from->getTimestamp()) / $speed));

        $this->assertNotEmpty($r['mediciones']);
        $prev = null;
        foreach ($r['mediciones'] as $m) {
            $this->assertLessThanOrEqual($real->getTimestamp(), $m->medidoEn->getTimestamp());
            $this->assertGreaterThanOrEqual($minimum->getTimestamp(), $m->medidoEn->getTimestamp());
            if ($prev !== null) {
                $this->assertGreaterThanOrEqual($prev, $m->medidoEn->getTimestamp());
            }
            $prev = $m->medidoEn->getTimestamp();
        }
    }

    public function test_values_are_within_the_valid_range(): void
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

    /** @return array<string, Estado> worst state per indicator during the scenario */
    private function worstState(string $clave, int $seed = 21): array
    {
        $s = $this->sim();
        $ev = new EvaluadorEstado($this->umbrales);
        $start = $this->t('2026-11-16 02:00:00');
        $duration = (int) $this->config['escenarios'][$clave]['duracion_min'] * 60;
        $initial = $s->estadoInicial($seed);
        $r = $s->avanzar($initial, $this->t('2026-11-16 00:00:00'), $start->copy()->addSeconds($duration + 3600), 1, $this->t('2026-11-17 00:00:00'), [
            'clave' => $clave, 'inicio' => $start, 'dura_s' => $duration,
        ]);
        $worst = [];
        foreach ($r['mediciones'] as $m) {
            $e = $ev->evaluar($m->indicador, $m->valores);
            $worst[$m->indicador] = isset($worst[$m->indicador]) ? $worst[$m->indicador]->peor($e) : $e;
        }
        $this->assertTrue($r['escenario_terminado']);

        return $worst;
    }

    public function test_fever_reaches_critical_in_temperature(): void
    {
        $this->assertSame(Estado::Critico, $this->worstState('fiebre')['temperatura']);
    }

    public function test_hypoxia_reaches_alert_in_spo2_and_pulse(): void
    {
        $p = $this->worstState('hipoxia');
        $this->assertGreaterThanOrEqual(1, $p['saturacion_o2']->severidad());
        $this->assertSame(Estado::Critico, $p['saturacion_o2']);
        $this->assertGreaterThanOrEqual(1, $p['frecuencia_cardiaca']->severidad());
    }

    public function test_arrhythmia_reaches_alert_in_pulse(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->worstState('arritmia')['frecuencia_cardiaca']->severidad());
    }

    public function test_scenario_emits_immediately_and_ends(): void
    {
        $s = $this->sim();
        $start = $this->t('2026-11-16 02:00:00');
        $initial = $s->estadoInicial(4);
        // First one-minute advance: the daily sleep cadence is not due, but the scenario forces a reading.
        $r = $s->avanzar($initial, $start->copy()->subMinute(), $start->copy()->addMinute(), 1, $start, [
            'clave' => 'falta_sueno', 'inicio' => $start, 'dura_s' => 18000,
        ]);
        $this->assertContains('sueno', array_map(fn ($m) => $m->indicador, $r['mediciones']));
        $this->assertFalse($r['escenario_terminado']);

        $r2 = $s->avanzar($r['estado'], $start->copy()->addMinute(), $start->copy()->addSeconds(18001), 1, $start, [
            'clave' => 'falta_sueno', 'inicio' => $start, 'dura_s' => 18000,
        ]);
        $this->assertTrue($r2['escenario_terminado']);
    }

    public function test_sensor_failure_freezes_and_yields_impossible_values(): void
    {
        $s = $this->sim();
        $start = $this->t('2026-11-16 02:00:00');
        $r = $s->avanzar($s->estadoInicial(8), $this->t('2026-11-16 01:00:00'), $this->t('2026-11-16 03:00:00'), 1, $this->t('2026-11-16 03:00:00'), [
            'clave' => 'fallo_sensor', 'inicio' => $start, 'dura_s' => 3600,
        ]);
        $impossible = array_filter($r['mediciones'], fn ($m) => $m->indicador === 'saturacion_o2' && $m->valores['valor'] < 50);
        $this->assertNotEmpty($impossible);
        $fc = array_values(array_filter($r['mediciones'], fn ($m) => $m->indicador === 'frecuencia_cardiaca' && $m->medidoEn >= $this->t('2026-11-16 02:10:00')));
        $this->assertNotEmpty($fc);
        $this->assertCount(1, array_unique(array_map(fn ($m) => $m->valores['valor'], array_slice($fc, 0, 6))));
    }

    public function test_daily_and_weekly_cadence(): void
    {
        $s = $this->sim();
        $r = $s->avanzar($s->estadoInicial(6), $this->t('2026-11-16 00:00:00'), $this->t('2026-11-30 00:00:00'), 360, $this->t('2026-11-30 00:00:00'));
        $counts = array_count_values(array_map(fn ($m) => $m->indicador, $r['mediciones']));
        $this->assertLessThanOrEqual(14, $counts['sueno']);
        $this->assertGreaterThanOrEqual(10, $counts['sueno']);
        $this->assertLessThanOrEqual(2, $counts['masa_muscular']);
    }
}
