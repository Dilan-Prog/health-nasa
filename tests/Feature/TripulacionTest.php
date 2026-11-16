<?php

namespace Tests\Feature;

use App\Livewire\Tripulacion;
use App\Models\Astronauta;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class TripulacionTest extends TestCase
{
    use DatabaseTransactions;

    private function expedicion(): Mision
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        // Aísla el test de tripulantes extra que existan en la BD local (se revierte con la transacción).
        Astronauta::where('mision_id', $mision->id)->whereNotIn('nombre', array_column(ExpedicionSeeder::TRIPULACION, 'nombre'))->delete();
        session(['mision_id' => $mision->id]);

        return $mision;
    }

    public function test_la_ruta_responde_con_la_pantalla(): void
    {
        $this->expedicion();

        $this->get(route('tripulacion'))->assertOk()->assertSee('Expedición 74 · 5 tripulantes', false);
    }

    public function test_muestra_los_tripulantes_de_la_mision_con_perfil_y_estado_de_medicion(): void
    {
        $this->expedicion();

        Livewire::test(Tripulacion::class)
            ->assertSee('Dilan García')->assertSee('Elena Moreau')->assertSee('Kenji Arai')
            ->assertSee('Amara Osei')->assertSee('Lucas Ferreira')
            ->assertSee('Especialista de misión · 36 años')
            ->assertSee('1.78 m')->assertSee('73.4 kg')->assertSee('A+')
            ->assertSee('MEDIDO 07:31')->assertSee('PENDIENTE')
            ->assertSee('Abrir panel de Kenji Arai · Medición pendiente de hoy')
            ->assertSee('Abrir panel de Dilan García · Medición realizada · 07:31 UTC');
    }

    public function test_los_conteos_son_los_del_resumen(): void
    {
        $this->expedicion();

        // Dilan: 2 críticos, 1 a revisar, 7 bien (Nivel 2: radiación y dosis pasan a normal).
        Livewire::test(Tripulacion::class)
            ->assertSeeInOrder(['Dilan García', '2 Crítico', '1 Revisar', '7 Bien', 'Elena Moreau'])
            // Amara: todo en rango, sin chips de crítico/revisar.
            ->assertSeeInOrder(['Amara Osei', '10 Bien', 'Lucas Ferreira']);
    }

    public function test_clic_en_la_tarjeta_fija_el_tripulante_y_redirige_al_panel(): void
    {
        $mision = $this->expedicion();
        $kenji = Astronauta::where('mision_id', $mision->id)->where('nombre', 'Kenji Arai')->firstOrFail();

        Livewire::test(Tripulacion::class)
            ->call('abrir', $kenji->id)
            ->assertDispatched('tripulante-cambiado')
            ->assertRedirect(route('panel'));

        $this->assertSame($kenji->id, session('astronauta_id'));
    }

    public function test_no_abre_un_tripulante_ajeno_a_la_mision(): void
    {
        $this->expedicion();
        $otra = Mision::create(['nombre' => 'Otra misión', 'entorno' => 'estacion']);
        $ajeno = Astronauta::create(['mision_id' => $otra->id, 'nombre' => 'Zeta Zeta', 'mision' => 'X', 'entorno' => 'estacion']);

        Livewire::test(Tripulacion::class)->call('abrir', $ajeno->id)->assertNoRedirect();

        $this->assertNotSame($ajeno->id, session('astronauta_id'));
    }

    public function test_cambiar_la_vista_de_una_tarjeta_no_cambia_el_tripulante(): void
    {
        $mision = $this->expedicion();
        $ids = Astronauta::where('mision_id', $mision->id)->orderBy('id')->pluck('id');
        session(['astronauta_id' => $ids[0]]);

        Livewire::test(Tripulacion::class)
            ->call('cambiarVista', $ids[2], 'posterior')
            ->assertSet('vistas', [$ids[2] => 'posterior'])
            ->assertNoRedirect()
            ->assertNotDispatched('tripulante-cambiado')
            ->call('cambiarVista', $ids[2], 'inventada')
            ->assertSet('vistas', [$ids[2] => 'posterior']);

        $this->assertSame($ids[0], session('astronauta_id'));
    }

    public function test_misiones_sin_tripulantes_muestran_el_estado_vacio(): void
    {
        Astronauta::query()->update(['mision_id' => null]); // dentro de la transacción: se revierte
        Livewire::test(Tripulacion::class)->assertSee('aún no tiene tripulantes');
    }

    public function test_estado_vacio_con_enlace_para_crear_tripulante(): void
    {
        Astronauta::query()->update(['mision_id' => null]);

        Livewire::test(Tripulacion::class)
            ->assertSee('Esta misión aún no tiene tripulantes')
            ->assertSee(route('tripulacion.nuevo'), false);
    }

    public function test_el_aviso_de_alta_resalta_la_tarjeta_y_se_puede_cerrar(): void
    {
        $mision = $this->expedicion();
        $kenji = Astronauta::where('mision_id', $mision->id)->where('nombre', 'Kenji Arai')->firstOrFail();
        session(['astronauta_id' => $kenji->id]);
        session()->flash('tripulante_creado', 'Kenji Arai');

        Livewire::test(Tripulacion::class)
            ->assertSee('Tripulante creado')
            ->assertSee('Tripulante «Kenji Arai» registrado correctamente.', false)
            ->assertSet('nuevoId', $kenji->id)
            ->assertSee('is-nueva')
            ->call('cerrarAviso')
            ->assertDontSee('Tripulante creado');
    }

    public function test_sin_flash_no_hay_aviso_ni_resalte(): void
    {
        $this->expedicion();

        Livewire::test(Tripulacion::class)->assertSet('aviso', null)->assertSet('nuevoId', null)->assertDontSee('is-nueva');
    }

    public function test_cada_tarjeta_enlaza_al_expediente_si_existe_la_ruta(): void
    {
        $mision = $this->expedicion();
        if (! Route::has('tripulacion.ficha')) {
            $this->markTestSkipped('La ruta tripulacion.ficha aún no existe.');
        }
        $kenji = Astronauta::where('mision_id', $mision->id)->where('nombre', 'Kenji Arai')->firstOrFail();

        Livewire::test(Tripulacion::class)
            ->assertSee(route('tripulacion.ficha', $kenji), false)
            ->assertSee('Expediente de Kenji Arai');
    }

    public function test_con_la_tripulacion_completa_el_boton_queda_desactivado(): void
    {
        $this->expedicion(); // el seeder crea 5 = máximo

        Livewire::test(Tripulacion::class)
            ->assertSee('Tripulación completa')
            ->assertSee('5 / 5 tripulantes')
            ->assertDontSee('Nuevo tripulante');
    }
}
