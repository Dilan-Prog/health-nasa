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

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database data. */
class FlightSurgeonTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        $this->a = Astronauta::create(['nombre' => 'Flight Surgeon Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $mission->id]);
        TripulanteActual::fijar($this->a->id);
        Cache::forget('tierra.enlace');
    }

    protected function tearDown(): void
    {
        Cache::forget('tierra.enlace');
        parent::tearDown();
    }

    private function critical(Astronauta $a, float $spo2 = 85): void
    {
        ProcesadorMediciones::make()->registrar($a, 'saturacion_o2', ['valor' => $spo2], 'simulado');
    }

    public function test_route_responds_and_the_menu_points_to_it(): void
    {
        $this->withSession(['mision_id' => $this->a->mision_id, 'astronauta_id' => $this->a->id])
            ->get('/medico-de-vuelo')->assertOk()->assertSee('Guidance only, not a diagnosis')->assertSee('/medico-de-vuelo');
    }

    public function test_without_alerts_shows_the_reassuring_state(): void
    {
        Livewire::test(MedicoVuelo::class)
            ->assertSee('All in range today')
            ->assertSee('You have not sent any consultations');
    }

    public function test_shows_the_guidance_card_with_its_seal(): void
    {
        $this->critical($this->a, 80);
        Livewire::test(MedicoVuelo::class)
            ->assertDontSee('All in range today')
            ->assertViewHas('tarjetas', fn ($t) => count($t) >= 1)
            ->assertSee('WHAT TO DO NOW')->assertSee('Fixed protocol verified');
    }

    public function test_emergency_with_spo2_85_shows_banner_and_contact_button(): void
    {
        $this->critical($this->a, 85);
        Livewire::test(MedicoVuelo::class)
            ->assertSee('EMERGENCY RULE')
            ->assertSee('Contact the ground now')
            ->assertViewHas('emergencia', fn ($e) => $e && $e['kind'] === 'emerg');
    }

    public function test_sending_a_message_creates_a_consultation(): void
    {
        $this->critical($this->a, 85);
        Livewire::test(MedicoVuelo::class)
            ->set('nota', 'Value review')
            ->call('enviar', 'msg')
            ->assertSet('error', null)
            ->assertSee('Message · ');
        $c = ConsultaTierra::where('astronauta_id', $this->a->id)->first();
        $this->assertNotNull($c);
        $this->assertSame('msg', $c->tipo);
        $this->assertContains('saturacion_o2', $c->indicadores);
        $this->assertSame('Value review', $c->nota);
    }

    public function test_requesting_a_video_consultation_creates_a_video_consultation(): void
    {
        $this->critical($this->a, 85);
        Livewire::test(MedicoVuelo::class)->call('enviar', 'video')->assertSet('error', null);
        $this->assertSame('video', ConsultaTierra::where('astronauta_id', $this->a->id)->value('tipo'));
    }

    public function test_without_a_selection_shows_an_error_and_does_not_send(): void
    {
        // Crew member without measurements: there is nothing to share.
        Livewire::test(MedicoVuelo::class)
            ->call('enviar', 'msg')
            ->assertSee('Choose at least one alert');
        $this->assertSame(0, ConsultaTierra::where('astronauta_id', $this->a->id)->count());
    }

    public function test_voting_records_and_withdraws_the_opinion(): void
    {
        $c = Livewire::test(MedicoVuelo::class)->call('votar', 'saturacion_o2', 'up');
        $this->assertSame('up', ConsultasTierra::voto($this->a, 'saturacion_o2'));
        $c->assertSet('gracias.saturacion_o2', 'up')->call('votar', 'saturacion_o2', 'up');
        $this->assertNull(ConsultasTierra::voto($this->a, 'saturacion_o2'));
    }

    public function test_adding_to_the_message_marks_the_indicator(): void
    {
        $this->critical($this->a, 85);
        Livewire::test(MedicoVuelo::class)->call('anadir', 'saturacion_o2')
            ->assertSet('anadidas.saturacion_o2', true)
            ->assertSet('marcados.saturacion_o2', true);
    }

    public function test_without_a_link_the_consultation_is_queued_and_can_be_retried(): void
    {
        $this->critical($this->a, 85);
        Cache::put('tierra.enlace', false);

        $t = Livewire::test(MedicoVuelo::class)->call('enviar', 'msg');
        $t->assertSee('No ground link')->assertSee('Retry');
        $c = ConsultaTierra::where('astronauta_id', $this->a->id)->firstOrFail();
        $this->assertTrue($c->fallida);

        $t->call('reintentar', $c->id)->assertSet('anuncio', 'Still no link. It will be sent automatically when it is restored.');
        $this->assertTrue($c->fresh()->fallida);

        Cache::put('tierra.enlace', true);
        $t->call('reintentar', $c->id)->assertSet('anuncio', 'Send retried. The link is active.');
        $this->assertFalse($c->fresh()->fallida);
    }
}
