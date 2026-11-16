<?php

namespace Tests\Feature;

use App\Livewire\ColaTierra;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Mision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class GroundQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lists_alerts_with_status_and_summary(): void
    {
        $m = Mision::create(['nombre' => 'Expedition CT', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(3), 'duracion_dias' => 30]);
        $a = Astronauta::create(['mision_id' => $m->id, 'nombre' => 'Zoe Queue', 'mision' => 'CT', 'entorno' => 'estacion']);
        AlertaSaliente::create(['astronauta_id' => $a->id, 'estado' => 'pendiente',
            'carga' => ['indicador' => 'saturacion_o2', 'valor' => '88 %', 'motivo' => 'Low saturation']]);
        AlertaSaliente::create(['astronauta_id' => $a->id, 'estado' => 'enviada', 'enviada_en' => now(),
            'carga' => ['indicador' => 'pulse', 'valor' => '150 bpm']]);

        Livewire::test(ColaTierra::class)
            ->assertSee('Zoe Queue')
            ->assertSee('Pending')
            ->assertSee('Sent')
            ->assertSee('saturacion_o2')
            ->assertSee('Low saturation')
            ->assertSee('150 bpm');
    }

    public function test_queue_without_alerts_shows_empty_state(): void
    {
        AlertaSaliente::query()->delete();
        Livewire::test(ColaTierra::class)->assertSee('No alerts in the queue.');
    }

    public function test_shows_link_status_and_route_responds(): void
    {
        $this->get('/cola-a-tierra')->assertOk()->assertSee('Alerts to ground');
    }
}
