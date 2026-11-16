<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Livewire\Sistemas;
use App\Models\Mision;
use App\Services\Tripulacion\AltaTripulante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Hoja de ruta "Próximamente": solo texto atenuado, sin enlaces ni campos. */
class ProximamenteTest extends TestCase
{
    use DatabaseTransactions;

    private function items(): array
    {
        return config('proximamente.items');
    }

    public function test_todas_apuntan_a_un_sistema_existente_y_con_fuente(): void
    {
        $claves = array_column(config('sistemas.grupos'), 'clave');
        $this->assertCount(15, $this->items());
        foreach ($this->items() as $p) {
            $this->assertContains($p['sistema'], $claves, $p['nombre']);
            $this->assertFileExists(base_path($p['fuente']), $p['nombre']);
        }
    }

    public function test_guia_las_muestra_atenuadas_sin_enlaces_ni_campos(): void
    {
        $html = Livewire::test(Guia::class)->html();
        $this->assertGreaterThanOrEqual(1, preg_match('#<section class="prox".*?</section>#s', $html, $m));
        $seccion = $m[0];
        preg_match_all('#<li class="prox-item".*?</li>#s', $seccion, $lis);
        $this->assertCount(count($this->items()), $lis[0]);
        foreach ($this->items() as $i => $p) {
            $li = $lis[0][$i];
            $this->assertStringContainsString(e($p['nombre']), $li);
            $this->assertStringContainsString('Próximamente', $li);
            $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $li);
        }
        $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $seccion);
    }

    public function test_sistemas_muestra_la_linea_en_la_tarjeta_correcta(): void
    {
        $m = Mision::create(['nombre' => 'Test Prox '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now()->toDateString(), 'duracion_dias' => 100]);
        $a = app(AltaTripulante::class)->registrar([
            'nombre' => 'Tripulante Prox', 'fecha_nacimiento' => now()->subYears(35)->toDateString(), 'sexo' => 'masculino',
            'rol' => 'Piloto', 'agencia' => 'AEM', 'nacionalidad' => 'Mexicana', 'altura_m' => 1.8, 'peso_kg' => 80, 'grupo_sanguineo' => 'A+',
            'aptitud_vuelo' => 'apto',
        ], $m);
        session(['mision_id' => $m->id, 'astronauta_id' => $a->id]);

        $html = Livewire::test(Sistemas::class)->html();
        preg_match_all('#<p class="sis-pendiente prox-linea"[^>]*>.*?</p>#s', $html, $lineas);
        $nombres = array_column($this->items(), 'nombre');
        $this->assertNotEmpty($lineas[0]);
        $todo = implode(' ', $lineas[0]);
        foreach ($nombres as $n) {
            $this->assertStringContainsString(e($n), $todo);
        }
        foreach ($lineas[0] as $l) {
            $this->assertStringContainsString('Próximamente', $l);
            $this->assertDoesNotMatchRegularExpression('#<(a|button|input|select|textarea)\b#i', $l);
        }
    }
}
