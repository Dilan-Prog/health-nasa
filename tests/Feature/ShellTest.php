<?php

namespace Tests\Feature;

use App\Livewire\SelectorTripulante;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. */
class ShellTest extends TestCase
{
    use DatabaseTransactions;

    private function mission(int $n = 3): array
    {
        $m = Mision::create(['nombre' => 'Expedition ST', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(9), 'duracion_dias' => 30]);
        $a = [];
        foreach (['Ana Ruiz', 'Beto Gil', 'Carla Paz', 'Dani Sol'] as $i => $name) {
            if ($i >= $n) {
                break;
            }
            $a[] = Astronauta::create(['mision_id' => $m->id, 'nombre' => $name, 'mision' => 'ST', 'entorno' => 'estacion']);
        }

        return [$m, $a];
    }

    public function test_without_session_returns_first_member_of_latest_mission(): void
    {
        [$m, $a] = $this->mission();
        $this->assertSame($m->id, TripulanteActual::mision()->id);
        $this->assertSame($a[0]->id, TripulanteActual::obtener()->id);
        $this->assertCount(3, TripulanteActual::tripulacion());
    }

    public function test_set_validates_mission_membership(): void
    {
        [, $a] = $this->mission();
        TripulanteActual::fijar($a[1]->id);
        $this->assertSame($a[1]->id, TripulanteActual::obtener()->id);

        $otro = Astronauta::create(['nombre' => 'No mission', 'mision' => 'X', 'entorno' => 'estacion']);
        TripulanteActual::fijar($otro->id);
        $this->assertSame($a[1]->id, TripulanteActual::obtener()->id);

        session(['astronauta_id' => 999999]);
        $this->assertSame($a[0]->id, TripulanteActual::obtener()->id);
    }

    public function test_selector_switches_crew_member_and_dispatches_event(): void
    {
        [, $a] = $this->mission();
        Livewire::test(SelectorTripulante::class)
            ->assertSee('CREW MEMBER 1/3')
            ->call('alternar')->assertSee('Beto Gil')->assertSee('PENDING')
            ->call('elegir', $a[2]->id)
            ->assertDispatched('tripulante-cambiado')
            ->assertSet('abierto', false)
            ->assertSee('CREW MEMBER 3/3');
        $this->assertSame($a[2]->id, TripulanteActual::obtener()->id);
    }

    public function test_compact_selector_cycles_to_next_member(): void
    {
        [, $a] = $this->mission(2);
        Livewire::test(SelectorTripulante::class, ['variante' => 'compacta'])
            ->call('siguiente')->assertSet('actualId', $a[1]->id)
            ->call('siguiente')->assertSet('actualId', $a[0]->id);
    }

    public function test_layout_marks_active_route_and_disables_the_rest(): void
    {
        $this->mission();
        // Test route named `panel` so the test does not depend on the Dashboard screen being complete.
        app('router')->get('/__shell-test', fn () => view('layouts.app', ['slot' => '']))->name('panel');
        $html = $this->get('/__shell-test')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="nav-link is-activo"\s+aria-current="page"\s*>\s*<span class="icono">dashboard/', $html);
        $this->assertStringContainsString('aria-disabled="true" title="Coming soon"', $html);
        $this->assertStringContainsString('EXPEDITION ST', $html);
    }

    public function test_header_tolerates_null_data(): void
    {
        $html = $this->blade('<x-encabezado titulo-a="Dashboard" titulo-b="Crew member X" />');
        $html->assertSee('Dashboard')->assertSee('ORBIT');
    }
}
