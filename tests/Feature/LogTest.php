<?php

namespace Tests\Feature;

use App\Events\MedicionRegistrada;
use App\Livewire\Registrar;
use App\Models\AlertaSaliente;
use App\Models\Medicion;
use App\Models\Mision;
use App\Models\RegistroSalud;
use App\Models\ZonaDolor;
use App\Services\Salud\AnalisisRegistro;
use App\Support\MusculosCuerpo;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never deletes data from the local database. */
class LogTest extends TestCase
{
    use DatabaseTransactions;

    private function crew()
    {
        Event::fake([MedicionRegistrada::class]); // no Reverb in tests
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $crew = $m->tripulantes()->orderBy('id')->get();
        session(['astronauta_id' => $crew->first()->id]);

        return $crew->first();
    }

    private function with(\Livewire\Features\SupportTesting\Testable $c, array $values)
    {
        foreach ($values as $k => $v) {
            $c->set('campos.'.$k, $v);
        }

        return $c;
    }

    public function test_the_log_route_loads_the_screen(): void
    {
        $this->crew();
        $this->get('/registrar')->assertOk()->assertSeeLivewire(Registrar::class)
            ->assertSee('Where do you feel pain?')->assertSee('New measurement');
    }

    public function test_empty_state_ten_indicators_four_views_and_save_disabled(): void
    {
        $this->crew();
        $c = Livewire::test(Registrar::class)
            ->assertSee('Tap a muscle to mark a pain zone')
            ->assertSee('0 zones marked')
            ->assertSee('0 measurements · 0 pain zones · 0 critical')
            ->assertSee('Enter at least one measurement or mark a pain zone')
            ->assertSee('Save log entry')
            ->assertSeeHtml('aria-disabled="true"')
            ->assertSee('This tool provides guidance only; it does not diagnose');
        foreach (['Front', 'Right side', 'Left side', 'Back'] as $v) {
            $c->assertSee($v);
        }
        foreach (config('panel.indicadores') as $cfg) {
            $c->assertSee($cfg['etiqueta'])->assertSee($cfg['rango']);
        }
        foreach (['Dizziness', 'Headache', 'Nausea', 'Shortness of breath', 'Fatigue', 'Sleep disturbances'] as $s) {
            $c->assertSee($s);
        }
        $c->assertSee('Manual')->assertSee('Device')->assertSee('Simulated');
    }

    public function test_marking_and_unmarking_a_zone_uses_real_ids_and_highlights_the_silhouette(): void
    {
        $this->crew();
        $name = MusculosCuerpo::nombreEn('pectoral_mayor_izq');
        $c = Livewire::test(Registrar::class)
            ->call('alternarZona', 'pectoral_mayor_izq')
            ->assertSet('zonas.pectoral_mayor_izq', ['int' => 5, 'tipo' => 'Punzante', 'desde' => 'Hoy'])
            ->assertSee($name)->assertSee('1 zone marked')->assertSee('5/10 · Moderate')
            ->assertSeeHtml('aria-label="Remove '.$name.'"')
            ->assertSee('Clear all');
        $this->assertStringContainsString('is-activo', $c->html());
        $c->call('alternarZona', 'pectoral_mayor_izq')->assertSet('zonas', [])->assertDontSee('Clear all');
        // An id that does not exist in MusculosCuerpo is ignored.
        $c->call('alternarZona', 'frontal_pecho_pectoral_mayor_izq')->call('alternarZona', 'no_existe')->assertSet('zonas', []);
        $this->assertContains('pectoral_mayor_izq', MusculosCuerpo::ids());
    }

    public function test_intensity_type_onset_remove_and_clear(): void
    {
        $this->crew();
        $c = Livewire::test(Registrar::class)
            ->call('alternarZona', 'vasto_lateral_izq')->call('alternarZona', 'biceps_der')
            ->call('fijarIntensidad', 'vasto_lateral_izq', 8)->call('fijarTipo', 'vasto_lateral_izq', 'Calambre')
            ->call('fijarDesde', 'vasto_lateral_izq', 'Más de 3 días')
            ->assertSet('zonas.vasto_lateral_izq', ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días'])
            ->assertSee('8/10 · Strong');
        // Values outside the list or range are not accepted.
        $c->call('fijarIntensidad', 'vasto_lateral_izq', 11)->call('fijarTipo', 'vasto_lateral_izq', 'Raro')
            ->call('fijarDesde', 'vasto_lateral_izq', 'Ayer')->call('fijarIntensidad', 'no_marcada', 3)
            ->assertSet('zonas.vasto_lateral_izq', ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días']);
        $c->call('alternarZona', 'biceps_der')->assertSet('zonas', ['vasto_lateral_izq' => ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días']]);
        $c->call('limpiarZonas')->assertSet('zonas', [])->assertSee('Tap a muscle');
    }

    public function test_intensity_helpers(): void
    {
        // ZonaDolor::palabra() is the Spanish original; the English display word lives in the Registrar component.
        $this->assertSame('Leve', ZonaDolor::palabra(3));
        $this->assertSame('Moderado', ZonaDolor::palabra(6));
        $this->assertSame('Fuerte', ZonaDolor::palabra(8));
        $this->assertSame('Muy fuerte', ZonaDolor::palabra(10));
        $screen = new Registrar;
        $this->assertSame(['Mild', 'Moderate', 'Strong', 'Very strong'], [$screen->palabraDolor(3), $screen->palabraDolor(6), $screen->palabraDolor(8), $screen->palabraDolor(10)]);
        $this->assertSame('oklch(0.68 0.17 150)', ZonaDolor::color(1));
        $this->assertSame('oklch(0.68 0.17 25)', ZonaDolor::color(10));
        $this->assertSame(['ok', 'warn', 'crit'], [ZonaDolor::estado(3), ZonaDolor::estado(4), ZonaDolor::estado(8)]);
    }

    public function test_live_validation_with_badges_and_messages(): void
    {
        $this->crew();
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['frecuencia_cardiaca' => '64'])->assertSee('NORMAL')->assertSee('Within your range');
        $this->with($c, ['frecuencia_cardiaca' => '110'])->assertSee('CAUTION')->assertSee('110 exceeds your maximum of 100');
        $this->with($c, ['frecuencia_cardiaca' => '180'])->assertSee('CRITICAL');
        $this->with($c, ['frecuencia_cardiaca' => '300'])->assertSee('ERROR')->assertSee('Value outside the possible range')
            ->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('aria-describedby="msg-frecuencia_cardiaca"');
        $this->with($c, ['frecuencia_cardiaca' => 'abc'])->assertSee('Enter a number');
        $this->with($c, ['frecuencia_cardiaca' => '', 'temperatura' => '36,6'])->assertSee('NORMAL');
        $this->with($c, ['temperatura' => '', 'perdida_osea' => "\u{2212}1,6"])->assertSee('Loss of 1.6 exceeds your limit of 1 %/month');
    }

    public function test_blood_pressure_with_two_fields(): void
    {
        $this->crew();
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['pa_s' => '128'])->assertSee('Enter systolic and diastolic');
        $this->with($c, ['pa_d' => '84'])->assertSee('CAUTION')->assertSee('128/84 exceeds your maximum of 120/80');
        $this->with($c, ['pa_s' => '116', 'pa_d' => '76'])->assertSee('Within your range')->assertSee('1 measurement · 0 pain zones · 0 critical');
        $this->with($c, ['pa_s' => '300', 'pa_d' => '84'])->assertSee('Value outside the possible range');
    }

    public function test_with_errors_nothing_is_saved_and_the_summary_says_so(): void
    {
        $a = $this->crew();
        $before = Medicion::where('astronauta_id', $a->id)->count();
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['temperatura' => '80', 'frecuencia_cardiaca' => '64'])
            ->assertSee('Fix 1 value before saving')
            ->call('guardar')->assertSet('guardado', null);
        $this->assertSame($before, Medicion::where('astronauta_id', $a->id)->count());
        $this->assertSame(0, RegistroSalud::count());
    }

    public function test_saving_normal_values_persists_entry_zones_symptoms_and_origin(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', true);
        $alerts = AlertaSaliente::count();
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['pa_s' => '116', 'pa_d' => '76', 'frecuencia_cardiaca' => '64', 'saturacion_o2' => '98', 'temperatura' => '36.7'])
            ->call('fijarOrigen', 'dispositivo')
            ->call('alternarZona', 'deltoides_anterior_der')->call('fijarIntensidad', 'deltoides_anterior_der', 3)
            ->call('fijarTipo', 'deltoides_anterior_der', 'Rigidez')->call('fijarDesde', 'deltoides_anterior_der', '1–3 días')
            ->call('alternarSintoma', 'Fatiga')->call('alternarSintoma', 'Mareo')->call('alternarSintoma', 'Mareo')
            ->set('nota', '  Tirantez al despertar  ')
            ->assertSee('Save log entry')->assertDontSee('Save and alert ground')
            ->assertSee('4 measurements · 1 pain zone · 0 critical')
            ->call('guardar')
            ->assertSee('Log entry saved')->assertSee('View suggestions on the Dashboard')->assertSee('New log entry')
            ->assertSee('4 measurements saved and 1 pain zone saved')
            ->assertSee(MusculosCuerpo::nombreEn('deltoides_anterior_der').' · '.strtolower(ZonaDolor::etiquetaTipo('Rigidez')))->assertSee('3/10')
            ->assertDispatched('medicion-guardada', astronautaId: $a->id);

        $entry = RegistroSalud::where('astronauta_id', $a->id)->latest('id')->firstOrFail();
        $this->assertSame('dispositivo', $entry->origen);
        $this->assertSame(['Fatiga'], $entry->sintomas);
        $this->assertSame('Tirantez al despertar', $entry->nota);
        $this->assertFalse($entry->aviso_tierra);
        $z = $entry->zonas()->firstOrFail();
        $this->assertSame([$a->id, 'deltoides_anterior_der', 3, 'Rigidez', '1–3 días'], [$z->astronauta_id, $z->musculo, $z->intensidad, $z->tipo, $z->desde]);
        $m = Medicion::where('astronauta_id', $a->id)->where('indicador', 'presion_arterial')->latest('id')->first();
        $this->assertSame('dispositivo', $m->origen);
        $this->assertEquals(116, $m->valores['sistolica']);
        $this->assertSame($entry->registrado_en->timestamp, $m->medido_en->timestamp);
        $this->assertSame($alerts, AlertaSaliente::count());
    }

    public function test_critical_without_link_queues_the_pending_alert(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', false);
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['temperatura' => '39.5'])
            ->call('alternarZona', 'vasto_lateral_izq')->call('fijarIntensidad', 'vasto_lateral_izq', 6)
            ->assertSee('NO LINK')->assertSee('Save and queue alert')
            ->assertSee('No link to the ground: it will be saved on the device and the alert will be queued')
            ->call('guardar')
            ->assertSee('Saved on the device')->assertSee('No link to the ground. It will be sent automatically');
        $alert = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $alert->estado);
    }

    public function test_critical_with_link_shows_save_and_alert_and_queues(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', true);
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['temperatura' => '39.5'])
            ->call('alternarZona', 'vasto_lateral_izq')->call('fijarIntensidad', 'vasto_lateral_izq', 6)
            ->assertSee('Save and alert ground')->assertSee('ground response in ~')
            ->assertSee('1 measurement · 1 pain zone · 1 critical')
            ->call('guardar')
            ->assertSee('The ground has received the alert.')->assertSee('CRITICAL');
        $alert = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertNull($alert->evaluacion_id);
        $this->assertSame(ZonaDolor::nombre('vasto_lateral_izq'), $alert->carga['zonas_dolor'][0]['zona']);
        $this->assertTrue(RegistroSalud::where('astronauta_id', $a->id)->latest('id')->first()->aviso_tierra);
    }

    public function test_severe_pain_without_measurements_also_alerts(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', false);
        Livewire::test(Registrar::class)
            ->call('alternarZona', 'recto_femoral_der')->call('fijarIntensidad', 'recto_femoral_der', 8)
            ->assertSee('Save and queue alert')->assertSee('0 measurements · 1 pain zone · 0 critical')
            ->call('guardar')->assertSee('Saved on the device');
        $alert = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $alert->estado);
        $this->assertSame(8, $alert->carga['zonas_dolor'][0]['intensidad']);
        $this->assertNull($alert->carga['valores']);
    }

    public function test_mild_pain_only_saves_without_an_alert(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', true);
        $before = AlertaSaliente::count();
        Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der')->call('fijarIntensidad', 'biceps_der', 7)
            ->assertSee('Save log entry')->call('guardar')->assertSee('Log entry saved');
        $this->assertSame($before, AlertaSaliente::count());
        $this->assertSame(1, ZonaDolor::where('astronauta_id', $a->id)->count());
    }

    public function test_duplicate_measurement_shows_the_error_on_its_card_and_saves_nothing(): void
    {
        $a = $this->crew();
        Cache::forever('tierra.enlace', true);
        $c = Livewire::test(Registrar::class);
        $this->with($c, ['frecuencia_cardiaca' => '71'])->call('guardar')->assertSee('Log entry saved');
        $n = RegistroSalud::count();
        $c->call('nuevoRegistro')->assertSet('guardado', null);
        $this->with($c, ['frecuencia_cardiaca' => '71', 'temperatura' => '36.6'])->call('alternarZona', 'biceps_der')
            ->call('guardar')->assertSet('guardado', null)->assertSee('Duplicate measurement')->assertDispatched('registro-foco-error');
        $this->assertSame($n, RegistroSalud::count());
        $this->assertSame(0, ZonaDolor::where('astronauta_id', $a->id)->where('musculo', 'biceps_der')->count());
    }

    public function test_cancel_navigates_to_the_dashboard_and_new_entry_empties_the_form(): void
    {
        $this->crew();
        Livewire::test(Registrar::class)->call('cancelar')->assertRedirect(route('panel'));
        $c = Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der')->call('guardar')->assertSet('guardado.nZonas', 1)
            ->assertSeeHtml(route('panel'))->call('nuevoRegistro')->assertSet('zonas', [])->assertSet('guardado', null);
        $c->assertSee('Save log entry');
    }

    public function test_indicator_in_the_url_is_validated_and_exposed_for_focus(): void
    {
        $this->crew();
        Livewire::withQueryParams(['indicador' => 'glucosa'])->test(Registrar::class)->assertSet('indicador', 'glucosa')->assertSeeHtml('data-enfocar="glucosa"');
        Livewire::withQueryParams(['indicador' => 'inventado'])->test(Registrar::class)->assertSet('indicador', '');
    }

    public function test_switching_crew_member_resets_the_form(): void
    {
        $this->crew();
        $c = Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der');
        $this->with($c, ['glucosa' => '100'])->dispatch('tripulante-cambiado')->assertSet('zonas', [])->assertSet('campos.glucosa', '');
    }

    public function test_dashboard_links_to_the_log_and_the_open_log_event_still_exists(): void
    {
        $this->crew();
        // With today's measurement pending (3rd seeded crew member) the Dashboard offers "Log measurement".
        session(['astronauta_id' => TripulanteActual::tripulacion()->get(2)->id]);
        $this->get('/')->assertOk()->assertSee(route('registrar'), false)->assertDontSee('livewire:registrar-medicion', false);
        $this->get('/')->assertSee("Livewire.on('abrir-registro'", false);
    }

    public function test_shared_log_analysis(): void
    {
        $an = AnalisisRegistro::make();
        $this->assertSame(['valor' => -1.6], $an->valorSimple('perdida_osea', 1.6));
        $this->assertSame(['error' => AnalisisRegistro::IMPOSIBLE, 'estado' => null], $an->evaluar('temperatura', ['valor' => 80]));
        $this->assertSame('crit', $an->evaluar('temperatura', ['valor' => 39.5])['estado']);
        $this->assertSame('35.5 is below your minimum of 36.1', $an->mensaje('temperatura', ['valor' => 35.5], 'warn'));
        $this->assertSame('85/50 is below your minimum of 90/60', $an->mensaje('presion_arterial', ['sistolica' => 85, 'diastolica' => 50], 'warn'));
        $this->assertNull($an->numero('abc'));
        $this->assertSame(-0.5, $an->numero("\u{2212}0,5"));
    }
}
