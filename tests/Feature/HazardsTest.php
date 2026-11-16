<?php

namespace Tests\Feature;

use App\Livewire\ContextoMision;
use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never deletes data from the local database. */
class HazardsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_config_has_5_hazards_and_17_grouped_risks(): void
    {
        $this->assertCount(5, config('peligros.peligros'));
        $groups = config('peligros.grupos');
        $this->assertSame([11, 5, 1], [count($groups['fisiologia']['riesgos']), count($groups['conducta']['riesgos']), count($groups['radiacion']['riesgos'])]);
        foreach ($groups as $g) {
            foreach ($g['riesgos'] as [$name, $status, $sentence]) {
                $this->assertContains($status, ['cubierto', 'parcial', 'sin']);
                $this->assertNotSame('', $sentence);
            }
        }
    }

    private function verify($html): void
    {
        $html->assertSee('Hazards and risks')
            ->assertSee('They do not act in isolation: they can combine')
            ->assertSee('example value / internal assessment')
            ->assertSee('Risk of Radiation-Induced Cancers')
            ->assertSee('No coverage');
        foreach (config('peligros.peligros') as $p) {
            $html->assertSee($p['nombre']);
        }
        foreach (config('peligros.grupos') as $g) {
            foreach ($g['riesgos'] as $r) {
                $html->assertSee($r[0]);
            }
        }
        $this->assertSame(17, substr_count($html->html(), 'class="peli-riesgo"'));
    }

    public function test_the_guide_shows_the_hazards_and_the_17_risks(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);

        $this->verify(Livewire::test(Guia::class));
    }

    public function test_the_mission_shows_the_hazards_and_the_17_risks(): void
    {
        $this->seed(ExpedicionSeeder::class);
        session(['mision_id' => Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->id]);

        $this->verify(Livewire::test(ContextoMision::class));
    }
}
