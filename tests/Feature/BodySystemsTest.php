<?php

namespace Tests\Feature;

use App\Livewire\Sistemas;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class SistemasTest extends TestCase
{
    use DatabaseTransactions;

    private function fijarTripulante(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);
    }

    public function test_muestra_una_tarjeta_por_sistema_con_titulo_y_aviso(): void
    {
        $this->fijarTripulante();
        Livewire::test(Sistemas::class)
            ->assertSee('Sistemas corporales')
            ->assertSee('Cardiovascular')
            ->assertSee('Sensorial y vestibular')
            ->assertSee('Sin cobertura todavía')
            ->assertSee('Orienta, no diagnostica')
            ->assertViewHas('tarjetas', fn ($t) => count($t) === 11);
    }

    public function test_cada_marcador_del_catalogo_esta_en_un_solo_grupo(): void
    {
        $claves = collect(config('sistemas.grupos'))->pluck('marcadores')->flatten()->all();
        $this->assertEqualsCanonicalizing(array_keys(config('indicadores.marcadores')), $claves);
    }

    public function test_el_color_y_la_pildora_de_cada_tarjeta_son_el_peor_estado(): void
    {
        $this->fijarTripulante();
        Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($tarjetas) {
            foreach ($tarjetas as $t) {
                if ($t['sin_cobertura']) {
                    continue; // sin indicadores: no hay "peor estado" (se prueba en SistemasFuenteUnicaTest)
                }
                $orden = collect($t['filas'])->pluck('estado')->map(fn ($e) => Sistemas::ESTADOS[$e][2]);
                $peor = array_search($orden->min(), array_column(Sistemas::ESTADOS, 2), true);
                if ($t['estado'] !== array_keys(Sistemas::ESTADOS)[$peor] || $t['estado_txt'] !== Sistemas::ESTADOS[$t['estado']][0]) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_las_filas_enlazan_al_indicador_con_su_id(): void
    {
        $this->fijarTripulante();
        $c = Livewire::test(Sistemas::class)->assertViewHas('tarjetas', function ($tarjetas) {
            $filas = collect($tarjetas)->pluck('filas')->flatten(1);

            return $filas->count() === 14
                && $filas->every(fn ($f) => $f['url'] === route('indicador', ['id' => $f['clave']]));
        });
        $c->assertSeeHtml('href="'.route('indicador', ['id' => 'presion_arterial']).'"');
        $c->assertSeeHtml('href="'.route('indicador', ['id' => 'vision_sans']).'"');
    }

    public function test_sin_tripulante_muestra_el_estado_vacio(): void
    {
        session()->forget(['astronauta_id', 'mision_id']);
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Astronauta::query()->delete(); // dentro de la transacción de la prueba: se revierte
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        Livewire::test(Sistemas::class)
            ->assertSee('Sin tripulante seleccionado')
            ->assertViewHas('tarjetas', []);
    }
}
