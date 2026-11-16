<?php

namespace Tests\Feature;

use App\Livewire\SiluetaDemo;
use App\Support\MusculosCuerpo;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class BodySilhouetteTest extends TestCase
{
    private function render(array $active = []): string
    {
        return Blade::render('<x-silueta-corporal :activos="$a" />', ['a' => $active]);
    }

    public function test_all_muscles_in_the_three_views_are_interactive_and_named(): void
    {
        $html = $this->render();

        $expected = ['frontal' => 50, 'lateral' => 26, 'lateral_izq' => 26, 'posterior' => 32];
        foreach ($expected as $view => $n) {
            $this->assertCount($n, MusculosCuerpo::POR_VISTA[$view], $view);
            $this->assertStringContainsString('data-vista="'.$view.'"', $html);
            foreach (MusculosCuerpo::POR_VISTA[$view] as $id) {
                $this->assertNotNull(MusculosCuerpo::nombre($id), "No name: {$id}");
                $this->assertStringContainsString('data-musculo="'.$id.'"', $html);
            }
        }
        $this->assertSame(134, substr_count($html, 'data-musculo="'));
        $this->assertSame(134, substr_count($html, 'role="button" tabindex="0"'));
        $this->assertCount(92, MusculosCuerpo::ids());
        $this->assertStringContainsString('aria-label="'.MusculosCuerpo::nombreEn('pectoral_mayor_izq').'"', $html);
        foreach (MusculosCuerpo::VISTAS as $label) {
            $this->assertStringContainsString('>'.$label.'</button>', $html);
        }
    }

    public function test_an_id_present_in_several_views_is_highlighted_in_all(): void
    {
        $html = $this->render(['biceps_der']);

        // biceps_der exists in the front view and in the right side view
        $this->assertSame(2, substr_count($html, 'is-activo'));
        $this->assertSame(2, substr_count($this->render(['biceps_izq']), 'is-activo')); // front + left side (mirrored)
    }

    public function test_mask_ids_are_unique_per_instance(): void
    {
        preg_match_all('/id="(sil-mask-[a-z0-9]+-(?:lateral_izq|lateral|posterior))"/', $this->render().$this->render(), $m);

        $this->assertCount(6, $m[1]);
        $this->assertCount(6, array_unique($m[1]));
    }

    public function test_only_active_muscles_get_the_class_and_unknown_ones_are_ignored(): void
    {
        $html = $this->render(['recto_abdominal_3_der', 'no_existe_der']);

        $this->assertSame(1, substr_count($html, 'is-activo'));
        $this->assertMatchesRegularExpression('/class="musculo is-activo"\s+data-musculo="recto_abdominal_3_der"/', $html);
        $this->assertMatchesRegularExpression('/class="musculo"\s+data-musculo="recto_abdominal_3_izq"/', $html);
        $this->assertStringContainsString('aria-label="'.MusculosCuerpo::nombreEn('recto_abdominal_3_der').', highlighted"', $html);
    }

    public function test_paths_have_no_hardcoded_colors(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('#ECECEC', $html);
        $this->assertStringNotContainsString('#9CA3AF', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_an_id_with_unexpected_format_is_not_guessed(): void
    {
        $this->assertNull(MusculosCuerpo::nombre('frontal_pecho_pectoral_superior_izq'));
        $this->assertNull(MusculosCuerpo::nombre('biceps'));
        $this->assertNull(MusculosCuerpo::nombre('biceps_central'));
        $this->assertSame('Recto abdominal (porción 2) derecho', MusculosCuerpo::nombre('recto_abdominal_2_der')); // stored Spanish name is unchanged
        $this->assertNull(MusculosCuerpo::nombreEn('biceps_central'));
    }

    public function test_demo_updates_highlight_and_receives_click_event(): void
    {
        Livewire::test(SiluetaDemo::class)
            ->assertSeeHtml('class="musculo is-activo"')
            ->dispatch('musculo-seleccionado', id: 'biceps_izq')
            ->assertSet('seleccionado', 'biceps_izq')
            ->assertSee(MusculosCuerpo::nombreEn('biceps_izq'))
            ->set('ejemplo', 'b')
            ->assertSeeHtml('data-musculo="gemelo_medial_izq"');

        $this->get('/demo/silueta')->assertOk();
    }

    public function test_thumbnail_mode_renders_a_single_view_without_interaction(): void
    {
        $html = Blade::render('<x-silueta-corporal modo="miniatura" vista="posterior" :activos="$a" />', ['a' => ['biceps_izq', 'gluteo_mayor_der']]);

        $this->assertSame(1, substr_count($html, '<svg'));
        $this->assertStringContainsString('data-vista="posterior"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        foreach (['role="button"', 'tabindex', 'x-data', 'x-show', 'x-on:', 'data-nombre', 'aria-label', '<button', 'role="tooltip"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, $forbidden);
        }
        $this->assertSame(count(MusculosCuerpo::POR_VISTA['posterior']), substr_count($html, 'data-musculo="'));
        $this->assertStringContainsString('silueta-corporal--miniatura', $html);
        $this->assertSame(1, substr_count($html, 'is-activo')); // gluteo_mayor_der (biceps_izq is not in the posterior view)
    }

    public function test_thumbnail_highlights_the_active_muscles_of_its_view(): void
    {
        $html = Blade::render('<x-silueta-corporal modo="miniatura" vista="frontal" :activos="$a" />', ['a' => ['biceps_izq']]);

        $this->assertSame(1, substr_count($html, 'is-activo'));
        $this->assertSame(1, substr_count($html, '<svg'));
    }

    public function test_selector_false_hides_buttons_and_the_external_event_is_present(): void
    {
        $without = Blade::render('<x-silueta-corporal :selector="false" />');
        $with = Blade::render('<x-silueta-corporal />');

        $this->assertStringNotContainsString('<button', $without);
        $this->assertStringNotContainsString('silueta-vistas', $without);
        $this->assertStringContainsString('<button', $with);
        foreach ([$without, $with] as $html) {
            $this->assertStringContainsString('x-on:silueta-vista.window="externa($event.detail)"', $html);
            $this->assertStringContainsString('silueta-vista-cambiada', $html);
            $this->assertSame(134, substr_count($html, 'role="button" tabindex="0"'));
        }
    }
}
