<?php

namespace Tests\Feature;

use App\Livewire\SelectorTripulante;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ShellTest extends TestCase
{
    use DatabaseTransactions;

    private function mision(int $n = 3): array
    {
        $m = Mision::create(['nombre' => 'Expedición ST', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(9), 'duracion_dias' => 30]);
        $a = [];
        foreach (['Ana Ruiz', 'Beto Gil', 'Carla Paz', 'Dani Sol'] as $i => $nombre) {
            if ($i >= $n) {
                break;
            }
            $a[] = Astronauta::create(['mision_id' => $m->id, 'nombre' => $nombre, 'mision' => 'ST', 'entorno' => 'estacion']);
        }

        return [$m, $a];
    }

    public function test_sin_sesion_devuelve_el_primero_de_la_ultima_mision(): void
    {
        [$m, $a] = $this->mision();
        $this->assertSame($m->id, TripulanteActual::mision()->id);
        $this->assertSame($a[0]->id, TripulanteActual::obtener()->id);
        $this->assertCount(3, TripulanteActual::tripulacion());
    }

    public function test_fijar_valida_pertenencia_a_la_mision(): void
    {
        [, $a] = $this->mision();
        TripulanteActual::fijar($a[1]->id);
        $this->assertSame($a[1]->id, TripulanteActual::obtener()->id);

        $otro = Astronauta::create(['nombre' => 'Sin misión', 'mision' => 'X', 'entorno' => 'estacion']);
        TripulanteActual::fijar($otro->id);
        $this->assertSame($a[1]->id, TripulanteActual::obtener()->id);

        session(['astronauta_id' => 999999]);
        $this->assertSame($a[0]->id, TripulanteActual::obtener()->id);
    }

    public function test_selector_cambia_de_tripulante_y_despacha_evento(): void
    {
        [, $a] = $this->mision();
        Livewire::test(SelectorTripulante::class)
            ->assertSee('TRIPULANTE 1/3')
            ->call('alternar')->assertSee('Beto Gil')->assertSee('PENDIENTE')
            ->call('elegir', $a[2]->id)
            ->assertDispatched('tripulante-cambiado')
            ->assertSet('abierto', false)
            ->assertSee('TRIPULANTE 3/3');
        $this->assertSame($a[2]->id, TripulanteActual::obtener()->id);
    }

    public function test_selector_compacto_pasa_al_siguiente_en_circulo(): void
    {
        [, $a] = $this->mision(2);
        Livewire::test(SelectorTripulante::class, ['variante' => 'compacta'])
            ->call('siguiente')->assertSet('actualId', $a[1]->id)
            ->call('siguiente')->assertSet('actualId', $a[0]->id);
    }

    public function test_layout_marca_la_ruta_activa_y_deshabilita_el_resto(): void
    {
        $this->mision();
        // Ruta de prueba con el nombre `panel` para no depender de que la pantalla Panel esté completa.
        app('router')->get('/__shell-test', fn () => view('layouts.app', ['slot' => '']))->name('panel');
        $html = $this->get('/__shell-test')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="nav-link is-activo"\s+aria-current="page"\s*>\s*<span class="icono">dashboard/', $html);
        $this->assertStringContainsString('aria-disabled="true" title="Próximamente"', $html);
        $this->assertStringContainsString('EXPEDICIÓN ST', $html);
    }

    public function test_encabezado_tolera_datos_nulos(): void
    {
        $html = $this->blade('<x-encabezado titulo-a="Panel" titulo-b="Tripulante X" />');
        $html->assertSee('Panel')->assertSee('ÓRBITA');
    }
}
