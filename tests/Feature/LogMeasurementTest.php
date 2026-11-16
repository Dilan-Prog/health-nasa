<?php

namespace Tests\Feature;

use App\Events\MedicionRegistrada;
use App\Livewire\RegistrarMedicion;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class LogMeasurementTest extends TestCase
{
    use DatabaseTransactions;

    /** Own mission and crew member: the test does not depend on the local database data (rolled back with the transaction). */
    protected function setUp(): void
    {
        parent::setUp();
        $mission = Mision::create(['nombre' => 'Mission RM '.uniqid(), 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);
        $a = Astronauta::create(['mision_id' => $mission->id, 'mision' => $mission->nombre, 'entorno' => 'estacion', 'nombre' => 'Test Crew Member']);
        session(['mision_id' => $mission->id, 'astronauta_id' => $a->id]);
    }

    private function clearFields($c)
    {
        foreach (array_keys(config('panel.indicadores')) as $k) {
            $c->set("campos.$k", '');
        }

        return $c;
    }

    public function test_hidden_and_opens_with_the_event(): void
    {
        Livewire::test(RegistrarMedicion::class)
            ->assertSet('abierto', false)->assertDontSee('role="dialog"', false)
            ->dispatch('abrir-registro', indicador: 'glucosa')
            ->assertSet('abierto', true)->assertSet('enfocar', 'glucosa')
            ->assertSee('role="dialog"', false)->assertSee('Log measurement')
            ->assertSee(TripulanteActual::obtener()->nombre);
    }

    public function test_preloads_the_last_value(): void
    {
        Medicion::create(['astronauta_id' => TripulanteActual::obtener()->id, 'indicador' => 'presion_arterial', 'valores' => ['sistolica' => 118, 'diastolica' => 76], 'medido_en' => now()->subHour(), 'origen' => 'manual']);
        $c = Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro');
        $this->assertCount(10, $c->get('campos'));
        $this->assertMatchesRegularExpression('#^\d+/\d+$#', $c->get('campos')['presion_arterial']);
    }

    public function test_impossible_value_shows_an_error_and_does_not_save(): void
    {
        $c = $this->clearFields(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $before = Medicion::count();
        $c->set('campos.temperatura', '80')
            ->assertSee('Value outside the possible range')
            ->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('reg-err-temperatura')
            ->call('guardar')->assertSet('abierto', true);
        $this->assertSame($before, Medicion::count());
    }

    public function test_live_badge_with_comma_and_minus_sign(): void
    {
        $c = $this->clearFields(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $c->set('campos.temperatura', '36,6')->assertSee('NORMAL')
            ->set('campos.temperatura', '37,8')->assertSee('CAUTION')
            ->set('campos.temperatura', '39,5')->assertSee('CRITICAL');
        $c->set('campos.temperatura', '')->set('campos.perdida_osea', "\u{2212}0,5")->assertSee('NORMAL');
    }

    public function test_saves_only_filled_fields_closes_and_dispatches(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = TripulanteActual::obtener();
        $c = $this->clearFields(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $before = Medicion::where('astronauta_id', $a->id)->count();
        $c->set('campos.presion_arterial', '128/84')->set('campos.perdida_osea', "\u{2212}1,6")->set('campos.frecuencia_cardiaca', '66')
            ->call('guardar')
            ->assertSet('abierto', false)
            ->assertDispatched('medicion-guardada')->assertDispatched('medicion-registrada', astronautaId: $a->id);

        $this->assertSame($before + 3, Medicion::where('astronauta_id', $a->id)->count());
        $bp = Medicion::where('astronauta_id', $a->id)->where('indicador', 'presion_arterial')->latest('id')->first();
        $this->assertSame(128.0, (float) $bp->valores['sistolica']);
        $this->assertSame('manual', $bp->origen);
        $this->assertNotNull($bp->evaluacion);
        $boneLoss = Medicion::where('astronauta_id', $a->id)->where('indicador', 'perdida_osea')->latest('id')->first();
        $this->assertSame(-1.6, (float) $boneLoss->valores['valor']);
    }

    public function test_empty_form_does_not_save(): void
    {
        $c = $this->clearFields(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $before = Medicion::count();
        $c->call('guardar')->assertSet('abierto', true)->assertSee('Enter at least one value');
        $this->assertSame($before, Medicion::count());
    }

    public function test_cancel_closes(): void
    {
        Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro')->call('cerrar')->assertSet('abierto', false);
    }
}
