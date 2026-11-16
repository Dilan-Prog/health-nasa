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

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class DetectorPatronesTest extends TestCase
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

    /** Registra sin disparar el gancho (origen simulado, sin evento) y sin línea base: estados deterministas. */
    private function reg(Astronauta $a, string $ind, array $valores, int $min, string $origen = 'simulado')
    {
        return ProcesadorMediciones::make()->registrar($a, $ind, $valores, $origen, $this->t($min), emitir: false, usarLineaBase: false);
    }

    /** @return array<string, CasoClinico> abiertos por patrón */
    private function abiertos(Astronauta $a): array
    {
        return CasoClinico::abiertos()->where('astronauta_id', $a->id)->get()->keyBy('patron')->all();
    }

    private function alertasPatron(Astronauta $a)
    {
        return AlertaSaliente::where('astronauta_id', $a->id)->get()->filter(fn ($x) => isset($x->carga['caso_id'])); // alerta propia del patrón o aviso crítico enriquecido con el caso
    }

    public static function tablaDePatrones(): array
    {
        return [
            'fiebre' => ['fiebre', [['temperatura', ['valor' => 37.6], 0]], 'precaucion', 'temperatura'],
            'hipoxia' => ['hipoxia', [['saturacion_o2', ['valor' => 92], 0]], 'precaucion', 'saturacion_o2'],
            'deshidratacion' => ['deshidratacion', [['hidratacion', ['valor' => 2.2], 0]], 'precaucion', 'hidratacion'],
            'estres' => ['estres', [['estres_cognitivo', ['valor' => 65], 0]], 'precaucion', 'estres_cognitivo'],
            'arritmia' => ['arritmia', [['frecuencia_cardiaca', ['valor' => 110], 0]], 'precaucion', 'frecuencia_cardiaca'],
            'falta_sueno' => ['falta_sueno', [['sueno', ['valor' => 6.0], 0]], 'precaucion', 'sueno'],
            'fallo_sensor' => ['fallo_sensor', [['saturacion_o2', ['valor' => 98], 0], ['saturacion_o2', ['valor' => 80], 10]], 'precaucion', 'saturacion_o2'],
        ];
    }

    /** @dataProvider tablaDePatrones */
    public function test_cada_patron_abre_su_caso_con_evidencia(string $patron, array $lecturas, string $severidad, string $indicador): void
    {
        $a = $this->astronauta();
        foreach ($lecturas as [$ind, $val, $min]) {
            $this->reg($a, $ind, $val, $min);
        }

        DetectorPatrones::evaluar($a);

        $abiertos = $this->abiertos($a);
        $this->assertArrayHasKey($patron, $abiertos);
        $caso = $abiertos[$patron];
        $this->assertSame($severidad, $caso->severidad);
        $this->assertSame($indicador, $caso->evidencia['senales'][0]['indicador']);
        $this->assertSame((int) config("patrones.{$patron}.ventana_min"), $caso->evidencia['ventana_min']);
        $this->assertNotNull($caso->detectado_en);
        $this->assertNull($caso->escalado_en);
    }

    /** @dataProvider tablaDePatrones */
    public function test_una_lectura_normal_no_abre_caso(string $patron, array $lecturas): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 72], 0);
        $this->reg($a, 'saturacion_o2', ['valor' => 98], 1);
        $this->reg($a, 'temperatura', ['valor' => 36.6], 2);

        $this->assertSame([], DetectorPatrones::evaluar($a));
        $this->assertSame([], $this->abiertos($a));
    }

    public function test_dos_evaluaciones_no_duplican_el_caso(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 92], 0);

        DetectorPatrones::evaluar($a);
        DetectorPatrones::evaluar($a);

        $this->assertSame(1, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'hipoxia')->count());
    }

    public function test_la_senal_de_apoyo_se_suma_a_la_evidencia(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 92], 0);
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 5);

        DetectorPatrones::evaluar($a);

        $caso = $this->abiertos($a)['hipoxia'];
        $this->assertSame(2, $caso->evidencia['coinciden']);
        $this->assertEqualsCanonicalizing(['saturacion_o2', 'frecuencia_cardiaca'], array_column($caso->evidencia['senales'], 'indicador'));
    }

    public function test_la_severidad_sube_si_empeora_y_no_baja(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);
        $this->assertSame('precaucion', $this->abiertos($a)['arritmia']->severidad);

        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 140], 10);
        DetectorPatrones::evaluar($a);
        $this->assertSame('critico', $this->abiertos($a)['arritmia']->severidad);
        $this->assertSame(1, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->count());

        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 112], 20);
        DetectorPatrones::evaluar($a);
        $this->assertSame('critico', $this->abiertos($a)['arritmia']->severidad, 'un caso abierto no baja de severidad');
    }

    public function test_se_cierra_cuando_las_senales_vuelven_a_normal(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);

        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 80], 10);
        $tocados = DetectorPatrones::evaluar($a);

        $this->assertSame([], $this->abiertos($a));
        $caso = CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->first();
        $this->assertSame('cerrado', $caso->estado);
        $this->assertNotNull($caso->cerrado_en);
        $this->assertSame('cerrado', $tocados[0]->estado);
    }

    public function test_un_caso_cerrado_no_impide_abrir_uno_nuevo(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 0);
        DetectorPatrones::evaluar($a);
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 80], 10);
        DetectorPatrones::evaluar($a);
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 112], 20);
        DetectorPatrones::evaluar($a);

        $this->assertSame(2, CasoClinico::where('astronauta_id', $a->id)->where('patron', 'arritmia')->count());
        $this->assertArrayHasKey('arritmia', $this->abiertos($a));
    }

    public function test_una_senal_fuera_de_la_ventana_no_cuenta(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'temperatura', ['valor' => 37.6], 0);
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 72], (int) config('patrones.fiebre.ventana_min') + 60);

        DetectorPatrones::evaluar($a);

        $this->assertArrayNotHasKey('fiebre', $this->abiertos($a));
    }

    public function test_no_dispara_con_datos_sospechosos_salvo_fallo_de_sensor(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 98], 0);
        $ev = $this->reg($a, 'saturacion_o2', ['valor' => 80], 10); // salto brusco: sospechosa y crítica

        $this->assertSame('sospechosa', $ev->medicion->calidad);
        $this->assertSame('critico', $ev->estado);

        DetectorPatrones::evaluar($a);

        $abiertos = $this->abiertos($a);
        $this->assertArrayNotHasKey('hipoxia', $abiertos);
        $this->assertArrayHasKey('fallo_sensor', $abiertos);
        $this->assertSame('precaucion', $abiertos['fallo_sensor']->severidad, 'un fallo de sensor no escala por sí mismo');
        $this->assertNull($abiertos['fallo_sensor']->escalado_en);
        $this->assertSame(0, $this->alertasPatron($a)->count());
    }

    public function test_el_fallo_de_sensor_se_cierra_con_una_lectura_buena(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 98], 0);
        $this->reg($a, 'saturacion_o2', ['valor' => 80], 10);
        DetectorPatrones::evaluar($a);
        $this->assertArrayHasKey('fallo_sensor', $this->abiertos($a));

        $this->reg($a, 'saturacion_o2', ['valor' => 97], 20);
        DetectorPatrones::evaluar($a);

        $this->assertArrayNotHasKey('fallo_sensor', $this->abiertos($a));
    }

    public function test_un_patron_critico_escala_a_tierra_una_sola_vez(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 85], 0); // < 90: crítico

        DetectorPatrones::evaluar($a);
        DetectorPatrones::evaluar($a);
        $this->reg($a, 'saturacion_o2', ['valor' => 84], 5);
        DetectorPatrones::evaluar($a);

        $caso = $this->abiertos($a)['hipoxia'];
        $this->assertSame('critico', $caso->severidad);
        $this->assertNotNull($caso->escalado_en);
        $alertas = $this->alertasPatron($a);
        $this->assertCount(1, $alertas);
        $carga = $alertas->first()->carga;
        $this->assertSame('pendiente', $alertas->first()->estado);
        $this->assertSame('hipoxia', $carga['patron']);
        $this->assertSame('critico', $carga['severidad']);
        $this->assertSame($caso->id, $carga['caso_id']);
        $this->assertSame('Test', $carga['astronauta']);
        $this->assertArrayHasKey('senales', $carga['evidencia']);
    }

    public function test_un_caso_que_empeora_a_critico_escala_cuando_lo_hace(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'saturacion_o2', ['valor' => 92], 0);
        DetectorPatrones::evaluar($a);
        $this->assertSame(0, $this->alertasPatron($a)->count());

        $this->reg($a, 'saturacion_o2', ['valor' => 86], 5);
        DetectorPatrones::evaluar($a);

        $this->assertSame(1, $this->alertasPatron($a)->count());
    }

    public function test_enlaza_el_evento_de_simulacion_abierto_del_mismo_escenario(): void
    {
        $a = $this->astronauta();
        $evento = SimulacionEvento::create(['astronauta_id' => $a->id, 'escenario' => 'hipoxia', 'inicio_en' => now()]);
        SimulacionEvento::create(['astronauta_id' => $a->id, 'escenario' => 'fiebre', 'inicio_en' => now()]);
        $this->reg($a, 'saturacion_o2', ['valor' => 92], 0);

        DetectorPatrones::evaluar($a);

        $this->assertSame($evento->id, $this->abiertos($a)['hipoxia']->simulacion_evento_id);
    }

    public function test_el_gancho_abre_el_caso_al_registrar_una_medicion(): void
    {
        $a = $this->astronauta();

        $this->reg($a, 'saturacion_o2', ['valor' => 92], 0, 'manual');

        $this->assertArrayHasKey('hipoxia', $this->abiertos($a));
    }

    public function test_el_gancho_retorna_al_instante_si_todo_es_normal(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 72], 0, 'manual');

        $this->assertSame([], $this->abiertos($a));
        $this->assertFalse(Cache::has('patrones.enfriamiento.'.$a->id), 'no evaluó: ni siquiera tocó el enfriamiento');
    }

    public function test_el_enfriamiento_evita_recalcular_dentro_de_la_ventana(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 0, 'manual'); // evalúa: arritmia
        $this->assertArrayHasKey('arritmia', $this->abiertos($a));

        $this->reg($a, 'saturacion_o2', ['valor' => 92], 5); // sin gancho
        DetectorPatrones::alMedir($a, 'saturacion_o2', 'precaucion'); // en enfriamiento
        $this->assertArrayNotHasKey('hipoxia', $this->abiertos($a));

        Cache::forget('patrones.enfriamiento.'.$a->id);
        DetectorPatrones::alMedir($a, 'saturacion_o2', 'precaucion');
        $this->assertArrayHasKey('hipoxia', $this->abiertos($a));
    }

    public function test_una_lectura_critica_no_espera_al_enfriamiento_si_no_hay_caso_critico(): void
    {
        $a = $this->astronauta();
        $this->reg($a, 'frecuencia_cardiaca', ['valor' => 110], 0, 'manual'); // abre arritmia (precaución) y arma el enfriamiento

        $this->reg($a, 'saturacion_o2', ['valor' => 85], 5, 'manual'); // crítica: evalúa pese al enfriamiento

        $this->assertSame('critico', $this->abiertos($a)['hipoxia']->severidad);
        $this->assertSame(1, $this->alertasPatron($a)->count());
    }

    public function test_el_gancho_nunca_rompe_el_registro_si_el_detector_falla(): void
    {
        $a = $this->astronauta();
        config(['patrones' => 'configuracion-rota']);

        $ev = $this->reg($a, 'saturacion_o2', ['valor' => 92], 0, 'manual');

        $this->assertSame('precaucion', $ev->estado);
        $this->assertSame(1, Medicion::where('astronauta_id', $a->id)->count());
        $this->expectException(\Throwable::class); // evaluar() sí lanza: solo el gancho lo traga
        DetectorPatrones::evaluar($a);
    }
}
