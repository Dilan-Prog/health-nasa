<?php

namespace Tests\Feature;

use App\Models\Alergia;
use App\Models\AntecedenteFamiliar;
use App\Models\Astronauta;
use App\Models\AuditoriaAcceso;
use App\Models\BotiquinItem;
use App\Models\CasoClinico;
use App\Models\CondicionMedica;
use App\Models\ConsultaTierra;
use App\Models\MedicamentoTripulante;
use App\Models\Medicion;
use App\Models\Mision;
use App\Models\OrientacionFeedback;
use App\Models\RegistroSalud;
use App\Models\Rutina;
use App\Models\RutinaEjecucion;
use App\Models\User;
use App\Models\ZonaDolor;
use App\Services\Ia\ContextoTripulante;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class ContextoTripulanteTest extends TestCase
{
    use DatabaseTransactions;

    private Mision $mision;

    private Astronauta $a;

    private Astronauta $b;

    protected function setUp(): void
    {
        parent::setUp();
        config(['contexto.cache_s' => 0, 'mision.exigir_login' => false]);
        $this->mision = Mision::create(['nombre' => 'Test Contexto '.uniqid(), 'entorno' => 'nave', 'inicio_en' => now('UTC')->subDays(141)->toDateString(), 'duracion_dias' => 180, 'care_level' => 4]);
        $this->a = Astronauta::create(['mision_id' => $this->mision->id, 'nombre' => 'Ana Contexto', 'sexo' => 'F', 'edad' => 38, 'entorno' => 'nave']);
        $this->b = Astronauta::create(['mision_id' => $this->mision->id, 'nombre' => 'Beto Contexto', 'sexo' => 'M', 'edad' => 41, 'entorno' => 'nave']);
    }

    private function medir(Astronauta $x, string $indicador, array $valores, ?Carbon $cuando = null, string $calidad = 'ok'): Medicion
    {
        return Medicion::create(['astronauta_id' => $x->id, 'indicador' => $indicador, 'valores' => $valores, 'medido_en' => $cuando ?? now('UTC'), 'origen' => 'test', 'calidad' => $calidad]);
    }

    public function test_estructura_completa(): void
    {
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 72]);
        $c = ContextoTripulante::de($this->a);

        foreach (['astronauta', 'expediente', 'indicadores', 'casos', 'sintomas_registrados', 'dolores', 'consultas_previas', 'rutina', 'mision', 'botiquin', 'consultas_tierra', 'usados'] as $k) {
            $this->assertArrayHasKey($k, $c, $k);
        }
        $this->assertSame(['id', 'nombre', 'edad', 'sexo'], array_keys($c['astronauta']));
        $this->assertSame(['alergias', 'condiciones', 'antecedentes', 'medicacion'], array_keys($c['expediente']));
        $this->assertSame(['ejercicio_min_semana', 'meta_min_dia', 'sueno_h_ultimo', 'ejecuciones', 'ejercicio_intenso_reciente'], array_keys($c['rutina']));
        $this->assertSame(['care_level', 'fase', 'dia', 'entorno', 'retardo_s', 'eventos_proximos'], array_keys($c['mision']));
        $this->assertSame(150, $c['rutina']['meta_min_dia']);

        $fc = $c['indicadores']['frecuencia_cardiaca'];
        $this->assertSame(['etiqueta', 'unidad', 'valor_texto', 'valores', 'estado', 'medido_en', 'base_texto', 'desviacion_pct', 'serie_7d', 'lecturas_24h', 'sospechoso', 'direccion'], array_keys($fc));
        $this->assertSame('72', $fc['valor_texto']);
        $this->assertSame('ok', $fc['estado']);
        $this->assertCount(7, $fc['serie_7d']);
        $this->assertSame(1, $fc['lecturas_24h']);
        $this->assertFalse($fc['sospechoso']);
        $this->assertNull($fc['direccion']);

        $this->assertSame('Ana Contexto', $c['astronauta']['nombre']);
        $this->assertSame(4, $c['mision']['care_level']);
        $this->assertSame(142, $c['mision']['dia']);
        $this->assertSame(60, $c['mision']['retardo_s']);
        $this->assertSame('nave', $c['mision']['entorno']);
        $eventos = array_column($c['mision']['eventos_proximos'], 'dia');
        $this->assertContains(143, $eventos);
        $this->assertSame(1, $c['mision']['eventos_proximos'][0]['en_dias']);
        $this->assertNotEmpty($c['usados']);
        foreach ($c['usados'] as $u) {
            $this->assertSame(['etiqueta', 'detalle'], array_keys($u));
        }
    }

    public function test_sin_tabla_consultas_sintomas_devuelve_lista_vacia(): void
    {
        $this->assertSame([], ContextoTripulante::de($this->a)['consultas_previas']);
    }

    public function test_solo_incluye_lo_del_tripulante_pedido(): void
    {
        $b = $this->b;
        Alergia::create(['astronauta_id' => $b->id, 'sustancia' => 'Mariscos-B', 'reaccion' => 'Urticaria-B', 'gravedad' => 'leve']);
        CondicionMedica::create(['astronauta_id' => $b->id, 'nombre' => 'Asma-B', 'estado' => 'controlada']);
        AntecedenteFamiliar::create(['astronauta_id' => $b->id, 'parentesco' => 'padre', 'condicion' => 'Infarto-B']);
        MedicamentoTripulante::create(['astronauta_id' => $b->id, 'nombre' => 'Medicamento-B', 'motivo' => 'Motivo-B', 'activo' => true]);
        $this->medir($b, 'glucosa', ['valor' => 177]);
        CasoClinico::create(['astronauta_id' => $b->id, 'patron' => 'patron_solo_b', 'severidad' => 'critico', 'estado' => 'abierto', 'detectado_en' => now('UTC')]);
        $r = RegistroSalud::create(['astronauta_id' => $b->id, 'origen' => 'manual', 'sintomas' => ['Mareo'], 'nota' => 'nota-solo-b', 'aviso_tierra' => true, 'registrado_en' => now('UTC')]);
        ZonaDolor::create(['registro_id' => $r->id, 'astronauta_id' => $b->id, 'musculo' => 'pectoral_mayor_izq', 'intensidad' => 7, 'tipo' => 'Punzante', 'desde' => 'Hoy']);
        ConsultaTierra::create(['astronauta_id' => $b->id, 'tipo' => 'msg', 'titulo' => 'titulo-solo-b', 'indicadores' => ['glucosa']]);
        OrientacionFeedback::create(['astronauta_id' => $b->id, 'indicador' => 'glucosa', 'voto' => 'up']);
        $ru = Rutina::create(['astronauta_id' => $b->id, 'nombre' => 'rutina-solo-b', 'tipo' => 'Cardio', 'icono' => 'pedal_bike', 'kind' => 'ej', 'hora' => '08:00', 'cantidad' => 40, 'dias' => [1, 2, 3, 4, 5, 6, 7], 'activa' => true]);
        RutinaEjecucion::create(['rutina_id' => $ru->id, 'fecha' => now('UTC')->toDateString(), 'estado' => 'done', 'hecha_en' => now('UTC')]);

        $a = ContextoTripulante::de($this->a);
        $json = json_encode($a, JSON_UNESCAPED_UNICODE);
        foreach (['-B', 'solo-b', 'solo_b', 'Beto'] as $traza) {
            $this->assertStringNotContainsString($traza, $json, $traza);
        }
        $this->assertSame([], $a['indicadores']);
        $this->assertSame([], $a['casos']);
        $this->assertSame([], $a['dolores']);
        $this->assertSame([], $a['sintomas_registrados']);
        $this->assertSame([], $a['consultas_tierra']);
        $this->assertSame([], $a['votos']);
        $this->assertSame(0, $a['rutina']['ejercicio_min_semana']);
        $this->assertFalse($a['rutina']['ejercicio_intenso_reciente']);

        // Y el de B sí lo trae todo (misma instantánea, otro tripulante).
        $cb = ContextoTripulante::de($b);
        $this->assertSame('Mariscos-B', $cb['expediente']['alergias'][0]['sustancia']);
        $this->assertSame(40, $cb['rutina']['ejercicio_min_semana']);
        $this->assertTrue($cb['rutina']['ejercicio_intenso_reciente']);
        $this->assertSame('patron_solo_b', $cb['casos'][0]['patron']);
        $this->assertTrue($cb['sintomas_registrados'][0]['aviso_a_tierra']);
        $this->assertSame(7, $cb['dolores'][0]['intensidad']);
        $this->assertSame('pectoral_mayor_izq', $cb['dolores'][0]['musculo']);
        $this->assertSame([['indicador' => 'glucosa', 'voto' => 'up']], $cb['votos']);
        $this->assertSame(['glucosa'], $cb['consultas_tierra'][0]['indicadores']);
    }

    public function test_respeta_roles_cuando_el_login_esta_activo(): void
    {
        Alergia::create(['astronauta_id' => $this->b->id, 'sustancia' => 'Polen-B', 'reaccion' => 'Rinitis', 'gravedad' => 'leve']);
        $this->medir($this->b, 'glucosa', ['valor' => 100]);
        $userA = User::factory()->create(['rol' => 'tripulante']);
        $this->a->update(['user_id' => $userA->id]);
        config(['mision.exigir_login' => true]);

        $this->actingAs($userA);
        $propio = ContextoTripulante::de($this->a);
        $this->assertSame('Ana Contexto', $propio['astronauta']['nombre']);

        $ajeno = ContextoTripulante::de($this->b);
        $this->assertSame([], $ajeno['expediente']['alergias']);
        $this->assertSame([], $ajeno['indicadores']);
        $this->assertNull($ajeno['astronauta']['nombre']);
        $this->assertStringNotContainsString('Polen-B', json_encode($ajeno));
        $this->assertSame('Acceso', $ajeno['usados'][0]['etiqueta']);

        $this->actingAs(User::factory()->create(['rol' => 'flight_surgeon']));
        $this->assertSame('Polen-B', ContextoTripulante::de($this->b)['expediente']['alergias'][0]['sustancia']);
    }

    public function test_ignora_mediciones_sospechosas_en_la_base_pero_las_marca(): void
    {
        for ($d = 7; $d >= 1; $d--) {
            $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 64], now('UTC')->subDays($d)->setTime(10, 0));
        }
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 70], now('UTC')->subMinute());
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 220], now('UTC'), 'sospechosa');

        $fc = ContextoTripulante::de($this->a)['indicadores']['frecuencia_cardiaca'];
        $this->assertSame('70', $fc['valor_texto']);
        $this->assertTrue($fc['sospechoso']);
        $this->assertStringContainsString('media personal', $fc['base_texto']);
        $this->assertStringContainsString('64', $fc['base_texto']);
        $this->assertEqualsWithDelta(9.4, $fc['desviacion_pct'], 0.2); // (70-64)/64, sin la lectura de 220
        $this->assertNotContains(220.0, $fc['serie_7d']);
        $this->assertLessThanOrEqual(2, $fc['lecturas_24h']); // la sospechosa no cuenta
    }

    public function test_indicador_solo_con_lecturas_sospechosas_se_marca_sin_valores(): void
    {
        $this->medir($this->a, 'saturacion_o2', ['valor' => 40], now('UTC'), 'sospechosa');
        $s = ContextoTripulante::de($this->a)['indicadores']['saturacion_o2'];
        $this->assertTrue($s['sospechoso']);
        $this->assertNull($s['valor_texto']);
        $this->assertNull($s['estado']);
    }

    public function test_expediente_descifrado(): void
    {
        Alergia::create(['astronauta_id' => $this->a->id, 'sustancia' => 'Penicilina', 'reaccion' => 'Urticaria', 'gravedad' => 'grave']);
        CondicionMedica::create(['astronauta_id' => $this->a->id, 'nombre' => 'Hipertensión leve', 'estado' => 'controlada']);
        AntecedenteFamiliar::create(['astronauta_id' => $this->a->id, 'parentesco' => 'madre', 'condicion' => 'Diabetes tipo 2']);
        MedicamentoTripulante::create(['astronauta_id' => $this->a->id, 'nombre' => 'Losartán', 'motivo' => 'Presión', 'activo' => true, 'dosis_texto' => 'NO-DEBE-SALIR']);
        MedicamentoTripulante::create(['astronauta_id' => $this->a->id, 'nombre' => 'Inactivo', 'motivo' => 'x', 'activo' => false]);

        $e = ContextoTripulante::de($this->a)['expediente'];
        $this->assertSame([['sustancia' => 'Penicilina', 'reaccion' => 'Urticaria']], $e['alergias']);
        $this->assertSame([['nombre' => 'Hipertensión leve', 'estado' => 'controlada']], $e['condiciones']);
        $this->assertSame([['condicion' => 'Diabetes tipo 2', 'parentesco' => 'madre']], $e['antecedentes']);
        $this->assertSame([['nombre' => 'Losartán', 'motivo' => 'Presión']], $e['medicacion']);
        $this->assertStringNotContainsString('NO-DEBE-SALIR', json_encode(ContextoTripulante::de($this->a)));
    }

    public function test_audita_el_uso_sin_valores_medicos(): void
    {
        Alergia::create(['astronauta_id' => $this->a->id, 'sustancia' => 'Penicilina', 'reaccion' => 'Urticaria', 'gravedad' => 'grave']);
        $c = ContextoTripulante::de($this->a);

        $fila = AuditoriaAcceso::where('accion', 'ver')->where('recurso', 'orientador')->where('astronauta_id', $this->a->id)->first();
        $this->assertNotNull($fila);
        $this->assertSame($this->a->id, $fila->recurso_id);
        $volcado = json_encode($fila->getAttributes());
        $this->assertStringNotContainsString('Penicilina', $volcado);
        $this->assertStringNotContainsString('Urticaria', $volcado);
        // 'usados' tampoco filtra valores: solo recuentos.
        $this->assertStringNotContainsString('Penicilina', json_encode($c['usados']));
    }

    public function test_botiquin_vigente_es_stock_y_no_caducado(): void
    {
        $u = uniqid();
        $mk = fn (string $n, int $stock, ?string $cad) => BotiquinItem::create(['nombre' => $n.$u, 'grupo' => 'Mareo por adaptación', 'stock' => $stock, 'caducidad' => $cad]);
        $ok = $mk('ok', 5, now('UTC')->addMonths(6)->toDateString());
        $sinStock = $mk('sinstock', 0, now('UTC')->addMonths(6)->toDateString());
        $caducado = $mk('caducado', 5, now('UTC')->subDay()->toDateString());
        $hoy = $mk('hoy', 2, now('UTC')->toDateString());

        $por = collect(ContextoTripulante::de($this->a)['botiquin'])->keyBy('id');
        $this->assertTrue($por[$ok->id]['vigente']);
        $this->assertFalse($por[$sinStock->id]['vigente']);
        $this->assertFalse($por[$caducado->id]['vigente']);
        $this->assertTrue($por[$hoy->id]['vigente']);
        $this->assertSame(['id', 'nombre', 'grupo', 'stock', 'caduca', 'vigente'], array_keys($por[$ok->id]));
        $this->assertSame(now('UTC')->subDay()->toDateString(), $por[$caducado->id]['caduca']);
    }

    public function test_direccion_alto_bajo_o_null(): void
    {
        $this->medir($this->a, 'glucosa', ['valor' => 180]);   // por encima de 140 -> alto
        $this->medir($this->b, 'glucosa', ['valor' => 62]);    // por debajo de 70 -> bajo
        $c = $this->mision->tripulantes()->create(['nombre' => 'Cleo Contexto']);
        $this->medir($c, 'glucosa', ['valor' => 100]);         // normal

        $ga = ContextoTripulante::de($this->a)['indicadores']['glucosa'];
        $gb = ContextoTripulante::de($this->b)['indicadores']['glucosa'];
        $gc = ContextoTripulante::de($c)['indicadores']['glucosa'];
        $this->assertSame('warn', $ga['estado']);
        $this->assertSame('alto', $ga['direccion']);
        $this->assertSame('bajo', $gb['direccion']);
        $this->assertSame('ok', $gc['estado']);
        $this->assertNull($gc['direccion']);

        $this->medir($c, 'presion_arterial', ['sistolica' => 150, 'diastolica' => 95], now('UTC')->addSecond());
        $this->assertSame('alto', ContextoTripulante::de($c)['indicadores']['presion_arterial']['direccion']);
        $this->assertSame('150/95', ContextoTripulante::de($c)['indicadores']['presion_arterial']['valor_texto']);
    }

    public function test_serie_7d_y_lecturas_24h(): void
    {
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 60], now('UTC')->subDays(5)->setTime(9, 0));
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 70], now('UTC')->subDays(3)->setTime(9, 0));
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 80], now('UTC')->subMinutes(2));
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 82], now('UTC')->subMinute());

        $fc = ContextoTripulante::de($this->a)['indicadores']['frecuencia_cardiaca'];
        $this->assertCount(7, $fc['serie_7d']);
        $this->assertSame(82.0, $fc['serie_7d'][6]);
        $this->assertSame(60.0, $fc['serie_7d'][1]);      // hace 5 días
        $this->assertEqualsWithDelta(65.0, $fc['serie_7d'][2], 0.01); // interpolado entre 60 y 70
        $this->assertGreaterThanOrEqual(2, $fc['lecturas_24h']);
    }

    public function test_cache_de_indicadores_se_invalida_con_una_medicion_nueva(): void
    {
        config(['contexto.cache_s' => 60]);
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 70], now('UTC')->subMinutes(5));
        $this->assertSame('70', ContextoTripulante::de($this->a)['indicadores']['frecuencia_cardiaca']['valor_texto']);
        $this->medir($this->a, 'frecuencia_cardiaca', ['valor' => 90], now('UTC')->subMinute());
        $this->assertSame('90', ContextoTripulante::de($this->a)['indicadores']['frecuencia_cardiaca']['valor_texto']);
    }
}
