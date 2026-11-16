<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database data. */
class GuideTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);
    }

    public function test_shows_the_guide_with_the_14_indicators_and_the_notice(): void
    {
        Livewire::test(Guia::class)
            ->assertSee('How space affects your body')
            ->assertSee('Bone loss (hip)')
            ->assertSee('Guidance only, not a diagnosis')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 14 && collect($t)->every(fn ($x) => $x['texto'] !== '' && $x['rec'] !== ''));
    }

    public function test_every_catalog_marker_has_guide_content(): void
    {
        $this->assertSame(array_keys(config('indicadores.marcadores')), array_keys(config('guia.marcadores')));
    }

    public function test_still_shows_the_content_without_a_crew_member(): void
    {
        session()->forget(['astronauta_id', 'mision_id']);
        Livewire::test(Guia::class)->assertSee('How space affects your body');
    }
}
