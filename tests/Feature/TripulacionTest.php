<?php

namespace Tests\Feature;

use App\Livewire\Tripulacion;
use App\Models\Astronauta;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        // Dilan: 3 críticos, 2 a revisar, 5 bien (valores del diseño).
        Livewire::test(Tripulacion::class)
            ->assertSeeInOrder(['Dilan García', '3 Crítico', '2 Revisar', '5 Bien', 'Elena Moreau'])
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
}
