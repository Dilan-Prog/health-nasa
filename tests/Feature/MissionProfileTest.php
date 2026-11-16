<?php

namespace Tests\Feature;

use App\Livewire\ContextoMision;
use App\Livewire\MedicoVuelo;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\ConsultasTierra;
use App\Support\TripulanteActual;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class MissionProfileTest extends TestCase
{
    use DatabaseTransactions;

    private Mision $m;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = Mision::create(['nombre' => 'Profile Test', 'entorno' => 'estacion', 'care_level' => 3, 'mission_phase' => 'vuelo', 'duracion_dias' => 100, 'inicio_en' => now()->subDays(5)]);
        $this->a = Astronauta::create(['nombre' => 'Profile Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $this->m->id]);
        session(['mision_id' => $this->m->id]);
        TripulanteActual::fijar($this->a->id);
        Cache::forever('tierra.enlace', true);
    }

    protected function tearDown(): void
    {
        Cache::forget('tierra.enlace');
        parent::tearDown();
    }

    public function test_model_helpers(): void
    {
        $this->assertFalse($this->m->triajeAutonomo());
        $this->assertStringContainsString('Synchronous telemedicine', $this->m->etiquetaCareLevel());
        $this->m->care_level = 4;
        $this->assertTrue($this->m->triajeAutonomo());
        $this->assertStringContainsString('Local autonomous triage', $this->m->etiquetaCareLevel());
    }

    public function test_selector_saves_level_and_phase(): void
    {
        Livewire::test(ContextoMision::class)
            ->assertSet('careLevel', 3)
            ->set('careLevel', 5)
            ->set('missionPhase', 'posvuelo')
            ->assertHasNoErrors();
        $this->m->refresh();
        $this->assertSame(5, (int) $this->m->care_level);
        $this->assertSame('posvuelo', $this->m->mission_phase);
    }

    public function test_invalid_values_are_not_saved(): void
    {
        Livewire::test(ContextoMision::class)
            ->set('careLevel', 2)->assertHasErrors('careLevel')
            ->set('careLevel', 3)->set('missionPhase', 'orbita')->assertHasErrors('missionPhase');
        $this->m->refresh();
        $this->assertSame(3, (int) $this->m->care_level);
        $this->assertSame('vuelo', $this->m->mission_phase);
    }

    public function test_flight_surgeon_level_3_offers_video_consultation(): void
    {
        Livewire::test(MedicoVuelo::class)
            ->assertSee('Request video consultation')
            ->assertSee('Synchronous telemedicine')
            ->assertDontSee('Local autonomous triage: immediate on-board guidance');
    }

    public function test_flight_surgeon_level_4_and_5_do_not_offer_video_consultation(): void
    {
        foreach ([4, 5] as $n) {
            $this->m->update(['care_level' => $n]);
            Livewire::test(MedicoVuelo::class)
                ->assertDontSee('Request video consultation')
                ->assertSee('Local autonomous triage: immediate on-board guidance and an asynchronous message to the ground')
                ->assertSee('Send message');
        }
    }

    public function test_ground_consultations_video_with_autonomous_triage_throws_exception(): void
    {
        $this->m->update(['care_level' => 4]);
        $this->a->refresh();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Local autonomous triage');
        ConsultasTierra::enviar($this->a, 'video', ['glucosa']);
    }

    public function test_ground_consultations_video_level_3_and_message_level_5_work(): void
    {
        $this->assertSame('video', ConsultasTierra::enviar($this->a, 'video', ['glucosa'])->tipo);
        $this->m->update(['care_level' => 5]);
        $this->a->refresh();
        $this->assertSame('msg', ConsultasTierra::enviar($this->a, 'msg', ['glucosa'])->tipo);
    }

    public function test_without_a_link_the_consultation_is_still_marked_failed(): void
    {
        Cache::forever('tierra.enlace', false);
        $this->assertTrue(ConsultasTierra::enviar($this->a, 'video', ['glucosa'])->fallida);
    }

    public function test_card_shows_source_reference_and_band(): void
    {
        $card = [
            'id' => 'card-x', 'kind' => 'rule', 'sev' => 'warn', 'finding' => 'High pressure', 'steps' => ['Rest'],
            'banda' => 'Hypertension stage 1',
            'fuente' => ['tipo' => 'proyecto', 'revision' => 'Rev C', 'validado' => false, 'codigo' => null, 'etiqueta' => 'Project assumption', 'es_proyeccion' => true],
            'referencia' => ['tipo' => 'norma', 'revision' => 'Rev C', 'validado' => true, 'codigo' => 'V1 4027', 'etiqueta' => 'NASA-STD-3001 V1 4027', 'es_proyeccion' => false],
        ];
        $html = view('livewire.medico-vuelo', [
            'astronauta' => $this->a, 'tarjetas' => [$card], 'emergencia' => null, 'hayEmerg' => false, 'nPausa' => 0,
            'votos' => [], 'compartibles' => [], 'elegidos' => [], 'adjuntos' => [], 'envios' => [], 'retardo' => 0,
            'enlace' => true, 'puedeVideo' => true, 'autonomo' => false, 'nivelAtencion' => 'Level 3',
            'fuente' => [], 'anadidas' => [], 'marcados' => [], 'adjuntosSel' => [], 'nota' => '', 'error' => null, 'anuncio' => '', 'gracias' => [],
        ])->render();
        $this->assertStringContainsString('Hypertension stage 1', $html);
        $this->assertStringContainsString('Projection (HRP)', $html);
        $this->assertStringContainsString('Source: Project assumption', $html);
        $this->assertStringContainsString('NASA-STD-3001 V1 4027', $html);
    }
}
