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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ResumenTripulanteTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulacion(): Collection
    {
        $this->seed(ExpedicionSeeder::class);

        return Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->tripulantes()->orderBy('id')->get();
    }

    public function test_forma_y_orden_de_los_indicadores(): void
    {
        $dilan = $this->tripulacion()->first();
        $r = ResumenTripulante::make()->de($dilan);

        $this->assertSame(['indicadores', 'conteo', 'peor', 'medido_hoy', 'ultima_medicion', 'incidencias', 'musculos_activos'], array_keys($r));
        $this->assertSame(array_keys(config('panel.indicadores')), array_column($r['indicadores'], 'clave'));

        $pa = $r['indicadores'][0];
        $this->assertSame(['clave', 'etiqueta', 'icono', 'unidad', 'rango', 'zona', 'musculos', 'acciones', 'valor', 'sospechoso', 'estado', 'razon', 'medido_en', 'tendencia', 'base'], array_keys($pa));
        $this->assertSame('128/84', $pa['valor']);
        $this->assertSame('warn', $pa['estado']);
        $this->assertSame('128/84 mmHg supera tu máximo de 120/80', $pa['razon']);
        $this->assertSame('116/76', $pa['base']);
        $this->assertInstanceOf(Carbon::class, $pa['medido_en']);
    }

    public function test_estados_y_razones_del_diseno(): void
    {
        foreach ($this->tripulacion() as $i => $a) {
            $r = ResumenTripulante::make()->de($a);
            foreach (ExpedicionSeeder::TRIPULACION[$i]['m'] as $k => $m) {
                $ind = $r['indicadores'][$k];
                $this->assertSame(end($m), $ind['estado'], "{$a->nombre} · {$ind['clave']}");
                $this->assertSame($ind['estado'] === 'ok', $ind['razon'] === null);
            }
        }
    }

    public function test_formato_con_signo_menos_y_razones_de_perdida_y_dosis(): void
    {
        $r = ResumenTripulante::make()->de($this->tripulacion()->first());
        $por = array_column($r['indicadores'], null, 'clave');

        $this->assertSame("\u{2212}1.6", $por['perdida_osea']['valor']);
        $this->assertSame('Pérdida de 1.6 %/mes: supera tu límite de 1 %/mes', $por['perdida_osea']['razon']);
        $this->assertSame("\u{2212}0.6", $por['perdida_osea']['base']);
        // Nivel 2: 76.7 mSv (de 600) y 0.54 mSv/día (de 0.9 o 1.3) ya no son incidencia.
        $this->assertNull($por['dosis_acumulada']['razon']);
        $this->assertNull($por['radiacion_diaria']['razon']);
    }

    public function test_conteo_peor_incidencias_y_musculos(): void
    {
        $trip = $this->tripulacion();
        $r = ResumenTripulante::make()->de($trip->first()); // Dilan

        $this->assertSame(['crit' => 2, 'warn' => 1, 'ok' => 7], $r['conteo']);
        $this->assertSame('crit', $r['peor']);
        $this->assertSame(['crit', 'crit', 'warn'], array_column($r['incidencias'], 'estado'));
        $this->assertSame(['perdida_osea', 'masa_muscular', 'presion_arterial'], array_column($r['incidencias'], 'clave'));
        $this->assertSame(array_values(array_unique($r['musculos_activos'])), $r['musculos_activos']);
        $this->assertContains('vasto_lateral_izq', $r['musculos_activos']);
        $this->assertContains('pectoral_mayor_izq', $r['musculos_activos']);

        $amara = ResumenTripulante::make()->de($trip->get(3));
        $this->assertSame(['crit' => 0, 'warn' => 0, 'ok' => 10], $amara['conteo']);
        $this->assertSame('ok', $amara['peor']);
        $this->assertSame([], $amara['incidencias']);
        $this->assertSame([], $amara['musculos_activos']);
    }

    public function test_tendencia_de_7_puntos_con_valor_real_hoy(): void
    {
        $r = ResumenTripulante::make()->de($this->tripulacion()->first());

        foreach ($r['indicadores'] as $ind) {
            $this->assertCount(7, $ind['tendencia']['puntos'], $ind['clave']);
            $this->assertIsFloat($ind['tendencia']['base']);
        }
        $pa = $r['indicadores'][0]['tendencia'];
        $this->assertEquals(128.0, $pa['puntos'][6]);
        $this->assertEquals(116.0, $pa['base']);
        $this->assertEquals(-1.6, $r['indicadores'][6]['tendencia']['puntos'][6]);
    }

    public function test_tendencia_interpola_huecos(): void
    {
        $a = Astronauta::create(['nombre' => 'Hueco', 'mision' => 'T', 'entorno' => 'estacion']);
        $hoy = Carbon::now('UTC')->startOfDay();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 60], 'manual', $hoy->copy()->subDays(4)->setTime(8, 0), false);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 80], 'manual', $hoy->copy()->subDays(2)->setTime(8, 0), false);
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $hoy->copy()->subDay()->setTime(8, 0), false);

        $fc = ResumenTripulante::make()->de($a)['indicadores'][1];

        // días: -6 y -5 (relleno con el vecino), -4 = 60, -3 interpolado, -2 = 80, -1 = 70, hoy (hueco final) = 70
        $this->assertEquals([60, 60, 60, 70, 80, 70, 70], $fc['tendencia']['puntos']);
        $this->assertSame('70', $fc['valor']);
    }

    public function test_pendiente_de_hoy_y_sin_datos(): void
    {
        $trip = $this->tripulacion();
        $kenji = ResumenTripulante::make()->de($trip->get(2));

        $this->assertFalse($kenji['medido_hoy']);
        $this->assertSame('crit', $kenji['peor']);
        $this->assertTrue($kenji['ultima_medicion']->lt(Carbon::now('UTC')->startOfDay()));
        $this->assertTrue(ResumenTripulante::make()->de($trip->first())['medido_hoy']);

        $vacio = Astronauta::create(['nombre' => 'Sin datos', 'mision' => 'T', 'entorno' => 'estacion']);
        $r = ResumenTripulante::make()->de($vacio);
        $this->assertNull($r['peor']);
        $this->assertFalse($r['medido_hoy']);
        $this->assertNull($r['ultima_medicion']);
        $this->assertSame(['crit' => 0, 'warn' => 0, 'ok' => 0], $r['conteo']);
        $this->assertNull($r['indicadores'][0]['valor']);
        $this->assertNull($r['indicadores'][0]['estado']);
        $this->assertCount(7, $r['indicadores'][0]['tendencia']['puntos']);
    }

    public function test_de_varios_usa_pocas_consultas(): void
    {
        $trip = $this->tripulacion();
        $svc = ResumenTripulante::make();

        DB::enableQueryLog();
        $todos = $svc->deVarios($trip);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($trip->pluck('id')->all(), array_keys($todos));
        $this->assertLessThanOrEqual(6, $consultas, "consultas: {$consultas}");
        $this->assertEquals($svc->de($trip->first()), $todos[$trip->first()->id]);
    }

    public function test_seeder_es_idempotente(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $foto = fn () => [
            Mision::where('nombre', ExpedicionSeeder::MISION)->count(),
            $mision->tripulantes()->count(),
            Medicion::whereIn('astronauta_id', $mision->tripulantes()->pluck('id'))->where('origen', '!=', 'historial')->count(), // el historial lo siembra HistorialExpedicionSeeder
        ];
        $antes = $foto();

        $this->seed(ExpedicionSeeder::class);

        $this->assertSame($antes, $foto());
        $this->assertSame([1, 5], array_slice($antes, 0, 2));
        // 3 con hoy (7 días) + 2 pendientes (6 días), 10 indicadores
        $this->assertSame((3 * 7 + 2 * 6) * 10, $antes[2]);
        $this->assertSame(141, (int) Carbon::parse($mision->fresh()->inicio_en)->diffInDays(Carbon::now('UTC')->startOfDay()));

        $dilan = $mision->tripulantes()->where('nombre', 'Dilan García')->first();
        $this->assertEquals([36, 'Especialista de misión', 1.78, 73.4, 'A+', 4], [$dilan->edad, $dilan->rol, $dilan->altura_m, $dilan->peso_kg, $dilan->grupo_sanguineo, $dilan->evas]);
    }
}
