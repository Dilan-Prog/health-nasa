<?php

namespace Tests\Feature;

use App\Livewire\Historial;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Carbon\Carbon;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. Hora fijada: domingo 15 nov 2026, 10:15 UTC. */
class HistorialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:15:00', 'UTC'));
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        $dilan = Astronauta::where('mision_id', $mision->id)->where('nombre', 'Dilan García')->firstOrFail();
        TripulanteActual::fijar($dilan->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renderiza_cuatro_graficas_por_defecto(): void
    {
        $c = Livewire::test(Historial::class)
            ->assertSee('Historial')->assertSee('Tripulante Dilan García')
            ->assertSee('Últimos 7 días')->assertSee('Toda la misión')->assertSee('Personalizado')
            ->assertSee('Orienta, no diagnostica')
            ->assertSet('preset', '30')
            ->assertSet('charts', [['presion_arterial', 'line'], ['perdida_osea', 'area'], ['radiacion_diaria', 'bar'], ['glucosa', 'dots']]);
        $html = $c->html();
        $this->assertSame(4, substr_count($html, 'class="hist-grafica"') + substr_count($html, 'class="hist-grafica '));
        $this->assertStringContainsString('Indicador de la gráfica 4', $html);
        $this->assertStringContainsString('Mostrando días 113–142', $html);
    }

    public function test_cambiar_de_periodo(): void
    {
        Livewire::test(Historial::class)
            ->call('elegirPreset', '7')->assertSet('preset', '7')->assertSee('Mostrando días 136–142')
            ->call('elegirPreset', 'all')->assertSee('Mostrando días 1–142')
            ->call('elegirPreset', 'inventado')->assertSet('preset', '30');
    }

    public function test_rango_personalizado_invalido_muestra_mensaje(): void
    {
        Livewire::test(Historial::class)
            ->call('elegirPreset', 'custom')
            ->assertSee('Desde día')->assertSee('Hasta día')
            ->set('desde', 50)->set('hasta', 20)
            ->assertSee('Rango no válido')
            ->set('desde', 20)->set('hasta', 50)
            ->assertDontSee('Rango no válido')->assertSee('Mostrando días 20–50 de la misión');
    }

    public function test_cambiar_tipo_e_indicador_de_una_grafica(): void
    {
        Livewire::test(Historial::class)
            ->call('cambiarTipo', 0, 'bar')->assertSet('charts.0.1', 'bar')
            ->call('cambiarTipo', 0, 'xyz')->assertSet('charts.0.1', 'bar')
            ->call('cambiarIndicador', 1, 'sueno')->assertSet('charts.1.0', 'sueno')
            ->call('cambiarIndicador', 1, 'no_existe')->assertSet('charts.1.0', 'sueno')
            ->call('cambiarTipo', 9, 'line')->assertSet('charts', [['presion_arterial', 'bar'], ['sueno', 'area'], ['radiacion_diaria', 'bar'], ['glucosa', 'dots']]);
    }

    public function test_la_ruta_historial_responde_si_esta_conectada(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('historial')) {
            $this->markTestSkipped('La ruta historial aún no está conectada.');
        }
        $this->get(route('historial'))->assertOk()->assertSee('Historial');
    }
}
