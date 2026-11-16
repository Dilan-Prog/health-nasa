<?php

namespace Tests\Feature;

use App\Livewire\Sistemas;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class BodySystemsTest extends TestCase
{
    use DatabaseTransactions;

    private function pinCrewMember(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);
    }

    public function test_shows_one_card_per_system_with_title_and_notice(): void
    {
        $this->pinCrewMember();
        Livewire::test(Sistemas::class)
            ->assertSee('Body systems')
            ->assertSee(config('sistemas.grupos.0.titulo'))
            ->assertSee(collect(config('sistemas.grupos'))->firstWhere('clave', 'sensorial')['titulo'])
            ->assertSee('No coverage yet')
            ->assertSee('Guidance only')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 11);
    }

    public function test_each_catalog_marker_is_in_exactly_one_group(): void
    {
        $keys = collect(config('sistemas.grupos'))->pluck('marcadores')->flatten()->all();
        $this->assertEqualsCanonicalizing(array_keys(config('indicadores.marcadores')), $keys);
    }

    public function test_card_color_and_pill_reflect_the_worst_state(): void
    {
        $this->pinCrewMember();
        Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($cards) {
            foreach ($cards as $t) {
                if ($t['sin_cobertura']) {
                    continue; // no indicators: there is no "worst state" (covered in BodySystemsSingleSourceTest)
                }
                $order = collect($t['filas'])->pluck('estado')->map(fn ($e) => Sistemas::ESTADOS[$e][2]);
                $worst = array_search($order->min(), array_column(Sistemas::ESTADOS, 2), true);
                if ($t['estado'] !== array_keys(Sistemas::ESTADOS)[$worst] || $t['estado_txt'] !== Sistemas::ESTADOS[$t['estado']][0]) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_rows_link_to_the_indicator_by_id(): void
    {
        $this->pinCrewMember();
        $c = Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($cards) {
            $rows = collect($cards)->pluck('filas')->flatten(1);

            return $rows->count() === 14
                && $rows->every(fn ($f) => $f['url'] === route('indicador', ['id' => $f['clave']]));
        });
        $c->assertSeeHtml('href="'.route('indicador', ['id' => 'presion_arterial']).'"');
        $c->assertSeeHtml('href="'.route('indicador', ['id' => 'vision_sans']).'"');
    }

    public function test_without_crew_member_shows_the_empty_state(): void
    {
        session()->forget(['astronauta_id', 'mision_id']);
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Astronauta::query()->delete(); // inside the test transaction: rolled back
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        Livewire::test(Sistemas::class)
            ->assertSee('No crew member selected')
            ->assertViewHas('tarjetas', []);
    }
}
