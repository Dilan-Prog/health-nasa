<?php

namespace Tests\Feature;

use App\Livewire\SiluetaDemo;
use App\Support\MusculosCuerpo;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class SiluetaCorporalTest extends TestCase
{
    private function render(array $activos = []): string
    {
        return Blade::render('<x-silueta-corporal :activos="$a" />', ['a' => $activos]);
    }

    public function test_todos_los_musculos_son_interactivos_y_tienen_nombre(): void
    {
        $html = $this->render();

        $this->assertCount(50, MusculosCuerpo::IDS);
        foreach (MusculosCuerpo::IDS as $id) {
            $this->assertNotNull(MusculosCuerpo::nombre($id), "Sin nombre: {$id}");
            $this->assertStringContainsString('data-musculo="'.$id.'"', $html);
        }
        $this->assertSame(50, substr_count($html, 'data-musculo="'));
        $this->assertSame(50, substr_count($html, 'role="button" tabindex="0"'));
        $this->assertStringContainsString('aria-label="Pectoral mayor izquierdo"', $html);
    }

    public function test_solo_los_activos_llevan_la_clase_y_los_desconocidos_se_ignoran(): void
    {
        $html = $this->render(['biceps_izq', 'recto_abdominal_3_der', 'no_existe_der']);

        $this->assertSame(2, substr_count($html, 'is-activo'));
        $this->assertMatchesRegularExpression('/class="musculo is-activo"\s+data-musculo="biceps_izq"/', $html);
        $this->assertMatchesRegularExpression('/class="musculo"\s+data-musculo="biceps_der"/', $html);
        $this->assertStringContainsString('aria-label="Bíceps izquierdo, resaltado"', $html);
    }

    public function test_no_hay_colores_fijos_en_los_paths(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString('#ECECEC', $html);
        $this->assertStringNotContainsString('#9CA3AF', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_un_id_con_formato_inesperado_no_se_adivina(): void
    {
        $this->assertNull(MusculosCuerpo::nombre('frontal_pecho_pectoral_superior_izq'));
        $this->assertNull(MusculosCuerpo::nombre('biceps'));
        $this->assertNull(MusculosCuerpo::nombre('biceps_central'));
        $this->assertSame('Recto abdominal (porción 2) derecho', MusculosCuerpo::nombre('recto_abdominal_2_der'));
    }

    public function test_ejemplo_actualiza_el_resaltado_y_recibe_el_evento_de_clic(): void
    {
        Livewire::test(SiluetaDemo::class)
            ->assertSeeHtml('class="musculo is-activo"')
            ->dispatch('musculo-seleccionado', id: 'biceps_izq')
            ->assertSet('seleccionado', 'biceps_izq')
            ->assertSee('Bíceps izquierdo')
            ->set('ejemplo', 'b')
            ->assertSeeHtml('data-musculo="gemelo_medial_izq"');

        $this->get('/demo/silueta')->assertOk();
    }
}
