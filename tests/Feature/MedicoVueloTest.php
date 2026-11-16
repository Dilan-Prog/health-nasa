<?php

namespace Tests\Feature;

use App\Livewire\MedicoVuelo;
use App\Models\Astronauta;
use App\Models\ConsultaTierra;
use App\Models\Mision;
use App\Services\Salud\ConsultasTierra;
use App\Services\Salud\ProcesadorMediciones;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class MedicoVueloTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        $this->a = Astronauta::create(['nombre' => 'Medico Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $mision->id]);
        TripulanteActual::fijar($this->a->id);
        Cache::forget('tierra.enlace');
    }

    protected function tearDown(): void
    {
        Cache::forget('tierra.enlace');
        parent::tearDown();
    }

    private function critico(Astronauta $a, float $spo2 = 85): void
    {
        ProcesadorMediciones::make()->registrar($a, 'saturacion_o2', ['valor' => $spo2], 'simulado');
    }

    public function test_la_ruta_responde_y_el_menu_apunta_a_ella(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id, 'astronauta_id' => $this->a->id])
            ->get('/medico-de-vuelo')->assertOk()->assertSee('Orienta, no diagnostica')->assertSee('/medico-de-vuelo');
    }

    public function test_sin_alertas_muestra_el_estado_tranquilizador(): void
    {
        Livewire::test(MedicoVuelo::class)
            ->assertSee('Todo en rango hoy')
            ->assertSee('Aún no has enviado consultas');
    }

    public function test_muestra_la_tarjeta_de_orientacion_con_su_sello(): void
    {
        $this->critico($this->a, 80);
        Livewire::test(MedicoVuelo::class)
            ->assertDontSee('Todo en rango hoy')
            ->assertViewHas('tarjetas', fn ($t) => count($t) >= 1)
            ->assertSee('QUÉ HACER AHORA')->assertSee('Protocolo fijo verificado');
    }

    public function test_la_emergencia_con_spo2_85_muestra_banner_y_boton_de_contacto(): void
    {
        $this->critico($this->a, 85);
        Livewire::test(MedicoVuelo::class)
            ->assertSee('REGLA DE EMERGENCIA')
            ->assertSee('Contactar a tierra ahora')
            ->assertViewHas('emergencia', fn ($e) => $e && $e['kind'] === 'emerg');
    }

    public function test_enviar_mensaje_crea_una_consulta(): void
    {
        $this->critico($this->a, 85);
        Livewire::test(MedicoVuelo::class)
            ->set('nota', 'Revisión del valor')
            ->call('enviar', 'msg')
            ->assertSet('error', null)
            ->assertSee('Mensaje · ');
        $c = ConsultaTierra::where('astronauta_id', $this->a->id)->first();
        $this->assertNotNull($c);
        $this->assertSame('msg', $c->tipo);
        $this->assertContains('saturacion_o2', $c->indicadores);
        $this->assertSame('Revisión del valor', $c->nota);
    }

    public function test_solicitar_videoconsulta_crea_una_consulta_de_video(): void
    {
        $this->critico($this->a, 85);
        Livewire::test(MedicoVuelo::class)->call('enviar', 'video')->assertSet('error', null);
        $this->assertSame('video', ConsultaTierra::where('astronauta_id', $this->a->id)->value('tipo'));
    }

    public function test_sin_seleccion_muestra_un_error_y_no_envia(): void
    {
        // Tripulante sin mediciones: no hay nada que compartir.
        Livewire::test(MedicoVuelo::class)
            ->call('enviar', 'msg')
            ->assertSee('Elige al menos una alerta');
        $this->assertSame(0, ConsultaTierra::where('astronauta_id', $this->a->id)->count());
    }

    public function test_votar_registra_y_retira_la_opinion(): void
    {
        $c = Livewire::test(MedicoVuelo::class)->call('votar', 'saturacion_o2', 'up');
        $this->assertSame('up', ConsultasTierra::voto($this->a, 'saturacion_o2'));
        $c->assertSet('gracias.saturacion_o2', 'up')->call('votar', 'saturacion_o2', 'up');
        $this->assertNull(ConsultasTierra::voto($this->a, 'saturacion_o2'));
    }

    public function test_anadir_al_mensaje_marca_el_indicador(): void
    {
        $this->critico($this->a, 85);
        Livewire::test(MedicoVuelo::class)->call('anadir', 'saturacion_o2')
            ->assertSet('anadidas.saturacion_o2', true)
            ->assertSet('marcados.saturacion_o2', true);
    }

    public function test_sin_enlace_la_consulta_queda_en_cola_y_se_puede_reintentar(): void
    {
        $this->critico($this->a, 85);
        Cache::put('tierra.enlace', false);

        $t = Livewire::test(MedicoVuelo::class)->call('enviar', 'msg');
        $t->assertSee('Sin enlace con tierra')->assertSee('Reintentar');
        $c = ConsultaTierra::where('astronauta_id', $this->a->id)->firstOrFail();
        $this->assertTrue($c->fallida);

        $t->call('reintentar', $c->id)->assertSet('anuncio', 'Sigue sin enlace. Se enviará automáticamente al recuperarlo.');
        $this->assertTrue($c->fresh()->fallida);

        Cache::put('tierra.enlace', true);
        $t->call('reintentar', $c->id)->assertSet('anuncio', 'Envío reintentado. El enlace está activo.');
        $this->assertFalse($c->fresh()->fallida);
    }
}
