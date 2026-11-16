<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class GuiaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);
    }

    public function test_muestra_la_guia_con_los_14_indicadores_y_el_aviso(): void
    {
        Livewire::test(Guia::class)
            ->assertSee('Cómo afecta el espacio a tu cuerpo')
            ->assertSee('Pérdida ósea (cadera)')
            ->assertSee('Orienta, no diagnostica')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 14 && collect($t)->every(fn ($x) => $x['texto'] !== '' && $x['rec'] !== ''));
    }

    public function test_cada_marcador_del_catalogo_tiene_contenido_en_la_guia(): void
    {
        $this->assertSame(array_keys(config('indicadores.marcadores')), array_keys(config('guia.marcadores')));
    }

    public function test_sin_tripulante_sigue_mostrando_el_contenido(): void
    {
        session()->forget(['astronauta_id', 'mision_id']);
        Livewire::test(Guia::class)->assertSee('Cómo afecta el espacio a tu cuerpo');
    }
}
