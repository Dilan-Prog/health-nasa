<?php

namespace Tests\Unit\Salud;

use App\Services\Salud\Estado;
use App\Services\Salud\EvaluadorEstado;
use App\Services\Salud\ValidadorMedicion;
use Database\Seeders\ExpedicionSeeder;
use PHPUnit\Framework\TestCase;

/** Los 50 valores del diseño (5 tripulantes x 10 indicadores) deben dar exactamente su estado. */
class EstadosDisenoTest extends TestCase
{
    private const UI = ['ok' => Estado::Normal, 'warn' => Estado::Precaucion, 'crit' => Estado::Critico];

    public function test_los_50_estados_del_diseno(): void
    {
        $config = require __DIR__.'/../../../config/umbrales.php';
        $evaluador = new EvaluadorEstado($config);
        $validador = new ValidadorMedicion($config);
        $total = 0;

        foreach (ExpedicionSeeder::TRIPULACION as $t) {
            foreach (ExpedicionSeeder::CLAVES as $k => $clave) {
                $m = $t['m'][$k];
                $valores = $clave === 'presion_arterial'
                    ? ['sistolica' => $m[0], 'diastolica' => $m[1]]
                    : ['valor' => $m[0]];
                $esperado = self::UI[end($m)];
                // Nivel 2: la temperatura de Lucas (37.3) es normal por salto sobre su base móvil; este test no
                // lleva base personal, así que aquí rige el rango absoluto de respaldo (precaución).
                if ($t['nombre'] === 'Lucas Ferreira' && $clave === 'temperatura') {
                    $esperado = Estado::Precaucion;
                }

                $this->assertSame([], $validador->validar($clave, $valores), "{$t['nombre']} {$clave} es válido");
                $this->assertSame($esperado, $evaluador->evaluar($clave, $valores), "{$t['nombre']} · {$clave}");
                $total++;
            }
        }

        $this->assertSame(50, $total);
    }

    public function test_valores_negativos_son_validos_y_se_evaluan_con_signo(): void
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
