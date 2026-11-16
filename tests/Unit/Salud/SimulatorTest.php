<?php

namespace Tests\Unit\Salud;

use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\SimuladorMediciones;
use App\Services\Salud\ValidadorMedicion;
use PHPUnit\Framework\TestCase;

class SimulatorTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require __DIR__.'/../../../config/umbrales.php';
    }

    public function test_without_drift_generates_valid_normal_measurements(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $val = new ValidadorMedicion($this->config);
        $eva = new EvaluadorEstado($this->config);

        foreach (array_keys($this->config['indicadores']) as $clave) {
            foreach (range(1, 30) as $seed) {
                $v = $sim->generar($clave, 0.0, $seed);
                $this->assertSame([], $val->validar($clave, $v), "{$clave} seed {$seed}");
                $this->assertSame(Estado::Normal, $eva->evaluar($clave, $v), "{$clave} seed {$seed}");
            }
        }
    }

    public function test_strong_drift_leaves_normal_but_stays_valid(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $val = new ValidadorMedicion($this->config);
        $eva = new EvaluadorEstado($this->config);

        $v = $sim->generar('frecuencia_cardiaca', 1.0, 7);
        $this->assertSame([], $val->validar('frecuencia_cardiaca', $v));
        $this->assertNotSame(Estado::Normal, $eva->evaluar('frecuencia_cardiaca', $v));
    }

    public function test_is_deterministic_with_seed(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $this->assertSame($sim->generar('glucosa', 0.3, 42), $sim->generar('glucosa', 0.3, 42));
    }
}
