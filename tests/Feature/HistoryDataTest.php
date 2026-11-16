<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\DatosHistorial;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. */
class HistoryDataTest extends TestCase
{
    use DatabaseTransactions;

    private const MINUS = "\u{2212}";

    /** A mission (today = day $dias) and one astronaut. */
    private function crewMember(int $dias = 10): Astronauta
    {
        $mision = Mision::create([
            'nombre' => 'History test', 'entorno' => 'estacion',
            'inicio_en' => Carbon::now('UTC')->startOfDay()->subDays($dias - 1), 'duracion_dias' => 180,
        ]);

        return Astronauta::create(['nombre' => 'Hist', 'mision' => 'T', 'entorno' => 'estacion', 'mision_id' => $mision->id]);
    }

    /** Measurement on mission day $dia (10-day mission) at $hora UTC. */
    private function measure(Astronauta $a, string $ind, array $valores, int $dia, string $hora = '12:00:00'): void
    {
        $f = Carbon::now('UTC')->startOfDay()->subDays(10 - $dia)->setTimeFromTimeString($hora);
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => $ind, 'valores' => $valores, 'medido_en' => $f, 'origen' => 'test']);
    }

    public function test_range_presets(): void
    {
        $r = DatosHistorial::rango('7', 142, null, null);
        $this->assertSame([136, 142, false, 'date_range'], [$r['desde'], $r['hasta'], $r['invalido'], $r['icono']]);
        $this->assertSame('Showing days 136–142 of the mission · 7 days', $r['mensaje']);

        $r30 = DatosHistorial::rango('30', 142, null, null);
        $this->assertSame([113, 142], [$r30['desde'], $r30['hasta']]);
        $this->assertSame(1, DatosHistorial::rango('90', 50, null, null)['desde']);
        $all = DatosHistorial::rango('all', 142, null, null);
        $this->assertSame([1, 142], [$all['desde'], $all['hasta']]);
        $this->assertSame('Showing days 1–142 of the mission · 142 days', $all['mensaje']);
    }

    public function test_custom_range_valid_and_invalid(): void
    {
        $ok = DatosHistorial::rango('custom', 142, 10, 20);
        $this->assertSame([10, 20, false], [$ok['desde'], $ok['hasta'], $ok['invalido']]);
        $this->assertSame('Showing days 10–20 of the mission · 11 days', $ok['mensaje']);

        foreach ([[20, 10], [5, 5], [0, 10], [10, 143], [null, 10], [10, null]] as [$d, $h]) {
            $r = DatosHistorial::rango('custom', 142, $d, $h);
            $this->assertTrue($r['invalido']);
            $this->assertSame([113, 142, 'error'], [$r['desde'], $r['hasta'], $r['icono']]);
            $this->assertSame('Invalid range: the start day must be less than the end day (1–142)', $r['mensaje']);
        }
    }

    public function test_catalog_and_constants(): void
    {
        $this->assertCount(14, DatosHistorial::indicadores());
        $this->assertSame(['id' => 'presion_arterial', 'label' => 'Blood pressure'], DatosHistorial::indicadores()[0]);
        $this->assertCount(6, DatosHistorial::PRESETS);
        $this->assertSame(['line', 'bar', 'area', 'dots'], array_column(DatosHistorial::TIPOS, 0));
    }

    public function test_mission_days(): void
    {
        $this->assertSame(10, DatosHistorial::diasMision($this->crewMember(10)));
    }

    public function test_series_stats_and_geometry(): void
    {
        $a = $this->crewMember();
        foreach ([1 => 60, 2 => 70, 3 => 80, 4 => 90] as $dia => $v) {
            $this->measure($a, 'frecuencia_cardiaca', ['valor' => $v], $dia);
        }
        // Another earlier measurement the same day: the last one of the day (12:00) wins.
        $this->measure($a, 'frecuencia_cardiaca', ['valor' => 1], 4, '08:00:00');

        $g = DatosHistorial::grafica($a, 'frecuencia_cardiaca', 'line', 1, 10);
        $this->assertFalse($g['vacio']);
        $this->assertSame(4, $g['n']);
        $this->assertSame('Heart rate, days 1 to 10, line chart', $g['aria']);
        $this->assertSame('60–100', $g['rangeTxt']);

        $this->assertSame('LATEST', $g['stats'][0]['k']);
        $this->assertSame('90 bpm', $g['stats'][0]['v']);
        $this->assertSame('75 bpm', $g['stats'][1]['v']);
        $this->assertSame('60 bpm', $g['stats'][2]['v']);
        $this->assertSame('90 bpm', $g['stats'][3]['v']);
        $this->assertSame('ok', $g['estado']);
        $this->assertSame('trending_up', $g['trendIc']);
        $this->assertSame('+30 bpm over the period', $g['trendTxt']);

        $pts = explode(' ', $g['linePts']);
        $this->assertCount(4, $pts);
        $this->assertStringStartsWith('0,', $pts[0]);
        $this->assertStringStartsWith('600,', $pts[3]);
        $this->assertSame('', $g['areaD']);
        $this->assertSame('', $g['dotsD']);
        $this->assertSame([], $g['bars']);
        $this->assertGreaterThan(0, $g['baseY']);

        // mn = 60 - 3.2, mx = 100 + 3.2: the normal band runs from Y(100) to Y(60).
        $esc = 164 / (40 + 2 * 3.2);
        $this->assertEqualsWithDelta(172 - (100 - 56.8) * $esc, $g['bandY'], 0.11);
        $this->assertEqualsWithDelta(40 * $esc, $g['bandH'], 0.2);
        $this->assertSame('103', $g['yMax']);
        $this->assertSame('57', $g['yMin']);
    }

    public function test_filters_by_day_range(): void
    {
        $a = $this->crewMember();
        foreach (range(1, 10) as $d) {
            $this->measure($a, 'saturacion_o2', ['valor' => 90 + $d], $d);
        }
        $g = DatosHistorial::grafica($a, 'saturacion_o2', 'dots', 4, 6);
        $this->assertSame(3, $g['n']);
        $this->assertSame('96 %', $g['stats'][0]['v']);
        $this->assertSame('94 %', $g['stats'][2]['v']);
        $this->assertSame('', $g['linePts']);
        $this->assertSame(3, substr_count($g['dotsD'], ' l0 0'));
        $this->assertStringStartsWith('M0 ', $g['dotsD']);
    }

    public function test_bars_and_area(): void
    {
        $a = $this->crewMember();
        $this->measure($a, 'frecuencia_cardiaca', ['valor' => 70], 9);
        $this->measure($a, 'frecuencia_cardiaca', ['valor' => 130], 10); // outside the normal range

        $b = DatosHistorial::grafica($a, 'frecuencia_cardiaca', 'bar', 1, 10);
        $this->assertCount(2, $b['bars']);
        $this->assertSame('ok', $b['bars'][0]['estado']);
        $this->assertNotSame('ok', $b['bars'][1]['estado']);
        $this->assertSame($b['bars'][1]['estado'], $b['estado']);
        $this->assertSame(['x', 'w', 'y', 'h', 'estado'], array_keys($b['bars'][0]));
        $this->assertSame('', $b['linePts']);

        $ar = DatosHistorial::grafica($a, 'frecuencia_cardiaca', 'area', 1, 10);
        $this->assertStringStartsWith('M0,172 L0,', $ar['areaD']);
        $this->assertStringEndsWith(' L600,172 Z', $ar['areaD']);
        $this->assertNotSame('', $ar['linePts']);
    }

    public function test_blood_pressure_uses_systolic(): void
    {
        $a = $this->crewMember();
        $this->measure($a, 'presion_arterial', ['sistolica' => 118, 'diastolica' => 76], 9);
        $this->measure($a, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84], 10);
        $g = DatosHistorial::grafica($a, 'presion_arterial', 'line', 1, 10);
        $this->assertSame('128 mmHg', $g['stats'][0]['v']);
        $this->assertSame('+10 mmHg over the period', $g['trendTxt']);
    }

    public function test_loss_marker_inverts_min_max_and_uses_minus_sign(): void
    {
        $a = $this->crewMember();
        foreach ([1 => -0.4, 2 => -0.8, 3 => -1.6] as $dia => $v) {
            $this->measure($a, 'perdida_osea', ['valor' => $v], $dia);
        }
        $g = DatosHistorial::grafica($a, 'perdida_osea', 'line', 1, 10);
        $this->assertSame(self::MINUS.'1.6 %/month', $g['stats'][0]['v']);
        $this->assertSame(self::MINUS.'0.9 %/month', $g['stats'][1]['v']);
        $this->assertSame(self::MINUS.'1.6 %/month', $g['stats'][2]['v']); // MIN inverted (as in the design): the largest loss
        $this->assertSame(self::MINUS.'0.4 %/month', $g['stats'][3]['v']); // MAX inverted: the smallest loss
        $this->assertStringStartsWith(self::MINUS, $g['yMax']);
        $this->assertSame('+1.2 %/month over the period', $g['trendTxt']);
        $this->assertSame('trending_up', $g['trendIc']); // in magnitude the loss grows
        $this->assertSame(self::MINUS.'1.0–0.0', $g['rangeTxt']);
    }

    public function test_cumulative_dose_has_no_baseline(): void
    {
        $a = $this->crewMember();
        $this->measure($a, 'dosis_acumulada', ['valor' => 10], 1);
        $this->measure($a, 'dosis_acumulada', ['valor' => 20], 2);
        $g = DatosHistorial::grafica($a, 'dosis_acumulada', 'line', 1, 10);
        $this->assertSame(-10.0, $g['baseY']);
        $this->assertSame('20.0 mSv', $g['stats'][0]['v']);
    }

    public function test_empty_state_and_single_point(): void
    {
        $a = $this->crewMember();
        $g = DatosHistorial::grafica($a, 'glucosa', 'bar', 1, 10);
        $this->assertTrue($g['vacio']);
        $this->assertSame(0, $g['n']);
        $this->assertSame([], $g['stats']);
        $this->assertSame([], $g['bars']);
        $this->assertSame('', $g['linePts']);
        $this->assertSame('', $g['areaD']);
        $this->assertSame('', $g['dotsD']);
        $this->assertSame(-10.0, $g['baseY']);
        $this->assertSame('trending_flat', $g['trendIc']);
        $this->assertSame('glucosa', $g['id']);

        $this->measure($a, 'glucosa', ['valor' => 100], 10);
        $u = DatosHistorial::grafica($a, 'glucosa', 'line', 1, 10);
        $this->assertSame(1, $u['n']);
        $this->assertStringStartsWith('300,', $u['linePts']);
    }
}
