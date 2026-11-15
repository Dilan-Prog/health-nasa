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

    public function test_todos_los_musculos_de_las_tres_vistas_son_interactivos_y_tienen_nombre(): void
    {
        $html = $this->render();

        $esperado = ['frontal' => 50, 'lateral' => 26, 'posterior' => 32];
        foreach ($esperado as $vista => $n) {
            $this->assertCount($n, MusculosCuerpo::POR_VISTA[$vista], $vista);
            $this->assertStringContainsString('data-vista="'.$vista.'"', $html);
            foreach (MusculosCuerpo::POR_VISTA[$vista] as $id) {
                $this->assertNotNull(MusculosCuerpo::nombre($id), "Sin nombre: {$id}");
                $this->assertStringContainsString('data-musculo="'.$id.'"', $html);
            }
        }
        $this->assertSame(108, substr_count($html, 'data-musculo="'));
        $this->assertSame(108, substr_count($html, 'role="button" tabindex="0"'));
        $this->assertCount(81, MusculosCuerpo::ids());
        $this->assertStringContainsString('aria-label="Pectoral mayor izquierdo"', $html);
        foreach (['Frontal', 'De costado', 'De atrás'] as $boton) {
            $this->assertStringContainsString('>'.$boton.'</button>', $html);
        }
    }

    public function test_un_id_presente_en_varias_vistas_se_resalta_en_todas(): void
    {
        $html = $this->render(['biceps_der']);

        // biceps_der existe en la vista frontal y en la lateral
        $this->assertSame(2, substr_count($html, 'is-activo'));
    }

    public function test_los_ids_de_mascara_son_unicos_por_instancia(): void
    {
        preg_match_all('/id="(sil-mask-[a-z0-9]+-(?:lateral|posterior))"/', $this->render().$this->render(), $m);

        $this->assertCount(4, $m[1]);
        $this->assertCount(4, array_unique($m[1]));
    }

    public function test_solo_los_activos_llevan_la_clase_y_los_desconocidos_se_ignoran(): void
    {
        $html = $this->render(['recto_abdominal_3_der', 'no_existe_der']);

        $this->assertSame(1, substr_count($html, 'is-activo'));
        $this->assertMatchesRegularExpression('/class="musculo is-activo"\s+data-musculo="recto_abdominal_3_der"/', $html);
        $this->assertMatchesRegularExpression('/class="musculo"\s+data-musculo="recto_abdominal_3_izq"/', $html);
        $this->assertStringContainsString('aria-label="Recto abdominal (porción 3) derecho, resaltado"', $html);
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
