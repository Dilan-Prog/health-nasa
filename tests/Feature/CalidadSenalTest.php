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

/** Datos dudosos: se guardan siempre y se etiquetan "sospechosa" (salto brusco, señal plana); no rechazar. */
class CalidadSenalTest extends TestCase
{
    use DatabaseTransactions;

    private function astronauta(): Astronauta
    {
        Event::fake();

        return Astronauta::create(['nombre' => 'Test', 'mision' => 'T-1', 'entorno' => 'estacion']);
    }

    private function t(int $min): Carbon
    {
        return Carbon::parse('2026-11-16 08:00:00', 'UTC')->addMinutes($min);
    }

    public function test_lectura_normal_queda_ok(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 74], 'manual', $this->t(10));

        $this->assertSame('ok', $ev->medicion->calidad);
        $this->assertNull($ev->medicion->calidad_motivo);
    }

    public function test_salto_brusco_se_guarda_como_sospechosa_sin_alerta_a_tierra(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 118], 'manual', $this->t(10)); // +48 lpm en 10 min

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertStringContainsString('Salto brusco', $ev->medicion->calidad_motivo);
        $this->assertSame(2, Medicion::where('astronauta_id', $a->id)->count(), 'se guarda siempre');
        $this->assertNotSame('critico', $ev->estado);
        $this->assertSame(0, AlertaSaliente::where('astronauta_id', $a->id)->count(), 'una sospechosa no avisa a tierra por sí sola');
    }

    public function test_salto_grande_con_mucho_tiempo_entre_lecturas_no_es_sospechoso(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 70], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 118], 'manual', $this->t(600));

        $this->assertSame('ok', $ev->medicion->calidad);
    }

    public function test_senal_plana_n_lecturas_identicas_seguidas(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $n = config('calidad.indicadores.frecuencia_cardiaca.plana_n');
        $cal = [];
        for ($i = 0; $i < $n; $i++) {
            $cal[] = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 64], 'manual', $this->t($i * 5))->medicion->calidad;
        }

        $this->assertSame(array_fill(0, $n - 1, 'ok'), array_slice($cal, 0, $n - 1));
        $this->assertSame('sospechosa', $cal[$n - 1]);
        $this->assertStringContainsString('Señal plana', Medicion::where('astronauta_id', $a->id)->orderByDesc('medido_en')->first()->calidad_motivo);
    }

    public function test_sospechosa_que_ademas_es_critica_mantiene_critico_y_el_aviso(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $p->registrar($a, 'saturacion_o2', ['valor' => 97], 'manual', $this->t(0));
        $ev = $p->registrar($a, 'saturacion_o2', ['valor' => 78], 'manual', $this->t(10)); // salto de 19 puntos y crítico

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertSame('critico', $ev->estado, 'no se oculta el riesgo');
        $alerta = AlertaSaliente::where('evaluacion_id', $ev->id)->first();
        $this->assertNotNull($alerta);
        $this->assertStringContainsString('Salto brusco', $alerta->carga['dato_sospechoso']);
    }

    public function test_sospechosa_no_contamina_la_linea_base_movil(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        foreach ([60, 61, 60, 61, 60] as $i => $v) {
            $p->registrar($a, 'frecuencia_cardiaca', ['valor' => $v], 'manual', $this->t($i * 5));
        }
        $this->assertSame('sospechosa', $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 150], 'manual', $this->t(35))->medicion->calidad);

        // Con línea base limpia (≈60,4) un 66 se aleja >2 desviaciones y sube a precaución; si el 150 la contaminara, seguiría normal.
        $ev = $p->registrar($a, 'frecuencia_cardiaca', ['valor' => 66], 'manual', $this->t(45));
        $this->assertSame('ok', $ev->medicion->calidad);
        $this->assertSame('precaucion', $ev->estado);
    }

    public function test_escenario_fallo_sensor_del_simulador_se_marca_sospechoso(): void
    {
        $a = $this->astronauta();
        $p = ProcesadorMediciones::make();
        $sim = new SimuladorVivo(config('simulacion'), config('umbrales'));
        $inicio = Carbon::parse('2026-11-16 02:00:00', 'UTC');
        $r = $sim->avanzar($sim->estadoInicial(8), Carbon::parse('2026-11-16 01:00:00', 'UTC'), Carbon::parse('2026-11-16 03:00:00', 'UTC'), 1,
            Carbon::parse('2026-11-16 03:00:00', 'UTC'), ['clave' => 'fallo_sensor', 'inicio' => $inicio, 'dura_s' => 3600]);

        foreach ($r['mediciones'] as $m) {
            if (in_array($m->indicador, ['frecuencia_cardiaca', 'saturacion_o2'], true)) {
                try {
                    $p->registrar($a, $m->indicador, $m->valores, 'simulado', $m->medidoEn, false);
                } catch (\Illuminate\Validation\ValidationException) {
                    // duplicadas del propio simulador (como hace ControlSimulacion)
                }
            }
        }

        $o2 = Medicion::where('astronauta_id', $a->id)->where('indicador', 'saturacion_o2')->where('calidad', 'sospechosa')->get();
        $fc = Medicion::where('astronauta_id', $a->id)->where('indicador', 'frecuencia_cardiaca')->where('calidad', 'sospechosa')->get();
        $this->assertTrue($o2->contains(fn ($m) => $m->valores['valor'] < 50), 'SpO₂ imposible: guardada como sospechosa, no rechazada');
        $this->assertGreaterThan(0, $fc->count(), 'pulso congelado: señal plana');
        $this->assertStringContainsString('Señal plana', $fc->first()->calidad_motivo);
    }

    public function test_el_registro_manual_imposible_sigue_rechazandose(): void
    {
        $a = $this->astronauta();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ProcesadorMediciones::make()->registrar($a, 'saturacion_o2', ['valor' => 0], 'manual', $this->t(0));
    }
}
