<?php

namespace Tests\Feature;

use App\Livewire\ContextoMision;
use App\Models\Mision;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ContextoMisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_muestra_el_contexto_de_la_mision(): void
    {
        $this->seed(ExpedicionSeeder::class);
        session(['mision_id' => Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail()->id]);

        Livewire::test(ContextoMision::class)
            ->assertSee('Expedición 74')
            ->assertSee('Estación Orbital')
            ->assertSee('LÍNEA DE TIEMPO', false)
            ->assertSee('PRÓXIMOS EVENTOS', false)
            ->assertSee('5 a bordo');
    }
}
