<?php

namespace Tests\Feature;

use App\Livewire\Panel;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class PanelMisionTest extends TestCase
{
    use DatabaseTransactions;

    private function mision(string $nombre, int $tripulantes): Mision
    {
        $m = Mision::create(['nombre' => $nombre, 'entorno' => 'nave']);
        for ($i = 1; $i <= $tripulantes; $i++) {
            Astronauta::create(['mision_id' => $m->id, 'nombre' => "{$nombre} T{$i}", 'mision' => $nombre, 'entorno' => 'nave']);
        }

        return $m;
    }

    public function test_muestra_solo_la_tripulacion_de_la_mision_elegida(): void
    {
        $a = $this->mision('PM-A', 2);
        $b = $this->mision('PM-B', 3);

        Livewire::test(Panel::class)
            ->set('misionId', $a->id)
            ->assertSee('PM-A T1')->assertSee('PM-A T2')->assertDontSee('PM-B T1')
            ->set('misionId', $b->id)
            ->assertSee('PM-B T3')->assertDontSee('PM-A T1')
            ->assertSet('astronautaId', $b->tripulantes()->orderBy('id')->value('id'));
    }

    public function test_seleccionar_cambia_de_tripulante_y_rechaza_uno_de_otra_mision(): void
    {
        $a = $this->mision('PM-C', 2);
        $otra = $this->mision('PM-D', 1);
        $segundo = $a->tripulantes()->orderBy('id')->skip(1)->first();

        Livewire::test(Panel::class)
            ->set('misionId', $a->id)
            ->call('seleccionar', $segundo->id)
            ->assertSet('astronautaId', $segundo->id)
            ->call('seleccionar', $otra->tripulantes()->first()->id)
            ->assertSet('astronautaId', $segundo->id);
    }

    public function test_resumen_marca_el_peor_estado_de_cada_tripulante(): void
    {
        $m = $this->mision('PM-E', 2);
        [$sano, $grave] = $m->tripulantes()->orderBy('id')->get()->all();
        $proc = ProcesadorMediciones::make();
        $proc->registrar($sano, 'frecuencia_cardiaca', ['valor' => 72], 'manual', null, false);
        $proc->registrar($grave, 'frecuencia_cardiaca', ['valor' => 150], 'manual', null, false);

        $html = Livewire::test(Panel::class)->set('misionId', $m->id)->html();

        $this->assertStringContainsString('Normal', $html);
        $this->assertStringContainsString('Crítico', $html);
        $this->assertStringContainsString('1 crítico', $html);
    }
}
