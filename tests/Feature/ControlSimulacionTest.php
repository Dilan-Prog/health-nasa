<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\SimulacionEvento;
use App\Models\SimulacionMotor;
use App\Models\SimulacionTripulante;
use App\Services\Salud\ControlSimulacion;
use App\Services\Salud\SimuladorVivo;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ControlSimulacionTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        // Solo el tripulante de prueba queda activo dentro de la transacción.
        SimulacionTripulante::query()->update(['activa' => false]);
        $this->a = Astronauta::create(['nombre' => 'Sim Test', 'mision' => 'T-1', 'entorno' => 'estacion']);
        // SimulacionMotor::actual() asume id 1 (el id no es fillable); en una BD de pruebas con autoincrement avanzado hay que fijarlo.
        \DB::table('simulacion_motor')->delete();
        \DB::table('simulacion_motor')->insert(['id' => 1, 'activa' => false, 'velocidad' => 60, 'intervalo' => 5, 'created_at' => now(), 'updated_at' => now()]);
        SimulacionMotor::actual()->update(['activa' => true, 'velocidad' => 360, 'intervalo' => 5, 'latido_en' => null]);
        SimulacionTripulante::de($this->a)->update(['activa' => true, 'fuente' => 'simulado']);
    }

    private function ticks(int $n, int $cadaS = 300): array
    {
        $t0 = now();
        $r = null;
        for ($i = 0; $i <= $n; $i++) {
            $r = ControlSimulacion::tick($t0->copy()->addSeconds($i * $cadaS));
        }

        return $r;
    }

    public function test_tick_genera_mediciones_simuladas_y_marca_latido(): void
    {
        $this->assertNull(SimulacionMotor::actual()->latido_en);
        $r = $this->ticks(4);

        $this->assertNotNull(SimulacionMotor::actual()->latido_en);
        $this->assertSame(1, $r['activos']);
        $this->assertSame(0, $r['errores']);
        $n = Medicion::where('astronauta_id', $this->a->id)->where('origen', 'simulado')->count();
        $this->assertGreaterThan(0, $n);
        $this->assertSame($n, Medicion::where('astronauta_id', $this->a->id)->count());
    }

    public function test_motor_apagado_no_genera_pero_si_late(): void
    {
        SimulacionMotor::actual()->update(['activa' => false]);
        $r = $this->ticks(3);

        $this->assertSame(0, $r['generadas']);
        $this->assertSame(0, Medicion::where('astronauta_id', $this->a->id)->count());
        $this->assertNotNull(SimulacionMotor::actual()->latido_en);
    }

    public function test_tripulante_con_reloj_nunca_se_simula(): void
    {
        $this->a->forceFill(['token_dispositivo' => hash('sha256', 'x')])->save();
        SimulacionTripulante::de($this->a)->update(['activa' => true, 'fuente' => 'reloj']);

        $r = $this->ticks(3);

        $this->assertSame(0, $r['activos']);
        $this->assertSame(0, Medicion::where('astronauta_id', $this->a->id)->count());
        $this->expectException(InvalidArgumentException::class);
        ControlSimulacion::iniciar($this->a, 'fiebre');
    }

    public function test_iniciar_y_cancelar_escribe_el_evento(): void
    {
        $ev = ControlSimulacion::iniciar($this->a, 'fiebre');
        $this->assertNull($ev->fin_en);
        $t = SimulacionTripulante::de($this->a)->fresh();
        $this->assertSame('fiebre', $t->escenario);
        $this->assertGreaterThan(0, $t->escenario_dura_s);
        $this->assertNotNull(ControlSimulacion::restanteReal($t, SimulacionMotor::actual()));

        ControlSimulacion::iniciar($this->a, 'hipoxia'); // cierra el anterior como cancelado
        $this->assertSame('cancelado', $ev->fresh()->motivo_fin);

        ControlSimulacion::cancelar($this->a);
        $t->refresh();
        $this->assertNull($t->escenario);
        $this->assertNull(ControlSimulacion::restanteReal($t, SimulacionMotor::actual()));
        $this->assertSame(2, SimulacionEvento::where('astronauta_id', $this->a->id)->whereNotNull('fin_en')->where('motivo_fin', 'cancelado')->count());
        ControlSimulacion::cancelar($this->a); // no-op
    }

    public function test_escenario_desconocido_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ControlSimulacion::iniciar($this->a, 'no_existe');
    }

    public function test_hipoxia_lleva_a_alerta_y_genera_una_sola_alerta_a_tierra_por_indicador(): void
    {
        $t0 = now();
        ControlSimulacion::tick($t0); // ancla el reloj
        ControlSimulacion::iniciar($this->a, 'hipoxia');
        for ($i = 1; $i <= 12; $i++) {
            ControlSimulacion::tick($t0->copy()->addSeconds($i * 120));
        }

        $malos = Medicion::where('astronauta_id', $this->a->id)
            ->whereHas('evaluacion', fn ($q) => $q->whereIn('estado', ['precaucion', 'critico']))->count();
        $this->assertGreaterThan(0, $malos, 'El escenario debe llevar algún indicador a precaución/crítico');

        $porIndicador = AlertaSaliente::where('astronauta_id', $this->a->id)->get()->groupBy(fn ($al) => $al->carga['indicador']);
        $this->assertGreaterThan(0, $porIndicador->count());
        foreach ($porIndicador as $nombre => $alertas) {
            $this->assertCount(1, $alertas, "Más de una alerta a tierra para {$nombre}");
        }
    }

    public function test_los_escenarios_del_catalogo_existen(): void
    {
        $e = (new SimuladorVivo())->escenarios();
        $this->assertArrayHasKey('fiebre', $e);
        $this->assertArrayHasKey('hipoxia', $e);
    }
}
