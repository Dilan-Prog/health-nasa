<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Medicion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PruneMeasurementsTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Astronauta::create(['nombre' => 'Poda', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function measurement(string $indicator, string $origin, int $daysAgo): Medicion
    {
        $m = $this->a->mediciones()->create([
            'indicador' => $indicator, 'valores' => ['valor' => 70], 'medido_en' => now()->subDays($daysAgo), 'origen' => $origin,
        ]);
        Evaluacion::create(['medicion_id' => $m->id, 'estado' => 'normal', 'tendencia' => 'estable', 'accion' => 'ninguno', 'recomendacion' => 'No action required.']);

        return $m;
    }

    public function test_by_default_it_only_simulates_and_deletes_nothing(): void
    {
        $this->measurement('frecuencia_cardiaca', 'simulado', 20);

        $this->artisan('mediciones:podar')->expectsOutputToContain('Measurements to prune: 1')->assertSuccessful();

        $this->assertSame(1, $this->a->mediciones()->count());
    }

    public function test_with_confirm_it_deletes_only_old_live_origin_measurements(): void
    {
        $old = $this->measurement('frecuencia_cardiaca', 'simulado', 20);
        $recent = $this->measurement('frecuencia_cardiaca', 'simulado', 3);
        $history = $this->measurement('frecuencia_cardiaca', 'historial', 40);
        $manual = $this->measurement('frecuencia_cardiaca', 'manual', 40);

        $this->artisan('mediciones:podar --confirmar')->assertSuccessful();

        $this->assertNull(Medicion::find($old->id));
        $this->assertNull(Evaluacion::where('medicion_id', $old->id)->first());
        $this->assertNotNull(Medicion::find($recent->id));
        $this->assertNotNull(Medicion::find($history->id));
        $this->assertNotNull(Medicion::find($manual->id));
    }

    public function test_it_keeps_long_term_markers_and_measurements_with_pending_alerts(): void
    {
        $longTerm = $this->measurement('dosis_acumulada', 'simulado', 30);
        $withAlert = $this->measurement('frecuencia_cardiaca', 'reloj', 30);
        AlertaSaliente::create([
            'evaluacion_id' => $withAlert->evaluacion->id, 'astronauta_id' => $this->a->id,
            'carga' => ['x' => 1], 'estado' => 'pendiente',
        ]);
        $sent = $this->measurement('frecuencia_cardiaca', 'reloj', 31);
        AlertaSaliente::create([
            'evaluacion_id' => $sent->evaluacion->id, 'astronauta_id' => $this->a->id,
            'carga' => ['x' => 1], 'estado' => 'enviada', 'enviada_en' => now(),
        ]);

        $this->artisan('mediciones:podar --confirmar')->assertSuccessful();

        $this->assertNotNull(Medicion::find($longTerm->id));
        $this->assertNotNull(Medicion::find($withAlert->id));
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $withAlert->evaluacion->id)->count());
        $this->assertNull(Medicion::find($sent->id));
    }

    public function test_it_honors_the_given_days_and_origin_and_the_recent_scope(): void
    {
        $a10 = $this->measurement('frecuencia_cardiaca', 'simulado', 10);
        $this->measurement('frecuencia_cardiaca', 'manual', 10);

        $this->artisan('mediciones:podar --dias=5 --origen=manual --confirmar')->assertSuccessful();

        $this->assertNotNull(Medicion::find($a10->id));
        $this->assertSame(1, $this->a->mediciones()->count());
        $this->assertSame(1, $this->a->mediciones()->recientes(15)->count());
        $this->assertSame(0, $this->a->mediciones()->recientes(5)->count());
    }

    public function test_invalid_days_fails(): void
    {
        $this->artisan('mediciones:podar --dias=0')->assertFailed();
    }
}
