<?php

namespace Tests\Feature;

use App\Events\MedicionRegistrada;
use App\Livewire\Registrar;
use App\Models\Medicion;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/** Estado de ánimo como escala VAS 0-100 en Registrar. DatabaseTransactions: nunca borra la BD local. */
class RegistrarAnimoTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulante()
    {
        Event::fake([MedicionRegistrada::class]);
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $t = $m->tripulantes()->orderBy('id')->get()->first();
        session(['astronauta_id' => $t->id]);

        return $t;
    }

    public function test_la_config_define_animo_como_vas_0_100(): void
    {
        $c = config('umbrales.indicadores.animo.componentes.valor');
        $this->assertSame([0, 100], $c['valido']);
        $this->assertSame('VAS 0-100', config('umbrales.indicadores.animo.unidad'));
    }

    public function test_la_pantalla_ofrece_registrar_el_animo_y_muestra_el_deslizador_al_activarlo(): void
    {
        $this->tripulante();
        $c = Livewire::test(Registrar::class)
            ->assertSee('Estado de ánimo')->assertSee('Registrar mi ánimo')
            ->assertDontSeeHtml('type="range"');
        $c->call('alternarAnimo')
            ->assertSet('campos.animo', '50')
            ->assertSeeHtml('type="range"')->assertSeeHtml('min="0"')->assertSeeHtml('max="100"')
            ->assertSee('1 medición');
        $c->call('alternarAnimo')->assertSet('campos.animo', '')->assertDontSeeHtml('type="range"');
    }

    public function test_estados_en_vivo_segun_los_cortes_de_ejemplo(): void
    {
        $this->tripulante();
        $c = Livewire::test(Registrar::class);
        $c->set('campos.animo', '80')->assertSee('Dentro de lo habitual');
        $c->set('campos.animo', '30')->assertSee('Por debajo de lo habitual');
        $c->set('campos.animo', '10')->assertSee('Muy bajo');
        $c->set('campos.animo', '140')->assertSee('Valor fuera de lo posible');
    }

    public function test_guardar_solo_el_animo_persiste_la_medicion_y_aparece_en_el_resumen(): void
    {
        $a = $this->tripulante();
        Livewire::test(Registrar::class)
            ->set('campos.animo', '72')
            ->call('guardar')
            ->assertSee('Estado de ánimo')->assertSee('72');
        $m = Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->latest('id')->first();
        $this->assertNotNull($m);
        $this->assertEquals(72, $m->valores['valor']);
    }

    public function test_un_animo_invalido_bloquea_el_guardado(): void
    {
        $a = $this->tripulante();
        $antes = Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->count();
        Livewire::test(Registrar::class)->set('campos.animo', '250')->call('guardar')->assertSet('guardado', null);
        $this->assertSame($antes, Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->count());
    }
}
