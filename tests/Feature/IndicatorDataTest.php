<?php

namespace Tests\Feature;

use App\Models\Medicion;
use App\Models\Mision;
use App\Services\Salud\DatosIndicador;
use App\Services\Salud\ResumenTripulante;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\HistorialExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. */
class IndicatorDataTest extends TestCase
{
    use DatabaseTransactions;

    private const IDS = [
        'presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2', 'glucosa', 'temperatura', 'imc', 'hidratacion',
        'perdida_osea', 'masa_muscular', 'radiacion_diaria', 'dosis_acumulada', 'sueno', 'estres_cognitivo', 'vision_sans',
    ];

    /** Mockup states (S0) for Dilan. */
    private const S0 = [
        'presion_arterial' => 'warn', 'frecuencia_cardiaca' => 'ok', 'saturacion_o2' => 'ok', 'glucosa' => 'ok', 'temperatura' => 'ok',
        'imc' => 'ok', 'hidratacion' => 'warn', 'perdida_osea' => 'crit', 'masa_muscular' => 'crit', 'radiacion_diaria' => 'ok',
        'dosis_acumulada' => 'ok', 'sueno' => 'crit', 'estres_cognitivo' => 'ok', 'vision_sans' => 'warn',
    ];

    private function crew()
    {
        $this->seed(ExpedicionSeeder::class);
        $this->seed(HistorialExpedicionSeeder::class);

        return Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->tripulantes()->orderBy('id')->get();
    }

    /** One seeding per test (the history is ~11,500 rows): all payload checks run together. */
    public function test_full_payload_of_the_expedition_history(): void
    {
        $crew = $this->crew();
        $dilan = $crew->first();
        $p = DatosIndicador::para($dilan);

        $this->checkShape($p);
        $this->checkSeries($p);
        $this->checkDilanStates($p, $dilan);
        $this->checkCrew($crew);

        DB::flushQueryLog();
        DB::enableQueryLog();
        DatosIndicador::para($dilan);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog())); // 3 + 1 for the baseline (temperature)
    }

    private function checkShape(array $p): void
    {

        $this->assertSame(['hoy', 'dias', 'sistemas', 'marcadores', 'diaria', 'horaria'], array_keys($p));
        $this->assertSame(now('UTC')->toDateString(), $p['hoy']);
        $this->assertSame(142, $p['dias']);
        $this->assertCount(count(config('sistemas.grupos')), $p['sistemas']); // single source: config/sistemas.php
        $this->assertSame([config('sistemas.grupos.0.titulo'), config('sistemas.grupos.0.ic')], $p['sistemas'][0]);
        $this->assertSame(self::IDS, array_column($p['marcadores'], 'id'));
        $this->assertSame(self::IDS, array_keys($p['diaria']));
        $this->assertSame(self::IDS, array_keys($p['horaria']));

        $pa = $p['marcadores'][0];
        $this->assertSame(['id', 'label', 'ic', 'unit', 'sys', 'val', 'disp', 'lo', 'hi', 'base', 'dec', 'rec', 'estado', 'loss', 'cum', 'signo'], array_keys($pa));
        $this->assertSame(config('indicadores.marcadores.presion_arterial.label'), $pa['label']);
        $this->assertSame('monitor_heart', $pa['ic']);
        $this->assertEquals(128, $pa['val']);
        $this->assertSame('128/84', $pa['disp']);
        $this->assertSame([90, 120, 116, 0], [$pa['lo'], $pa['hi'], $pa['base'], $pa['dec']]);

        $por = array_column($p['marcadores'], null, 'id');
        $this->assertTrue($por['perdida_osea']['loss'] && $por['masa_muscular']['loss']);
        $this->assertTrue($por['dosis_acumulada']['cum']);
        $this->assertTrue($por['vision_sans']['signo']);
        $this->assertSame("\u{2212}1.6", $por['perdida_osea']['disp']);
        $this->assertSame('+0.6', $por['vision_sans']['disp']);
        $this->assertSame('76.7', $por['dosis_acumulada']['disp']);
        // sys = group position in config/sistemas.php (derived from there; not hard-coded by hand)
        $this->assertSame([0, 0, 1, 9, 9, 9, 9, 2, 2, 10, 10, 8, 7, 4], array_column($p['marcadores'], 'sys'));
    }

    private function checkSeries(array $p): void
    {

        foreach ($p['marcadores'] as $m) {
            $this->assertCount(142, $p['diaria'][$m['id']], $m['id']);
            $this->assertCount(24, $p['horaria'][$m['id']], $m['id']);
            $this->assertEquals($m['val'], $p['diaria'][$m['id']][141], $m['id'].' diaria');
            $this->assertEquals($m['val'], $p['horaria'][$m['id']][23], $m['id'].' horaria');
            foreach ([...$p['diaria'][$m['id']], ...$p['horaria'][$m['id']]] as $v) {
                $this->assertIsNumeric($v);
            }
        }
        // Cumulative dose: increasing from ~0 to the current value.
        $dosis = $p['diaria']['dosis_acumulada'];
        $this->assertEquals(0.0, $dosis[0]);
        $this->assertEquals(76.7, $dosis[141]);
        for ($i = 1; $i < 142; $i++) {
            $this->assertGreaterThanOrEqual($dosis[$i - 1], $dosis[$i]);
        }
    }

    private function checkDilanStates(array $p, $dilan): void
    {

        foreach ($p['marcadores'] as $m) {
            $this->assertSame(self::S0[$m['id']], $m['estado'], $m['id']);
        }

        // The Dashboard still shows the same values and states as the design.
        $r = ResumenTripulante::make()->de($dilan);
        $this->assertSame(['crit' => 2, 'warn' => 1, 'ok' => 7], $r['conteo']); // Level 2: radiation and dose become normal
        $this->assertSame('128/84', $r['indicadores'][0]['valor']);
        $this->assertTrue($r['medido_hoy']);
        $this->assertSame('07:31', $r['ultima_medicion']->utc()->format('H:i'));
    }

    private function checkCrew($crew): void
    {
        $this->assertCount(5, $crew);
        $expected = ['presion_arterial' => ['warn', 'ok', 'crit', 'ok', 'warn'], 'sueno' => ['crit', 'ok', 'warn', 'ok', 'warn'], 'dosis_acumulada' => ['ok', 'ok', 'ok', 'ok', 'ok']];

        foreach ($crew as $i => $a) {
            $p = DatosIndicador::para($a);
            $por = array_column($p['marcadores'], null, 'id');
            foreach ($expected as $key => $states) {
                $this->assertSame($states[$i], $por[$key]['estado'], $a->nombre.' '.$key);
            }
            foreach (self::IDS as $id) {
                $this->assertCount(142, $p['diaria'][$id]);
            }
        }
        // Kenji and Lucas are still "pending" today: the history adds no measurements for today.
        $this->assertFalse(ResumenTripulante::make()->de($crew[2])['medido_hoy']);
        $this->assertFalse(ResumenTripulante::make()->de($crew[4])['medido_hoy']);
    }

    public function test_seeder_is_idempotent_with_no_alerts_or_effects_outside_the_5(): void
    {
        $crew = $this->crew();
        $ids = $crew->pluck('id');
        $count = fn () => [
            Medicion::whereIn('astronauta_id', $ids)->where('origen', 'historial')->count(),
            DB::table('evaluaciones')->join('mediciones', 'mediciones.id', '=', 'evaluaciones.medicion_id')->whereIn('astronauta_id', $ids)->where('origen', 'historial')->count(),
            Medicion::whereIn('astronauta_id', $ids)->where('origen', '!=', 'historial')->count(),
        ];
        $alerts = fn () => DB::table('alertas_salientes')->count();

        $before = $count();
        $others = Medicion::whereNotIn('astronauta_id', $ids)->count();
        $alertsBefore = $alerts();
        $this->seed(HistorialExpedicionSeeder::class);

        $this->assertSame($before, $count());
        $this->assertGreaterThan(10000, $before[0]);
        $this->assertSame($before[0], $before[1]); // every historical measurement has its evaluation
        $this->assertSame($others, Medicion::whereNotIn('astronauta_id', $ids)->count());
        $this->assertSame($alertsBefore, $alerts()); // the history queues no ground alerts
    }
}
