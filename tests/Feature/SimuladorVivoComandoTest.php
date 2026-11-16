<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\SimulacionMotor;
use App\Models\SimulacionTripulante;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class SimuladorVivoComandoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        // SimulacionMotor::actual() asume id 1 (el id no es fillable); en una BD de pruebas con autoincrement avanzado hay que fijarlo.
        \DB::table('simulacion_motor')->delete();
        \DB::table('simulacion_motor')->insert(['id' => 1, 'activa' => false, 'velocidad' => 60, 'intervalo' => 5, 'created_at' => now(), 'updated_at' => now()]);
        SimulacionTripulante::query()->update(['activa' => false]);
    }

    public function test_once_con_motor_apagado_avisa_y_late(): void
    {
        SimulacionMotor::actual()->update(['activa' => false, 'latido_en' => null]);

        $this->artisan('salud:simulador-vivo', ['--once' => true])
            ->expectsOutputToContain('/simulacion')
            ->assertSuccessful();

        $this->assertNotNull(SimulacionMotor::actual()->latido_en);
    }

    public function test_once_con_motor_encendido_imprime_resumen_y_registra(): void
    {
        $a = Astronauta::create(['nombre' => 'Cmd Test', 'mision' => 'T-1', 'entorno' => 'estacion']);
        SimulacionMotor::actual()->update(['activa' => true, 'velocidad' => 360]);
        SimulacionTripulante::de($a)->update(['activa' => true, 'fuente' => 'simulado', 'virtual_en' => now()->subMinutes(10), 'ultimo_tick' => now()->subMinutes(10)]);

        $this->artisan('salud:simulador-vivo', ['--once' => true])
            ->expectsOutputToContain('activos=1')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Medicion::where('astronauta_id', $a->id)->where('origen', 'simulado')->count());
    }

    public function test_bloqueado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('salud:simulador-vivo', ['--once' => true])
            ->expectsOutputToContain('bloqueada en producción')
            ->assertFailed();
    }
}
