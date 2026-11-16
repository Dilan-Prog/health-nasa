<?php

namespace Tests\Feature;

use App\Livewire\Simulacion;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\SimulacionEvento;
use App\Models\SimulacionMotor;
use App\Models\SimulacionTripulante;
use App\Services\Salud\SimuladorVivo;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. */
class SimulationPanelTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        $this->a = Astronauta::where('mision_id', $mission->id)->firstOrFail();
        TripulanteActual::fijar($this->a->id);
        // The engine's single row must have id 1 (actual() cannot set it: id is not fillable).
        DB::table('simulacion_motor')->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        SimulacionMotor::actual()->update(['activa' => false, 'velocidad' => 60, 'intervalo' => 5, 'latido_en' => null]);
    }

    private function scenario(): string
    {
        return array_key_first((new SimuladorVivo)->escenarios());
    }

    public function test_route_responds(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id])->get('/simulacion')->assertOk()->assertSee('Live measurement engine');
    }

    public function test_returns_404_in_production(): void
    {
        $this->app['env'] = 'production';
        Livewire::test(Simulacion::class)->assertStatus(404);
    }

    public function test_master_switch_speed_and_interval_persist(): void
    {
        Livewire::test(Simulacion::class)
            ->call('alternarMotor')->call('fijarVelocidad', 360)->call('fijarIntervalo', 10);
        $m = SimulacionMotor::actual()->fresh();
        $this->assertTrue($m->activa);
        $this->assertSame(360, $m->velocidad);
        $this->assertSame(10, $m->intervalo);

        Livewire::test(Simulacion::class)->call('fijarVelocidad', 7)->call('fijarIntervalo', 99);
        $m = SimulacionMotor::actual()->fresh();
        $this->assertSame(360, $m->velocidad);
        $this->assertSame(10, $m->intervalo);
    }

    public function test_individual_switch(): void
    {
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id);
        $this->assertTrue(SimulacionTripulante::de($this->a)->fresh()->activa);
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id);
        $this->assertFalse(SimulacionTripulante::de($this->a)->fresh()->activa);
    }

    public function test_crew_member_with_watch_source_cannot_be_activated(): void
    {
        SimulacionTripulante::de($this->a)->update(['fuente' => 'reloj', 'activa' => false]);
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id)->assertSee('WATCH');
        $this->assertFalse(SimulacionTripulante::de($this->a)->fresh()->activa);

        Livewire::test(Simulacion::class)->call('fijarFuente', $this->a->id, 'simulado')->call('fijarFuente', $this->a->id, 'reloj');
        $t = SimulacionTripulante::de($this->a)->fresh();
        $this->assertSame('reloj', $t->fuente);
        $this->assertFalse($t->activa);
    }

    public function test_launching_scenario_creates_event_and_cancel_closes_it(): void
    {
        $key = $this->scenario();
        SimulacionMotor::actual()->update(['activa' => true, 'latido_en' => now()]);
        Livewire::test(Simulacion::class)
            ->set('escenarioSel.'.$this->a->id, $key)->call('lanzar', $this->a->id)->assertSee('SCENARIO RUNNING');
        $this->assertSame($key, SimulacionTripulante::de($this->a)->fresh()->escenario);
        $ev = SimulacionEvento::where('astronauta_id', $this->a->id)->latest('id')->first();
        $this->assertNotNull($ev);
        $this->assertNull($ev->fin_en);

        Livewire::test(Simulacion::class)->call('cancelar', $this->a->id);
        $this->assertNull(SimulacionTripulante::de($this->a)->fresh()->escenario);
        $this->assertNotNull($ev->fresh()->fin_en);
        $this->assertSame('cancelado', $ev->fresh()->motivo_fin);
    }

    public function test_unknown_scenario_shows_error(): void
    {
        Livewire::test(Simulacion::class)
            ->set('escenarioSel.'.$this->a->id, 'no-existe')->call('lanzar', $this->a->id)
            ->assertSee('Unknown scenario');
    }

    public function test_command_notice_depends_on_heartbeat(): void
    {
        Livewire::test(Simulacion::class)->assertSee('php artisan salud:simulador-vivo')->assertSee('STOPPED');

        SimulacionMotor::actual()->update(['latido_en' => now(), 'activa' => true]);
        Livewire::test(Simulacion::class)->assertDontSee('php artisan salud:simulador-vivo')->assertSee('RUNNING');

        SimulacionMotor::actual()->update(['activa' => false]);
        Livewire::test(Simulacion::class)->assertSee('PAUSED');
    }
}
