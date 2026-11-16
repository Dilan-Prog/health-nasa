<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\Medicion;
use App\Models\SimulacionEvento;
use App\Services\Ia\DetectorPatrones;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): never wipes the local DB data. */
class PatternDetectorTest extends TestCase
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

    /** Records without firing the hook (simulated origin, no event) and without a baseline: deterministic states. */
    private function record(Astronauta $a, string $ind, array $valores, int $min, string $origen = 'simulado')
    {
        return ProcesadorMediciones::make()->registrar($a, $ind, $valores, $origen, $this->t($min), emitir: false, usarLineaBase: false);
    }

    /** @return array<string, CasoClinico> open cases keyed by pattern */
    private function openCases(Astronauta $a): array
    {
        return CasoClinico::abiertos()->where('astronauta_id', $a->id)->get()->keyBy('patron')->all();
    }

    private function patternAlerts(Astronauta $a)
    {
        return AlertaSaliente::where('astronauta_id', $a->id)->get()->filter(fn ($x) => isset($x->carga['caso_id'])); // the pattern's own alert or a critical notice enriched with the case
    }

    public static function patternTable(): array
    {
        return [
            'fever' => ['fiebre', [['temperatura', ['valor' => 37.6], 0]], 'precaucion', 'temperatura'],
            'hypoxia' => ['hipoxia', [['saturacion_o2', ['valor' => 92], 0]], 'precaucion', 'saturacion_o2'],
            'dehydration' => ['deshidratacion', [['hidratacion', ['valor' => 2.2], 0]], 'precaucion', 'hidratacion'],
            'stress' => ['estres', [['estres_cognitivo', ['valor' => 65], 0]], 'precaucion', 'estres_cognitivo'],
            'arrhythmia' => ['arritmia', [['frecuencia_cardiaca', ['valor' => 110], 0]], 'precaucion', 'frecuencia_cardiaca'],
            'sleep_deprivation' => ['falta_sueno', [['sueno', ['valor' => 6.0], 0]], 'precaucion', 'sueno'],
            'sensor_failure' => ['fallo_sensor', [['saturacion_o2', ['valor' => 98], 0], ['saturacion_o2', ['valor' => 80], 10]], 'precaucion', 'saturacion_o2'],
        ];
    }

    /** @dataProvider patternTable */
    public function test_each_pattern_opens_its_case_with_evidence(string $patron, array $lecturas, string $severidad, string $indicador): void
    {
        $a = $this->crewMember();
        foreach ($lecturas as [$ind, $val, $min]) {
            $this->record($a, $ind, $val, $min);
        }

        DetectorPatrones::evaluar($a);

        $open = $this->openCases($a);
        $this->assertArrayHasKey($patron, $open);
        $caso = $open[$patron];
        $this->assertSame($severidad, $caso->severidad);
        $this->assertSame($indicador, $caso->evidencia['senales'][0]['indicador']);
        $this->assertSame((int) config("patrones.{$patron}.ventana_min"), $caso->evidencia['ventana_min']);
        $this->assertNotNull($caso->detectado_en);
        $this->assertNull($caso->escalado_en);
    }

    /** @dataProvider patternTable */
    public function test_a_normal_reading_opens_no_case(string $patron, array $lecturas): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 72], 0);
        $this->record($a, 'saturacion_o2', ['valor' => 98], 1);
        $this->record($a, 'temperatura', ['valor' => 36.6], 2);

        $this->assertSame([], DetectorPatrones::evaluar($a));
        $this->assertSame([], $this->openCases($a));
    }

    public function test_two_evaluations_do_not_duplicate_the_case(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 92], 0);

        DetectorPatrones::evaluar($a);
        DetectorPatrones::evaluar($a);

        $this->assertSame(1, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'hipoxia')->count());
    }

    public function test_supporting_signal_is_added_to_the_evidence(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 92], 0);
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 5);

        DetectorPatrones::evaluar($a);

        $caso = $this->openCases($a)['hipoxia'];
        $this->assertSame(2, $caso->evidencia['coinciden']);
        $this->assertEqualsCanonicalizing(['saturacion_o2', 'frecuencia_cardiaca'], array_column($caso->evidencia['senales'], 'indicador'));
    }

    public function test_severity_rises_when_worsening_and_never_drops(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);
        $this->assertSame('precaucion', $this->openCases($a)['arritmia']->severidad);
        $this->assertArrayNotHasKey('empeoro', $this->openCases($a)['arritmia']->evidencia, 'no worsening when the case is opened');

        $this->record($a, 'frecuencia_cardiaca', ['valor' => 140], 10);
        DetectorPatrones::evaluar($a);
        $this->assertSame('critico', $this->openCases($a)['arritmia']->severidad);
        $this->assertTrue($this->openCases($a)['arritmia']->evidencia['empeoro'], 'severity rose: it is recorded in the evidence');
        $this->assertSame(1, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->count());

        $this->record($a, 'frecuencia_cardiaca', ['valor' => 112], 20);
        DetectorPatrones::evaluar($a);
        $this->assertSame('critico', $this->openCases($a)['arritmia']->severidad, 'an open case never drops in severity');
        $this->assertTrue($this->openCases($a)['arritmia']->evidencia['empeoro'], 'the worsened flag is kept');
    }

    public function test_closes_when_signals_return_to_normal(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);

        $this->record($a, 'frecuencia_cardiaca', ['valor' => 80], 10);
        $touched = DetectorPatrones::evaluar($a);

        $this->assertSame([], $this->openCases($a));
        $caso = CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->first();
        $this->assertSame('cerrado', $caso->estado);
        $this->assertNotNull($caso->cerrado_en);
        $this->assertSame('cerrado', $touched[0]->estado);
    }

    public function test_a_closed_case_does_not_prevent_opening_a_new_one(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 80], 10);
        DetectorPatrones::evaluar($a);
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 112], 20);
        DetectorPatrones::evaluar($a);

        $this->assertSame(2, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->count());
        $this->assertArrayHasKey('arritmia', $this->openCases($a));
    }

    public function test_a_signal_outside_the_window_does_not_count(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'temperatura', ['valor' => 37.6], 0);
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 72], (int) config('patrones.fiebre.ventana_min') + 60);

        DetectorPatrones::evaluar($a);

        $this->assertArrayNotHasKey('fiebre', $this->openCases($a));
    }

    public function test_does_not_fire_on_suspect_data_except_sensor_failure(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 98], 0);
        $ev = $this->record($a, 'saturacion_o2', ['valor' => 80], 10); // abrupt jump: suspect and critical

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertSame('critico', $ev->estado);

        DetectorPatrones::evaluar($a);

        $open = $this->openCases($a);
        $this->assertArrayNotHasKey('hipoxia', $open);
        $this->assertArrayHasKey('fallo_sensor', $open);
        $this->assertSame('precaucion', $open['fallo_sensor']->severidad, 'a sensor failure does not escalate by itself');
        $this->assertNull($open['fallo_sensor']->escalado_en);
        $this->assertSame(0, $this->patternAlerts($a)->count());
    }

    public function test_sensor_failure_closes_on_a_good_reading(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 98], 0);
        $this->record($a, 'saturacion_o2', ['valor' => 80], 10);
        DetectorPatrones::evaluar($a);
        $this->assertArrayHasKey('fallo_sensor', $this->openCases($a));

        $this->record($a, 'saturacion_o2', ['valor' => 97], 20);
        DetectorPatrones::evaluar($a);

        $this->assertArrayNotHasKey('fallo_sensor', $this->openCases($a));
    }

    public function test_a_critical_pattern_escalates_to_ground_only_once(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 85], 0); // < 90: critical

        DetectorPatrones::evaluar($a);
        DetectorPatrones::evaluar($a);
        $this->record($a, 'saturacion_o2', ['valor' => 84], 5);
        DetectorPatrones::evaluar($a);

        $caso = $this->openCases($a)['hipoxia'];
        $this->assertSame('critico', $caso->severidad);
        $this->assertNotNull($caso->escalado_en);
        $alerts = $this->patternAlerts($a);
        $this->assertCount(1, $alerts);
        $payload = $alerts->first()->carga;
        $this->assertSame('pendiente', $alerts->first()->estado);
        $this->assertSame('hipoxia', $payload['patron']);
        $this->assertSame('critico', $payload['severidad']);
        $this->assertSame($caso->id, $payload['caso_id']);
        $this->assertSame('Test', $payload['astronauta']);
        $this->assertArrayHasKey('senales', $payload['evidencia']);
    }

    public function test_a_case_that_worsens_to_critical_escalates_when_it_does(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'saturacion_o2', ['valor' => 92], 0);
        DetectorPatrones::evaluar($a);
        $this->assertSame(0, $this->patternAlerts($a)->count());

        $this->record($a, 'saturacion_o2', ['valor' => 86], 5);
        DetectorPatrones::evaluar($a);

        $this->assertSame(1, $this->patternAlerts($a)->count());
    }

    public function test_links_the_open_simulation_event_of_the_same_scenario(): void
    {
        $a = $this->crewMember();
        $event = SimulacionEvento::create(['astronauta_id' => $a->id, 'escenario' => 'hipoxia', 'inicio_en' => now()]);
        SimulacionEvento::create(['astronauta_id' => $a->id, 'escenario' => 'fiebre', 'inicio_en' => now()]);
        $this->record($a, 'saturacion_o2', ['valor' => 92], 0);

        DetectorPatrones::evaluar($a);

        $this->assertSame($event->id, $this->openCases($a)['hipoxia']->simulacion_evento_id);
    }

    public function test_the_hook_opens_the_case_when_a_measurement_is_recorded(): void
    {
        $a = $this->crewMember();

        $this->record($a, 'saturacion_o2', ['valor' => 92], 0, 'manual');

        $this->assertArrayHasKey('hipoxia', $this->openCases($a));
    }

    public function test_the_hook_returns_immediately_when_everything_is_normal(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 72], 0, 'manual');

        $this->assertSame([], $this->openCases($a));
        $this->assertFalse(Cache::has('patrones.enfriamiento.'.$a->id), 'it did not evaluate: it did not even touch the cooldown');
    }

    public function test_cooldown_prevents_recalculating_within_the_window(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 0, 'manual'); // evaluates: arrhythmia
        $this->assertArrayHasKey('arritmia', $this->openCases($a));

        $this->record($a, 'saturacion_o2', ['valor' => 92], 5); // without the hook
        DetectorPatrones::alMedir($a, 'saturacion_o2', 'precaucion'); // in cooldown
        $this->assertArrayNotHasKey('hipoxia', $this->openCases($a));

        Cache::forget('patrones.enfriamiento.'.$a->id);
        DetectorPatrones::alMedir($a, 'saturacion_o2', 'precaucion');
        $this->assertArrayHasKey('hipoxia', $this->openCases($a));
    }

    public function test_a_critical_reading_does_not_wait_for_cooldown_when_there_is_no_critical_case(): void
    {
        $a = $this->crewMember();
        $this->record($a, 'frecuencia_cardiaca', ['valor' => 110], 0, 'manual'); // opens arrhythmia (caution) and arms the cooldown

        $this->record($a, 'saturacion_o2', ['valor' => 85], 5, 'manual'); // critical: evaluates despite the cooldown

        $this->assertSame('critico', $this->openCases($a)['hipoxia']->severidad);
        $this->assertSame(1, $this->patternAlerts($a)->count());
    }

    public function test_the_hook_never_breaks_recording_when_the_detector_fails(): void
    {
        $a = $this->crewMember();
        config(['patrones' => 'broken-configuration']);

        $ev = $this->record($a, 'saturacion_o2', ['valor' => 92], 0, 'manual');

        $this->assertSame('precaucion', $ev->estado);
        $this->assertSame(1, Medicion::where('astronauta_id', $a->id)->count());
        $this->expectException(\Throwable::class); // evaluar() does throw: only the hook swallows it
        DetectorPatrones::evaluar($a);
    }
}
