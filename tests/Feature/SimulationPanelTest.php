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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class SimulacionPanelTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        $this->a = Astronauta::where('mision_id', $mision->id)->firstOrFail();
        TripulanteActual::fijar($this->a->id);
        // La fila única del motor debe tener id 1 (actual() no puede fijarlo: id no es fillable).
        DB::table('simulacion_motor')->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        SimulacionMotor::actual()->update(['activa' => false, 'velocidad' => 60, 'intervalo' => 5, 'latido_en' => null]);
    }

    private function escenario(): string
    {
        return array_key_first((new SimuladorVivo)->escenarios());
    }

    public function test_la_ruta_responde(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id])->get('/simulacion')->assertOk()->assertSee('Motor de mediciones en vivo');
    }

    public function test_en_produccion_es_404(): void
    {
        $this->app['env'] = 'production';
        Livewire::test(Simulacion::class)->assertStatus(404);
    }

    public function test_interruptor_general_velocidad_e_intervalo_persisten(): void
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

    public function test_interruptor_individual(): void
    {
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id);
        $this->assertTrue(SimulacionTripulante::de($this->a)->fresh()->activa);
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id);
        $this->assertFalse(SimulacionTripulante::de($this->a)->fresh()->activa);
    }

    public function test_no_se_activa_un_tripulante_con_fuente_reloj(): void
    {
        SimulacionTripulante::de($this->a)->update(['fuente' => 'reloj', 'activa' => false]);
        Livewire::test(Simulacion::class)->call('alternarTripulante', $this->a->id)->assertSee('RELOJ');
        $this->assertFalse(SimulacionTripulante::de($this->a)->fresh()->activa);

        Livewire::test(Simulacion::class)->call('fijarFuente', $this->a->id, 'simulado')->call('fijarFuente', $this->a->id, 'reloj');
        $t = SimulacionTripulante::de($this->a)->fresh();
        $this->assertSame('reloj', $t->fuente);
        $this->assertFalse($t->activa);
    }

    public function test_lanzar_escenario_crea_evento_y_cancelar_lo_cierra(): void
    {
        $clave = $this->escenario();
        SimulacionMotor::actual()->update(['activa' => true, 'latido_en' => now()]);
        Livewire::test(Simulacion::class)
            ->set('escenarioSel.'.$this->a->id, $clave)->call('lanzar', $this->a->id)->assertSee('ESCENARIO EN CURSO');
        $this->assertSame($clave, SimulacionTripulante::de($this->a)->fresh()->escenario);
        $ev = SimulacionEvento::where('astronauta_id', $this->a->id)->latest('id')->first();
        $this->assertNotNull($ev);
        $this->assertNull($ev->fin_en);

        Livewire::test(Simulacion::class)->call('cancelar', $this->a->id);
        $this->assertNull(SimulacionTripulante::de($this->a)->fresh()->escenario);
        $this->assertNotNull($ev->fresh()->fin_en);
        $this->assertSame('cancelado', $ev->fresh()->motivo_fin);
    }

    public function test_escenario_desconocido_muestra_error(): void
    {
        Livewire::test(Simulacion::class)
            ->set('escenarioSel.'.$this->a->id, 'no-existe')->call('lanzar', $this->a->id)
            ->assertSee('Escenario desconocido');
    }

    public function test_aviso_del_comando_segun_latido(): void
    {
        Livewire::test(Simulacion::class)->assertSee('php artisan salud:simulador-vivo')->assertSee('PARADO');

        SimulacionMotor::actual()->update(['latido_en' => now(), 'activa' => true]);
        Livewire::test(Simulacion::class)->assertDontSee('php artisan salud:simulador-vivo')->assertSee('EN MARCHA');

        SimulacionMotor::actual()->update(['activa' => false]);
        Livewire::test(Simulacion::class)->assertSee('PAUSADA');
    }
}
