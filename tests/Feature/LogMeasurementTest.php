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

class RegistrarMedicionTest extends TestCase
{
    use DatabaseTransactions;

    /** Misión y tripulante propios: el test no depende de los datos de la BD local (se revierten con la transacción). */
    protected function setUp(): void
    {
        parent::setUp();
        $mision = Mision::create(['nombre' => 'Misión RM '.uniqid(), 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);
        $a = Astronauta::create(['mision_id' => $mision->id, 'mision' => $mision->nombre, 'entorno' => 'estacion', 'nombre' => 'Tripulante Prueba']);
        session(['mision_id' => $mision->id, 'astronauta_id' => $a->id]);
    }

    private function limpiar($c)
    {
        foreach (array_keys(config('panel.indicadores')) as $k) {
            $c->set("campos.$k", '');
        }

        return $c;
    }

    public function test_oculto_y_se_abre_con_el_evento(): void
    {
        Livewire::test(RegistrarMedicion::class)
            ->assertSet('abierto', false)->assertDontSee('role="dialog"', false)
            ->dispatch('abrir-registro', indicador: 'glucosa')
            ->assertSet('abierto', true)->assertSet('enfocar', 'glucosa')
            ->assertSee('role="dialog"', false)->assertSee('Registrar medición')
            ->assertSee(TripulanteActual::obtener()->nombre);
    }

    public function test_precarga_el_ultimo_valor(): void
    {
        Medicion::create(['astronauta_id' => TripulanteActual::obtener()->id, 'indicador' => 'presion_arterial', 'valores' => ['sistolica' => 118, 'diastolica' => 76], 'medido_en' => now()->subHour(), 'origen' => 'manual']);
        $c = Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro');
        $this->assertCount(10, $c->get('campos'));
        $this->assertMatchesRegularExpression('#^\d+/\d+$#', $c->get('campos')['presion_arterial']);
    }

    public function test_valor_imposible_muestra_error_y_no_guarda(): void
    {
        $c = $this->limpiar(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $antes = Medicion::count();
        $c->set('campos.temperatura', '80')
            ->assertSee('Valor fuera de lo posible, revisa la medición')
            ->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('reg-err-temperatura')
            ->call('guardar')->assertSet('abierto', true);
        $this->assertSame($antes, Medicion::count());
    }

    public function test_insignia_en_vivo_con_coma_y_signo_menos(): void
    {
        $c = $this->limpiar(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $c->set('campos.temperatura', '36,6')->assertSee('BIEN')
            ->set('campos.temperatura', '37,8')->assertSee('REVISAR')
            ->set('campos.temperatura', '39,5')->assertSee('CRÍTICO');
        $c->set('campos.temperatura', '')->set('campos.perdida_osea', "\u{2212}0,5")->assertSee('BIEN');
    }

    public function test_guarda_solo_campos_llenos_cierra_y_despacha(): void
    {
        Event::fake([MedicionRegistrada::class]);
        $a = TripulanteActual::obtener();
        $c = $this->limpiar(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $antes = Medicion::where('astronauta_id', $a->id)->count();
        $c->set('campos.presion_arterial', '128/84')->set('campos.perdida_osea', "\u{2212}1,6")->set('campos.frecuencia_cardiaca', '66')
            ->call('guardar')
            ->assertSet('abierto', false)
            ->assertDispatched('medicion-guardada')->assertDispatched('medicion-registrada', astronautaId: $a->id);

        $this->assertSame($antes + 3, Medicion::where('astronauta_id', $a->id)->count());
        $pa = Medicion::where('astronauta_id', $a->id)->where('indicador', 'presion_arterial')->latest('id')->first();
        $this->assertSame(128.0, (float) $pa->valores['sistolica']);
        $this->assertSame('manual', $pa->origen);
        $this->assertNotNull($pa->evaluacion);
        $po = Medicion::where('astronauta_id', $a->id)->where('indicador', 'perdida_osea')->latest('id')->first();
        $this->assertSame(-1.6, (float) $po->valores['valor']);
    }

    public function test_formulario_vacio_no_guarda(): void
    {
        $c = $this->limpiar(Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro'));
        $antes = Medicion::count();
        $c->call('guardar')->assertSet('abierto', true)->assertSee('Escribe al menos un valor');
        $this->assertSame($antes, Medicion::count());
    }

    public function test_cancelar_cierra(): void
    {
        Livewire::test(RegistrarMedicion::class)->dispatch('abrir-registro')->call('cerrar')->assertSet('abierto', false);
    }
}
