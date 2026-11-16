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

/**
 * config/sistemas.php is the ONLY definition of the body systems. These tests keep
 * Indicators, Body systems and the per-system index from diverging again.
 */
class BodySystemsSingleSourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nine_document_sections_and_two_project_groups(): void
    {
        $groups = collect(config('sistemas.grupos'));

        $this->assertCount(9, $groups->where('origen', 'documento'));
        $this->assertSame(['metabolico', 'radiacion'], $groups->where('origen', 'proyecto')->pluck('clave')->values()->all());
        $this->assertSame(['1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7', '1.8', '1.9'],
            $groups->where('origen', 'documento')->pluck('seccion')->sort()->values()->all());
        $this->assertSame($groups->pluck('clave')->all(), $groups->pluck('clave')->unique()->values()->all(), 'unique keys');
    }

    public function test_indicator_list_is_derived_from_the_single_source(): void
    {
        $expected = collect(config('sistemas.grupos'))->map(fn ($g) => [$g['titulo'], $g['ic']])->all();

        $this->assertSame($expected, config('indicadores.sistemas'));
    }

    public function test_each_marker_points_to_the_group_that_contains_it(): void
    {
        $groups = config('sistemas.grupos');
        foreach (config('indicadores.marcadores') as $clave => $m) {
            $this->assertNotNull($m['sys'], "$clave does not belong to any system");
            $this->assertContains($clave, $groups[$m['sys']]['marcadores'], "$clave points to a group that does not contain it");
        }
    }

    public function test_threshold_system_matches_each_indicators_group(): void
    {
        $byKey = [];
        foreach (config('sistemas.grupos') as $g) {
            foreach (array_merge($g['marcadores'], $g['extra'] ?? []) as $clave) {
                $byKey[$clave] = $g['clave'];
            }
        }

        foreach (config('umbrales.indicadores') as $clave => $i) {
            $this->assertSame($byKey[$clave] ?? null, $i['sistema'] ?? null, "$clave: system differs between thresholds and config/sistemas.php");
        }
        $this->assertSame(array_column(config('sistemas.grupos'), 'clave'), config('umbrales.sistemas'));
        // The case that used to contradict itself: temperature was "immune" in thresholds and "Metabolism" on the screens.
        $this->assertSame('metabolico', config('umbrales.indicadores.temperatura.sistema'));
    }

    public function test_system_without_indicators_declares_what_is_not_yet_measured(): void
    {
        $empty = collect(config('sistemas.grupos'))->filter(fn ($g) => $g['marcadores'] === [] && empty($g['extra']));

        $this->assertSame(['sensorial', 'inmunologico', 'hematologico'], $empty->pluck('clave')->values()->all());
        foreach ($empty as $g) {
            $this->assertNotEmpty($g['pendiente'] ?? '', $g['clave'].' must state what is not yet measured');
        }
    }

    public function test_screen_marks_empty_systems_as_no_coverage_not_normal(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);

        $title = fn (string $key) => collect(config('sistemas.grupos'))->firstWhere('clave', $key)['titulo'];

        Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($cards) use ($title) {
            $byTitle = collect($cards)->keyBy('titulo');
            foreach (['sensorial', 'inmunologico', 'hematologico'] as $key) {
                $t = $title($key);
                if ($byTitle[$t]['estado'] !== 'sin' || $byTitle[$t]['estado_txt'] !== 'NO COVERAGE YET' || $byTitle[$t]['filas'] !== []) {
                    return false;
                }
            }
            $respiratory = $byTitle[$title('respiratorio')];

            return $respiratory['estado'] !== 'sin' && count($respiratory['filas']) === 1;
        });
    }
}
