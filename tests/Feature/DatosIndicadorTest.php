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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class DatosIndicadorTest extends TestCase
{
    use DatabaseTransactions;

    private const IDS = [
        'presion_arterial', 'frecuencia_cardiaca', 'saturacion_o2', 'glucosa', 'temperatura', 'imc', 'hidratacion',
        'perdida_osea', 'masa_muscular', 'radiacion_diaria', 'dosis_acumulada', 'sueno', 'estres_cognitivo', 'vision_sans',
    ];

    /** Estados de la maqueta (S0) para Dilan. */
    private const S0 = [
        'presion_arterial' => 'warn', 'frecuencia_cardiaca' => 'ok', 'saturacion_o2' => 'ok', 'glucosa' => 'ok', 'temperatura' => 'ok',
        'imc' => 'ok', 'hidratacion' => 'warn', 'perdida_osea' => 'crit', 'masa_muscular' => 'crit', 'radiacion_diaria' => 'crit',
        'dosis_acumulada' => 'warn', 'sueno' => 'crit', 'estres_cognitivo' => 'ok', 'vision_sans' => 'warn',
    ];

    private function tripulacion()
    {
        $this->seed(ExpedicionSeeder::class);
        $this->seed(HistorialExpedicionSeeder::class);

        return Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->tripulantes()->orderBy('id')->get();
    }

    /** Una sola siembra por prueba (el historial son ~11.500 filas): todas las comprobaciones del payload van juntas. */
    public function test_payload_completo_del_historial_de_la_expedicion(): void
    {
        $trip = $this->tripulacion();
        $dilan = $trip->first();
        $p = DatosIndicador::para($dilan);

        $this->comprobarForma($p);
        $this->comprobarSeries($p);
        $this->comprobarEstadosDilan($p, $dilan);
        $this->comprobarTripulacion($trip);

        DB::flushQueryLog();
        DB::enableQueryLog();
        DatosIndicador::para($dilan);
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
    }

    private function comprobarForma(array $p): void
    {

        $this->assertSame(['hoy', 'dias', 'sistemas', 'marcadores', 'diaria', 'horaria'], array_keys($p));
        $this->assertSame(now('UTC')->toDateString(), $p['hoy']);
        $this->assertSame(142, $p['dias']);
        $this->assertCount(5, $p['sistemas']);
        $this->assertSame(['Cardiovascular', 'cardiology'], $p['sistemas'][0]);
        $this->assertSame(self::IDS, array_column($p['marcadores'], 'id'));
        $this->assertSame(self::IDS, array_keys($p['diaria']));
        $this->assertSame(self::IDS, array_keys($p['horaria']));

        $pa = $p['marcadores'][0];
        $this->assertSame(['id', 'label', 'ic', 'unit', 'sys', 'val', 'disp', 'lo', 'hi', 'base', 'dec', 'rec', 'estado', 'loss', 'cum', 'signo'], array_keys($pa));
        $this->assertSame('Presión arterial', $pa['label']);
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
        $this->assertSame([0, 0, 0, 1, 1, 1, 1, 2, 2, 3, 3, 4, 4, 4], array_column($p['marcadores'], 'sys'));
    }

    private function comprobarSeries(array $p): void
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
        // Dosis acumulada: creciente de ~0 al valor actual.
        $dosis = $p['diaria']['dosis_acumulada'];
        $this->assertEquals(0.0, $dosis[0]);
        $this->assertEquals(76.7, $dosis[141]);
        for ($i = 1; $i < 142; $i++) {
            $this->assertGreaterThanOrEqual($dosis[$i - 1], $dosis[$i]);
        }
    }

    private function comprobarEstadosDilan(array $p, $dilan): void
    {

        foreach ($p['marcadores'] as $m) {
            $this->assertSame(self::S0[$m['id']], $m['estado'], $m['id']);
        }

        // El Panel sigue mostrando los mismos valores y estados del diseño.
        $r = ResumenTripulante::make()->de($dilan);
        $this->assertSame(['crit' => 3, 'warn' => 2, 'ok' => 5], $r['conteo']);
        $this->assertSame('128/84', $r['indicadores'][0]['valor']);
        $this->assertTrue($r['medido_hoy']);
        $this->assertSame('07:31', $r['ultima_medicion']->utc()->format('H:i'));
    }

    private function comprobarTripulacion($trip): void
    {
        $this->assertCount(5, $trip);
        $esperado = ['presion_arterial' => ['warn', 'ok', 'crit', 'ok', 'warn'], 'sueno' => ['crit', 'ok', 'warn', 'ok', 'warn'], 'dosis_acumulada' => ['warn', 'warn', 'ok', 'ok', 'ok']];

        foreach ($trip as $i => $a) {
            $p = DatosIndicador::para($a);
            $por = array_column($p['marcadores'], null, 'id');
            foreach ($esperado as $clave => $estados) {
                $this->assertSame($estados[$i], $por[$clave]['estado'], $a->nombre.' '.$clave);
            }
            foreach (self::IDS as $id) {
                $this->assertCount(142, $p['diaria'][$id]);
            }
        }
        // Kenji y Lucas siguen "pendientes" hoy: el historial no añade mediciones de hoy.
        $this->assertFalse(ResumenTripulante::make()->de($trip[2])['medido_hoy']);
        $this->assertFalse(ResumenTripulante::make()->de($trip[4])['medido_hoy']);
    }

    public function test_seeder_idempotente_y_sin_alertas_ni_efectos_fuera_de_los_5(): void
    {
        $trip = $this->tripulacion();
        $ids = $trip->pluck('id');
        $cuenta = fn () => [
            Medicion::whereIn('astronauta_id', $ids)->where('origen', 'historial')->count(),
            DB::table('evaluaciones')->join('mediciones', 'mediciones.id', '=', 'evaluaciones.medicion_id')->whereIn('astronauta_id', $ids)->where('origen', 'historial')->count(),
            Medicion::whereIn('astronauta_id', $ids)->where('origen', '!=', 'historial')->count(),
        ];
        $alertas = fn () => DB::table('alertas_salientes')->count();

        $antes = $cuenta();
        $otras = Medicion::whereNotIn('astronauta_id', $ids)->count();
        $alertasAntes = $alertas();
        $this->seed(HistorialExpedicionSeeder::class);

        $this->assertSame($antes, $cuenta());
        $this->assertGreaterThan(10000, $antes[0]);
        $this->assertSame($antes[0], $antes[1]); // cada medición histórica tiene su evaluación
        $this->assertSame($otras, Medicion::whereNotIn('astronauta_id', $ids)->count());
        $this->assertSame($alertasAntes, $alertas()); // el historial no encola alertas a tierra
    }
}
