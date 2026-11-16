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

class MisionPerfilTest extends TestCase
{
    use DatabaseTransactions;

    private Mision $m;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = Mision::create(['nombre' => 'Perfil Test', 'entorno' => 'estacion', 'care_level' => 3, 'mission_phase' => 'vuelo', 'duracion_dias' => 100, 'inicio_en' => now()->subDays(5)]);
        $this->a = Astronauta::create(['nombre' => 'Perfil Test', 'mision' => 'T-1', 'entorno' => 'estacion', 'mision_id' => $this->m->id]);
        session(['mision_id' => $this->m->id]);
        TripulanteActual::fijar($this->a->id);
        Cache::forever('tierra.enlace', true);
    }

    protected function tearDown(): void
    {
        Cache::forget('tierra.enlace');
        parent::tearDown();
    }

    public function test_helpers_del_modelo(): void
    {
        $this->assertFalse($this->m->triajeAutonomo());
        $this->assertStringContainsString('Telemedicina síncrona', $this->m->etiquetaCareLevel());
        $this->m->care_level = 4;
        $this->assertTrue($this->m->triajeAutonomo());
        $this->assertStringContainsString('Triaje autónomo local', $this->m->etiquetaCareLevel());
    }

    public function test_el_selector_guarda_nivel_y_fase(): void
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

    public function test_valores_invalidos_no_se_guardan(): void
    {
        Livewire::test(ContextoMision::class)
            ->set('careLevel', 2)->assertHasErrors('careLevel')
            ->set('careLevel', 3)->set('missionPhase', 'orbita')->assertHasErrors('missionPhase');
        $this->m->refresh();
        $this->assertSame(3, (int) $this->m->care_level);
        $this->assertSame('vuelo', $this->m->mission_phase);
    }

    public function test_medico_nivel_3_ofrece_videoconsulta(): void
    {
        Livewire::test(MedicoVuelo::class)
            ->assertSee('Solicitar videoconsulta')
            ->assertSee('Telemedicina síncrona')
            ->assertDontSee('Triaje autónomo local: orientación inmediata');
    }

    public function test_medico_nivel_4_y_5_no_ofrece_videoconsulta(): void
    {
        foreach ([4, 5] as $n) {
            $this->m->update(['care_level' => $n]);
            Livewire::test(MedicoVuelo::class)
                ->assertDontSee('Solicitar videoconsulta')
                ->assertSee('Triaje autónomo local: orientación inmediata a bordo y mensaje asíncrono a tierra')
                ->assertSee('Enviar mensaje');
        }
    }

    public function test_consultas_tierra_video_con_triaje_autonomo_lanza_excepcion(): void
    {
        $this->m->update(['care_level' => 4]);
        $this->a->refresh();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Triaje autónomo local');
        ConsultasTierra::enviar($this->a, 'video', ['glucosa']);
    }

    public function test_consultas_tierra_video_nivel_3_y_mensaje_nivel_5_funcionan(): void
    {
        $this->assertSame('video', ConsultasTierra::enviar($this->a, 'video', ['glucosa'])->tipo);
        $this->m->update(['care_level' => 5]);
        $this->a->refresh();
        $this->assertSame('msg', ConsultasTierra::enviar($this->a, 'msg', ['glucosa'])->tipo);
    }

    public function test_sin_enlace_la_consulta_sigue_marcandose_fallida(): void
    {
        Cache::forever('tierra.enlace', false);
        $this->assertTrue(ConsultasTierra::enviar($this->a, 'video', ['glucosa'])->fallida);
    }

    public function test_la_tarjeta_muestra_fuente_referencia_y_banda(): void
    {
        $tarjeta = [
            'id' => 'tarjeta-x', 'kind' => 'rule', 'sev' => 'warn', 'finding' => 'Presión alta', 'steps' => ['Descansa'],
            'banda' => 'Hipertensión 1',
            'fuente' => ['tipo' => 'proyecto', 'revision' => 'Rev C', 'validado' => false, 'codigo' => null, 'etiqueta' => 'Supuesto del proyecto', 'es_proyeccion' => true],
            'referencia' => ['tipo' => 'norma', 'revision' => 'Rev C', 'validado' => true, 'codigo' => 'V1 4027', 'etiqueta' => 'NASA-STD-3001 V1 4027', 'es_proyeccion' => false],
        ];
        $html = view('livewire.medico-vuelo', [
            'astronauta' => $this->a, 'tarjetas' => [$tarjeta], 'emergencia' => null, 'hayEmerg' => false, 'nPausa' => 0,
            'votos' => [], 'compartibles' => [], 'elegidos' => [], 'adjuntos' => [], 'envios' => [], 'retardo' => 0,
            'enlace' => true, 'puedeVideo' => true, 'autonomo' => false, 'nivelAtencion' => 'Nivel 3',
            'fuente' => [], 'anadidas' => [], 'marcados' => [], 'adjuntosSel' => [], 'nota' => '', 'error' => null, 'anuncio' => '', 'gracias' => [],
        ])->render();
        $this->assertStringContainsString('Hipertensión 1', $html);
        $this->assertStringContainsString('Proyección (HRP)', $html);
        $this->assertStringContainsString('Fuente: Supuesto del proyecto', $html);
        $this->assertStringContainsString('NASA-STD-3001 V1 4027', $html);
    }
}
