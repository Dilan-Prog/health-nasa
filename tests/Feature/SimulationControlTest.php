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

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. */
class SimulationControlTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        // Only the test crew member stays active inside the transaction.
        SimulacionTripulante::query()->update(['activa' => false]);
        $this->a = Astronauta::create(['nombre' => 'Sim Test', 'mision' => 'T-1', 'entorno' => 'estacion']);
        // SimulacionMotor::actual() assumes id 1 (id is not fillable); on a test DB with an advanced auto-increment it has to be pinned.
        \DB::table('simulacion_motor')->delete();
        \DB::table('simulacion_motor')->insert(['id' => 1, 'activa' => false, 'velocidad' => 60, 'intervalo' => 5, 'created_at' => now(), 'updated_at' => now()]);
        SimulacionMotor::actual()->update(['activa' => true, 'velocidad' => 360, 'intervalo' => 5, 'latido_en' => null]);
        SimulacionTripulante::de($this->a)->update(['activa' => true, 'fuente' => 'simulado']);
    }

    private function ticks(int $n, int $everyS = 300): array
    {
        $t0 = now();
        $r = null;
        for ($i = 0; $i <= $n; $i++) {
            $r = ControlSimulacion::tick($t0->copy()->addSeconds($i * $everyS));
        }

        return $r;
    }

    public function test_tick_generates_simulated_measurements_and_marks_heartbeat(): void
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

    public function test_stopped_engine_generates_nothing_but_still_beats(): void
    {
        SimulacionMotor::actual()->update(['activa' => false]);
        $r = $this->ticks(3);

        $this->assertSame(0, $r['generadas']);
        $this->assertSame(0, Medicion::where('astronauta_id', $this->a->id)->count());
        $this->assertNotNull(SimulacionMotor::actual()->latido_en);
    }

    public function test_crew_member_with_watch_is_never_simulated(): void
    {
        $this->a->forceFill(['token_dispositivo' => hash('sha256', 'x')])->save();
        SimulacionTripulante::de($this->a)->update(['activa' => true, 'fuente' => 'reloj']);

        $r = $this->ticks(3);

        $this->assertSame(0, $r['activos']);
        $this->assertSame(0, Medicion::where('astronauta_id', $this->a->id)->count());
        $this->expectException(InvalidArgumentException::class);
        ControlSimulacion::iniciar($this->a, 'fiebre');
    }

    public function test_start_and_cancel_write_the_event(): void
    {
        $ev = ControlSimulacion::iniciar($this->a, 'fiebre');
        $this->assertNull($ev->fin_en);
        $t = SimulacionTripulante::de($this->a)->fresh();
        $this->assertSame('fiebre', $t->escenario);
        $this->assertGreaterThan(0, $t->escenario_dura_s);
        $this->assertNotNull(ControlSimulacion::restanteReal($t, SimulacionMotor::actual()));

        ControlSimulacion::iniciar($this->a, 'hipoxia'); // closes the previous one as cancelled
        $this->assertSame('cancelado', $ev->fresh()->motivo_fin);

        ControlSimulacion::cancelar($this->a);
        $t->refresh();
        $this->assertNull($t->escenario);
        $this->assertNull(ControlSimulacion::restanteReal($t, SimulacionMotor::actual()));
        $this->assertSame(2, SimulacionEvento::where('astronauta_id', $this->a->id)->whereNotNull('fin_en')->where('motivo_fin', 'cancelado')->count());
        ControlSimulacion::cancelar($this->a); // no-op
    }

    public function test_unknown_scenario_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ControlSimulacion::iniciar($this->a, 'no_existe');
    }

    public function test_hypoxia_reaches_alert_and_queues_one_ground_alert_per_indicator(): void
    {
        $t0 = now();
        ControlSimulacion::tick($t0); // anchors the clock
        ControlSimulacion::iniciar($this->a, 'hipoxia');
        for ($i = 1; $i <= 12; $i++) {
            ControlSimulacion::tick($t0->copy()->addSeconds($i * 120));
        }

        $bad = Medicion::where('astronauta_id', $this->a->id)
            ->whereHas('evaluacion', fn ($q) => $q->whereIn('estado', ['precaucion', 'critico']))->count();
        $this->assertGreaterThan(0, $bad, 'The scenario must take at least one indicator to caution/critical');

        $perIndicator = AlertaSaliente::where('astronauta_id', $this->a->id)->get()->groupBy(fn ($al) => $al->carga['indicador']);
        $this->assertGreaterThan(0, $perIndicator->count());
        foreach ($perIndicator as $nombre => $alerts) {
            $this->assertCount(1, $alerts, "More than one ground alert for {$nombre}");
        }
    }

    public function test_catalog_scenarios_exist(): void
    {
        $e = (new SimuladorVivo())->escenarios();
        $this->assertArrayHasKey('fiebre', $e);
        $this->assertArrayHasKey('hipoxia', $e);
    }
}
