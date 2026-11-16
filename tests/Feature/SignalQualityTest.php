<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Medicion;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\SimuladorVivo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Questionable data: always saved and labeled "sospechosa" (suspect; abrupt jump, flat signal); never rejected. */
class SignalQualityTest extends TestCase
{
    use DatabaseTransactions;

    private function crewMember(): Astronauta
    {
        Event::fake();

        return Astronauta::create(['nombre' => 'Test', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function t(int $min): Carbon
    {
        return Carbon::parse('2026-11-16 08:00:00', 'UTC')->addMinutes($min);
    }

    public function test_normal_reading_is_ok(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 74], 'manual', $this->t(10));

        $this->assertSame('ok', $ev->medicion->calidad);
        $this->assertNull($ev->medicion->calidad_motivo);
    }

    public function test_abrupt_jump_is_saved_as_suspect_without_ground_alert(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 118], 'manual', $this->t(10)); // +48 bpm in 10 min

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertStringContainsString('Abrupt jump', $ev->medicion->calidad_motivo);
        $this->assertSame(2, Medicion::where('astronauta_id', $a->id)->count(), 'always saved');
        $this->assertNotSame('critico', $ev->estado);
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $a->id)->count(), 'a suspect reading does not alert ground by itself');
    }

    public function test_large_jump_with_long_gap_between_readings_is_not_suspect(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 118], 'manual', $this->t(600));

        $this->assertSame('ok', $ev->medicion->calidad);
    }

    public function test_flat_signal_n_identical_consecutive_readings(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $n = config('calidad.indicadores.frecuencia_cardiaca.plana_n');
        $cal = [];
        for ($i = 0; $i < $n; $i++) {
            $cal[] = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 64], 'manual', $this->t($i * 5))->medicion->calidad;
        }

        $this->assertSame(array_fill(0, $n - 1, 'ok'), array_slice($cal, 0, $n - 1));
        $this->assertSame('sospechosa', $cal[$n - 1]);
        $this->assertStringContainsString('Flat signal', Medicion::where('astronauta_id', $a->id)->orderByDesc('medido_en')->first()->calidad_motivo);
    }

    public function test_suspect_that_is_also_critical_keeps_critical_and_the_alert(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'saturacion_o2', ['valor' => 97], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'saturacion_o2', ['valor' => 78], 'manual', $this->t(10)); // 19-point jump and critical

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertSame('critico', $ev->estado, 'risk is not hidden');
        $alerta = AlertaSaliente::where('evaluacion_id', $ev->id)->first();
        $this->assertNotNull($alerta);
        $this->assertStringContainsString('Abrupt jump', $alerta->carga['dato_sospechoso']);
    }

    public function test_suspect_does_not_contaminate_rolling_baseline(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        foreach ([60, 61, 60, 61, 60] as $i => $v) {
            $p->registrar($a, 'frecuencia_cardiaca', ['valor' => $v], 'manual', $this->t($i * 5));
        }
        $this->assertSame('sospechosa', $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'manual', $this->t(35))->medicion->calidad);

        // With a clean baseline (≈60.4) a 66 is more than 2 deviations away and rises to caution; if the 150 contaminated it, it would stay normal.
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 66], 'manual', $this->t(45));
        $this->assertSame('ok', $ev->medicion->calidad);
        $this->assertSame('precaucion', $ev->estado);
    }

    public function test_simulator_sensor_failure_scenario_is_flagged_suspect(): void
    {
        $a = $this->crewMember();
        $p = ProcesadorMediciones::make();
        $sim = new SimuladorVivo(config('simulacion'), config('umbrales'));
        $start = Carbon::parse('2026-11-16 02:00:00', 'UTC');
        $r = $sim->avanzar($sim->estadoInicial(8), Carbon::parse('2026-11-16 01:00:00', 'UTC'), Carbon::parse('2026-11-16 03:00:00', 'UTC'), 1,
            Carbon::parse('2026-11-16 03:00:00', 'UTC'), ['clave' => 'fallo_sensor', 'inicio' => $start, 'dura_s' => 3600]);

        foreach ($r['mediciones'] as $m) {
            if (in_array($m->indicador, ['frecuencia_cardiaca', 'saturacion_o2'], true)) {
                try {
                    $p->registrar($a, $m->indicador, $m->valores, 'simulado', $m->medidoEn, false);
                } catch (\Illuminate\Validation\ValidationException) {
                    // duplicates from the simulator itself (as ControlSimulacion does)
                }
            }
        }

        $o2 = Medicion::where('astronauta_id', $a->id)->where('indicador', 'saturacion_o2')->where('calidad', 'sospechosa')->get();
        $fc = Medicion::where('astronauta_id', $a->id)->where('indicador', 'frecuencia_cardiaca')->where('calidad', 'sospechosa')->get();
        $this->assertTrue($o2->contains(fn ($m) => $m->valores['valor'] < 50), 'impossible SpO₂: saved as suspect, not rejected');
        $this->assertGreaterThan(0, $fc->count(), 'frozen pulse: flat signal');
        $this->assertStringContainsString('Flat signal', $fc->first()->calidad_motivo);
    }

    public function test_impossible_manual_entry_is_still_rejected(): void
    {
        $a = $this->crewMember();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ProcesadorMediciones::make()->registrar($a, 'saturacion_o2', ['valor' => 0], 'manual', $this->t(0));
    }
}
