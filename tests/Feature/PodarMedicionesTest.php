<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Medicion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PodarMedicionesTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Astronauta::create(['nombre' => 'Poda', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function med(string $ind, string $origen, int $diasAtras): Medicion
    {
        $m = $this->a->mediciones()->create([
            'indicador' => $ind, 'valores' => ['valor' => 70], 'medido_en' => now()->subDays($diasAtras), 'origen' => $origen,
        ]);
        Evaluacion::create(['medicion_id' => $m->id, 'estado' => 'normal', 'tendencia' => 'estable', 'accion' => 'ninguno', 'recomendacion' => 'Sin acción requerida.']);

        return $m;
    }

    public function test_por_defecto_solo_simula_y_no_borra(): void
    {
        $this->med('frecuencia_cardiaca', 'simulado', 20);

        $this->artisan('mediciones:podar')->expectsOutputToContain('Mediciones a podar: 1')->assertSuccessful();

        $this->assertSame(1, $this->a->mediciones()->count());
    }

    public function test_con_confirmar_borra_solo_lo_antiguo_de_origen_en_vivo(): void
    {
        $vieja = $this->med('frecuencia_cardiaca', 'simulado', 20);
        $reciente = $this->med('frecuencia_cardiaca', 'simulado', 3);
        $historial = $this->med('frecuencia_cardiaca', 'historial', 40);
        $manual = $this->med('frecuencia_cardiaca', 'manual', 40);

        $this->artisan('mediciones:podar --confirmar')->assertSuccessful();

        $this->assertNull(Medicion::find($vieja->id));
        $this->assertNull(Evaluacion::where('medicion_id', $vieja->id)->first());
        $this->assertNotNull(Medicion::find($reciente->id));
        $this->assertNotNull(Medicion::find($historial->id));
        $this->assertNotNull(Medicion::find($manual->id));
    }

    public function test_no_borra_marcadores_de_largo_plazo_ni_alertas_pendientes(): void
    {
        $largo = $this->med('dosis_acumulada', 'simulado', 30);
        $conAlerta = $this->med('frecuencia_cardiaca', 'reloj', 30);
        AlertaSaliente::create([
            'evaluacion_id' => $conAlerta->evaluacion->id, 'astronauta_id' => $this->a->id,
            'carga' => ['x' => 1], 'estado' => 'pendiente',
        ]);
        $enviada = $this->med('frecuencia_cardiaca', 'reloj', 31);
        AlertaSaliente::create([
            'evaluacion_id' => $enviada->evaluacion->id, 'astronauta_id' => $this->a->id,
            'carga' => ['x' => 1], 'estado' => 'enviada', 'enviada_en' => now(),
        ]);

        $this->artisan('mediciones:podar --confirmar')->assertSuccessful();

        $this->assertNotNull(Medicion::find($largo->id));
        $this->assertNotNull(Medicion::find($conAlerta->id));
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $conAlerta->evaluacion->id)->count());
        $this->assertNull(Medicion::find($enviada->id));
    }

    public function test_respeta_dias_y_origen_indicados_y_el_scope_recientes(): void
    {
        $a10 = $this->med('frecuencia_cardiaca', 'simulado', 10);
        $this->med('frecuencia_cardiaca', 'manual', 10);

        $this->artisan('mediciones:podar --dias=5 --origen=manual --confirmar')->assertSuccessful();

        $this->assertNotNull(Medicion::find($a10->id));
        $this->assertSame(1, $this->a->mediciones()->count());
        $this->assertSame(1, $this->a->mediciones()->recientes(15)->count());
        $this->assertSame(0, $this->a->mediciones()->recientes(5)->count());
    }

    public function test_dias_invalido_falla(): void
    {
        $this->artisan('mediciones:podar --dias=0')->assertFailed();
    }
}
