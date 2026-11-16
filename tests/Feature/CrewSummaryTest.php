<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ResumenTripulante;
use Carbon\Carbon;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. */
class CrewSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private function crew(): Collection
    {
        $this->seed(ExpedicionSeeder::class);

        return Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->tripulantes()->orderBy('id')->get();
    }

    public function test_indicator_shape_and_order(): void
    {
        $dilan = $this->crew()->first();
        $r = ResumenTripulante::make()->de($dilan);

        $this->assertSame(['indicadores', 'conteo', 'peor', 'medido_hoy', 'ultima_medicion', 'incidencias', 'musculos_activos'], array_keys($r));
        $this->assertSame(array_keys(config('panel.indicadores')), array_column($r['indicadores'], 'clave'));

        $bp = $r['indicadores'][0];
        $this->assertSame(['clave', 'etiqueta', 'icono', 'unidad', 'rango', 'zona', 'musculos', 'acciones', 'valor', 'sospechoso', 'estado', 'razon', 'medido_en', 'tendencia', 'base'], array_keys($bp));
        $this->assertSame('128/84', $bp['valor']);
        $this->assertSame('warn', $bp['estado']);
        $this->assertSame('128/84 mmHg exceeds your maximum of 120/80', $bp['razon']);
        $this->assertSame('116/76', $bp['base']);
        $this->assertInstanceOf(Carbon::class, $bp['medido_en']);
    }

    public function test_states_and_reasons_match_the_design(): void
    {
        foreach ($this->crew() as $i => $a) {
            $r = ResumenTripulante::make()->de($a);
            foreach (ExpedicionSeeder::TRIPULACION[$i]['m'] as $k => $m) {
                $ind = $r['indicadores'][$k];
                $this->assertSame(end($m), $ind['estado'], "{$a->nombre} · {$ind['clave']}");
                $this->assertSame($ind['estado'] === 'ok', $ind['razon'] === null);
            }
        }
    }

    public function test_minus_sign_format_and_loss_and_dose_reasons(): void
    {
        $r = ResumenTripulante::make()->de($this->crew()->first());
        $byKey = array_column($r['indicadores'], null, 'clave');

        $this->assertSame("\u{2212}1.6", $byKey['perdida_osea']['valor']);
        $this->assertSame('Loss of 1.6 %/month: exceeds your limit of 1 %/month', $byKey['perdida_osea']['razon']);
        $this->assertSame("\u{2212}0.6", $byKey['perdida_osea']['base']);
        // Level 2: 76.7 mSv (of 600) and 0.54 mSv/day (of 0.9 or 1.3) are no longer incidents.
        $this->assertNull($byKey['dosis_acumulada']['razon']);
        $this->assertNull($byKey['radiacion_diaria']['razon']);
    }

    public function test_count_worst_incidents_and_muscles(): void
    {
        $crew = $this->crew();
        $r = ResumenTripulante::make()->de($crew->first()); // Dilan

        $this->assertSame(['crit' => 2, 'warn' => 1, 'ok' => 7], $r['conteo']);
        $this->assertSame('crit', $r['peor']);
        $this->assertSame(['crit', 'crit', 'warn'], array_column($r['incidencias'], 'estado'));
        $this->assertSame(['perdida_osea', 'masa_muscular', 'presion_arterial'], array_column($r['incidencias'], 'clave'));
        $this->assertSame(array_values(array_unique($r['musculos_activos'])), $r['musculos_activos']);
        $this->assertContains('vasto_lateral_izq', $r['musculos_activos']);
        $this->assertContains('pectoral_mayor_izq', $r['musculos_activos']);

        $amara = ResumenTripulante::make()->de($crew->get(3));
        $this->assertSame(['crit' => 0, 'warn' => 0, 'ok' => 10], $amara['conteo']);
        $this->assertSame('ok', $amara['peor']);
        $this->assertSame([], $amara['incidencias']);
        $this->assertSame([], $amara['musculos_activos']);
    }

    public function test_trend_has_7_points_with_real_value_today(): void
    {
        $r = ResumenTripulante::make()->de($this->crew()->first());

        foreach ($r['indicadores'] as $ind) {
            $this->assertCount(7, $ind['tendencia']['puntos'], $ind['clave']);
            $this->assertIsFloat($ind['tendencia']['base']);
        }
        $bp = $r['indicadores'][0]['tendencia'];
        $this->assertEquals(128.0, $bp['puntos'][6]);
        $this->assertEquals(116.0, $bp['base']);
        $this->assertEquals(-1.6, $r['indicadores'][6]['tendencia']['puntos'][6]);
    }

    public function test_trend_interpolates_gaps(): void
    {
        $a = Astronauta::create(['nombre' => 'Gap', 'mision' => 'T', 'entorno' => 'estacion']);
        $today = Carbon::now('UTC')->startOfDay();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 60], 'manual', $today->copy()->subDays(4)->setTime(8, 0), false);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 80], 'manual', $today->copy()->subDays(2)->setTime(8, 0), false);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $today->copy()->subDay()->setTime(8, 0), false);

        $fc = ResumenTripulante::make()->de($a)['indicadores'][1];

        // days: -6 and -5 (filled from the nearest neighbor), -4 = 60, -3 interpolated, -2 = 80, -1 = 70, today (trailing gap) = 70
        $this->assertEquals([60, 60, 60, 70, 80, 70, 70], $fc['tendencia']['puntos']);
        $this->assertSame('70', $fc['valor']);
    }

    public function test_not_yet_measured_today_and_no_data(): void
    {
        $crew = $this->crew();
        $kenji = ResumenTripulante::make()->de($crew->get(2));

        $this->assertFalse($kenji['medido_hoy']);
        $this->assertSame('crit', $kenji['peor']);
        $this->assertTrue($kenji['ultima_medicion']->lt(Carbon::now('UTC')->startOfDay()));
        $this->assertTrue(ResumenTripulante::make()->de($crew->first())['medido_hoy']);

        $empty = Astronauta::create(['nombre' => 'No data', 'mision' => 'T', 'entorno' => 'estacion']);
        $r = ResumenTripulante::make()->de($empty);
        $this->assertNull($r['peor']);
        $this->assertFalse($r['medido_hoy']);
        $this->assertNull($r['ultima_medicion']);
        $this->assertSame(['crit' => 0, 'warn' => 0, 'ok' => 0], $r['conteo']);
        $this->assertNull($r['indicadores'][0]['valor']);
        $this->assertNull($r['indicadores'][0]['estado']);
        $this->assertCount(7, $r['indicadores'][0]['tendencia']['puntos']);
    }

    public function test_batch_summary_uses_few_queries(): void
    {
        $crew = $this->crew();
        $svc = ResumenTripulante::make();

        DB::enableQueryLog();
        $all = $svc->deVarios($crew);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($crew->pluck('id')->all(), array_keys($all));
        $this->assertLessThanOrEqual(6, $queries, "queries: {$queries}");
        $this->assertEquals($svc->de($crew->first()), $all[$crew->first()->id]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $snapshot = fn () => [
            Mision::where('nombre', ExpedicionSeeder::MISION)->count(),
            $mission->tripulantes()->count(),
            Medicion::whereIn('astronauta_id', $mission->tripulantes()->pluck('id'))->where('origen', '!=', 'historial')->count(), // the history is seeded by HistorialExpedicionSeeder
        ];
        $before = $snapshot();

        $this->seed(ExpedicionSeeder::class);

        $this->assertSame($before, $snapshot());
        $this->assertSame([1, 5], array_slice($before, 0, 2));
        // 3 with today (7 days) + 2 pending (6 days), 10 indicators
        $this->assertSame((3 * 7 + 2 * 6) * 10, $before[2]);
        $this->assertSame(141, (int) Carbon::parse($mission->fresh()->inicio_en)->diffInDays(Carbon::now('UTC')->startOfDay()));

        $dilan = $mission->tripulantes()->where('nombre', 'Dilan García')->first();
        $this->assertEquals([36, 'Mission Specialist', 1.78, 73.4, 'A+', 4], [$dilan->edad, $dilan->rol, $dilan->altura_m, $dilan->peso_kg, $dilan->grupo_sanguineo, $dilan->evas]);
    }
}
