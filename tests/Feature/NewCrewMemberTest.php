<?php

namespace Tests\Feature;

use App\Livewire\NuevoTripulante;
use App\Livewire\Tripulacion;
use App\Models\Astronauta;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database data. */
class NewCrewMemberTest extends TestCase
{
    use DatabaseTransactions;

    /** Own test mission with 2 crew members (free slots); does not depend on the local database data. */
    private function expedition(int $crewCount = 2): Mision
    {
        $mission = Mision::create(['nombre' => 'Mission NT '.uniqid(), 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);
        for ($i = 1; $i <= $crewCount; $i++) {
            Astronauta::create(['mision_id' => $mission->id, 'mision' => $mission->nombre, 'entorno' => 'estacion', 'nombre' => 'Crew member '.$i]);
        }
        session(['mision_id' => $mission->id]);

        return $mission;
    }

    public function test_full_crew_blocks_the_wizard_and_does_not_save(): void
    {
        $mission = $this->expedition(5);

        Livewire::test(NuevoTripulante::class)->assertSee('Crew full')->call('guardar');
        $this->assertSame(5, Astronauta::where('mision_id', $mission->id)->count());
        $this->get(route('tripulacion'))->assertSee('Crew full')->assertSee('5 / 5');
    }

    private function step1(\Livewire\Features\SupportTesting\Testable $t): \Livewire\Features\SupportTesting\Testable
    {
        return $t->set('nombre', 'Nora Lindqvist')->set('fecha_nacimiento', '1988-04-12')->set('sexo', 'femenino')
            ->set('nacionalidad', 'Swedish')->set('agencia', 'ESA')->set('rol', 'Pilot')->set('fecha_ingreso', '2015-06-01');
    }

    public function test_shows_the_wizard_on_step_one(): void
    {
        $this->expedition();

        Livewire::test(NuevoTripulante::class)
            ->assertSet('paso', 1)
            ->assertSee('Step 1 of 5')->assertSee('Full name')->assertSee('Date of birth');
    }

    public function test_without_a_mission_shows_the_block_and_neither_advances_nor_saves(): void
    {
        Astronauta::query()->update(['mision_id' => null]); // inside the transaction: it is rolled back
        $before = Astronauta::count();

        $this->step1(Livewire::test(NuevoTripulante::class))
            ->assertSee('There is no active mission')
            ->call('siguiente')->assertSet('paso', 1)
            ->call('guardar')->assertNoRedirect();

        $this->assertSame($before, Astronauta::count());
    }

    public function test_step_one_blocks_advancing_when_required_data_is_missing(): void
    {
        $this->expedition();

        Livewire::test(NuevoTripulante::class)
            ->call('siguiente')
            ->assertSet('paso', 1)
            ->assertHasErrors(['nombre', 'fecha_nacimiento', 'sexo'])
            ->assertSee('The name field is required.');
    }

    public function test_rejects_minors_and_out_of_range_values(): void
    {
        $this->expedition();

        $t = Livewire::test(NuevoTripulante::class)
            ->set('nombre', 'Young')->set('fecha_nacimiento', now()->subYears(10)->toDateString())->set('sexo', 'otro')
            ->call('siguiente')->assertSet('paso', 1)->assertHasErrors(['fecha_nacimiento']);

        $this->step1($t)->call('siguiente')->assertSet('paso', 2)->assertHasNoErrors()
            ->set('altura_m', '5')->call('siguiente')->assertSet('paso', 2)->assertHasErrors(['altura_m']);
    }

    public function test_previous_and_go_to_step_navigation_only_reaches_valid_steps(): void
    {
        $this->expedition();

        $t = Livewire::test(NuevoTripulante::class)->call('irAPaso', 3)->assertSet('paso', 1);
        $this->step1($t)->call('siguiente')->assertSet('paso', 2)->assertSet('pasosValidos', [1])
            ->call('irAPaso', 3)->assertSet('paso', 2)      // step 2 is not validated yet
            ->call('irAPaso', 1)->assertSet('paso', 1)
            ->call('irAPaso', 2)->assertSet('paso', 2)
            ->call('anterior')->assertSet('paso', 1)
            ->call('anterior')->assertSet('paso', 1);
    }

    public function test_age_and_bmi_are_calculated_live(): void
    {
        $this->expedition();
        $birth = now()->subYears(40)->subDays(3)->toDateString();

        $t = Livewire::test(NuevoTripulante::class)->set('fecha_nacimiento', $birth)->set('altura_m', '1,80')->set('peso_kg', '81');

        $this->assertSame(40, $t->get('edad'));
        $this->assertSame(25.0, $t->get('imc'));
        $t->set('fecha_nacimiento', 'not-a-date')->set('peso_kg', '');
        $this->assertNull($t->get('edad'));
        $this->assertNull($t->get('imc'));
    }

    public function test_baseline_preload_uses_default_values_without_overwriting_typed_input(): void
    {
        $this->expedition();
        $markers = config('indicadores.marcadores');

        $t = Livewire::test(NuevoTripulante::class);
        $this->assertSame(array_keys($markers), array_keys($t->get('linea_base')));
        $this->assertSame('', $t->get('linea_base.glucosa'));

        $t->set('linea_base.glucosa', '90')->call('precargarLineaBase');
        $lb = $t->get('linea_base');
        $this->assertSame('90', $lb['glucosa']);                 // typed input is kept
        $this->assertSame('36.7', $lb['temperatura']);           // default 36.7, 1 decimal
        $this->assertSame('116', $lb['presion_arterial']);       // default 116, 0 decimals
        foreach ($lb as $v) {
            $this->assertNotSame('', $v);
        }

        $t->call('vaciarLineaBase');
        $this->assertSame('', $t->get('linea_base.glucosa'));
    }

    public function test_repeatable_lists_add_and_remove_rows(): void
    {
        $this->expedition();

        Livewire::test(NuevoTripulante::class)
            ->call('agregarAlergia')->call('agregarAlergia')->assertCount('alergias', 2)
            ->set('alergias.1.sustancia', 'Penicillin')->call('quitarAlergia', 0)
            ->assertCount('alergias', 1)->assertSet('alergias.0.sustancia', 'Penicillin')
            ->call('agregarCondicion')->assertCount('condiciones', 1)->call('quitarCondicion', 0)->assertCount('condiciones', 0)
            ->call('agregarMedicamento')->assertCount('medicamentos', 1)->assertSet('medicamentos.0.activo', true)
            ->call('quitarMedicamento', 0)->assertCount('medicamentos', 0)
            ->call('agregarAntecedente')->assertCount('antecedentes', 1)->call('quitarAntecedente', 0)->assertCount('antecedentes', 0)
            ->call('quitarAlergia', 99)->assertCount('alergias', 1);
    }

    public function test_lists_respect_the_configured_maximum(): void
    {
        $this->expedition();
        $max = config('expediente.max_items.contactos');

        $t = Livewire::test(NuevoTripulante::class);
        for ($i = 0; $i < $max + 3; $i++) {
            $t->call('agregarContacto');
        }
        $t->assertCount('contactos', $max);
    }

    public function test_only_one_contact_can_be_primary(): void
    {
        $this->expedition();

        $t = Livewire::test(NuevoTripulante::class)
            ->call('agregarContacto')->call('agregarContacto')->call('agregarContacto')
            ->assertSet('contactos.0.principal', true)->assertSet('contactos.1.principal', false)
            ->call('marcarPrincipal', 2)
            ->assertSet('contactos.0.principal', false)->assertSet('contactos.2.principal', true);

        $this->assertCount(1, array_filter($t->get('contactos'), fn ($c) => $c['principal']));

        // When the primary is removed, another one inherits it.
        $t->call('quitarContacto', 2);
        $this->assertCount(1, array_filter($t->get('contactos'), fn ($c) => $c['principal']));
    }

    public function test_the_contacts_step_requires_a_phone_and_a_primary_contact(): void
    {
        $this->expedition();

        Livewire::test(NuevoTripulante::class)->set('paso', 4)
            ->call('agregarContacto')->set('contactos.0.nombre', 'Ana')->set('contactos.0.parentesco', 'pareja')->set('contactos.0.telefono', 'abc')
            ->call('siguiente')->assertSet('paso', 4)->assertHasErrors(['contactos.0.telefono'])
            ->set('contactos.0.telefono', '+34 600 111 222')->call('siguiente')->assertSet('paso', 5)->assertHasNoErrors();
    }

    public function test_full_flow_creates_the_astronaut_and_record(): void
    {
        $mission = $this->expedition();

        $t = $this->step1(Livewire::test(NuevoTripulante::class))
            ->call('siguiente')->assertSet('paso', 2)
            ->set('altura_m', '1.70')->set('peso_kg', '62,5')->set('grupo_sanguineo', 'O+')->set('evas', '3')
            ->set('fc_reposo', '58')->set('presion_basal_sis', '112')->set('presion_basal_dia', '72')
            ->set('horas_sueno_habituales', '7.5')->set('actividad_fisica_semana_min', '300')->set('fumador', '0')->set('alcohol', 'ocasional')
            ->call('precargarLineaBase')
            ->call('siguiente')->assertSet('paso', 3)
            ->set('aptitud_vuelo', 'apto')->set('ultimo_examen_en', '2026-10-01')
            ->call('agregarAlergia')->set('alergias.0.sustancia', 'Penicillin')->set('alergias.0.gravedad', 'grave')
            ->call('agregarCondicion')->set('condiciones.0.nombre', 'Mild asthma')->set('condiciones.0.estado', 'controlada')
            ->call('agregarMedicamento')->set('medicamentos.0.nombre', 'Salbutamol')->set('medicamentos.0.dosis_texto', '100 mcg')
            ->call('agregarAntecedente')->set('antecedentes.0.parentesco', 'madre')->set('antecedentes.0.condicion', 'Hypertension')->set('antecedentes.0.edad_aparicion', '55')
            ->call('siguiente')->assertSet('paso', 4)
            ->call('agregarContacto')->set('contactos.0.nombre', 'Erik')->set('contactos.0.parentesco', 'pareja')->set('contactos.0.telefono', '+46 70 123 45 67')
            ->call('siguiente')->assertSet('paso', 5)
            ->assertSee('Nora Lindqvist')->assertSee('Penicillin')->assertSee('Record complete at');

        $t->call('guardar')->assertHasNoErrors()->assertDispatched('tripulante-cambiado')->assertRedirect(route('tripulacion'));

        $a = Astronauta::where('nombre', 'Nora Lindqvist')->firstOrFail();
        $this->assertSame($mission->id, $a->mision_id);
        $this->assertSame('ESA', $a->agencia);
        $this->assertSame('O+', $a->grupo_sanguineo);
        $this->assertEquals(62.5, $a->peso_kg);
        $this->assertSame($a->id, session('astronauta_id'));
        $this->assertSame('Nora Lindqvist', session('tripulante_creado'));

        $e = $a->expediente;
        $this->assertNotNull($e);
        $this->assertSame('apto', $e->aptitud_vuelo);
        $this->assertSame(58, (int) $e->fc_reposo);
        $this->assertSame('ocasional', $e->alcohol);
        $this->assertFalse((bool) $e->fumador);
        $this->assertEquals(116, $e->linea_base['presion_arterial']);
        $this->assertSame('Penicillin', $a->alergias->first()->sustancia);
        $this->assertSame('controlada', $a->condiciones->first()->estado);
        $this->assertSame('Salbutamol', $a->medicamentos->first()->nombre);
        $this->assertSame('madre', $a->antecedentes->first()->parentesco);
        $this->assertTrue($a->contactos->first()->principal);
    }

    public function test_saving_returns_to_the_first_step_on_errors(): void
    {
        $this->expedition();
        $before = Astronauta::count();

        Livewire::test(NuevoTripulante::class)->set('paso', 5)
            ->set('nombre', 'No Data')
            ->call('guardar')
            ->assertSet('paso', 1)->assertHasErrors(['fecha_nacimiento', 'sexo'])->assertNoRedirect();

        $this->assertSame($before, Astronauta::count());
    }

    public function test_cancel_redirects_to_crew_without_saving(): void
    {
        $this->expedition();
        $before = Astronauta::count();

        $this->step1(Livewire::test(NuevoTripulante::class))->call('cancelar')->assertRedirect(route('tripulacion'));

        $this->assertSame($before, Astronauta::count());
    }

    public function test_the_crew_screen_shows_the_link_and_the_success_notice(): void
    {
        $this->expedition();

        Livewire::test(Tripulacion::class)
            ->assertSee('New crew member')
            ->assertSeeHtml('href="'.route('tripulacion.nuevo').'"')
            ->assertDontSee('registered successfully');

        session()->flash('tripulante_creado', 'Nora Lindqvist');
        Livewire::test(Tripulacion::class)->assertSee('Nora Lindqvist')->assertSee('registered successfully');
    }

    public function test_route_responds(): void
    {
        $this->expedition();

        $this->get(route('tripulacion.nuevo'))->assertOk()->assertSee('New crew member');
    }

    public function test_error_summary_lists_each_field_with_its_link_and_focus_goes_to_the_summary(): void
    {
        $this->expedition();

        $t = Livewire::test(NuevoTripulante::class)->call('siguiente');
        $t->assertDispatched('nt-foco', destino: 'errores')
            ->assertSee('Review the 3 marked fields')
            ->assertSeeHtml('href="#nt-nombre"')
            ->assertSeeHtml('href="#nt-fecha_nacimiento"')
            ->assertSeeHtml('id="resumen-errores"');
        $keys = collect($t->get('erroresResumen'))->pluck('clave')->all();
        $this->assertContains('nombre', $keys);
        $this->assertSame('Date of birth', collect($t->get('erroresResumen'))->firstWhere('clave', 'fecha_nacimiento')['etiqueta']);
    }

    public function test_changing_step_focuses_the_title_and_marks_the_current_step(): void
    {
        $this->expedition();

        $t = $this->step1(Livewire::test(NuevoTripulante::class))->call('siguiente');
        $t->assertSet('paso', 2)
            ->assertDispatched('nt-foco', destino: 'titulo')
            ->assertSeeHtml('id="paso-titulo"')
            ->assertSeeHtml('aria-current="step"')
            ->assertSee('Measurements, habits, and resting values before the mission.');
        $this->assertSame([1 => 'completo', 2 => 'actual', 3 => 'pendiente', 4 => 'pendiente', 5 => 'pendiente'], $t->get('estadosPasos'));
    }

    public function test_summary_shows_initials_age_bmi_with_tone_and_completeness(): void
    {
        $this->expedition();

        $t = Livewire::test(NuevoTripulante::class)->set('nombre', 'nora lindqvist ruiz')
            ->set('altura_m', '1,80')->set('peso_kg', '75');
        $this->assertSame('NL', $t->get('iniciales'));
        $this->assertSame('ok', $t->get('tonoImc')['estado']);
        $t->set('peso_kg', '100');
        $this->assertSame('crit', $t->get('tonoImc')['estado']);
        $t->set('peso_kg', '55');
        $this->assertSame('warn', $t->get('tonoImc')['estado']);
        $t->set('altura_m', '');
        $this->assertNull($t->get('tonoImc'));
        $this->assertSame('··', $t->set('nombre', '')->get('iniciales'));
        $t->assertSee('Record summary')->assertSee('Role not set')->assertSeeHtml('role="progressbar"');
    }

    public function test_blocks_use_the_notice_panel(): void
    {
        $this->expedition(5);
        Livewire::test(NuevoTripulante::class)->assertSeeHtml('nt-bloqueo')->assertSee('Back to Crew')->assertDontSeeHtml('class="nt-form"');
    }
}
