<?php

namespace Tests\Feature;

use App\Models\Medicion;
use App\Models\Mision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (not RefreshDatabase): never wipes the local database. */
class MissionSimulatorCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_n_crew_members_with_their_history(): void
    {
        $this->artisan('salud:simular-mision', [
            '--nombre' => 'T-Test', '--tripulantes' => 3, '--dias' => 5, '--semilla' => 7,
        ])->assertSuccessful();

        $mision = Mision::where('nombre', 'T-Test')->firstOrFail();
        $this->assertCount(3, $mision->tripulantes);
        $this->assertSame('estacion', $mision->entorno);
        $this->assertSame(['estacion'], $mision->tripulantes->pluck('entorno')->unique()->all());

        // 3 crew members; daily indicators on all 5 days, weekly ones only on day 0 of each week.
        $frequencies = array_count_values(array_column(config('umbrales.indicadores'), 'frecuencia'));
        $perCrewMember = 5 * ($frequencies['diaria'] ?? 0) + 1 * ($frequencies['semanal'] ?? 0);
        $this->assertSame(3 * $perCrewMember, $mision->mediciones()->count());
        $this->assertSame(0, Medicion::whereIn('astronauta_id', $mision->tripulantes->pluck('id'))->where('origen', '!=', 'simulado')->count());
    }

    public function test_same_seed_yields_same_measurements(): void
    {
        $read = function (): array {
            $this->artisan('salud:simular-mision', ['--nombre' => 'T-Seed', '--tripulantes' => 2, '--dias' => 4, '--semilla' => 99, '--reemplazar' => true])->assertSuccessful();

            return Mision::where('nombre', 'T-Seed')->first()->mediciones()->orderBy('mediciones.id')->get()
                ->map(fn ($m) => [$m->indicador, $m->valores])->all();
        };

        $this->assertSame($read(), $read());
    }

    public function test_rejects_duplicate_name_without_replace_and_invalid_environment(): void
    {
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-Dup', '--tripulantes' => 1, '--dias' => 2])->assertSuccessful();
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-Dup', '--tripulantes' => 1, '--dias' => 2])->assertFailed();
        $this->artisan('salud:simular-mision', ['--nombre' => 'T-X', '--entorno' => 'jupiter'])->assertFailed();

        $this->assertSame(1, Mision::where('nombre', 'T-Dup')->count());
        $this->assertSame(0, Mision::where('nombre', 'T-X')->count());
    }
}
