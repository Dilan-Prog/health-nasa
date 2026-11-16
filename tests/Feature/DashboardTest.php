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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class PanelTest extends TestCase
{
    use DatabaseTransactions;

    private function tripulacion()
    {
        $this->seed(ExpedicionSeeder::class);
        $m = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $m->id]);
        $t = $m->tripulantes()->orderBy('id')->get();
        session(['astronauta_id' => $t->first()->id]);

        return $t;
    }

    public function test_muestra_los_diez_indicadores_y_los_datos_del_tripulante(): void
    {
        $this->tripulacion();
        $t = Livewire::test(Panel::class)->assertSee('Dilan García')->assertSee('128/84')->assertSee('1.78 m')->assertSee('A+');
        foreach (config('panel.indicadores') as $cfg) {
            $t->assertSee($cfg['etiqueta']);
        }
        $this->assertSame(10, substr_count($t->html(), 'class="panel-fila '));
    }

    public function test_seleccionar_fija_sel_resalta_musculos_y_alterna(): void
    {
        $this->tripulacion();
        $t = Livewire::test(Panel::class)
            ->call('seleccionar', 'glucosa')
            ->assertSet('sel', 'glucosa')
            ->assertSee('En la figura: Glucosa')
            ->assertSeeHtml('aria-pressed="true"');
        $this->assertSame(config('panel.indicadores.glucosa.musculos'), $t->viewData('musculos'));

        $t->call('seleccionar', 'glucosa')->assertSet('sel', null)
            ->call('seleccionar', 'no_existe')->assertSet('sel', null);
        $this->assertNotEmpty($t->viewData('musculos')); // sin selección: los de las incidencias
    }

    public function test_tarjetas_con_criticos_primero(): void
    {
        $this->tripulacion();
        $est = collect(Livewire::test(Panel::class)->viewData('tarjetas'))->pluck('estado')->all();
        $this->assertSame(['crit', 'crit', 'warn'], $est);
    }

    public function test_autochequeo_rutas_verde_naranja_roja_y_vuelta_atras(): void
    {
        $this->tripulacion();
        $t = Livewire::test(Panel::class)->assertSee('PREGUNTA 1 DE 5');
        foreach (range(1, 5) as $_) {
            $t->call('responder', 'n');
        }
        $t->assertSee('Vigilar')->assertDontSee('Contactar a tierra ahora');

        $t->call('reiniciarChequeo');
        foreach (['y', 'n', 'n', 'n', 'n'] as $r) {
            $t->call('responder', $r);
        }
        $t->assertSee('Aplicar medida');

        $t->call('reiniciarChequeo');
        foreach (['n', 'n', 'y', 'n', 'n'] as $r) { // falta de aire => roja
            $t->call('responder', $r);
        }
        $t->assertSee('Contactar a tierra ahora')->assertSee('Encolar aviso a tierra');

        $t->call('reiniciarChequeo')->call('responder', 'y')->call('preguntaAnterior')->assertSet('respuestas', []);
    }

    public function test_avisar_a_tierra_crea_alerta_real_y_cambia_el_texto(): void
    {
        $this->tripulacion();
        $antes = AlertaSaliente::count();
        $t = Livewire::test(Panel::class)->assertSee('Encolar aviso a tierra')
            ->call('avisarATierra', 'perdida_osea')
            ->assertSee('En cola · se enviará al recuperar enlace');
        $this->assertSame($antes + 1, AlertaSaliente::count());
        $this->assertSame('panel', AlertaSaliente::latest('id')->first()->carga['origen']);

        $t->call('avisarATierra', 'perdida_osea'); // idempotente
        $this->assertSame($antes + 1, AlertaSaliente::count());
    }

    public function test_avisar_a_tierra_funciona_sin_ninguna_evaluacion_previa(): void
    {
        $m = Mision::create(['nombre' => 'PT-SinEval', 'entorno' => 'estacion']);
        $a = \App\Models\Astronauta::create(['mision_id' => $m->id, 'nombre' => 'PT Nuevo', 'mision' => 'PT-SinEval', 'entorno' => 'estacion']);
        session(['mision_id' => $m->id, 'astronauta_id' => $a->id]);

        Livewire::test(Panel::class)
            ->call('avisarATierra', 'perdida_osea')
            ->call('responder', 'y')->call('avisarATierra', 'chk');

        $alertas = AlertaSaliente::where('astronauta_id', $a->id)->get();
        $this->assertCount(2, $alertas);
        $this->assertNull($alertas->first()->evaluacion_id);
        $this->assertSame('PT Nuevo', $alertas->first()->carga['astronauta']);
        $this->assertSame('contactar_tierra', $alertas->first()->carga['accion']);
    }

    public function test_repetir_medicion_selecciona_y_despacha_abrir_registro(): void
    {
        $this->tripulacion();
        Livewire::test(Panel::class)->call('repetirMedicion', 'presion_arterial')
            ->assertSet('sel', 'presion_arterial')->assertDispatched('abrir-registro', indicador: 'presion_arterial');
    }

    public function test_marcar_accion_tacha_el_texto(): void
    {
        $this->tripulacion();
        Livewire::test(Panel::class)->call('alternarAccion', 'presion_arterial', 0)->assertSeeHtml('panel-accion is-hecha');
    }

    public function test_cambiar_de_tripulante_actualiza_y_limpia_la_seleccion(): void
    {
        $t = $this->tripulacion();
        $c = Livewire::test(Panel::class)->call('seleccionar', 'glucosa')->assertSee('Dilan García');
        TripulanteActual::fijar($t[1]->id);
        $c->dispatch('tripulante-cambiado')->assertSee('Elena Moreau')->assertSet('sel', null)->assertDontSee('128/84');
    }

    public function test_estado_vacio_para_tripulante_sin_incidencias(): void
    {
        $t = $this->tripulacion();
        TripulanteActual::fijar($t[3]->id); // Amara Osei
        Livewire::test(Panel::class)->assertSee('Todo en rango, sin acciones pendientes')->assertSee('Medición realizada');
    }

    public function test_la_pagina_responde(): void
    {
        $this->get('/')->assertOk();
    }
}
