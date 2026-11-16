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

/** Mood as a VAS 0-100 scale in the Log screen. DatabaseTransactions: never deletes the local database. */
class LogMoodTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember()
    {
        Event::fake([MedicionRegistrada::class]);
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $t = $m->tripulantes()->orderBy('id')->get()->first();
        session(['astronauta_id' => $t->id]);

        return $t;
    }

    public function test_config_defines_mood_as_vas_0_100(): void
    {
        $c = config('umbrales.indicadores.animo.componentes.valor');
        $this->assertSame([0, 100], $c['valido']);
        $this->assertSame('VAS 0-100', config('umbrales.indicadores.animo.unidad'));
    }

    public function test_screen_offers_logging_mood_and_shows_the_slider_when_enabled(): void
    {
        $this->crewMember();
        $c = Livewire::test(Registrar::class)
            ->assertSee('Mood')->assertSee('Log my mood')
            ->assertDontSeeHtml('type="range"');
        $c->call('alternarAnimo')
            ->assertSet('campos.animo', '50')
            ->assertSeeHtml('type="range"')->assertSeeHtml('min="0"')->assertSeeHtml('max="100"')
            ->assertSee('1 measurement');
        $c->call('alternarAnimo')->assertSet('campos.animo', '')->assertDontSeeHtml('type="range"');
    }

    public function test_live_states_according_to_the_example_cutoffs(): void
    {
        $this->crewMember();
        $c = Livewire::test(Registrar::class);
        $c->set('campos.animo', '80')->assertSee('Within the usual range');
        $c->set('campos.animo', '30')->assertSee('Below the usual range');
        $c->set('campos.animo', '10')->assertSee('Very low');
        $c->set('campos.animo', '140')->assertSee('Value outside the possible range');
    }

    public function test_saving_only_the_mood_persists_the_measurement_and_shows_in_the_summary(): void
    {
        $a = $this->crewMember();
        Livewire::test(Registrar::class)
            ->set('campos.animo', '72')
            ->call('guardar')
            ->assertSee('Mood')->assertSee('72');
        $m = Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->latest('id')->first();
        $this->assertNotNull($m);
        $this->assertEquals(72, $m->valores['valor']);
    }

    public function test_an_invalid_mood_blocks_saving(): void
    {
        $a = $this->crewMember();
        $before = Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->count();
        Livewire::test(Registrar::class)->set('campos.animo', '250')->call('guardar')->assertSet('guardado', null);
        $this->assertSame($before, Medicion::where('astronauta_id', $a->id)->where('indicador', 'animo')->count());
    }
}
