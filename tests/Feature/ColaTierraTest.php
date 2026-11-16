<?php

namespace Tests\Feature;

use App\Livewire\ColaTierra;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Mision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ColaTierraTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lista_avisos_con_estado_y_resumen(): void
    {
        $m = Mision::create(['nombre' => 'Expedición CT', 'entorno' => 'estacion', 'inicio_en' => now()->subDays(3), 'duracion_dias' => 30]);
        $a = Astronauta::create(['mision_id' => $m->id, 'nombre' => 'Zoe Cola', 'mision' => 'CT', 'entorno' => 'estacion']);
        AlertaSaliente::create(['astronauta_id' => $a->id, 'estado' => 'pendiente',
            'carga' => ['indicador' => 'saturacion_o2', 'valor' => '88 %', 'motivo' => 'Saturación baja']]);
        AlertaSaliente::create(['astronauta_id' => $a->id, 'estado' => 'enviada', 'enviada_en' => now(),
            'carga' => ['indicador' => 'pulso', 'valor' => '150 lpm']]);

        Livewire::test(ColaTierra::class)
            ->assertSee('Zoe Cola')
            ->assertSee('Pendiente')
            ->assertSee('Enviada')
            ->assertSee('saturacion_o2')
            ->assertSee('Saturación baja')
            ->assertSee('150 lpm');
    }

    public function test_cola_sin_avisos_muestra_vacio(): void
    {
        AlertaSaliente::query()->delete();
        Livewire::test(ColaTierra::class)->assertSee('No hay avisos en la cola.');
    }

    public function test_muestra_estado_del_enlace_y_la_ruta_responde(): void
    {
        $this->get('/cola-a-tierra')->assertOk()->assertSee('Avisos a tierra');
    }
}
