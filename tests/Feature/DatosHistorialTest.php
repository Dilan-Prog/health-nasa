<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\DatosHistorial;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class DatosHistorialTest extends TestCase
{
    use DatabaseTransactions;

    private const MENOS = "\u{2212}";

    /** Misión (hoy = día $dias) y un astronauta. */
    private function astronauta(int $dias = 10): Astronauta
    {
        $mision = Mision::create([
            'nombre' => 'Test historial', 'entorno' => 'estacion',
            'inicio_en' => Carbon::now('UTC')->startOfDay()->subDays($dias - 1), 'duracion_dias' => 180,
        ]);

        return Astronauta::create(['nombre' => 'Hist', 'mision' => 'T', 'entorno' => 'estacion', 'mision_id' => $mision->id]);
    }

    /** Medición en el día de misión $dia (misión de 10 días) a las $hora UTC. */
    private function med(Astronauta $a, string $ind, array $valores, int $dia, string $hora = '12:00:00'): void
    {
        $f = Carbon::now('UTC')->startOfDay()->subDays(10 - $dia)->setTimeFromTimeString($hora);
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => $ind, 'valores' => $valores, 'medido_en' => $f, 'origen' => 'test']);
    }

    public function test_rango_presets(): void
    {
        $r = DatosHistorial::rango('7', 142, null, null);
        $this->assertSame([136, 142, false, 'date_range'], [$r['desde'], $r['hasta'], $r['invalido'], $r['icono']]);
        $this->assertSame('Mostrando días 136–142 de la misión · 7 días', $r['mensaje']);

        $r30 = DatosHistorial::rango('30', 142, null, null);
        $this->assertSame([113, 142], [$r30['desde'], $r30['hasta']]);
        $this->assertSame(1, DatosHistorial::rango('90', 50, null, null)['desde']);
        $all = DatosHistorial::rango('all', 142, null, null);
        $this->assertSame([1, 142], [$all['desde'], $all['hasta']]);
        $this->assertSame('Mostrando días 1–142 de la misión · 142 días', $all['mensaje']);
    }

    public function test_rango_personalizado_valido_e_invalido(): void
    {
        $ok = DatosHistorial::rango('custom', 142, 10, 20);
        $this->assertSame([10, 20, false], [$ok['desde'], $ok['hasta'], $ok['invalido']]);
        $this->assertSame('Mostrando días 10–20 de la misión · 11 días', $ok['mensaje']);

        foreach ([[20, 10], [5, 5], [0, 10], [10, 143], [null, 10], [10, null]] as [$d, $h]) {
            $r = DatosHistorial::rango('custom', 142, $d, $h);
            $this->assertTrue($r['invalido']);
            $this->assertSame([113, 142, 'error'], [$r['desde'], $r['hasta'], $r['icono']]);
            $this->assertSame('Rango no válido: el día inicial debe ser menor que el final (1–142)', $r['mensaje']);
        }
    }

    public function test_catalogo_y_constantes(): void
    {
        $this->assertCount(14, DatosHistorial::indicadores());
        $this->assertSame(['id' => 'presion_arterial', 'label' => 'Presión arterial'], DatosHistorial::indicadores()[0]);
        $this->assertCount(6, DatosHistorial::PRESETS);
        $this->assertSame(['line', 'bar', 'area', 'dots'], array_column(DatosHistorial::TIPOS, 0));
    }

    public function test_dias_mision(): void
    {
        $this->assertSame(10, DatosHistorial::diasMision($this->astronauta(10)));
    }

    public function test_serie_stats_y_geometria(): void
    {
        $a = $this->astronauta();
        foreach ([1 => 60, 2 => 70, 3 => 80, 4 => 90] as $dia => $v) {
            $this->med($a, 'frecuencia_cardiaca', ['valor' => $v], $dia);
        }
        // Otra medición anterior el mismo día: gana la última del día (12:00).
        $this->med($a, 'frecuencia_cardiaca', ['valor' => 1], 4, '08:00:00');

        $g = DatosHistorial::grafica($a, 'frecuencia_cardiaca', 'line', 1, 10);
        $this->assertFalse($g['vacio']);
        $this->assertSame(4, $g['n']);
        $this->assertSame('Frecuencia cardíaca, días 1 a 10, gráfica de línea', $g['aria']);
        $this->assertSame('60–100', $g['rangeTxt']);

        $this->assertSame('ÚLTIMO', $g['stats'][0]['k']);
        $this->assertSame('90 lpm', $g['stats'][0]['v']);
        $this->assertSame('75 lpm', $g['stats'][1]['v']);
        $this->assertSame('60 lpm', $g['stats'][2]['v']);
        $this->assertSame('90 lpm', $g['stats'][3]['v']);
        $this->assertSame('ok', $g['estado']);
        $this->assertSame('trending_up', $g['trendIc']);
        $this->assertSame('+30 lpm en el periodo', $g['trendTxt']);

        $pts = explode(' ', $g['linePts']);
        $this->assertCount(4, $pts);
        $this->assertStringStartsWith('0,', $pts[0]);
        $this->assertStringStartsWith('600,', $pts[3]);
        $this->assertSame('', $g['areaD']);
        $this->assertSame('', $g['dotsD']);
        $this->assertSame([], $g['bars']);
        $this->assertGreaterThan(0, $g['baseY']);

        // mn = 60 - 3.2, mx = 100 + 3.2: la banda normal va de Y(100) a Y(60).
        $esc = 164 / (40 + 2 * 3.2);
        $this->assertEqualsWithDelta(172 - (100 - 56.8) * $esc, $g['bandY'], 0.11);
        $this->assertEqualsWithDelta(40 * $esc, $g['bandH'], 0.2);
        $this->assertSame('103', $g['yMax']);
        $this->assertSame('57', $g['yMin']);
    }

    public function test_filtra_por_rango_de_dias(): void
    {
        $a = $this->astronauta();
        foreach (range(1, 10) as $d) {
            $this->med($a, 'saturacion_o2', ['valor' => 90 + $d], $d);
        }
        $g = DatosHistorial::grafica($a, 'saturacion_o2', 'dots', 4, 6);
        $this->assertSame(3, $g['n']);
        $this->assertSame('96 %', $g['stats'][0]['v']);
        $this->assertSame('94 %', $g['stats'][2]['v']);
        $this->assertSame('', $g['linePts']);
        $this->assertSame(3, substr_count($g['dotsD'], ' l0 0'));
        $this->assertStringStartsWith('M0 ', $g['dotsD']);
    }

    public function test_barras_y_area(): void
    {
        $a = $this->astronauta();
        $this->med($a, 'frecuencia_cardiaca', ['valor' => 70], 9);
        $this->med($a, 'frecuencia_cardiaca', ['valor' => 130], 10); // fuera del rango normal

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

    public function test_presion_usa_la_sistolica(): void
    {
        $a = $this->astronauta();
        $this->med($a, 'presion_arterial', ['sistolica' => 118, 'diastolica' => 76], 9);
        $this->med($a, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84], 10);
        $g = DatosHistorial::grafica($a, 'presion_arterial', 'line', 1, 10);
        $this->assertSame('128 mmHg', $g['stats'][0]['v']);
        $this->assertSame('+10 mmHg en el periodo', $g['trendTxt']);
    }

    public function test_marcador_de_perdida_invierte_minimos_y_usa_signo_menos(): void
    {
        $a = $this->astronauta();
        foreach ([1 => -0.4, 2 => -0.8, 3 => -1.6] as $dia => $v) {
            $this->med($a, 'perdida_osea', ['valor' => $v], $dia);
        }
        $g = DatosHistorial::grafica($a, 'perdida_osea', 'line', 1, 10);
        $this->assertSame(self::MENOS.'1.6 %/mes', $g['stats'][0]['v']);
        $this->assertSame(self::MENOS.'0.9 %/mes', $g['stats'][1]['v']);
        $this->assertSame(self::MENOS.'1.6 %/mes', $g['stats'][2]['v']); // MÍN invertido (como el diseño): la mayor pérdida
        $this->assertSame(self::MENOS.'0.4 %/mes', $g['stats'][3]['v']); // MÁX invertido: la menor pérdida
        $this->assertStringStartsWith(self::MENOS, $g['yMax']);
        $this->assertSame('+1.2 %/mes en el periodo', $g['trendTxt']);
        $this->assertSame('trending_up', $g['trendIc']); // en magnitud la pérdida crece
        $this->assertSame(self::MENOS.'1.0–0.0', $g['rangeTxt']);
    }

    public function test_dosis_acumulada_sin_linea_base(): void
    {
        $a = $this->astronauta();
        $this->med($a, 'dosis_acumulada', ['valor' => 10], 1);
        $this->med($a, 'dosis_acumulada', ['valor' => 20], 2);
        $g = DatosHistorial::grafica($a, 'dosis_acumulada', 'line', 1, 10);
        $this->assertSame(-10.0, $g['baseY']);
        $this->assertSame('20.0 mSv', $g['stats'][0]['v']);
    }

    public function test_estado_vacio_y_punto_unico(): void
    {
        $a = $this->astronauta();
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

        $this->med($a, 'glucosa', ['valor' => 100], 10);
        $u = DatosHistorial::grafica($a, 'glucosa', 'line', 1, 10);
        $this->assertSame(1, $u['n']);
        $this->assertStringStartsWith('300,', $u['linePts']);
    }
}
