<?php

namespace Tests\Unit\Salud;

use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\ValidadorMedicion;
use Database\Seeders\ExpedicionSeeder;
use PHPUnit\Framework\TestCase;

/** The 50 design values (5 crew members x 10 indicators) must each yield exactly their state. */
class DesignStatesTest extends TestCase
{
    private const UI = ['ok' => Estado::Normal, 'warn' => Estado::Precaucion, 'crit' => Estado::Critico];

    public function test_the_50_design_states(): void
    {
        $config = require __DIR__.'/../../../config/umbrales.php';
        $evaluator = new EvaluadorEstado($config);
        $validator = new ValidadorMedicion($config);
        $total = 0;

        foreach (ExpedicionSeeder::TRIPULACION as $t) {
            foreach (ExpedicionSeeder::CLAVES as $k => $clave) {
                $m = $t['m'][$k];
                $values = $clave === 'presion_arterial'
                    ? ['sistolica' => $m[0], 'diastolica' => $m[1]]
                    : ['valor' => $m[0]];
                $expected = self::UI[end($m)];
                // Level 2: Lucas's temperature (37.3) is normal because of the jump over his rolling baseline; this test
                // carries no personal baseline, so the fallback absolute range (caution) applies here.
                if ($t['nombre'] === 'Lucas Ferreira' && $clave === 'temperatura') {
                    $expected = Estado::Precaucion;
                }

                $this->assertSame([], $validator->validar($clave, $values), "{$t['nombre']} {$clave} is valid");
                $this->assertSame($expected, $evaluator->evaluar($clave, $values), "{$t['nombre']} · {$clave}");
                $total++;
            }
        }

        $this->assertSame(50, $total);
    }

    public function test_negative_values_are_valid_and_evaluated_with_sign(): void
    {
        $config = require __DIR__.'/../../../config/umbrales.php';
        $e = new EvaluadorEstado($config);

        $this->assertSame(Estado::Normal, $e->evaluar('perdida_osea', ['valor' => -1.0]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('perdida_osea', ['valor' => -1.3]));
        $this->assertSame(Estado::Critico, $e->evaluar('perdida_osea', ['valor' => -1.4]));
        $this->assertSame(Estado::Critico, $e->evaluar('masa_muscular', ['valor' => -3.1]));
        $this->assertSame(Estado::Critico, $e->evaluar('radiacion_diaria', ['valor' => 0.9]));
    }
}
