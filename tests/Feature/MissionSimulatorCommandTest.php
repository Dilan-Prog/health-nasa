<?php

namespace Tests\Feature;

use App\Models\Medicion;
use App\Models\Mision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class SimularMisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crea_n_tripulantes_con_su_historial(): void
    {
        $this->artisan('salud:simular-mision', [
            '--nombre' => 'T-Test', '--tripulantes' => 3, '--dias' => 5, '--semilla' => 7,
        ])->assertSuccessful();

        $mision = Mision::where('nombre', 'T-Test')->firstOrFail();
        $this->assertCount(3, $mision->tripulantes);
        $this->assertSame('estacion', $mision->entorno);
        $this->assertSame(['estacion'], $mision->tripulantes->pluck('entorno')->unique()->all());

        // 3 tripulantes; indicadores diarios los 5 días y semanales solo el día 0 de cada semana.
        $frecuencias = array_count_values(array_column(config('umbrales.indicadores'), 'frecuencia'));
        $porTripulante = 5 * ($frecuencias['diaria'] ?? 0) + 1 * ($frecuencias['semanal'] ?? 0);
        $this->assertSame(3 * $porTripulante, $mision->mediciones()->count());
        $this->assertSame(0, Medicion::whereIn('astronauta_id', $mision->tripulantes->pluck('id'))->where('origen', '!=', 'simulado')->count());
    }

    public function test_misma_semilla_da_las_mismas_mediciones(): void
    {
        $leer = function (): array {
            $this->artisan('salud:simular-mision', ['--nombre' => 'T-Seed', '--tripulantes' => 2, '--dias' => 4, '--semilla' => 99, '--reemplazar' => true])->assertSuccessful();

            return Mision::where('nombre', 'T-Seed')->first()->mediciones()->orderBy('mediciones.id')->get()
                ->map(fn ($m) => [$m->indicador, $m->valores])->all();
        };

        $this->assertSame($leer(), $leer());
    }

    public function test_rechaza_nombre_repetido_sin_reemplazar_y_entorno_invalido(): void
    {
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-Dup', '--tripulantes' => 1, '--dias' => 2])->assertSuccessful();
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-Dup', '--tripulantes' => 1, '--dias' => 2])->assertFailed();
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-X', '--entorno' => 'jupiter'])->assertFailed();

        $this->assertSame(1, Mision::where('nombre', 'T-Dup')->count());
        $this->assertSame(0, Mision::where('nombre', 'T-X')->count());
    }
}
