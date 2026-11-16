<?php

namespace Tests\Feature;

use App\Livewire\Rutinas;
use App\Models\Actividad;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Models\Rutina;
use App\Models\RutinaEjecucion;
use App\Support\TripulanteActual;
use Carbon\Carbon;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RutinasSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. Fixed time: Sunday 15 Nov 2026, 10:15 UTC. */
class RoutinesTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $dilan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:15:00', 'UTC'));
        $this->seed(ExpedicionSeeder::class);
        $this->seed(RutinasSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        $this->dilan = Astronauta::where('mision_id', $mission->id)->where('nombre', 'Dilan García')->firstOrFail();
        TripulanteActual::fijar($this->dilan->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function routine(string $name): Rutina
    {
        return Rutina::where('astronauta_id', $this->dilan->id)->where('nombre', 'like', $name.'%')->firstOrFail();
    }

    public function test_route_responds_with_header_and_real_week(): void
    {
        $this->get(route('rutinas'))->assertOk()
            ->assertSee('Routines', false)->assertSee('Crew member Dilan García')
            ->assertSee('SUNDAY 15 NOV 2026 · DAY 142 · 10:15 UTC', false)
            ->assertSee('Week · 9–15 Nov', false)->assertSee('Log activity')
            ->assertSee('Guidance only, not a diagnosis.');
    }

    public function test_week_shows_real_dates_and_highlights_today(): void
    {
        $c = Livewire::test(Rutinas::class)
            ->assertSee('MON')->assertSee('TUE')->assertSee('WED')->assertSee('THU')->assertSee('FRI')->assertSee('SAT')->assertSee('TODAY');
        $html = $c->html();
        $this->assertStringContainsString('MON 9 Nov', $html);
        $this->assertStringContainsString('SUN 15 Nov', $html);
        $this->assertMatchesRegularExpression('/is-sel is-hoy|is-hoy is-sel/', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
    }

    public function test_week_that_crosses_a_month_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-02 08:00:00', 'UTC')); // Wednesday; Monday = 30 Nov
        Livewire::test(Rutinas::class)->assertSee('Week · 30 Nov–6 Dec', false);
    }

    public function test_today_agenda_has_time_derived_states(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee("Today's agenda")->assertSee('2 of 8 done')
            ->assertSee('ARED session, 45 min')->assertSee('DONE')
            ->assertSee('SKIPPED')       // stretching 08:15
            ->assertSee('OVERDUE')       // radiation 09:00 (already past and not done)
            ->assertSee('PENDING')       // 21:00 not yet
            ->assertSee('Mark Check radiation dose done')
            ->assertSee('Undo ARED session, 45 min')->assertSee('Resume Stretching, 10 min');
    }

    public function test_pending_state_is_not_stored_and_depends_on_time(): void
    {
        $strength = $this->routine('Leg strength routine');
        $this->assertSame('11:00', $strength->hora);
        Livewire::test(Rutinas::class)->assertSee('Mark Leg strength routine, 30 min done');
        $this->assertSame(0, RutinaEjecucion::where('rutina_id', $strength->id)->whereDate('fecha', '2026-11-15')->count());

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', 'UTC')); // now 11:00 has passed
        $c = Livewire::test(Rutinas::class);
        $html = $c->html();
        $this->assertMatchesRegularExpression('/est-late[^>]*>(?:(?!<\/li>).)*Leg strength routine/s', $html);
    }

    public function test_daily_progress_cards(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee('EXERCISE')->assertSee('45')->assertSee('/ 150 min')->assertSee('105 min to go')
            ->assertSee('HYDRATION')->assertSee('1.4')->assertSee('/ 2.5 L')
            ->assertSee('SLEEP')->assertSee('6.2')->assertSee('Below your goal')
            ->assertSee('MEASUREMENTS')->assertSee('1/2')->assertSee('1 pending');
    }

    public function test_mark_done_creates_execution_and_activity_and_updates_progress(): void
    {
        $r = $this->routine('Leg strength routine');
        Livewire::test(Rutinas::class)->call('marcar', $r->id)
            ->assertSee('3 of 8 done')->assertSee('Undo Leg strength routine')->assertSee('75 min to go');

        $this->assertDatabaseHas('rutina_ejecuciones', ['rutina_id' => $r->id, 'estado' => 'done']);
        $this->assertSame(1, Actividad::where('rutina_id', $r->id)->where('origen', 'recordatorio')->whereDate('inicio_en', '2026-11-15')->count());
    }

    public function test_undo_skip_and_resume(): void
    {
        $r = $this->routine('Check radiation dose');
        $c = Livewire::test(Rutinas::class)->call('marcar', $r->id)->assertSee('Undo Check radiation dose');
        $c->call('deshacer', $r->id)->assertSee('Mark Check radiation dose done');
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $r->id, 'fecha' => '2026-11-15']);

        $c->call('omitir', $r->id)->assertSee('Resume Check radiation dose');
        $this->assertDatabaseHas('rutina_ejecuciones', ['rutina_id' => $r->id, 'estado' => 'skipped']);
        $c->call('deshacer', $r->id)->assertSee('Mark Check radiation dose done');
    }

    public function test_marking_a_measurement_done_creates_no_journal_activity(): void
    {
        $r = $this->routine('Check radiation dose');
        Livewire::test(Rutinas::class)->call('marcar', $r->id)->assertSee('2/2')->assertSee('All up to date');
        $this->assertSame(0, Actividad::where('rutina_id', $r->id)->count());
    }

    public function test_cannot_mark_a_future_day_or_another_crew_members_routine(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'UTC')); // Wednesday
        $r = $this->routine('Log mood');
        RutinaEjecucion::where('rutina_id', $r->id)->whereDate('fecha', '>=', '2026-11-12')->delete(); // the seeder already filled it up to the 15th
        $c = Livewire::test(Rutinas::class)->call('seleccionarDia', '2026-11-13')->assertSee('Agenda · Fri 13');
        $c->call('marcar', $r->id);
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $r->id, 'fecha' => '2026-11-13']);

        $other = Astronauta::where('nombre', 'Elena Moreau')->firstOrFail();
        $foreign = Rutina::where('astronauta_id', $other->id)->firstOrFail();
        RutinaEjecucion::where('rutina_id', $foreign->id)->delete();
        $c->call('seleccionarDia', '2026-11-11')->call('marcar', $foreign->id);
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $foreign->id, 'fecha' => '2026-11-11']);
    }

    public function test_selecting_another_day_of_the_week_and_invalid_values(): void
    {
        $c = Livewire::test(Rutinas::class)->call('seleccionarDia', '2026-11-10')
            ->assertSet('dia', '2026-11-10')->assertSee('Agenda · Tue 10');
        $c->call('seleccionarDia', '2030-01-01')->assertSet('dia', '')->assertSee("Today's agenda");
        $c->call('seleccionarDia', 'garbage')->assertSet('dia', '');
    }

    public function test_why_it_matters_uses_the_real_indicator_state(): void
    {
        // Dilan: bone loss and muscle mass are critical.
        Livewire::test(Rutinas::class)
            ->assertSee('Why it matters')
            ->assertSee('Bone loss (hip)')->assertSee('Muscle mass')->assertSee('CRITICAL')
            ->assertSee('Complete your loading session (ARED) today')
            ->assertSee('ARED session, 45 min · DONE')
            ->assertSee('Leg strength routine, 30 min · PENDING')
            ->assertDontSee('Blood pressure');
    }

    public function test_why_it_matters_prioritizes_the_worst_state_of_another_crew_member(): void
    {
        // Kenji: critical blood pressure (142/92), bone loss and muscle mass at caution, normal radiation.
        $kenji = Astronauta::where('nombre', 'Kenji Arai')->firstOrFail();
        TripulanteActual::fijar($kenji->id);
        Livewire::test(Rutinas::class)
            ->assertSee('Crew member Kenji Arai')->assertSee('Blood pressure')->assertSee('Measure your blood pressure at rest')
            ->assertSee('CAUTION')->assertDontSee('Daily radiation');
    }

    public function test_switching_crew_member_refreshes_the_screen(): void
    {
        $c = Livewire::test(Rutinas::class)->assertSee('Crew member Dilan García');
        $elena = Astronauta::where('nombre', 'Elena Moreau')->firstOrFail();
        TripulanteActual::fijar($elena->id);
        $c->call('seleccionarDia', '2026-11-10')->dispatch('tripulante-cambiado')
            ->assertSet('dia', '')->assertSet('modal', null)->assertSee('Crew member Elena Moreau')->assertSee("Today's agenda");
    }

    public function test_recent_history_shows_origin_and_amounts(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee('Recent history')
            ->assertSee('1400 ml')->assertSee('ARED session, 45 min')->assertSee('Reminder')
            ->assertSee('6 h 12 min')->assertSee('Today · 22:40→04:52')->assertSee('Manual');
    }

    public function test_log_activity_validates_and_saves(): void
    {
        Livewire::test(Rutinas::class)->call('abrirActividad')->assertSet('modal', 'actividad')
            ->set('aCantidad', '')->call('guardarActividad')->assertHasErrors(['aCantidad'])->assertSee('Enter the duration')
            ->set('aCantidad', 'abc')->call('guardarActividad')->assertHasErrors(['aCantidad'])
            ->set('aCantidad', '999')->call('guardarActividad')->assertHasErrors(['aCantidad'])->assertSee('Maximum 720 min')
            ->set('aTipo', 'Hidratación')->set('aCantidad', '3500')->call('guardarActividad')->assertSee('Maximum 3000 ml')
            ->set('aHora', '')->set('aCantidad', '500')->call('guardarActividad')->assertHasErrors(['aHora'])
            ->set('aHora', '09:30')->call('guardarActividad')->assertHasNoErrors()->assertSet('modal', null)
            ->assertSee('1.9');

        $this->assertDatabaseHas('actividades', ['astronauta_id' => $this->dilan->id, 'tipo' => 'Hidratación', 'cantidad' => 500, 'unidad' => 'ml', 'origen' => 'manual']);
    }

    public function test_exercise_activity_adds_to_the_daily_goal(): void
    {
        Livewire::test(Rutinas::class)->call('abrirActividad')
            ->set('aTipo', 'Cardio')->set('aCantidad', '30')->set('aHora', '10:00')->set('aInt', 'Alta')->set('aNotas', 'Cycle ergometer')
            ->call('guardarActividad')->assertHasNoErrors()
            ->assertSee('75 min to go')->assertSee('Cardio · Cycle ergometer');
        $this->assertDatabaseHas('actividades', ['tipo' => 'Cardio', 'intensidad' => 'Alta', 'cantidad' => 30]);
    }

    public function test_add_routine_validates_and_appears_in_the_agenda(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')->assertSet('modal', 'rutina')
            ->call('guardarRutina')->assertHasErrors(['rNombre', 'rHora', 'rCantidad'])
            ->set('rNombre', 'Gentle yoga')->set('rHora', '17:30')->set('rCantidad', '20')->set('rDias', [])
            ->call('guardarRutina')->assertHasErrors(['rDias'])->assertSee('Choose at least one day')
            ->set('rDias', ['6', '7'])->set('rTipo', 'Otro')->set('rIcono', 'self_improvement')
            ->call('guardarRutina')->assertHasNoErrors()->assertSet('modal', null)
            ->assertSee('Gentle yoga')->assertSee('Sat · Sun');

        $r = Rutina::where('astronauta_id', $this->dilan->id)->where('nombre', 'Gentle yoga')->firstOrFail();
        $this->assertSame([6, 7], $r->dias);
        $this->assertSame('17:30', $r->hora);
        $this->assertSame('bien', $r->kind);
        $this->assertTrue($r->activa);
    }

    public function test_exercise_routine_requires_duration_and_formatted_time(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')
            ->set('rNombre', 'Weights')->set('rHora', '25:99')->set('rCantidad', '0')
            ->call('guardarRutina')->assertHasErrors(['rHora', 'rCantidad']);
    }

    public function test_edit_and_delete_own_routine_only(): void
    {
        $r = $this->routine('Stretching');
        Livewire::test(Rutinas::class)->call('abrirRutina', $r->id)
            ->assertSet('rNombre', 'Stretching, 10 min')->assertSet('rHora', '08:15')->assertSet('rDias', ['1', '2', '3', '4', '5', '6', '7'])
            ->set('rNombre', 'Stretching, 15 min')->set('rCantidad', '15')->set('rDias', ['1', '3', '5'])
            ->call('guardarRutina')->assertHasNoErrors()->assertSee('Stretching, 15 min')->assertSee('Mon · Wed · Fri');

        $r->refresh();
        $this->assertSame('ej', $r->kind); // the kind of the plan routine is preserved
        $this->assertSame([1, 3, 5], $r->dias);

        $another = Rutina::where('astronauta_id', Astronauta::where('nombre', 'Elena Moreau')->value('id'))->firstOrFail();
        $c = Livewire::test(Rutinas::class)->call('abrirRutina', $another->id)->assertSet('modal', null);
        $c->call('eliminarRutina', $another->id);
        $this->assertDatabaseHas('rutinas', ['id' => $another->id]);

        $c->call('eliminarRutina', $r->id);
        $this->assertDatabaseMissing('rutinas', ['id' => $r->id]);
    }

    public function test_close_modal(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')->assertSet('modal', 'rutina')->call('cerrar')->assertSet('modal', null);
    }

    public function test_empty_state_without_routines(): void
    {
        Rutina::where('astronauta_id', $this->dilan->id)->delete();
        Livewire::test(Rutinas::class)
            ->assertSee('You have no routines yet')->assertSee('Add routine')
            ->assertSee('There are no routines in your plan yet.');
    }

    public function test_error_state_and_retry(): void
    {
        Livewire::test(Rutinas::class)->set('error', true)
            ->assertSee('Your routines could not be loaded')->assertSee('Retry')
            ->call('reintentar')->assertSet('error', false)->assertDontSee('Your routines could not be loaded')->assertSee("Today's agenda");
    }

    public function test_without_crew_member_shows_notice(): void
    {
        Astronauta::query()->update(['mision_id' => null]);
        Mision::query()->delete();
        Livewire::test(Rutinas::class)->assertSee('No crew member selected');
    }

    public function test_basic_accessibility(): void
    {
        $this->get(route('rutinas'))
            ->assertSee('role="tablist"', false)->assertSee('role="tab"', false)->assertSee('role="progressbar"', false)
            ->assertSee('aria-labelledby="rut-sem-t"', false)->assertSee('aria-label="Day of the week"', false)
            ->assertSee('Mark Check radiation dose done', false);
    }

    public function test_seeder_is_idempotent_and_complete(): void
    {
        $this->seed(RutinasSeeder::class);
        $this->seed(RutinasSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        foreach (Astronauta::where('mision_id', $mission->id)->get() as $a) {
            $this->assertSame(8, Rutina::where('astronauta_id', $a->id)->count(), $a->nombre);
            $this->assertSame(1, Rutina::where('astronauta_id', $a->id)->where('indicador_clave', 'perdida_osea')->count());
            $this->assertGreaterThan(0, Actividad::where('astronauta_id', $a->id)->count());
        }
        $this->assertSame(5, Astronauta::where('mision_id', $mission->id)->count());
        $this->assertSame(1, Actividad::where('astronauta_id', $this->dilan->id)->where('tipo', 'Sueño')->count());
    }
}
