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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. Hora fijada: domingo 15 nov 2026, 10:15 UTC. */
class RutinasTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $dilan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:15:00', 'UTC'));
        $this->seed(ExpedicionSeeder::class);
        $this->seed(RutinasSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        $this->dilan = Astronauta::where('mision_id', $mision->id)->where('nombre', 'Dilan García')->firstOrFail();
        TripulanteActual::fijar($this->dilan->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rutina(string $nombre): Rutina
    {
        return Rutina::where('astronauta_id', $this->dilan->id)->where('nombre', 'like', $nombre.'%')->firstOrFail();
    }

    public function test_la_ruta_responde_con_cabecera_y_semana_real(): void
    {
        $this->get(route('rutinas'))->assertOk()
            ->assertSee('Rutinas', false)->assertSee('Tripulante Dilan García')
            ->assertSee('DOMINGO 15 NOV 2026 · DÍA 142 · 10:15 UTC', false)
            ->assertSee('Semana · 9–15 nov', false)->assertSee('Registrar actividad')
            ->assertSee('Orienta, no diagnostica');
    }

    public function test_semana_con_fechas_reales_y_hoy_resaltado(): void
    {
        $c = Livewire::test(Rutinas::class)
            ->assertSee('LUN')->assertSee('MAR')->assertSee('MIÉ')->assertSee('JUE')->assertSee('VIE')->assertSee('SÁB')->assertSee('HOY');
        $html = $c->html();
        $this->assertStringContainsString('LUN 9 de nov', $html);
        $this->assertStringContainsString('DOM 15 de nov', $html);
        $this->assertMatchesRegularExpression('/is-sel is-hoy|is-hoy is-sel/', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
    }

    public function test_semana_que_cruza_de_mes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-02 08:00:00', 'UTC')); // miércoles; lunes = 30 nov
        Livewire::test(Rutinas::class)->assertSee('Semana · 30 nov–6 dic', false);
    }

    public function test_agenda_de_hoy_con_estados_derivados_de_la_hora(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee('Agenda de hoy')->assertSee('2 de 8 hechas')
            ->assertSee('Sesión ARED 45 min')->assertSee('HECHA')
            ->assertSee('OMITIDA')       // estiramientos 08:15
            ->assertSee('ATRASADA')      // radiación 09:00 (ya pasó y no se hizo)
            ->assertSee('PENDIENTE')     // 21:00 aún no
            ->assertSee('Marcar hecha Revisar dosis de radiación')
            ->assertSee('Deshacer Sesión ARED 45 min')->assertSee('Retomar Estiramientos 10 min');
    }

    public function test_el_estado_pendiente_no_se_guarda_y_depende_de_la_hora(): void
    {
        $fuerza = $this->rutina('Rutina de fuerza');
        $this->assertSame('11:00', $fuerza->hora);
        Livewire::test(Rutinas::class)->assertSee('Marcar hecha Rutina de fuerza');
        $this->assertSame(0, RutinaEjecucion::where('rutina_id', $fuerza->id)->whereDate('fecha', '2026-11-15')->count());

        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', 'UTC')); // ahora ya pasó las 11:00
        $c = Livewire::test(Rutinas::class);
        $html = $c->html();
        $this->assertMatchesRegularExpression('/est-late[^>]*>(?:(?!<\/li>).)*Rutina de fuerza/s', $html);
    }

    public function test_tarjetas_de_progreso_del_dia(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee('EJERCICIO')->assertSee('45')->assertSee('/ 150 min')->assertSee('Faltan 105 min')
            ->assertSee('HIDRATACIÓN')->assertSee('1.4')->assertSee('/ 2.5 L')
            ->assertSee('SUEÑO')->assertSee('6.2')->assertSee('Por debajo de tu meta')
            ->assertSee('MEDICIONES')->assertSee('1/2')->assertSee('1 pendiente');
    }

    public function test_marcar_hecha_crea_ejecucion_y_actividad_y_actualiza_progreso(): void
    {
        $r = $this->rutina('Rutina de fuerza');
        Livewire::test(Rutinas::class)->call('marcar', $r->id)
            ->assertSee('3 de 8 hechas')->assertSee('Deshacer Rutina de fuerza')->assertSee('Faltan 75 min');

        $this->assertDatabaseHas('rutina_ejecuciones', ['rutina_id' => $r->id, 'estado' => 'done']);
        $this->assertSame(1, Actividad::where('rutina_id', $r->id)->where('origen', 'recordatorio')->whereDate('inicio_en', '2026-11-15')->count());
    }

    public function test_deshacer_y_omitir_y_retomar(): void
    {
        $r = $this->rutina('Revisar dosis');
        $c = Livewire::test(Rutinas::class)->call('marcar', $r->id)->assertSee('Deshacer Revisar dosis');
        $c->call('deshacer', $r->id)->assertSee('Marcar hecha Revisar dosis');
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $r->id, 'fecha' => '2026-11-15']);

        $c->call('omitir', $r->id)->assertSee('Retomar Revisar dosis');
        $this->assertDatabaseHas('rutina_ejecuciones', ['rutina_id' => $r->id, 'estado' => 'skipped']);
        $c->call('deshacer', $r->id)->assertSee('Marcar hecha Revisar dosis');
    }

    public function test_marcar_hecha_una_medicion_no_crea_actividad_de_diario(): void
    {
        $r = $this->rutina('Revisar dosis');
        Livewire::test(Rutinas::class)->call('marcar', $r->id)->assertSee('2/2')->assertSee('Todo al día');
        $this->assertSame(0, Actividad::where('rutina_id', $r->id)->count());
    }

    public function test_no_se_puede_marcar_un_dia_futuro_ni_una_rutina_ajena(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'UTC')); // miércoles
        $r = $this->rutina('Registrar ánimo');
        RutinaEjecucion::where('rutina_id', $r->id)->whereDate('fecha', '>=', '2026-11-12')->delete(); // el seeder ya rellenó hasta el 15
        $c = Livewire::test(Rutinas::class)->call('seleccionarDia', '2026-11-13')->assertSee('Agenda · vie 13');
        $c->call('marcar', $r->id);
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $r->id, 'fecha' => '2026-11-13']);

        $otro = Astronauta::where('nombre', 'Elena Moreau')->firstOrFail();
        $ajena = Rutina::where('astronauta_id', $otro->id)->firstOrFail();
        RutinaEjecucion::where('rutina_id', $ajena->id)->delete();
        $c->call('seleccionarDia', '2026-11-11')->call('marcar', $ajena->id);
        $this->assertDatabaseMissing('rutina_ejecuciones', ['rutina_id' => $ajena->id, 'fecha' => '2026-11-11']);
    }

    public function test_seleccionar_otro_dia_de_la_semana_y_valores_invalidos(): void
    {
        $c = Livewire::test(Rutinas::class)->call('seleccionarDia', '2026-11-10')
            ->assertSet('dia', '2026-11-10')->assertSee('Agenda · mar 10');
        $c->call('seleccionarDia', '2030-01-01')->assertSet('dia', '')->assertSee('Agenda de hoy');
        $c->call('seleccionarDia', 'basura')->assertSet('dia', '');
    }

    public function test_por_que_importa_usa_el_estado_real_de_los_indicadores(): void
    {
        // Dilan: pérdida ósea y masa muscular en crítico.
        Livewire::test(Rutinas::class)
            ->assertSee('Por qué importa')
            ->assertSee('Pérdida ósea (cadera)')->assertSee('Masa muscular')->assertSee('CRÍTICO')
            ->assertSee('Completa hoy tu sesión de carga (ARED)')
            ->assertSee('Sesión ARED 45 min · HECHA')
            ->assertSee('Rutina de fuerza de piernas 30 min · PENDIENTE')
            ->assertDontSee('Presión arterial');
    }

    public function test_por_que_importa_prioriza_el_peor_estado_de_otro_tripulante(): void
    {
        // Kenji: presión crítica (142/92), pérdida ósea y masa muscular en precaución, radiación normal.
        $kenji = Astronauta::where('nombre', 'Kenji Arai')->firstOrFail();
        TripulanteActual::fijar($kenji->id);
        Livewire::test(Rutinas::class)
            ->assertSee('Tripulante Kenji Arai')->assertSee('Presión arterial')->assertSee('Mide tu presión en reposo')
            ->assertSee('PRECAUCIÓN')->assertDontSee('Radiación diaria');
    }

    public function test_cambiar_de_tripulante_refresca_la_pantalla(): void
    {
        $c = Livewire::test(Rutinas::class)->assertSee('Tripulante Dilan García');
        $elena = Astronauta::where('nombre', 'Elena Moreau')->firstOrFail();
        TripulanteActual::fijar($elena->id);
        $c->call('seleccionarDia', '2026-11-10')->dispatch('tripulante-cambiado')
            ->assertSet('dia', '')->assertSet('modal', null)->assertSee('Tripulante Elena Moreau')->assertSee('Agenda de hoy');
    }

    public function test_historial_reciente_con_origen_y_cantidades(): void
    {
        Livewire::test(Rutinas::class)
            ->assertSee('Historial reciente')
            ->assertSee('1400 ml')->assertSee('Sesión ARED 45 min')->assertSee('Recordatorio')
            ->assertSee('6 h 12 min')->assertSee('Hoy · 22:40→04:52')->assertSee('Manual');
    }

    public function test_registrar_actividad_valida_y_guarda(): void
    {
        Livewire::test(Rutinas::class)->call('abrirActividad')->assertSet('modal', 'actividad')
            ->set('aCantidad', '')->call('guardarActividad')->assertHasErrors(['aCantidad'])->assertSee('Indica la duración')
            ->set('aCantidad', 'abc')->call('guardarActividad')->assertHasErrors(['aCantidad'])
            ->set('aCantidad', '999')->call('guardarActividad')->assertHasErrors(['aCantidad'])->assertSee('Máximo 720 min')
            ->set('aTipo', 'Hidratación')->set('aCantidad', '3500')->call('guardarActividad')->assertSee('Máximo 3000 ml')
            ->set('aHora', '')->set('aCantidad', '500')->call('guardarActividad')->assertHasErrors(['aHora'])
            ->set('aHora', '09:30')->call('guardarActividad')->assertHasNoErrors()->assertSet('modal', null)
            ->assertSee('1.9');

        $this->assertDatabaseHas('actividades', ['astronauta_id' => $this->dilan->id, 'tipo' => 'Hidratación', 'cantidad' => 500, 'unidad' => 'ml', 'origen' => 'manual']);
    }

    public function test_actividad_de_ejercicio_suma_a_la_meta_del_dia(): void
    {
        Livewire::test(Rutinas::class)->call('abrirActividad')
            ->set('aTipo', 'Cardio')->set('aCantidad', '30')->set('aHora', '10:00')->set('aInt', 'Alta')->set('aNotas', 'Cicloergómetro')
            ->call('guardarActividad')->assertHasNoErrors()
            ->assertSee('Faltan 75 min')->assertSee('Cardio · Cicloergómetro');
        $this->assertDatabaseHas('actividades', ['tipo' => 'Cardio', 'intensidad' => 'Alta', 'cantidad' => 30]);
    }

    public function test_anadir_rutina_valida_y_aparece_en_la_agenda(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')->assertSet('modal', 'rutina')
            ->call('guardarRutina')->assertHasErrors(['rNombre', 'rHora', 'rCantidad'])
            ->set('rNombre', 'Yoga suave')->set('rHora', '17:30')->set('rCantidad', '20')->set('rDias', [])
            ->call('guardarRutina')->assertHasErrors(['rDias'])->assertSee('Elige al menos un día')
            ->set('rDias', ['6', '7'])->set('rTipo', 'Otro')->set('rIcono', 'self_improvement')
            ->call('guardarRutina')->assertHasNoErrors()->assertSet('modal', null)
            ->assertSee('Yoga suave')->assertSee('Sáb · Dom');

        $r = Rutina::where('astronauta_id', $this->dilan->id)->where('nombre', 'Yoga suave')->firstOrFail();
        $this->assertSame([6, 7], $r->dias);
        $this->assertSame('17:30', $r->hora);
        $this->assertSame('bien', $r->kind);
        $this->assertTrue($r->activa);
    }

    public function test_rutina_de_ejercicio_exige_duracion_y_hora_con_formato(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')
            ->set('rNombre', 'Pesas')->set('rHora', '25:99')->set('rCantidad', '0')
            ->call('guardarRutina')->assertHasErrors(['rHora', 'rCantidad']);
    }

    public function test_editar_y_eliminar_rutina_solo_propia(): void
    {
        $r = $this->rutina('Estiramientos');
        Livewire::test(Rutinas::class)->call('abrirRutina', $r->id)
            ->assertSet('rNombre', 'Estiramientos 10 min')->assertSet('rHora', '08:15')->assertSet('rDias', ['1', '2', '3', '4', '5', '6', '7'])
            ->set('rNombre', 'Estiramientos 15 min')->set('rCantidad', '15')->set('rDias', ['1', '3', '5'])
            ->call('guardarRutina')->assertHasNoErrors()->assertSee('Estiramientos 15 min')->assertSee('Lun · Mié · Vie');

        $r->refresh();
        $this->assertSame('ej', $r->kind); // se conserva la clase de la rutina del plan
        $this->assertSame([1, 3, 5], $r->dias);

        $otra = Rutina::where('astronauta_id', Astronauta::where('nombre', 'Elena Moreau')->value('id'))->firstOrFail();
        $c = Livewire::test(Rutinas::class)->call('abrirRutina', $otra->id)->assertSet('modal', null);
        $c->call('eliminarRutina', $otra->id);
        $this->assertDatabaseHas('rutinas', ['id' => $otra->id]);

        $c->call('eliminarRutina', $r->id);
        $this->assertDatabaseMissing('rutinas', ['id' => $r->id]);
    }

    public function test_cerrar_modal(): void
    {
        Livewire::test(Rutinas::class)->call('abrirRutina')->assertSet('modal', 'rutina')->call('cerrar')->assertSet('modal', null);
    }

    public function test_estado_vacio_sin_rutinas(): void
    {
        Rutina::where('astronauta_id', $this->dilan->id)->delete();
        Livewire::test(Rutinas::class)
            ->assertSee('Aún no tienes rutinas')->assertSee('Añadir rutina')
            ->assertSee('Todavía no hay rutinas en tu plan.');
    }

    public function test_estado_de_error_y_reintento(): void
    {
        Livewire::test(Rutinas::class)->set('error', true)
            ->assertSee('No se pudieron cargar tus rutinas')->assertSee('Reintentar')
            ->call('reintentar')->assertSet('error', false)->assertDontSee('No se pudieron cargar tus rutinas')->assertSee('Agenda de hoy');
    }

    public function test_sin_tripulante_muestra_aviso(): void
    {
        Astronauta::query()->update(['mision_id' => null]);
        Mision::query()->delete();
        Livewire::test(Rutinas::class)->assertSee('No hay un tripulante seleccionado');
    }

    public function test_accesibilidad_basica(): void
    {
        $this->get(route('rutinas'))
            ->assertSee('role="tablist"', false)->assertSee('role="tab"', false)->assertSee('role="progressbar"', false)
            ->assertSee('aria-labelledby="rut-sem-t"', false)->assertSee('aria-label="Día de la semana"', false)
            ->assertSee('Marcar hecha Revisar dosis de radiación', false);
    }

    public function test_seeder_idempotente_y_completo(): void
    {
        $this->seed(RutinasSeeder::class);
        $this->seed(RutinasSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        foreach (Astronauta::where('mision_id', $mision->id)->get() as $a) {
            $this->assertSame(8, Rutina::where('astronauta_id', $a->id)->count(), $a->nombre);
            $this->assertSame(1, Rutina::where('astronauta_id', $a->id)->where('indicador_clave', 'perdida_osea')->count());
            $this->assertGreaterThan(0, Actividad::where('astronauta_id', $a->id)->count());
        }
        $this->assertSame(5, Astronauta::where('mision_id', $mision->id)->count());
        $this->assertSame(1, Actividad::where('astronauta_id', $this->dilan->id)->where('tipo', 'Sueño')->count());
    }
}
