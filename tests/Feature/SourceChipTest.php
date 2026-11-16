<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Models\Requisito;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class SourceChipTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);
    }

    public function test_chip_shows_label_revision_and_example_value(): void
    {
        $html = Blade::render('<x-fuente-chip tipo="hrp" revision="Rev. X" />');
        $this->assertStringContainsString('HRP (observation)', $html);
        $this->assertStringContainsString('Rev. X', $html);
        $this->assertStringContainsString('Example value (unvalidated)', $html);
    }

    public function test_validated_chip_does_not_show_example_value(): void
    {
        $html = Blade::render('<x-fuente-chip tipo="norma" :validado="true" />');
        $this->assertStringContainsString('NASA-STD-3001 Vol. 1 Rev. C', $html);
        $this->assertStringNotContainsString('Example value', $html);
    }

    public function test_chip_accepts_a_custom_label(): void
    {
        $this->assertStringContainsString('My source', Blade::render('<x-fuente-chip tipo="guia" label="My source" />'));
    }

    public function test_guide_shows_chips_and_the_fallback_without_a_requirement(): void
    {
        $this->crewMember();
        $this->seed(RequisitosSeeder::class);

        Livewire::test(Guia::class)
            ->assertSee('Example value (unvalidated)')
            ->assertSee('NASA-STD-3001 Vol. 1 Rev. C')
            ->assertSee('Example threshold (no associated requirement)') // glucose, BMI and SpO2 have no requirement
            ->assertViewHas('tarjetas', fn ($t) => collect($t)->firstWhere('clave', 'perdida_osea')['fuentes'] !== []
                && collect($t)->firstWhere('clave', 'glucosa')['fuentes'] === []);
    }

    public function test_guide_loads_requirements_with_a_single_query(): void
    {
        $this->crewMember();
        $this->seed(RequisitosSeeder::class);

        $n = 0;
        DB::listen(function ($q) use (&$n) {
            if (str_contains($q->sql, 'requisitos')) {
                $n++;
            }
        });
        Livewire::test(Guia::class);
        $this->assertSame(1, $n);
    }

    public function test_guide_renders_with_an_empty_requirements_table(): void
    {
        $this->crewMember();
        Requisito::query()->delete();

        Livewire::test(Guia::class)
            ->assertSee('How space affects your body')
            ->assertSee('Example threshold (no associated requirement)')
            ->assertDontSee('Source: ');
    }

    public function test_guide_shows_the_mood_card_without_touching_the_14_markers(): void
    {
        $this->crewMember();
        $this->assertCount(14, config('indicadores.marcadores'));

        Livewire::test(Guia::class)
            ->assertSee('Mood')
            ->assertSee('MIN 50 · MAX 100 VAS 0-100')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 14)
            ->assertViewHas('extras', fn ($x) => count($x) === 1 && $x[0]['clave'] === 'animo');
    }

    public function test_mood_card_shows_the_crew_members_latest_value(): void
    {
        $this->crewMember();
        $a = TripulanteActual::obtener();
        Medicion::create(['astronauta_id' => $a->id, 'indicador' => 'animo', 'valores' => ['valor' => 20], 'medido_en' => now(), 'origen' => 'manual']);

        Livewire::test(Guia::class)
            ->assertViewHas('extras', fn ($x) => $x[0]['valor'] === '20' && $x[0]['estado'] === 'crit');
    }
}
