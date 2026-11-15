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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class RegistrarTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulacion()
    {
        Event::fake([MedicionRegistrada::class]); // sin Reverb en pruebas
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $t = $m->tripulantes()->orderBy('id')->get();
        session(['astronauta_id' => $t->first()->id]);

        return $t->first();
    }

    private function con(\Livewire\Features\SupportTesting\Testable $c, array $valores)
    {
        foreach ($valores as $k => $v) {
            $c->set('campos.'.$k, $v);
        }

        return $c;
    }

    public function test_la_ruta_registrar_carga_la_pantalla(): void
    {
        $this->tripulacion();
        $this->get('/registrar')->assertOk()->assertSeeLivewire(Registrar::class)
            ->assertSee('¿Dónde sientes dolor?')->assertSee('Nueva medición');
    }

    public function test_estado_vacio_diez_indicadores_cuatro_vistas_y_guardar_deshabilitado(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class)
            ->assertSee('Toca un músculo para marcar una zona con dolor')
            ->assertSee('0 zonas marcadas')
            ->assertSee('0 mediciones · 0 zonas de dolor · 0 críticas')
            ->assertSee('Escribe al menos una medición o marca una zona con dolor')
            ->assertSee('Guardar registro')
            ->assertSeeHtml('aria-disabled="true"')
            ->assertSee('Esta herramienta orienta, no diagnostica');
        foreach (['Frontal', 'Costado der.', 'Costado izq.', 'De atrás'] as $v) {
            $c->assertSee($v);
        }
        foreach (config('panel.indicadores') as $cfg) {
            $c->assertSee($cfg['etiqueta'])->assertSee($cfg['rango']);
        }
        foreach (['Mareo', 'Dolor de cabeza', 'Náuseas', 'Falta de aire', 'Fatiga', 'Alteraciones del sueño'] as $s) {
            $c->assertSee($s);
        }
        $c->assertSee('Manual')->assertSee('Dispositivo')->assertSee('Simulado');
    }

    public function test_marcar_y_desmarcar_zona_usa_ids_reales_y_resalta_la_silueta(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class)
            ->call('alternarZona', 'pectoral_mayor_izq')
            ->assertSet('zonas.pectoral_mayor_izq', ['int' => 5, 'tipo' => 'Punzante', 'desde' => 'Hoy'])
            ->assertSee('Pectoral mayor izquierdo')->assertSee('1 zona marcada')->assertSee('5/10 · Moderado')
            ->assertSeeHtml('aria-label="Quitar Pectoral mayor izquierdo"')
            ->assertSee('Limpiar todo');
        $this->assertStringContainsString('is-activo', $c->html());
        $c->call('alternarZona', 'pectoral_mayor_izq')->assertSet('zonas', [])->assertDontSee('Limpiar todo');
        // Un id que no existe en MusculosCuerpo se ignora.
        $c->call('alternarZona', 'frontal_pecho_pectoral_mayor_izq')->call('alternarZona', 'no_existe')->assertSet('zonas', []);
        $this->assertContains('pectoral_mayor_izq', MusculosCuerpo::ids());
    }

    public function test_intensidad_tipo_desde_quitar_y_limpiar(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class)
            ->call('alternarZona', 'vasto_lateral_izq')->call('alternarZona', 'biceps_der')
            ->call('fijarIntensidad', 'vasto_lateral_izq', 8)->call('fijarTipo', 'vasto_lateral_izq', 'Calambre')
            ->call('fijarDesde', 'vasto_lateral_izq', 'Más de 3 días')
            ->assertSet('zonas.vasto_lateral_izq', ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días'])
            ->assertSee('8/10 · Fuerte');
        // Valores fuera de lista o de rango no se aceptan.
        $c->call('fijarIntensidad', 'vasto_lateral_izq', 11)->call('fijarTipo', 'vasto_lateral_izq', 'Raro')
            ->call('fijarDesde', 'vasto_lateral_izq', 'Ayer')->call('fijarIntensidad', 'no_marcada', 3)
            ->assertSet('zonas.vasto_lateral_izq', ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días']);
        $c->call('alternarZona', 'biceps_der')->assertSet('zonas', ['vasto_lateral_izq' => ['int' => 8, 'tipo' => 'Calambre', 'desde' => 'Más de 3 días']]);
        $c->call('limpiarZonas')->assertSet('zonas', [])->assertSee('Toca un músculo');
    }

    public function test_helpers_de_intensidad(): void
    {
        $this->assertSame('Leve', ZonaDolor::palabra(3));
        $this->assertSame('Moderado', ZonaDolor::palabra(6));
        $this->assertSame('Fuerte', ZonaDolor::palabra(8));
        $this->assertSame('Muy fuerte', ZonaDolor::palabra(10));
        $this->assertSame('oklch(0.68 0.17 150)', ZonaDolor::color(1));
        $this->assertSame('oklch(0.68 0.17 25)', ZonaDolor::color(10));
        $this->assertSame(['ok', 'warn', 'crit'], [ZonaDolor::estado(3), ZonaDolor::estado(4), ZonaDolor::estado(8)]);
    }

    public function test_validacion_en_vivo_con_insignias_y_mensajes(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['frecuencia_cardiaca' => '64'])->assertSee('BIEN')->assertSee('Dentro de tu rango');
        $this->con($c, ['frecuencia_cardiaca' => '110'])->assertSee('REVISAR')->assertSee('110 supera tu máximo de 100');
        $this->con($c, ['frecuencia_cardiaca' => '180'])->assertSee('CRÍTICO');
        $this->con($c, ['frecuencia_cardiaca' => '300'])->assertSee('ERROR')->assertSee('Valor fuera de lo posible, revisa la medición')
            ->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('aria-describedby="msg-frecuencia_cardiaca"');
        $this->con($c, ['frecuencia_cardiaca' => 'abc'])->assertSee('Introduce un número');
        $this->con($c, ['frecuencia_cardiaca' => '', 'temperatura' => '36,6'])->assertSee('BIEN');
        $this->con($c, ['temperatura' => '', 'perdida_osea' => "\u{2212}1,6"])->assertSee('Pérdida de 1.6 supera tu límite de 1 %/mes');
    }

    public function test_presion_arterial_con_dos_campos(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['pa_s' => '128'])->assertSee('Introduce sistólica y diastólica');
        $this->con($c, ['pa_d' => '84'])->assertSee('REVISAR')->assertSee('128/84 supera tu máximo de 120/80');
        $this->con($c, ['pa_s' => '116', 'pa_d' => '76'])->assertSee('Dentro de tu rango')->assertSee('1 medición · 0 zonas de dolor · 0 críticas');
        $this->con($c, ['pa_s' => '300', 'pa_d' => '84'])->assertSee('Valor fuera de lo posible');
    }

    public function test_con_errores_no_se_guarda_y_el_resumen_lo_dice(): void
    {
        $a = $this->tripulacion();
        $antes = Medicion::where('astronauta_id', $a->id)->count();
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['temperatura' => '80', 'frecuencia_cardiaca' => '64'])
            ->assertSee('Corrige 1 valor antes de guardar')
            ->call('guardar')->assertSet('guardado', null);
        $this->assertSame($antes, Medicion::where('astronauta_id', $a->id)->count());
        $this->assertSame(0, RegistroSalud::count());
    }

    public function test_guardar_valores_normales_persiste_registro_zonas_sintomas_y_origen(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', true);
        $alertas = AlertaSaliente::count();
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['pa_s' => '116', 'pa_d' => '76', 'frecuencia_cardiaca' => '64', 'saturacion_o2' => '98', 'temperatura' => '36.7'])
            ->call('fijarOrigen', 'dispositivo')
            ->call('alternarZona', 'deltoides_anterior_der')->call('fijarIntensidad', 'deltoides_anterior_der', 3)
            ->call('fijarTipo', 'deltoides_anterior_der', 'Rigidez')->call('fijarDesde', 'deltoides_anterior_der', '1–3 días')
            ->call('alternarSintoma', 'Fatiga')->call('alternarSintoma', 'Mareo')->call('alternarSintoma', 'Mareo')
            ->set('nota', '  Tirantez al despertar  ')
            ->assertSee('Guardar registro')->assertDontSee('Guardar y avisar a tierra')
            ->assertSee('4 mediciones · 1 zona de dolor · 0 críticas')
            ->call('guardar')
            ->assertSee('Registro guardado')->assertSee('Ver sugerencias en el Panel')->assertSee('Nuevo registro')
            ->assertSee('4 mediciones guardadas y 1 zona de dolor guardada')
            ->assertSee('Deltoides anterior derecho · rigidez')->assertSee('3/10')
            ->assertDispatched('medicion-guardada', astronautaId: $a->id);

        $reg = RegistroSalud::where('astronauta_id', $a->id)->latest('id')->firstOrFail();
        $this->assertSame('dispositivo', $reg->origen);
        $this->assertSame(['Fatiga'], $reg->sintomas);
        $this->assertSame('Tirantez al despertar', $reg->nota);
        $this->assertFalse($reg->aviso_tierra);
        $z = $reg->zonas()->firstOrFail();
        $this->assertSame([$a->id, 'deltoides_anterior_der', 3, 'Rigidez', '1–3 días'], [$z->astronauta_id, $z->musculo, $z->intensidad, $z->tipo, $z->desde]);
        $m = Medicion::where('astronauta_id', $a->id)->where('indicador', 'presion_arterial')->latest('id')->first();
        $this->assertSame('dispositivo', $m->origen);
        $this->assertEquals(116, $m->valores['sistolica']);
        $this->assertSame($reg->registrado_en->timestamp, $m->medido_en->timestamp);
        $this->assertSame($alertas, AlertaSaliente::count());
    }

    public function test_critico_sin_enlace_encola_el_aviso_pendiente(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', false);
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['temperatura' => '39.5'])
            ->call('alternarZona', 'vasto_lateral_izq')->call('fijarIntensidad', 'vasto_lateral_izq', 6)
            ->assertSee('SIN ENLACE')->assertSee('Guardar y encolar aviso')
            ->assertSee('Sin enlace con tierra: se guardará en el dispositivo y el aviso quedará en cola')
            ->call('guardar')
            ->assertSee('Guardado en el dispositivo')->assertSee('Sin enlace con tierra. Se enviará automáticamente');
        $alerta = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $alerta->estado);
    }

    public function test_critico_con_enlace_muestra_guardar_y_avisar_y_encola(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', true);
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['temperatura' => '39.5'])
            ->call('alternarZona', 'vasto_lateral_izq')->call('fijarIntensidad', 'vasto_lateral_izq', 6)
            ->assertSee('Guardar y avisar a tierra')->assertSee('respuesta de tierra en ~')
            ->assertSee('1 medición · 1 zona de dolor · 1 crítica')
            ->call('guardar')
            ->assertSee('Tierra ha recibido el aviso.')->assertSee('CRÍTICO');
        $alerta = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertNull($alerta->evaluacion_id);
        $this->assertSame('Vasto lateral izquierdo', $alerta->carga['zonas_dolor'][0]['zona']);
        $this->assertTrue(RegistroSalud::where('astronauta_id', $a->id)->latest('id')->first()->aviso_tierra);
    }

    public function test_dolor_intenso_sin_mediciones_tambien_avisa(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', false);
        Livewire::test(Registrar::class)
            ->call('alternarZona', 'recto_femoral_der')->call('fijarIntensidad', 'recto_femoral_der', 8)
            ->assertSee('Guardar y encolar aviso')->assertSee('0 mediciones · 1 zona de dolor · 0 críticas')
            ->call('guardar')->assertSee('Guardado en el dispositivo');
        $alerta = AlertaSaliente::where('astronauta_id', $a->id)->where('carga->origen', 'registro')->latest('id')->firstOrFail();
        $this->assertSame('pendiente', $alerta->estado);
        $this->assertSame(8, $alerta->carga['zonas_dolor'][0]['intensidad']);
        $this->assertNull($alerta->carga['valores']);
    }

    public function test_dolor_leve_solo_guarda_sin_aviso(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', true);
        $antes = AlertaSaliente::count();
        Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der')->call('fijarIntensidad', 'biceps_der', 7)
            ->assertSee('Guardar registro')->call('guardar')->assertSee('Registro guardado');
        $this->assertSame($antes, AlertaSaliente::count());
        $this->assertSame(1, ZonaDolor::where('astronauta_id', $a->id)->count());
    }

    public function test_medicion_duplicada_muestra_el_error_en_su_tarjeta_y_no_guarda_nada(): void
    {
        $a = $this->tripulacion();
        Cache::forever('tierra.enlace', true);
        $c = Livewire::test(Registrar::class);
        $this->con($c, ['frecuencia_cardiaca' => '71'])->call('guardar')->assertSee('Registro guardado');
        $n = RegistroSalud::count();
        $c->call('nuevoRegistro')->assertSet('guardado', null);
        $this->con($c, ['frecuencia_cardiaca' => '71', 'temperatura' => '36.6'])->call('alternarZona', 'biceps_der')
            ->call('guardar')->assertSet('guardado', null)->assertSee('Medición duplicada')->assertDispatched('registro-foco-error');
        $this->assertSame($n, RegistroSalud::count());
        $this->assertSame(0, ZonaDolor::where('astronauta_id', $a->id)->where('musculo', 'biceps_der')->count());
    }

    public function test_cancelar_navega_al_panel_y_nuevo_registro_vacia(): void
    {
        $this->tripulacion();
        Livewire::test(Registrar::class)->call('cancelar')->assertRedirect(route('panel'));
        $c = Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der')->call('guardar')->assertSet('guardado.nZonas', 1)
            ->assertSeeHtml(route('panel'))->call('nuevoRegistro')->assertSet('zonas', [])->assertSet('guardado', null);
        $c->assertSee('Guardar registro');
    }

    public function test_indicador_en_la_url_se_valida_y_se_expone_para_enfocar(): void
    {
        $this->tripulacion();
        Livewire::withQueryParams(['indicador' => 'glucosa'])->test(Registrar::class)->assertSet('indicador', 'glucosa')->assertSeeHtml('data-enfocar="glucosa"');
        Livewire::withQueryParams(['indicador' => 'inventado'])->test(Registrar::class)->assertSet('indicador', '');
    }

    public function test_cambiar_de_tripulante_reinicia_el_formulario(): void
    {
        $this->tripulacion();
        $c = Livewire::test(Registrar::class)->call('alternarZona', 'biceps_der');
        $this->con($c, ['glucosa' => '100'])->dispatch('tripulante-cambiado')->assertSet('zonas', [])->assertSet('campos.glucosa', '');
    }

    public function test_el_panel_enlaza_a_registrar_y_el_evento_abrir_registro_sigue_existiendo(): void
    {
        $this->tripulacion();
        // Con la medición de hoy pendiente (3.er tripulante del seeder) el Panel ofrece "Registrar medición".
        session(['astronauta_id' => TripulanteActual::tripulacion()->get(2)->id]);
        $this->get('/')->assertOk()->assertSee(route('registrar'), false)->assertDontSee('livewire:registrar-medicion', false);
        $this->get('/')->assertSee("Livewire.on('abrir-registro'", false);
    }

    public function test_analisis_registro_compartido(): void
    {
        $an = AnalisisRegistro::make();
        $this->assertSame(['valor' => -1.6], $an->valorSimple('perdida_osea', 1.6));
        $this->assertSame(['error' => AnalisisRegistro::IMPOSIBLE, 'estado' => null], $an->evaluar('temperatura', ['valor' => 80]));
        $this->assertSame('crit', $an->evaluar('temperatura', ['valor' => 39.5])['estado']);
        $this->assertSame('35.5 está por debajo de tu mínimo de 36.1', $an->mensaje('temperatura', ['valor' => 35.5], 'warn'));
        $this->assertSame('85/50 está por debajo de tu mínimo de 90/60', $an->mensaje('presion_arterial', ['sistolica' => 85, 'diastolica' => 50], 'warn'));
        $this->assertNull($an->numero('abc'));
        $this->assertSame(-0.5, $an->numero("\u{2212}0,5"));
    }
}
