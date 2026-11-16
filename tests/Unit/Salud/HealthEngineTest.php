<?php

namespace Tests\Unit\Salud;

use App\Services\Salud\CatalogoRecomendaciones;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\Estado;
use App\Services\Salud\IndiceSistemaCorporal;
use App\Services\Salud\LineaBasePersonal;
use App\Services\Salud\ReglasEscalamiento;
use App\Services\Salud\ValidadorMedicion;
use PHPUnit\Framework\TestCase;

class HealthEngineTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require __DIR__.'/../../../config/umbrales.php';
    }

    // ---- Evaluator -------------------------------------------------------

    public function test_simple_value_normal_caution_and_critical(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 72]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 110]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 150]));
    }

    public function test_exact_limits_are_inclusive(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 49]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 50]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 59]));
        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 60]));
        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 100]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 101]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 120]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 121]));
    }

    public function test_composite_pressure_takes_the_worst_component(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Normal, $e->evaluar('presion_arterial', ['sistolica' => 118, 'diastolica' => 76]));
        // Normal systolic, caution-level diastolic -> caution.
        $this->assertSame(Estado::Precaucion, $e->evaluar('presion_arterial', ['sistolica' => 118, 'diastolica' => 86]));
        // A critical systolic dominates even if the diastolic is normal.
        $this->assertSame(Estado::Critico, $e->evaluar('presion_arterial', ['sistolica' => 185, 'diastolica' => 80]));
    }

    public function test_evaluating_without_a_component_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EvaluadorEstado($this->config))->evaluar('presion_arterial', ['sistolica' => 120]);
    }

    public function test_unknown_indicator_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EvaluadorEstado($this->config))->evaluar('inexistente', ['valor' => 1]);
    }

    public function test_trends(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame('subiendo', $e->tendencia([70, 72, 80, 88, 95, 100]));
        $this->assertSame('bajando', $e->tendencia([8, 7.5, 6, 5, 4.5, 4]));
        $this->assertSame('estable', $e->tendencia([72, 73, 72, 71, 72, 73]));
        $this->assertSame('estable', $e->tendencia([70, 100]), 'little history does not set a trend');
    }

    // ---- Validator -------------------------------------------------------

    public function test_validator_accepts_a_correct_measurement(): void
    {
        $v = new ValidadorMedicion($this->config);
        $this->assertSame([], $v->validar('presion_arterial', ['sistolica' => 120, 'diastolica' => 80]));
    }

    public function test_validator_detects_impossible_missing_and_non_numeric_values(): void
    {
        $v = new ValidadorMedicion($this->config);

        $this->assertNotEmpty($v->validar('temperatura', ['valor' => 80]), 'impossible');
        $this->assertNotEmpty($v->validar('temperatura', []), 'missing');
        $this->assertNotEmpty($v->validar('temperatura', ['valor' => 'abc']), 'non-numeric');
        $this->assertNotEmpty($v->validar('presion_arterial', ['sistolica' => 80, 'diastolica' => 90]), 'diastolic >= systolic');
    }

    public function test_validator_detects_duplicates(): void
    {
        $v = new ValidadorMedicion($this->config);
        $previous = [['instante' => 1000, 'valores' => ['valor' => 72]]];

        $this->assertTrue($v->esDuplicada(['valor' => 72], 1030, $previous));
        $this->assertFalse($v->esDuplicada(['valor' => 72], 1200, $previous), 'outside the window');
        $this->assertFalse($v->esDuplicada(['valor' => 73], 1030, $previous), 'different value');
    }

    // ---- Baseline ------------------------------------------------------

    public function test_baseline_requires_minimum_history(): void
    {
        $this->assertNull((new LineaBasePersonal())->calcular([70, 71], 72));
    }

    public function test_baseline_escalates_a_normal_far_from_the_usual(): void
    {
        $lb = new LineaBasePersonal();
        $history = [58, 60, 59, 61, 60, 59, 60]; // astronaut with a habitually low pulse

        $base = $lb->calcular($history, 95); // 95 bpm is normal for the population but atypical for him
        $this->assertGreaterThanOrEqual(2.0, abs($base['z']));
        $this->assertSame(Estado::Precaucion, $lb->ajustar(Estado::Normal, $base));
    }

    public function test_baseline_does_not_downgrade_a_severe_state(): void
    {
        $lb = new LineaBasePersonal();
        $base = $lb->calcular([60, 61, 60, 62, 61], 61);

        $this->assertSame(Estado::Critico, $lb->ajustar(Estado::Critico, $base));
        $this->assertSame(Estado::Normal, $lb->ajustar(Estado::Normal, $base));
    }

    public function test_constant_baseline_does_not_produce_infinite_z(): void
    {
        $base = (new LineaBasePersonal())->calcular([60, 60, 60, 60, 60], 60);
        $this->assertEquals(0.0, $base['z']);
    }

    // ---- Escalation ----------------------------------------------------

    public function test_escalation_by_state(): void
    {
        $r = new ReglasEscalamiento();

        $this->assertSame('ninguno', $r->decidir(Estado::Normal)['accion']);
        $this->assertSame('recomendar', $r->decidir(Estado::Precaucion, 'estable')['accion']);
        $this->assertSame('alertar_tripulacion', $r->decidir(Estado::Precaucion, 'subiendo')['accion']);
        $this->assertSame('contactar_tierra', $r->decidir(Estado::Critico)['accion']);
    }

    public function test_critical_with_high_delay_is_handled_on_board_first(): void
    {
        $r = new ReglasEscalamiento();

        $near = $r->decidir(Estado::Critico, 'estable', 0);
        $mars = $r->decidir(Estado::Critico, 'estable', 20);

        $this->assertFalse($near['a_bordo']);
        $this->assertTrue($mars['a_bordo']);
        $this->assertTrue($near['encolar_tierra']);
        $this->assertTrue($mars['encolar_tierra']);
    }

    public function test_a_normal_is_never_queued_to_ground(): void
    {
        $this->assertFalse((new ReglasEscalamiento())->decidir(Estado::Normal)['encolar_tierra']);
    }

    // ---- Recommendations -------------------------------------------------

    public function test_specific_and_general_recommendations(): void
    {
        $c = new CatalogoRecomendaciones();

        $this->assertStringContainsString('at rest', $c->para('frecuencia_cardiaca', Estado::Precaucion));
        $this->assertStringContainsString('Flight Surgeon', $c->para('frecuencia_cardiaca', Estado::Critico));
        $this->assertNotSame('', $c->para('animo', Estado::Normal));
    }

    // ---- Per-system index ---------------------------------------------

    public function test_per_system_index(): void
    {
        $i = (new IndiceSistemaCorporal($this->config))->calcular([
            'frecuencia_cardiaca' => Estado::Normal,
            'presion_arterial' => Estado::Precaucion,
            'glucosa' => Estado::Critico,
        ]);

        $this->assertSame(75, $i['cardiovascular']['puntaje']);
        $this->assertSame(Estado::Precaucion, $i['cardiovascular']['estado']);
        $this->assertSame(Estado::Critico, $i['metabolico']['estado']);
        $this->assertLessThanOrEqual(40, $i['metabolico']['puntaje']);
        $this->assertNull($i['musculoesqueletico']['puntaje'], 'no data');
    }

    public function test_a_critical_is_not_hidden_by_the_average(): void
    {
        $i = (new IndiceSistemaCorporal($this->config))->calcular([
            'frecuencia_cardiaca' => Estado::Normal,
            'presion_arterial' => Estado::Critico,
        ]);

        $this->assertSame(Estado::Critico, $i['cardiovascular']['estado']);
        $this->assertLessThanOrEqual(40, $i['cardiovascular']['puntaje']);
    }
}
