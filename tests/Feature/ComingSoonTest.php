<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Livewire\Sistemas;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** "Coming soon" roadmap: dimmed text only, with no links or fields. */
class ComingSoonTest extends TestCase
{
    use DatabaseTransactions;

    private function items(): array
    {
        return config('proximamente.items');
    }

    public function test_all_point_to_an_existing_system_and_have_a_source(): void
    {
        $keys = array_column(config('sistemas.grupos'), 'clave');
        $this->assertCount(15, $this->items());
        foreach ($this->items() as $p) {
            $this->assertContains($p['sistema'], $keys, $p['nombre']);
            $this->assertFileExists(base_path($p['fuente']), $p['nombre']);
        }
    }

    public function test_platform_has_name_description_and_status_without_requiring_a_system(): void
    {
        $platform = config('proximamente.plataforma');
        $this->assertSame(['Local AI with Ollama', 'Orchestration with n8n'], array_column($platform, 'nombre'));
        foreach ($platform as $p) {
            $this->assertSame('proximamente', $p['estado']);
            $this->assertStringStartsWith('Coming soon:', $p['descripcion']);
        }
        $html = Livewire::test(Guia::class)->html();
        $this->assertMatchesRegularExpression('#<section class="prox prox-plataforma".*?</section>#s', $html);
        $this->assertStringContainsString('Local AI with Ollama', $html);
        $this->assertStringContainsString('Orchestration with n8n', $html);
    }

    public function test_guide_shows_them_dimmed_without_links_or_fields(): void
    {
        $html = Livewire::test(Guia::class)->html();
        $this->assertGreaterThanOrEqual(1, preg_match('#<section class="prox".*?</section>#s', $html, $m));
        $section = $m[0];
        preg_match_all('#<li class="prox-item".*?</li>#s', $section, $lis);
        $this->assertCount(count($this->items()), $lis[0]);
        foreach ($this->items() as $i => $p) {
            $li = $lis[0][$i];
            $this->assertStringContainsString(e($p['nombre']), $li);
            $this->assertStringContainsString('Coming soon', $li);
            $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $li);
        }
        $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $section);
    }

    public function test_body_systems_shows_the_line_on_the_right_card(): void
    {
        $m = Mision::create(['nombre' => 'Test Prox '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $a = app(AltaTripulante::class)->registrar([
            'nombre' => 'Crew Prox', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'masculino',
            'rol' => 'Piloto', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana', 'altura_m' => 1.8, 'peso_kg' => 80, 'grupo_sanguineo' => 'A+',
            'aptitud_vuelo' => 'apto',
        ], $m);
        session(['mision_id' => $m->id, 'astronauta_id' => $a->id]);

        $html = Livewire::test(Sistemas::class)->html();
        preg_match_all('#<p class="sis-pendiente prox-linea"[^>]*>.*?</p>#s', $html, $lines);
        $names = array_column($this->items(), 'nombre');
        $this->assertNotEmpty($lines[0]);
        $all = implode(' ', $lines[0]);
        foreach ($names as $n) {
            $this->assertStringContainsString(e($n), $all);
        }
        foreach ($lines[0] as $l) {
            $this->assertStringContainsString('Coming soon', $l);
            $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $l);
        }
    }
}
