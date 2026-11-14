<?php

namespace Tests\Unit\Salud;

use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\SimuladorMediciones;
use App\Services\Salud\ValidadorMedicion;
use PHPUnit\Framework\TestCase;

class SimuladorTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require __DIR__.'/../../../config/umbrales.php';
    }

    public function test_sin_deriva_genera_mediciones_validas_y_normales(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $val = new ValidadorMedicion($this->config);
        $eva = new EvaluadorEstado($this->config);

        foreach (array_keys($this->config['indicadores']) as $clave) {
            foreach (range(1, 30) as $semilla) {
                $v = $sim->generar($clave, 0.0, $semilla);
                $this->assertSame([], $val->validar($clave, $v), "{$clave} semilla {$semilla}");
                $this->assertSame(Estado::Normal, $eva->evaluar($clave, $v), "{$clave} semilla {$semilla}");
            }
        }
    }

    public function test_con_deriva_fuerte_se_sale_de_lo_normal_pero_sigue_siendo_valido(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $val = new ValidadorMedicion($this->config);
        $eva = new EvaluadorEstado($this->config);

        $v = $sim->generar('frecuencia_cardiaca', 1.0, 7);
        $this->assertSame([], $val->validar('frecuencia_cardiaca', $v));
        $this->assertNotSame(Estado::Normal, $eva->evaluar('frecuencia_cardiaca', $v));
    }

    public function test_es_determinista_con_semilla(): void
    {
        $sim = new SimuladorMediciones($this->config);
        $this->assertSame($sim->generar('glucosa', 0.3, 42), $sim->generar('glucosa', 0.3, 42));
    }
}
