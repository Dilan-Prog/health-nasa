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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class PeligrosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_config_tiene_5_peligros_y_17_riesgos_agrupados(): void
    {
        $this->assertCount(5, config('peligros.peligros'));
        $grupos = config('peligros.grupos');
        $this->assertSame([11, 5, 1], [count($grupos['fisiologia']['riesgos']), count($grupos['conducta']['riesgos']), count($grupos['radiacion']['riesgos'])]);
        foreach ($grupos as $g) {
            foreach ($g['riesgos'] as [$n, $estado, $frase]) {
                $this->assertContains($estado, ['cubierto', 'parcial', 'sin']);
                $this->assertNotSame('', $frase);
            }
        }
    }

    private function verifica($html): void
    {
        $html->assertSee('Peligros y riesgos')
            ->assertSee('No actúan de forma aislada: pueden combinarse')
            ->assertSee('valor de ejemplo / evaluación interna')
            ->assertSee('Cánceres inducidos por radiación')
            ->assertSee('Sin cobertura');
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

    public function test_la_guia_muestra_los_peligros_y_los_17_riesgos(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);

        $this->verifica(Livewire::test(Guia::class));
    }

    public function test_la_mision_muestra_los_peligros_y_los_17_riesgos(): void
    {
        $this->seed(ExpedicionSeeder::class);
        session(['mision_id' => Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->id]);

        $this->verifica(Livewire::test(ContextoMision::class));
    }
}
