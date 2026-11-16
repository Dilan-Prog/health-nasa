<?php

namespace Tests\Feature;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\LineaBase;
use App\Models\Mision;
use App\Models\Requisito;
use App\Models\Rutina;
use App\Models\RutinaEjecucion;
use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\ReglasEscalamiento;
use App\Services\Salud\ResolvedorRequisito;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Level 2: radiation by environment, career dose, blood pressure bands, temperature jump and care_level.
 * All numbers are example values (unvalidated). DatabaseTransactions: does not touch other data.
 */
class Level2ThresholdsTest extends TestCase
{
    use DatabaseTransactions;

    private const GCR_FREE_SPACE = 'GCR in free space';
    private const GCR_SURFACE = 'GCR on a planetary surface (habitable systems of more than 60 days)';
    private const CAREER = 'Career effective dose';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->seed(RequisitosSeeder::class);
        ResolvedorRequisito::olvidar();
    }

    private function astronaut(string $environment = 'estacion', ?int $care = null): Astronauta
    {
        $mission = $care ? Mision::create(['nombre' => 'M-N2-'.uniqid(), 'entorno' => $environment, 'care_level' => $care]) : null;

        return Astronauta::create(['nombre' => 'N2', 'mision' => 'T-N2', 'entorno' => $environment, 'mision_id' => $mission?->id]);
    }

    private function record(Astronauta $a, string $clave, array $values, $instant = null)
    {
        return ProcesadorMediciones::make()->registrar($a, $clave, $values, 'manual', $instant, false, false);
    }

    private function requirementText($evaluation): ?string
    {
        return $evaluation->requisito_id ? Requisito::find($evaluation->requisito_id)->texto : null;
    }

    // ---------------------------------------------------------------- daily radiation

    public static function dailyRadiation(): array
    {
        return [
            'station 1.03 normal' => ['estacion', 1.03, 'normal', self::GCR_FREE_SPACE],
            'station 1.04 caution (80 %)' => ['estacion', 1.04, 'precaucion', self::GCR_FREE_SPACE],
            'station 1.29 caution' => ['estacion', 1.29, 'precaucion', self::GCR_FREE_SPACE],
            'station 1.30 critical (limit)' => ['estacion', 1.30, 'critico', self::GCR_FREE_SPACE],
            'spacecraft 1.00 normal' => ['nave', 1.00, 'normal', self::GCR_FREE_SPACE],
            'spacecraft 1.35 critical' => ['nave', 1.35, 'critico', self::GCR_FREE_SPACE],
            'lunar base 0.71 normal' => ['base_lunar', 0.71, 'normal', self::GCR_SURFACE],
            'lunar base 0.72 caution' => ['base_lunar', 0.72, 'precaucion', self::GCR_SURFACE],
            'lunar base 0.90 critical (limit)' => ['base_lunar', 0.90, 'critico', self::GCR_SURFACE],
            'Mars base 0.95 critical' => ['base_marciana', 0.95, 'critico', self::GCR_SURFACE],
            // the same 1.00 is normal on the station and critical on a surface
            'Mars base 1.00 critical' => ['base_marciana', 1.00, 'critico', self::GCR_SURFACE],
        ];
    }

    /** @dataProvider dailyRadiation */
    public function test_daily_radiation_by_environment(string $environment, float $value, string $state, string $requirement): void
    {
        $ev = $this->record($this->astronaut($environment), 'radiacion_diaria', ['valor' => $value]);

        $this->assertSame($state, $ev->estado);
        $this->assertSame($requirement, $this->requirementText($ev));
    }

    public function test_critical_radiation_alert_carries_requirement_and_revision(): void
    {
        $ev = $this->record($this->astronaut('base_marciana'), 'radiacion_diaria', ['valor' => 0.95]);

        $payload = AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail()->carga;
        $this->assertSame(self::GCR_SURFACE, $payload['requisito']);
        $this->assertStringContainsString('NASA-STD-3001 Vol. 1 Rev. C', $payload['revision']);
        $this->assertSame('norma', $payload['tipo_fuente']);
    }

    public function test_evaluator_without_environment_uses_the_strictest_limit(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertSame(Estado::Critico, $e->evaluar('radiacion_diaria', ['valor' => 0.9]));
        $this->assertSame(Estado::Critico, $e->evaluar('radiacion_diaria', ['valor' => 0.9], ['entorno' => 'desconocido']));
        $this->assertSame(Estado::Normal, $e->evaluar('radiacion_diaria', ['valor' => 1.0], ['entorno' => 'estacion']));
    }

    // ---------------------------------------------------------------- cumulative dose

    public static function cumulativeDose(): array
    {
        return [
            '479 normal' => [479.0, 'normal'],
            '480 caution (80 % of 600)' => [480.0, 'precaucion'],
            '599 caution' => [599.0, 'precaucion'],
            '600 critical (career limit)' => [600.0, 'critico'],
            '650 critical' => [650.0, 'critico'],
        ];
    }

    /** @dataProvider cumulativeDose */
    public function test_cumulative_dose_against_600_msv(float $value, string $state): void
    {
        $ev = $this->record($this->astronaut(), 'dosis_acumulada', ['valor' => $value]);

        $this->assertSame($state, $ev->estado);
        $this->assertSame(self::CAREER, $this->requirementText($ev));
    }

    // ---------------------------------------------------------------- solar event (rule and text only)

    public function test_solar_event_projection_above_250_is_critical_with_shelter(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertNull($e->eventoSolar(250.0));
        $r = $e->eventoSolar(250.1);
        $this->assertSame(Estado::Critico, $r['estado']);
        $this->assertSame('alarm and shielded shelter', $r['accion']);
        $this->assertStringContainsString('shielded shelter', CatalogoRecomendaciones::EVENTO_SOLAR);
    }

    // ---------------------------------------------------------------- blood pressure by band

    public static function pressureBands(): array
    {
        $pa = fn (string $t) => $t;

        return [
            'normal 115/75' => [115, 75, 'Normal', 'normal', $pa('Normal: below 120/80 mmHg')],
            'normal 119/79' => [119, 79, 'Normal', 'normal', $pa('Normal: below 120/80 mmHg')],
            'elevated 125/75' => [125, 75, 'Elevated', 'precaucion', $pa('Elevated: systolic 120–129 and diastolic below 80 mmHg')],
            'elevated 129/79' => [129, 79, 'Elevated', 'precaucion', $pa('Elevated: systolic 120–129 and diastolic below 80 mmHg')],
            'stage 1 by systolic 135/78' => [135, 78, 'Hypertension stage 1', 'precaucion', $pa('Hypertension stage 1: systolic 130–139 or diastolic 80–89 mmHg')],
            'stage 1 by diastolic 118/85' => [118, 85, 'Hypertension stage 1', 'precaucion', $pa('Hypertension stage 1: systolic 130–139 or diastolic 80–89 mmHg')],
            'stage 2 by systolic 140/85' => [140, 85, 'Hypertension stage 2', 'critico', $pa('Hypertension stage 2: systolic ≥ 140 or diastolic ≥ 90 mmHg')],
            'stage 2 by diastolic 125/90' => [125, 90, 'Hypertension stage 2', 'critico', $pa('Hypertension stage 2: systolic ≥ 140 or diastolic ≥ 90 mmHg')],
            'crisis by systolic 185/100' => [185, 100, 'Hypertensive crisis', 'critico', $pa('Crisis: above 180/120 mmHg')],
            'crisis by diastolic 150/125' => [150, 125, 'Hypertensive crisis', 'critico', $pa('Crisis: above 180/120 mmHg')],
        ];
    }

    /** @dataProvider pressureBands */
    public function test_blood_pressure_by_band(int $systolic, int $diastolic, string $band, string $state, string $requirement): void
    {
        $values = ['sistolica' => $systolic, 'diastolica' => $diastolic];
        $this->assertSame($band, (new EvaluadorEstado(config('umbrales')))->banda('presion_arterial', $values));

        $ev = $this->record($this->astronaut(), 'presion_arterial', $values);

        $this->assertSame($state, $ev->estado);
        $this->assertSame($requirement, $this->requirementText($ev));
    }

    public function test_hypertensive_crisis_contacts_ground_with_its_text(): void
    {
        $ev = $this->record($this->astronaut(), 'presion_arterial', ['sistolica' => 190, 'diastolica' => 110]);

        $this->assertSame('contactar_tierra', $ev->accion);
        $this->assertStringContainsString('Hypertensive crisis', $ev->recomendacion);
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $ev->id)->count());
    }

    public function test_band_is_null_for_indicators_without_bands(): void
    {
        $this->assertNull((new EvaluadorEstado(config('umbrales')))->banda('glucosa', ['valor' => 90]));
    }

    // ---------------------------------------------------------------- temperature jump

    public static function temperatureWithPreflightBaseline(): array
    {
        // pre-flight baseline 36.6 °C; example jumps 0.6 (caution) and 1.2 (critical)
        return [
            'no jump 36.9' => [36.9, false, 'normal'],
            'jump 0.6 -> caution' => [37.2, false, 'precaucion'],
            'jump 1.2 -> critical' => [37.8, false, 'critico'],
            'jump 1.2 after exercise -> no alert' => [37.8, true, 'normal'],
            'jump 0.6 after exercise -> no alert' => [37.2, true, 'normal'],
        ];
    }

    /** @dataProvider temperatureWithPreflightBaseline */
    public function test_temperature_jump_over_preflight_baseline(float $value, bool $exercise, string $state): void
    {
        $a = $this->astronaut();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        if ($exercise) {
            $this->exerciseDone($a, now()->subMinutes(30));
        }

        $ev = $this->record($a, 'temperatura', ['valor' => $value]);

        $this->assertSame($state, $ev->estado);
        $this->assertNotNull($ev->requisito_id);
        $this->assertSame('temperatura', Requisito::find($ev->requisito_id)->dominio);
    }

    public function test_exercise_outside_the_last_hour_does_not_prevent_the_alert(): void
    {
        $a = $this->astronaut();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->exerciseDone($a, now()->subMinutes(120));

        $this->assertSame('critico', $this->record($a, 'temperatura', ['valor' => 37.8])->estado);
    }

    public function test_exercise_by_another_astronaut_or_non_exercise_does_not_count(): void
    {
        $a = $this->astronaut();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->exerciseDone($this->astronaut(), now()->subMinutes(10));
        $this->exerciseDone($a, now()->subMinutes(10), 'bien');

        $this->assertSame('critico', $this->record($a, 'temperatura', ['valor' => 37.8])->estado);
    }

    public function test_exercise_does_not_hide_a_low_temperature(): void
    {
        $a = $this->astronaut();
        LineaBase::create(['astronauta_id' => $a->id, 'metrica' => 'temperatura_basal', 'valor' => 36.6]);
        $this->exerciseDone($a, now()->subMinutes(10));

        $this->assertSame('critico', $this->record($a, 'temperatura', ['valor' => 35.3])->estado);
    }

    public static function temperatureWithoutPreflightBaseline(): array
    {
        // rolling history of 5 measurements at 36.5 °C
        return [
            'no jump 36.8' => [36.8, false, 'normal'],
            'jump 0.8 -> caution' => [37.3, false, 'precaucion'],
            'jump 1.5 -> critical' => [38.0, false, 'critico'],
            'jump 1.5 after exercise -> no alert' => [38.0, true, 'normal'],
        ];
    }

    /** @dataProvider temperatureWithoutPreflightBaseline */
    public function test_temperature_jump_over_rolling_baseline(float $value, bool $exercise, string $state): void
    {
        $a = $this->astronaut();
        foreach (range(1, 5) as $i) {
            $a->mediciones()->create(['indicador' => 'temperatura', 'valores' => ['valor' => 36.5], 'medido_en' => now()->subDays($i), 'origen' => 'manual']);
        }
        if ($exercise) {
            $this->exerciseDone($a, now()->subMinutes(20));
        }

        $this->assertSame($state, $this->record($a, 'temperatura', ['valor' => $value])->estado);
    }

    public function test_temperature_without_any_baseline_falls_back_to_the_absolute_range(): void
    {
        $a = $this->astronaut();

        $this->assertSame('normal', $this->record($a, 'temperatura', ['valor' => 36.8])->estado);
        $this->assertSame('critico', $this->record($a, 'temperatura', ['valor' => 38.6])->estado);
    }

    private function exerciseDone(Astronauta $a, $when, string $kind = 'ej'): void
    {
        $r = Rutina::create([
            'astronauta_id' => $a->id, 'nombre' => 'Treadmill', 'tipo' => 'Cardio', 'icono' => 'pedal_bike', 'kind' => $kind,
            'hora' => '08:00', 'cantidad' => 30, 'dias' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        RutinaEjecucion::create(['rutina_id' => $r->id, 'fecha' => $when->toDateString(), 'estado' => 'done', 'hecha_en' => $when]);
    }

    // ---------------------------------------------------------------- hydration (no rule change)

    public function test_hydration_2_0_stays_in_caution_per_its_current_rule(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));
        $this->assertSame(Estado::Precaucion, $e->evaluar('hidratacion', ['valor' => 2.0]));

        $ev = $this->record($this->astronaut(), 'hidratacion', ['valor' => 2.0]);

        $this->assertSame('precaucion', $ev->estado);
        $this->assertSame('Low daily hydration', $this->requirementText($ev));
    }

    public function test_pulse_keeps_its_thresholds(): void
    {
        $e = new EvaluadorEstado(config('umbrales'));

        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 100]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 120]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 121]));
    }

    public function test_indicator_without_a_mapped_requirement_stores_null(): void
    {
        $this->assertNull($this->record($this->astronaut(), 'glucosa', ['valor' => 90])->requisito_id);
    }

    // ---------------------------------------------------------------- care_level

    public static function careLevels(): array
    {
        // level, delay, expected on-board, expected video consult (critical action)
        return [
            'level 3, no delay' => [3, 0, false, true],
            'level 3, high delay' => [3, 20, true, true],
            'level 4, no delay' => [4, 0, true, false],
            'level 5, no delay' => [5, 0, true, false],
            'level 5, high delay' => [5, 20, true, false],
        ];
    }

    /** @dataProvider careLevels */
    public function test_care_level_in_the_escalation_rules(int $level, int $delay, bool $onBoard, bool $video): void
    {
        $d = (new ReglasEscalamiento())->decidir(Estado::Critico, 'estable', $delay, $level);

        $this->assertSame(ReglasEscalamiento::CONTACTAR_TIERRA, $d['accion']);
        $this->assertTrue($d['encolar_tierra']);
        $this->assertSame($onBoard, $d['a_bordo']);
        $this->assertSame($video, $d['videoconsulta']);
    }

    public function test_default_care_level_is_3_and_normal_does_nothing(): void
    {
        $r = new ReglasEscalamiento();

        $this->assertSame($r->decidir(Estado::Critico, 'estable', 0), $r->decidir(Estado::Critico, 'estable', 0, 3));
        $d = $r->decidir(Estado::Normal, 'estable', 0, 5);
        $this->assertFalse($d['a_bordo']);
        $this->assertFalse($d['videoconsulta']);
    }

    public function test_critical_with_care_level_4_still_queues_the_ground_alert(): void
    {
        $ev = $this->record($this->astronaut('estacion', 4), 'dosis_acumulada', ['valor' => 610]);

        $this->assertSame('critico', $ev->estado);
        $this->assertSame(1, AlertaSaliente::where('evaluacion_id', $ev->id)->count());
    }

    public function test_ground_alert_carries_requirement_and_revision(): void
    {
        $ev = $this->record($this->astronaut(), 'dosis_acumulada', ['valor' => 610]);

        $payload = AlertaSaliente::where('evaluacion_id', $ev->id)->firstOrFail()->carga;
        $this->assertSame(self::CAREER, $payload['requisito']);
        $this->assertStringContainsString('Rev. C', $payload['revision']);
        $this->assertSame('norma', $payload['tipo_fuente']);
        $this->assertNotNull($ev->requisito_id);
    }
}
