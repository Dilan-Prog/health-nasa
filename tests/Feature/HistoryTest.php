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

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database data. Fixed time: Sunday 15 Nov 2026, 10:15 UTC. */
class HistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:15:00', 'UTC'));
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        $dilan = Astronauta::where('mision_id', $mission->id)->where('nombre', 'Dilan García')->firstOrFail();
        TripulanteActual::fijar($dilan->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renders_four_charts_by_default(): void
    {
        $c = Livewire::test(Historial::class)
            ->assertSee('History')->assertSee('Crew member Dilan García')
            ->assertSee('Last 7 days')->assertSee('Entire mission')->assertSee('Custom')
            ->assertSee('Guidance only, not a diagnosis')
            ->assertSet('preset', '30')
            ->assertSet('charts', [['presion_arterial', 'line'], ['perdida_osea', 'area'], ['radiacion_diaria', 'bar'], ['glucosa', 'dots']]);
        $html = $c->html();
        $this->assertSame(4, substr_count($html, 'class="hist-grafica"') + substr_count($html, 'class="hist-grafica '));
        $this->assertStringContainsString('Indicator for chart 4', $html);
        $this->assertStringContainsString('Showing days 113–142', $html);
    }

    public function test_changing_the_period(): void
    {
        Livewire::test(Historial::class)
            ->call('elegirPreset', '7')->assertSet('preset', '7')->assertSee('Showing days 136–142')
            ->call('elegirPreset', 'all')->assertSee('Showing days 1–142')
            ->call('elegirPreset', 'invented')->assertSet('preset', '30');
    }

    public function test_invalid_custom_range_shows_a_message(): void
    {
        Livewire::test(Historial::class)
            ->call('elegirPreset', 'custom')
            ->assertSee('From day')->assertSee('To day')
            ->set('desde', 50)->set('hasta', 20)
            ->assertSee('Invalid range')
            ->set('desde', 20)->set('hasta', 50)
            ->assertDontSee('Invalid range')->assertSee('Showing days 20–50 of the mission');
    }

    public function test_changing_the_type_and_indicator_of_a_chart(): void
    {
        Livewire::test(Historial::class)
            ->call('cambiarTipo', 0, 'bar')->assertSet('charts.0.1', 'bar')
            ->call('cambiarTipo', 0, 'xyz')->assertSet('charts.0.1', 'bar')
            ->call('cambiarIndicador', 1, 'sueno')->assertSet('charts.1.0', 'sueno')
            ->call('cambiarIndicador', 1, 'no_existe')->assertSet('charts.1.0', 'sueno')
            ->call('cambiarTipo', 9, 'line')->assertSet('charts', [['presion_arterial', 'bar'], ['sueno', 'area'], ['radiacion_diaria', 'bar'], ['glucosa', 'dots']]);
    }

    public function test_history_route_responds_when_connected(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('historial')) {
            $this->markTestSkipped('The history route is not connected yet.');
        }
        $this->get(route('historial'))->assertOk()->assertSee('History');
    }
}
