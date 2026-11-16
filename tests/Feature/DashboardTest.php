<?php

namespace Tests\Feature;

use App\Livewire\Panel;
use App\Models\AlertaSaliente;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never deletes data from the local database. */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function crew()
    {
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $crew = $m->tripulantes()->orderBy('id')->get();
        session(['astronauta_id' => $crew->first()->id]);

        return $crew;
    }

    public function test_shows_the_ten_indicators_and_the_crew_member_data(): void
    {
        $this->crew();
        $t = Livewire::test(Panel::class)->assertSee('Dilan García')->assertSee('128/84')->assertSee('1.78 m')->assertSee('A+');
        foreach (config('panel.indicadores') as $cfg) {
            $t->assertSee($cfg['etiqueta']);
        }
        $this->assertSame(10, substr_count($t->html(), 'class="panel-fila '));
    }

    public function test_selecting_sets_sel_highlights_muscles_and_toggles(): void
    {
        $this->crew();
        $t = Livewire::test(Panel::class)
            ->call('seleccionar', 'glucosa')
            ->assertSet('sel', 'glucosa')
            ->assertSee('On the figure: '.config('panel.indicadores.glucosa.etiqueta'))
            ->assertSeeHtml('aria-pressed="true"');
        $this->assertSame(config('panel.indicadores.glucosa.musculos'), $t->viewData('musculos'));

        $t->call('seleccionar', 'glucosa')->assertSet('sel', null)
            ->call('seleccionar', 'no_existe')->assertSet('sel', null);
        $this->assertNotEmpty($t->viewData('musculos')); // no selection: the muscles of the incidents
    }

    public function test_cards_with_critical_first(): void
    {
        $this->crew();
        $states = collect(Livewire::test(Panel::class)->viewData('tarjetas'))->pluck('estado')->all();
        $this->assertSame(['crit', 'crit', 'warn'], $states);
    }

    public function test_self_check_green_orange_red_paths_and_going_back(): void
    {
        $this->crew();
        $t = Livewire::test(Panel::class)->assertSee('QUESTION 1 OF 5');
        foreach (range(1, 5) as $_) {
            $t->call('responder', 'n');
        }
        $t->assertSee('Monitor')->assertDontSee('Contact ground now');

        $t->call('reiniciarChequeo');
        foreach (['y', 'n', 'n', 'n', 'n'] as $answer) {
            $t->call('responder', $answer);
        }
        $t->assertSee('Apply measure');

        $t->call('reiniciarChequeo');
        foreach (['n', 'n', 'y', 'n', 'n'] as $answer) { // shortness of breath => red
            $t->call('responder', $answer);
        }
        $t->assertSee('Contact ground now')->assertSee('Queue alert to ground');

        $t->call('reiniciarChequeo')->call('responder', 'y')->call('preguntaAnterior')->assertSet('respuestas', []);
    }

    public function test_alerting_ground_creates_a_real_alert_and_changes_the_text(): void
    {
        $this->crew();
        $before = AlertaSaliente::count();
        $t = Livewire::test(Panel::class)->assertSee('Queue alert to ground')
            ->call('avisarATierra', 'perdida_osea')
            ->assertSee('Queued · will send when link is restored');
        $this->assertSame($before + 1, AlertaSaliente::count());
        $this->assertSame('panel', AlertaSaliente::latest('id')->first()->carga['origen']);

        $t->call('avisarATierra', 'perdida_osea'); // idempotent
        $this->assertSame($before + 1, AlertaSaliente::count());
    }

    public function test_alerting_ground_works_without_any_prior_evaluation(): void
    {
        $m = Mision::create(['nombre' => 'PT-NoEval', 'entorno' => 'estacion']);
        $a = \App\Models\Astronauta::create(['mision_id' => $m->id, 'nombre' => 'PT New', 'mision' => 'PT-NoEval', 'entorno' => 'estacion']);
        session(['mision_id' => $m->id, 'astronauta_id' => $a->id]);

        Livewire::test(Panel::class)
            ->call('avisarATierra', 'perdida_osea')
            ->call('responder', 'y')->call('avisarATierra', 'chk');

        $alerts = AlertaSaliente::where('astronauta_id', $a->id)->get();
        $this->assertCount(2, $alerts);
        $this->assertNull($alerts->first()->evaluacion_id);
        $this->assertSame('PT New', $alerts->first()->carga['astronauta']);
        $this->assertSame('contactar_tierra', $alerts->first()->carga['accion']);
    }

    public function test_repeat_measurement_selects_and_dispatches_open_log(): void
    {
        $this->crew();
        Livewire::test(Panel::class)->call('repetirMedicion', 'presion_arterial')
            ->assertSet('sel', 'presion_arterial')->assertDispatched('abrir-registro', indicador: 'presion_arterial');
    }

    public function test_marking_an_action_strikes_through_the_text(): void
    {
        $this->crew();
        Livewire::test(Panel::class)->call('alternarAccion', 'presion_arterial', 0)->assertSeeHtml('panel-accion is-hecha');
    }

    public function test_switching_crew_member_updates_and_clears_the_selection(): void
    {
        $crew = $this->crew();
        $c = Livewire::test(Panel::class)->call('seleccionar', 'glucosa')->assertSee('Dilan García');
        TripulanteActual::fijar($crew[1]->id);
        $c->dispatch('tripulante-cambiado')->assertSee('Elena Moreau')->assertSet('sel', null)->assertDontSee('128/84');
    }

    public function test_empty_state_for_crew_member_without_incidents(): void
    {
        $crew = $this->crew();
        TripulanteActual::fijar($crew[3]->id); // Amara Osei
        Livewire::test(Panel::class)->assertSee('All in range, no pending actions')->assertSee('Measurement done');
    }

    public function test_the_page_responds(): void
    {
        $this->get('/')->assertOk();
    }
}
