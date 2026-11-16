<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\OrientacionAlertas;
use App\Services\Salud\ProcesadorMediciones;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class OrientacionAlertasTest extends TestCase
{
    use DatabaseTransactions;

    private function astronauta(): Astronauta
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $base = $mision->tripulantes()->firstOrFail();
        $a = $base->replicate();
        $a->nombre = 'Prueba Orientación';
        $a->save();

        return $a;
    }

    private function medir(Astronauta $a, string $clave, array $valores): void
    {
        ProcesadorMediciones::make()->registrar($a, $clave, $valores, 'manual', null, false, false);
    }

    public function test_sin_incidencias_no_hay_tarjetas(): void
    {
        $a = $this->astronauta();
        $this->assertSame([], OrientacionAlertas::para($a));
        $this->assertSame([], OrientacionAlertas::compartibles($a));
    }

    public function test_forma_y_orden_de_las_tarjetas(): void
    {
        $a = $this->astronauta();
        $this->medir($a, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84]); // warn
        $this->medir($a, 'perdida_osea', ['valor' => -1.6]); // crit

        $t = OrientacionAlertas::para($a);
        $this->assertSame(['perdida_osea', 'presion_arterial'], array_column($t, 'id'));
        $this->assertEqualsCanonicalizing(['id', 'kind', 'rule', 'sev', 'finding', 'steps', 'pend', 'conf', 'safe', 'src', 'time', 'etiqueta', 'valor', 'icono'], array_keys($t[0]));
        $this->assertSame('ai', $t[0]['kind']);
        $this->assertSame('crit', $t[0]['sev']);
        $this->assertStringContainsString('−1.6 %/mes', $t[0]['finding']);
        $this->assertSame('escal', $t[0]['safe']);
        $this->assertSame(78, $t[0]['conf']);
        $this->assertStringContainsString('[pendiente de revisión médica]', $t[0]['pend']);
        $this->assertSame(['l', 't', 'ic'], array_keys($t[0]['src'][0]));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2} UTC$/', $t[0]['time']);
        $this->assertSame('Pérdida ósea (cadera)', $t[0]['etiqueta']);
        $this->assertSame('−1.6 %/mes', $t[0]['valor']);
        $this->assertSame('warn', $t[1]['sev']);
        $this->assertNull($t[1]['pend']);
    }

    public function test_emergencia_con_spo2_85_va_primero_y_sin_ia(): void
    {
        $a = $this->astronauta();
        $this->medir($a, 'perdida_osea', ['valor' => -1.6]);
        $this->medir($a, 'saturacion_o2', ['valor' => 85]);

        $t = OrientacionAlertas::para($a);
        $this->assertSame('saturacion_o2', $t[0]['id']);
        $this->assertSame('emerg', $t[0]['kind']);
        $this->assertSame('rule', $t[0]['safe']);
        $this->assertSame('crit', $t[0]['sev']);
        $this->assertNull($t[0]['conf']);
        $this->assertStringContainsString('85 %', $t[0]['finding']);
        $this->assertCount(1, array_filter($t, fn ($x) => $x['id'] === 'saturacion_o2'));

        $c = collect(OrientacionAlertas::compartibles($a))->keyBy('id');
        $this->assertTrue($c['saturacion_o2']['locked']);
        $this->assertFalse($c['perdida_osea']['locked']);
    }

    public function test_indicador_sin_libreria_da_tarjeta_generica_de_regla(): void
    {
        $a = $this->astronauta();
        $this->medir($a, 'imc', ['valor' => 30]);

        $t = OrientacionAlertas::para($a);
        $this->assertCount(1, $t);
        $this->assertSame('rule', $t[0]['kind']);
        $this->assertSame('none', $t[0]['safe']);
        $this->assertNotNull($t[0]['rule']);
        $this->assertSame(config('panel.indicadores.imc.acciones'), $t[0]['steps']);
        $this->assertStringContainsString('IMC fuera de rango', $t[0]['finding']);
    }

    public function test_compartibles_forma_y_orden(): void
    {
        $a = $this->astronauta();
        $this->medir($a, 'presion_arterial', ['sistolica' => 128, 'diastolica' => 84]);
        $this->medir($a, 'perdida_osea', ['valor' => -1.6]);

        $this->assertSame([
            ['id' => 'perdida_osea', 'label' => 'Pérdida ósea (cadera)', 'val' => '−1.6 %/mes', 'sev' => 'crit', 'locked' => false],
            ['id' => 'presion_arterial', 'label' => 'Presión arterial', 'val' => '128/84 mmHg', 'sev' => 'warn', 'locked' => false],
        ], OrientacionAlertas::compartibles($a));
    }
}
