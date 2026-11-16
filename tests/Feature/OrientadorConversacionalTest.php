<?php

namespace Tests\Feature;

use App\Models\Astronauta;
use App\Models\AuditoriaAcceso;
use App\Models\ConsultaSintomas;
use App\Models\Mision;
use App\Services\Ia\OrientadorConversacional;
use Database\Seeders\ExpedicionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Orquestación con colaboradores inyectados (fakes): no depende del analizador, el contexto ni el razonador reales. */
class OrientadorConversacionalTest extends TestCase
{
    use DatabaseTransactions;

    private Astronauta $a;

    /** Llamadas recibidas por los fakes. */
    private array $visto = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        $this->a = Astronauta::where('mision_id', $mision->id)->orderBy('id')->firstOrFail();
    }

    private function servicio(array $respuesta = [], ?array $sintomas = null): OrientadorConversacional
    {
        $sintomas ??= [['clave' => 'mareo', 'etiqueta' => 'Mareo', 'region' => null, 'lado' => null, 'calidad' => null, 'intensidad' => null, 'duracion_min' => null, 'confianza' => 90]];
        $analisis = ['entendido' => true, 'confianza' => 90, 'sintomas' => $sintomas, 'negados' => [], 'banderas_rojas' => []];

        return new OrientadorConversacional([
            'analizar' => function (string $t) use ($analisis) {
                $this->visto['texto'] = $t;

                return $analisis + ['texto' => $t];
            },
            'desdeEntidades' => function (array $s) use ($analisis) {
                $this->visto['desde'] = $s;

                return ['sintomas' => $s] + $analisis;
            },
            'contexto' => fn (Astronauta $a) => ['astronauta' => ['id' => $a->id]],
            'accionesPanel' => fn () => ['a1', 'a2'],
            'razonar' => function (array $an, array $ctx, array $op) use ($respuesta) {
                $this->visto['opciones'] = $op;
                $this->visto['contexto'] = $ctx;

                return $respuesta + ['urgencia' => 'autocuidado', 'alcance' => true, 'hipotesis' => [], 'acciones' => [], 'seguimiento' => [['texto' => 'Mide tu pulso', 'indicador' => 'frecuencia_cardiaca']], 'escalar_si' => [], 'escalar_ahora' => false];
            },
        ]);
    }

    public function test_responder_guarda_la_consulta_con_texto_cifrado_y_audita_sin_valores(): void
    {
        $r = $this->servicio()->responder($this->a, 'Estoy mareado');
        $c = ConsultaSintomas::findOrFail($r['consulta_id']);

        $this->assertSame('Estoy mareado', $c->texto);
        $this->assertSame('autocuidado', $c->urgencia);
        $this->assertSame('mareo', $c->entidades['sintomas'][0]['clave']);
        $this->assertArrayNotHasKey('texto', $c->entidades);
        $this->assertNull($c->util);

        $crudo = DB::table('consultas_sintomas')->where('id', $c->id)->value('texto');
        $this->assertStringNotContainsString('mareado', $crudo);
        $this->assertNotSame('Estoy mareado', $crudo);

        $a = AuditoriaAcceso::where('recurso', 'orientador')->where('recurso_id', $c->id)->first();
        $this->assertNotNull($a);
        $this->assertSame('enviar', $a->accion);
        $this->assertSame($this->a->id, (int) $a->astronauta_id);
        $this->assertStringNotContainsString('mareo', json_encode($a->getAttributes()));
    }

    public function test_pasa_las_acciones_del_panel_y_el_contexto_al_razonador(): void
    {
        $this->servicio()->responder($this->a, 'Estoy mareado');
        $this->assertSame(['a1', 'a2'], $this->visto['opciones']['acciones_panel']);
        $this->assertNull($this->visto['opciones']['evolucion']);
        $this->assertSame($this->a->id, $this->visto['contexto']['astronauta']['id']);
    }

    public function test_el_texto_se_limpia_y_se_limita(): void
    {
        config(['orientador.max_caracteres' => 20]);
        $this->servicio()->responder($this->a, "  uno   dos\n\ntres cuatro cinco seis siete  ");
        $this->assertSame('uno dos tres cuatro', $this->visto['texto']);
    }

    public function test_responder_con_entidades_editadas_usa_desde_entidades(): void
    {
        $s = [['clave' => 'nauseas', 'etiqueta' => 'Náuseas']];
        $r = $this->servicio()->responderConEntidades($this->a, 'Me siento mal', $s);
        $this->assertSame($s, $this->visto['desde']);
        $this->assertSame('Me siento mal', ConsultaSintomas::find($r['consulta_id'])->texto);
        $this->assertSame('nauseas', ConsultaSintomas::find($r['consulta_id'])->entidades['sintomas'][0]['clave']);
    }

    public function test_reevaluar_empeoro_pasa_la_evolucion_y_guarda_una_consulta_nueva(): void
    {
        $svc = $this->servicio();
        $primera = $svc->responder($this->a, 'Estoy mareado')['consulta_id'];
        $segunda = $svc->reevaluar($primera, 'empeoro');

        $this->assertNotSame($primera, $segunda['consulta_id']);
        $this->assertSame('empeoro', $this->visto['opciones']['evolucion']);
        $this->assertSame('autocuidado', $this->visto['opciones']['urgencia_previa']);
        $this->assertSame('empeoro', ConsultaSintomas::find($segunda['consulta_id'])->respuesta['evolucion']);
        $this->assertSame('Estoy mareado', ConsultaSintomas::find($segunda['consulta_id'])->texto);

        $this->expectException(\InvalidArgumentException::class);
        $svc->reevaluar($primera, 'otra');
    }

    public function test_marcar_util(): void
    {
        $svc = $this->servicio();
        $id = $svc->responder($this->a, 'Estoy mareado')['consulta_id'];
        $svc->marcarUtil($id, true);
        $this->assertTrue(ConsultaSintomas::find($id)->util);
        $svc->marcarUtil($id, false);
        $this->assertFalse(ConsultaSintomas::find($id)->util);
    }

    public function test_guardar_en_registro_crea_un_registro_con_los_sintomas(): void
    {
        $svc = $this->servicio();
        $id = $svc->responder($this->a, 'Estoy mareado')['consulta_id'];
        $reg = $svc->guardarEnRegistro($id);

        $this->assertSame($this->a->id, $reg->astronauta_id);
        $this->assertSame(['Mareo'], $reg->sintomas);
        $this->assertStringContainsString('Mareo', $reg->nota);
        $this->assertStringNotContainsString('Estoy mareado', $reg->nota);
    }

    public function test_avisar_a_tierra_envia_un_resumen_de_los_sintomas(): void
    {
        $svc = $this->servicio(['urgencia' => 'emergencia', 'escalar_ahora' => true]);
        $id = $svc->responder($this->a, 'Estoy mareado')['consulta_id'];

        $this->assertTrue($svc->avisarATierra($id));
        $this->assertDatabaseHas('consultas_tierra', ['astronauta_id' => $this->a->id, 'tipo' => 'msg']);
        $nota = DB::table('consultas_tierra')->where('astronauta_id', $this->a->id)->orderByDesc('id')->value('nota');
        $this->assertStringContainsString('Mareo', (string) $nota);
        $this->assertStringContainsString('emergencia', (string) $nota);
    }
}
