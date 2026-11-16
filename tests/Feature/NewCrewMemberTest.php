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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class NuevoTripulanteTest extends TestCase
{
    use DatabaseTransactions;

    /** Misión de prueba propia con 2 tripulantes (cupo libre); no depende de los datos de la BD local. */
    private function expedicion(int $tripulantes = 2): Mision
    {
        $mision = Mision::create(['nombre' => 'Misión NT '.uniqid(), 'entorno' => 'estacion', 'inicio_en' => now()->subDays(10), 'duracion_dias' => 180]);
        for ($i = 1; $i <= $tripulantes; $i++) {
            Astronauta::create(['mision_id' => $mision->id, 'mision' => $mision->nombre, 'entorno' => 'estacion', 'nombre' => 'Tripulante '.$i]);
        }
        session(['mision_id' => $mision->id]);

        return $mision;
    }

    public function test_con_el_cupo_lleno_bloquea_el_asistente_y_no_guarda(): void
    {
        $mision = $this->expedicion(5);

        Livewire::test(NuevoTripulante::class)->assertSee('Tripulación completa')->call('guardar');
        $this->assertSame(5, Astronauta::where('mision_id', $mision->id)->count());
        $this->get(route('tripulacion'))->assertSee('Tripulación completa')->assertSee('5 / 5');
    }

    private function paso1(\Livewire\Features\SupportTesting\Testable $t): \Livewire\Features\SupportTesting\Testable
    {
        return $t->set('nombre', 'Nora Lindqvist')->set('fecha_nacimiento', '1988-04-12')->set('sexo', 'femenino')
            ->set('nacionalidad', 'Sueca')->set('agencia', 'ESA')->set('rol', 'Piloto')->set('fecha_ingreso', '2015-06-01');
    }

    public function test_muestra_el_asistente_en_el_paso_uno(): void
    {
        $this->expedicion();

        Livewire::test(NuevoTripulante::class)
            ->assertSet('paso', 1)
            ->assertSee('Paso 1 de 5')->assertSee('Nombre completo')->assertSee('Fecha de nacimiento');
    }

    public function test_sin_mision_muestra_el_bloqueo_y_no_avanza_ni_guarda(): void
    {
        Astronauta::query()->update(['mision_id' => null]); // dentro de la transacción: se revierte
        $antes = Astronauta::count();

        $this->paso1(Livewire::test(NuevoTripulante::class))
            ->assertSee('No hay una misión activa')
            ->call('siguiente')->assertSet('paso', 1)
            ->call('guardar')->assertNoRedirect();

        $this->assertSame($antes, Astronauta::count());
    }

    public function test_el_paso_uno_bloquea_el_avance_si_faltan_datos_obligatorios(): void
    {
        $this->expedicion();

        Livewire::test(NuevoTripulante::class)
            ->call('siguiente')
            ->assertSet('paso', 1)
            ->assertHasErrors(['nombre', 'fecha_nacimiento', 'sexo'])
            ->assertSee('El campo nombre es obligatorio.');
    }

    public function test_rechaza_menores_de_edad_y_valores_fuera_de_rango(): void
    {
        $this->expedicion();

        $t = Livewire::test(NuevoTripulante::class)
            ->set('nombre', 'Joven')->set('fecha_nacimiento', now()->subYears(10)->toDateString())->set('sexo', 'otro')
            ->call('siguiente')->assertSet('paso', 1)->assertHasErrors(['fecha_nacimiento']);

        $this->paso1($t)->call('siguiente')->assertSet('paso', 2)->assertHasNoErrors()
            ->set('altura_m', '5')->call('siguiente')->assertSet('paso', 2)->assertHasErrors(['altura_m']);
    }

    public function test_navegacion_anterior_e_ir_a_paso_solo_a_pasos_validos(): void
    {
        $this->expedicion();

        $t = Livewire::test(NuevoTripulante::class)->call('irAPaso', 3)->assertSet('paso', 1);
        $this->paso1($t)->call('siguiente')->assertSet('paso', 2)->assertSet('pasosValidos', [1])
            ->call('irAPaso', 3)->assertSet('paso', 2)      // el 2 aún no está validado
            ->call('irAPaso', 1)->assertSet('paso', 1)
            ->call('irAPaso', 2)->assertSet('paso', 2)
            ->call('anterior')->assertSet('paso', 1)
            ->call('anterior')->assertSet('paso', 1);
    }

    public function test_la_edad_y_el_imc_se_calculan_en_vivo(): void
    {
        $this->expedicion();
        $nac = now()->subYears(40)->subDays(3)->toDateString();

        $t = Livewire::test(NuevoTripulante::class)->set('fecha_nacimiento', $nac)->set('altura_m', '1,80')->set('peso_kg', '81');

        $this->assertSame(40, $t->get('edad'));
        $this->assertSame(25.0, $t->get('imc'));
        $t->set('fecha_nacimiento', 'no-es-fecha')->set('peso_kg', '');
        $this->assertNull($t->get('edad'));
        $this->assertNull($t->get('imc'));
    }

    public function test_precarga_de_linea_base_con_los_valores_base_y_sin_pisar_lo_escrito(): void
    {
        $this->expedicion();
        $marcadores = config('indicadores.marcadores');

        $t = Livewire::test(NuevoTripulante::class);
        $this->assertSame(array_keys($marcadores), array_keys($t->get('linea_base')));
        $this->assertSame('', $t->get('linea_base.glucosa'));

        $t->set('linea_base.glucosa', '90')->call('precargarLineaBase');
        $lb = $t->get('linea_base');
        $this->assertSame('90', $lb['glucosa']);                 // lo escrito se conserva
        $this->assertSame('36.7', $lb['temperatura']);           // base 36.7, 1 decimal
        $this->assertSame('116', $lb['presion_arterial']);       // base 116, 0 decimales
        foreach ($lb as $v) {
            $this->assertNotSame('', $v);
        }

        $t->call('vaciarLineaBase');
        $this->assertSame('', $t->get('linea_base.glucosa'));
    }

    public function test_listas_repetibles_agregan_y_quitan_filas(): void
    {
        $this->expedicion();

        Livewire::test(NuevoTripulante::class)
            ->call('agregarAlergia')->call('agregarAlergia')->assertCount('alergias', 2)
            ->set('alergias.1.sustancia', 'Penicilina')->call('quitarAlergia', 0)
            ->assertCount('alergias', 1)->assertSet('alergias.0.sustancia', 'Penicilina')
            ->call('agregarCondicion')->assertCount('condiciones', 1)->call('quitarCondicion', 0)->assertCount('condiciones', 0)
            ->call('agregarMedicamento')->assertCount('medicamentos', 1)->assertSet('medicamentos.0.activo', true)
            ->call('quitarMedicamento', 0)->assertCount('medicamentos', 0)
            ->call('agregarAntecedente')->assertCount('antecedentes', 1)->call('quitarAntecedente', 0)->assertCount('antecedentes', 0)
            ->call('quitarAlergia', 99)->assertCount('alergias', 1);
    }

    public function test_las_listas_respetan_el_maximo_configurado(): void
    {
        $this->expedicion();
        $max = config('expediente.max_items.contactos');

        $t = Livewire::test(NuevoTripulante::class);
        for ($i = 0; $i < $max + 3; $i++) {
            $t->call('agregarContacto');
        }
        $t->assertCount('contactos', $max);
    }

    public function test_solo_un_contacto_puede_ser_principal(): void
    {
        $this->expedicion();

        $t = Livewire::test(NuevoTripulante::class)
            ->call('agregarContacto')->call('agregarContacto')->call('agregarContacto')
            ->assertSet('contactos.0.principal', true)->assertSet('contactos.1.principal', false)
            ->call('marcarPrincipal', 2)
            ->assertSet('contactos.0.principal', false)->assertSet('contactos.2.principal', true);

        $this->assertCount(1, array_filter($t->get('contactos'), fn ($c) => $c['principal']));

        // Al quitar el principal, otro lo hereda.
        $t->call('quitarContacto', 2);
        $this->assertCount(1, array_filter($t->get('contactos'), fn ($c) => $c['principal']));
    }

    public function test_el_paso_de_contactos_exige_telefono_y_un_principal(): void
    {
        $this->expedicion();

        Livewire::test(NuevoTripulante::class)->set('paso', 4)
            ->call('agregarContacto')->set('contactos.0.nombre', 'Ana')->set('contactos.0.parentesco', 'pareja')->set('contactos.0.telefono', 'abc')
            ->call('siguiente')->assertSet('paso', 4)->assertHasErrors(['contactos.0.telefono'])
            ->set('contactos.0.telefono', '+34 600 111 222')->call('siguiente')->assertSet('paso', 5)->assertHasNoErrors();
    }

    public function test_flujo_completo_crea_astronauta_y_expediente(): void
    {
        $mision = $this->expedicion();

        $t = $this->paso1(Livewire::test(NuevoTripulante::class))
            ->call('siguiente')->assertSet('paso', 2)
            ->set('altura_m', '1.70')->set('peso_kg', '62,5')->set('grupo_sanguineo', 'O+')->set('evas', '3')
            ->set('fc_reposo', '58')->set('presion_basal_sis', '112')->set('presion_basal_dia', '72')
            ->set('horas_sueno_habituales', '7.5')->set('actividad_fisica_semana_min', '300')->set('fumador', '0')->set('alcohol', 'ocasional')
            ->call('precargarLineaBase')
            ->call('siguiente')->assertSet('paso', 3)
            ->set('aptitud_vuelo', 'apto')->set('ultimo_examen_en', '2026-10-01')
            ->call('agregarAlergia')->set('alergias.0.sustancia', 'Penicilina')->set('alergias.0.gravedad', 'grave')
            ->call('agregarCondicion')->set('condiciones.0.nombre', 'Asma leve')->set('condiciones.0.estado', 'controlada')
            ->call('agregarMedicamento')->set('medicamentos.0.nombre', 'Salbutamol')->set('medicamentos.0.dosis_texto', '100 mcg')
            ->call('agregarAntecedente')->set('antecedentes.0.parentesco', 'madre')->set('antecedentes.0.condicion', 'Hipertensión')->set('antecedentes.0.edad_aparicion', '55')
            ->call('siguiente')->assertSet('paso', 4)
            ->call('agregarContacto')->set('contactos.0.nombre', 'Erik')->set('contactos.0.parentesco', 'pareja')->set('contactos.0.telefono', '+46 70 123 45 67')
            ->call('siguiente')->assertSet('paso', 5)
            ->assertSee('Nora Lindqvist')->assertSee('Penicilina')->assertSee('Expediente completo al');

        $t->call('guardar')->assertHasNoErrors()->assertDispatched('tripulante-cambiado')->assertRedirect(route('tripulacion'));

        $a = Astronauta::where('nombre', 'Nora Lindqvist')->firstOrFail();
        $this->assertSame($mision->id, $a->mision_id);
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
        $this->assertSame('Penicilina', $a->alergias->first()->sustancia);
        $this->assertSame('controlada', $a->condiciones->first()->estado);
        $this->assertSame('Salbutamol', $a->medicamentos->first()->nombre);
        $this->assertSame('madre', $a->antecedentes->first()->parentesco);
        $this->assertTrue($a->contactos->first()->principal);
    }

    public function test_guardar_vuelve_al_primer_paso_con_errores(): void
    {
        $this->expedicion();
        $antes = Astronauta::count();

        Livewire::test(NuevoTripulante::class)->set('paso', 5)
            ->set('nombre', 'Sin Datos')
            ->call('guardar')
            ->assertSet('paso', 1)->assertHasErrors(['fecha_nacimiento', 'sexo'])->assertNoRedirect();

        $this->assertSame($antes, Astronauta::count());
    }

    public function test_cancelar_redirige_a_tripulacion_sin_guardar(): void
    {
        $this->expedicion();
        $antes = Astronauta::count();

        $this->paso1(Livewire::test(NuevoTripulante::class))->call('cancelar')->assertRedirect(route('tripulacion'));

        $this->assertSame($antes, Astronauta::count());
    }

    public function test_la_pantalla_tripulacion_muestra_el_enlace_y_el_aviso_de_exito(): void
    {
        $this->expedicion();

        Livewire::test(Tripulacion::class)
            ->assertSee('Nuevo tripulante')
            ->assertSeeHtml('href="'.route('tripulacion.nuevo').'"')
            ->assertDontSee('registrado correctamente');

        session()->flash('tripulante_creado', 'Nora Lindqvist');
        Livewire::test(Tripulacion::class)->assertSee('Nora Lindqvist')->assertSee('registrado correctamente');
    }

    public function test_la_ruta_responde(): void
    {
        $this->expedicion();

        $this->get(route('tripulacion.nuevo'))->assertOk()->assertSee('Nuevo tripulante');
    }

    public function test_el_resumen_de_errores_lista_cada_campo_con_su_enlace_y_el_foco_va_al_resumen(): void
    {
        $this->expedicion();

        $t = Livewire::test(NuevoTripulante::class)->call('siguiente');
        $t->assertDispatched('nt-foco', destino: 'errores')
            ->assertSee('Revisa los 3 campos marcados')
            ->assertSeeHtml('href="#nt-nombre"')
            ->assertSeeHtml('href="#nt-fecha_nacimiento"')
            ->assertSeeHtml('id="resumen-errores"');
        $claves = collect($t->get('erroresResumen'))->pluck('clave')->all();
        $this->assertContains('nombre', $claves);
        $this->assertSame('Fecha de nacimiento', collect($t->get('erroresResumen'))->firstWhere('clave', 'fecha_nacimiento')['etiqueta']);
    }

    public function test_cambiar_de_paso_enfoca_el_titulo_y_marca_el_paso_actual(): void
    {
        $this->expedicion();

        $t = $this->paso1(Livewire::test(NuevoTripulante::class))->call('siguiente');
        $t->assertSet('paso', 2)
            ->assertDispatched('nt-foco', destino: 'titulo')
            ->assertSeeHtml('id="paso-titulo"')
            ->assertSeeHtml('aria-current="step"')
            ->assertSee('Medidas, hábitos y valores en reposo antes de la misión.');
        $this->assertSame([1 => 'completo', 2 => 'actual', 3 => 'pendiente', 4 => 'pendiente', 5 => 'pendiente'], $t->get('estadosPasos'));
    }

    public function test_el_resumen_muestra_iniciales_edad_imc_con_tono_y_completitud(): void
    {
        $this->expedicion();

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
        $t->assertSee('Resumen del expediente')->assertSee('Rol sin definir')->assertSeeHtml('role="progressbar"');
    }

    public function test_los_bloqueos_usan_el_panel_de_aviso(): void
    {
        $this->expedicion(5);
        Livewire::test(NuevoTripulante::class)->assertSeeHtml('nt-bloqueo')->assertSee('Volver a Tripulación')->assertDontSeeHtml('class="nt-form"');
    }
}
