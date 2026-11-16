<?php

namespace Tests\Feature;

use App\Contracts\Orientador;
use App\Livewire\Panel;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\Mision;
use App\Services\Ia\Orientacion;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase). The Advisor (Orientador) is faked. */
class DashboardCaseTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    private Astronauta $other;

    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        [$this->a, $this->other] = Astronauta::where('mision_id', $mission->id)->orderBy('id')->take(2)->get()->all();
        TripulanteActual::fijar($this->a->id);

        $this->fake = new class implements Orientador
        {
            public int $llamadas = 0;

            public function orientar(CasoClinico $caso): Orientacion
            {
                $this->llamadas++;

                return new Orientacion(
                    casoId: $caso->id, astronautaId: $caso->astronauta_id, patron: $caso->patron, severidad: $caso->severidad,
                    titulo: 'Possible hypoxia (test '.$caso->astronauta_id.')', hallazgo: 'The values could suggest a test pattern.',
                    acciones: [['texto' => 'Rest and repeat the measurement', 'fuente' => null]],
                    historial7d: [['indicador' => 'spo2', 'etiqueta' => 'SpO₂', 'unidad' => '%', 'serie' => [98, 96, 94], 'actual' => '94', 'base' => '98']],
                    expedienteRelevante: ['No related history'], tratamientos: ['No treatments recorded'],
                    fuentes: [], confianza: 60,
                );
            }
        };
        $this->app->instance(Orientador::class, $this->fake);
    }

    private function clinicalCase(Astronauta $crew, string $status = 'abierto'): CasoClinico
    {
        return CasoClinico::create([
            'astronauta_id' => $crew->id, 'patron' => 'hipoxia', 'severidad' => 'precaucion', 'evidencia' => [], 'estado' => $status,
            'detectado_en' => now(), 'cerrado_en' => $status === 'cerrado' ? now() : null,
        ]);
    }

    public function test_no_case_means_no_card(): void
    {
        Livewire::test(Panel::class)->assertDontSee('panel-caso')->assertViewHas('caso', null);
        $this->assertSame(0, $this->fake->llamadas);
    }

    public function test_a_closed_case_or_another_crew_members_case_does_not_generate_a_card(): void
    {
        $this->clinicalCase($this->a, 'cerrado');
        $this->clinicalCase($this->other);
        Livewire::test(Panel::class)->assertDontSee('panel-caso')->assertDontSee('Possible hypoxia (test');
    }

    public function test_an_open_case_shows_the_card_and_keeps_the_suggestions(): void
    {
        $this->clinicalCase($this->a);
        $t = Livewire::test(Panel::class)
            ->assertSee('Possible hypoxia (test '.$this->a->id.')')
            ->assertSee('The values could suggest a test pattern.')
            ->assertSee('Rest and repeat the measurement')
            ->assertSee('Open in Flight Surgeon')
            ->assertSee('abrir-medico-flotante', false)
            ->assertSee('What to do now')
            ->assertSee('Guided self-check')
            ->assertSee('This tool provides guidance only; it does not diagnose');
        // The usual cards remain: the view receives the same keys as before.
        $t->assertViewHas('tarjetas');
        $t->assertViewHas('caso');
    }

    public function test_the_advisor_is_not_called_twice_for_the_same_case(): void
    {
        $case = $this->clinicalCase($this->a);
        $t = Livewire::test(Panel::class);
        $t->call('refrescar');
        // The floating widget also shares the same cached guidance.
        \App\Livewire\MedicoFlotante::orientacionDe($case->fresh());
        $this->assertSame(1, $this->fake->llamadas);
    }
}
