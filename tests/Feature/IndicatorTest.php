<?php

namespace Tests\Feature;

use App\Livewire\Indicador;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database data. */
class IndicatorTest extends TestCase
{
    use DatabaseTransactions;

    private Mision $mission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $this->mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $this->mission->id]);
    }

    private function crewMember(string $name): Astronauta
    {
        return Astronauta::where('mision_id', $this->mission->id)->where('nombre', $name)->firstOrFail();
    }

    public function test_route_responds_with_the_screen_and_the_menu_links_to_it(): void
    {
        $this->get(route('indicador'))->assertOk()
            ->assertSee('Guidance only, not a diagnosis', false)
            ->assertSee('href="/indicador"', false)
            ->assertSee('indicador(', false);
    }

    public function test_loads_with_the_current_crew_member_and_14_markers(): void
    {
        $dilan = $this->crewMember('Dilan García');
        TripulanteActual::fijar($dilan->id);

        Livewire::test(Indicador::class)
            ->assertSee('Crew member Dilan García')
            ->assertViewHas('astronauta', fn ($a) => $a->id === $dilan->id)
            ->assertViewHas('payload', fn ($p) => count($p['marcadores']) === 14 && isset($p['diaria']['presion_arterial']) && isset($p['horaria']['glucosa']));
    }

    public function test_honors_the_id_query_and_discards_invalid_values(): void
    {
        Livewire::withQueryParams(['id' => 'glucosa', 'mode' => 'cmp', 'view' => 'heat', 'm' => 'presion_arterial,invented'])
            ->test(Indicador::class)
            ->assertViewHas('consulta', fn ($c) => $c === ['id' => 'glucosa', 'mode' => 'cmp', 'view' => 'heat', 'm' => 'presion_arterial']);

        Livewire::withQueryParams(['id' => 'no_existe', 'view' => 'x'])
            ->test(Indicador::class)
            ->assertViewHas('consulta', fn ($c) => $c === []);
    }

    public function test_refreshes_the_payload_when_the_crew_member_changes(): void
    {
        $dilan = $this->crewMember('Dilan García');
        $elena = $this->crewMember('Elena Moreau');
        TripulanteActual::fijar($dilan->id);

        $component = Livewire::test(Indicador::class)->assertSee('Crew member Dilan García');

        TripulanteActual::fijar($elena->id);
        $component->dispatch('tripulante-cambiado')
            ->assertSee('Crew member Elena Moreau')
            ->assertViewHas('astronauta', fn ($a) => $a->id === $elena->id)
            ->assertViewHas('payload', fn ($p) => count($p['marcadores']) === 14);
    }

    public function test_shows_the_error_state_and_allows_retrying(): void
    {
        $this->app->bind(\App\Services\Salud\DatosIndicador::class, fn () => throw new \RuntimeException('failure'));
        // Force the error on the property: the payload is not computed and "Retry" is offered.
        Livewire::test(Indicador::class)
            ->set('error', true)
            ->assertSee('Could not read the measurements')
            ->call('reintentar')
            ->assertSet('error', false);
    }
}
