<?php

namespace Tests\Feature;

use App\Livewire\Indicador;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class IndicadorTest extends TestCase
{
    use DatabaseTransactions;

    private Mision $mision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $this->mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $this->mision->id]);
    }

    private function tripulante(string $nombre): Astronauta
    {
        return Astronauta::where('mision_id', $this->mision->id)->where('nombre', $nombre)->firstOrFail();
    }

    public function test_la_ruta_responde_con_la_pantalla_y_el_menu_enlaza(): void
    {
        $this->get(route('indicador'))->assertOk()
            ->assertSee('Orienta, no diagnostica', false)
            ->assertSee('href="/indicador"', false)
            ->assertSee('indicador(', false);
    }

    public function test_carga_con_el_tripulante_actual_y_14_marcadores(): void
    {
        $dilan = $this->tripulante('Dilan García');
        TripulanteActual::fijar($dilan->id);

        Livewire::test(Indicador::class)
            ->assertSee('Tripulante Dilan García')
            ->assertViewHas('astronauta', fn ($a) => $a->id === $dilan->id)
            ->assertViewHas('payload', fn ($p) => count($p['marcadores']) === 14 && isset($p['diaria']['presion_arterial']) && isset($p['horaria']['glucosa']));
    }

    public function test_respeta_la_query_id_y_descarta_valores_invalidos(): void
    {
        Livewire::withQueryParams(['id' => 'glucosa', 'mode' => 'cmp', 'view' => 'heat', 'm' => 'presion_arterial,inventado'])
            ->test(Indicador::class)
            ->assertViewHas('consulta', fn ($c) => $c === ['id' => 'glucosa', 'mode' => 'cmp', 'view' => 'heat', 'm' => 'presion_arterial']);

        Livewire::withQueryParams(['id' => 'no_existe', 'view' => 'x'])
            ->test(Indicador::class)
            ->assertViewHas('consulta', fn ($c) => $c === []);
    }

    public function test_refresca_el_payload_al_cambiar_de_tripulante(): void
    {
        $dilan = $this->tripulante('Dilan García');
        $elena = $this->tripulante('Elena Moreau');
        TripulanteActual::fijar($dilan->id);

        $componente = Livewire::test(Indicador::class)->assertSee('Tripulante Dilan García');

        TripulanteActual::fijar($elena->id);
        $componente->dispatch('tripulante-cambiado')
            ->assertSee('Tripulante Elena Moreau')
            ->assertViewHas('astronauta', fn ($a) => $a->id === $elena->id)
            ->assertViewHas('payload', fn ($p) => count($p['marcadores']) === 14);
    }

    public function test_muestra_el_estado_de_error_y_permite_reintentar(): void
    {
        $this->app->bind(\App\Services\Salud\DatosIndicador::class, fn () => throw new \RuntimeException('falla'));
        // Forzamos el error en la propiedad: no se calcula el payload y se ofrece "Reintentar".
        Livewire::test(Indicador::class)
            ->set('error', true)
            ->assertSee('No se pudieron leer las mediciones')
            ->call('reintentar')
            ->assertSet('error', false);
    }
}
