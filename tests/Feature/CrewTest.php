<?php

namespace Tests\Feature;

use App\Livewire\Tripulacion;
use App\Models\Astronauta;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class CrewTest extends TestCase
{
    use DatabaseTransactions;

    private function expedition(): Mision
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        // Isolates the test from extra crew members in the local database (rolled back with the transaction).
        Astronauta::where('mision_id', $mission->id)->whereNotIn('nombre', array_column(ExpedicionSeeder::TRIPULACION, 'nombre'))->delete();
        session(['mision_id' => $mission->id]);

        return $mission;
    }

    public function test_route_responds_with_the_screen(): void
    {
        $this->expedition();

        $this->get(route('tripulacion'))->assertOk()->assertSee(ExpedicionSeeder::MISION.' · 5 crew members', false);
    }

    public function test_shows_the_mission_crew_with_profile_and_measurement_status(): void
    {
        $this->expedition();

        Livewire::test(Tripulacion::class)
            ->assertSee('Dilan García')->assertSee('Elena Moreau')->assertSee('Kenji Arai')
            ->assertSee('Amara Osei')->assertSee('Lucas Ferreira')
            ->assertSee('Mission Specialist · 36 yr')
            ->assertSee('1.78 m')->assertSee('73.4 kg')->assertSee('A+')
            ->assertSee('MEASURED 07:31')->assertSee('PENDING')
            ->assertSee('Open dashboard for Kenji Arai · Measurement pending today')
            ->assertSee('Open dashboard for Dilan García · Measurement taken · 07:31 UTC');
    }

    public function test_counts_match_the_summary(): void
    {
        $this->expedition();

        // Dilan: 2 critical, 1 caution, 7 normal (Level 2: radiation and dose become normal).
        Livewire::test(Tripulacion::class)
            ->assertSeeInOrder(['Dilan García', '2 Critical', '1 Caution', '7 Normal', 'Elena Moreau'])
            // Amara: everything in range, no critical/caution chips.
            ->assertSeeInOrder(['Amara Osei', '10 Normal', 'Lucas Ferreira']);
    }

    public function test_clicking_a_card_selects_the_crew_member_and_redirects_to_the_dashboard(): void
    {
        $mission = $this->expedition();
        $kenji = Astronauta::where('mision_id', $mission->id)->where('nombre', 'Kenji Arai')->firstOrFail();

        Livewire::test(Tripulacion::class)
            ->call('abrir', $kenji->id)
            ->assertDispatched('tripulante-cambiado')
            ->assertRedirect(route('panel'));

        $this->assertSame($kenji->id, session('astronauta_id'));
    }

    public function test_does_not_open_a_crew_member_from_another_mission(): void
    {
        $this->expedition();
        $other = Mision::create(['nombre' => 'Other mission', 'entorno' => 'estacion']);
        $outsider = Astronauta::create(['mision_id' => $other->id, 'nombre' => 'Zeta Zeta', 'mision' => 'X', 'entorno' => 'estacion']);

        Livewire::test(Tripulacion::class)->call('abrir', $outsider->id)->assertNoRedirect();

        $this->assertNotSame($outsider->id, session('astronauta_id'));
    }

    public function test_changing_a_card_view_does_not_change_the_crew_member(): void
    {
        $mission = $this->expedition();
        $ids = Astronauta::where('mision_id', $mission->id)->orderBy('id')->pluck('id');
        session(['astronauta_id' => $ids[0]]);

        Livewire::test(Tripulacion::class)
            ->call('cambiarVista', $ids[2], 'posterior')
            ->assertSet('vistas', [$ids[2] => 'posterior'])
            ->assertNoRedirect()
            ->assertNotDispatched('tripulante-cambiado')
            ->call('cambiarVista', $ids[2], 'inventada')
            ->assertSet('vistas', [$ids[2] => 'posterior']);

        $this->assertSame($ids[0], session('astronauta_id'));
    }

    public function test_mission_without_crew_shows_the_empty_state(): void
    {
        Astronauta::query()->update(['mision_id' => null]); // inside the transaction: rolled back
        Livewire::test(Tripulacion::class)->assertSee('has no crew members yet');
    }

    public function test_empty_state_has_a_link_to_create_a_crew_member(): void
    {
        Astronauta::query()->update(['mision_id' => null]);

        Livewire::test(Tripulacion::class)
            ->assertSee('This mission has no crew members yet')
            ->assertSee(route('tripulacion.nuevo'), false);
    }

    public function test_creation_notice_highlights_the_card_and_can_be_dismissed(): void
    {
        $mission = $this->expedition();
        $kenji = Astronauta::where('mision_id', $mission->id)->where('nombre', 'Kenji Arai')->firstOrFail();
        session(['astronauta_id' => $kenji->id]);
        session()->flash('tripulante_creado', 'Kenji Arai');

        Livewire::test(Tripulacion::class)
            ->assertSee('Crew member created')
            ->assertSee('Crew member “Kenji Arai” registered successfully.', false)
            ->assertSet('nuevoId', $kenji->id)
            ->assertSee('is-nueva')
            ->call('cerrarAviso')
            ->assertDontSee('Crew member created');
    }

    public function test_without_flash_there_is_no_notice_or_highlight(): void
    {
        $this->expedition();

        Livewire::test(Tripulacion::class)->assertSet('aviso', null)->assertSet('nuevoId', null)->assertDontSee('is-nueva');
    }

    public function test_each_card_links_to_the_record_if_the_route_exists(): void
    {
        $mission = $this->expedition();
        if (! Route::has('tripulacion.ficha')) {
            $this->markTestSkipped('The tripulacion.ficha route does not exist yet.');
        }
        $kenji = Astronauta::where('mision_id', $mission->id)->where('nombre', 'Kenji Arai')->firstOrFail();

        Livewire::test(Tripulacion::class)
            ->assertSee(route('tripulacion.ficha', $kenji), false)
            ->assertSee('Record of Kenji Arai');
    }

    public function test_with_a_full_crew_the_button_is_disabled(): void
    {
        $this->expedition(); // the seeder creates 5 = the maximum

        Livewire::test(Tripulacion::class)
            ->assertSee('Crew full')
            ->assertSee('5 / 5 crew')
            ->assertDontSee('New crew member');
    }
}
