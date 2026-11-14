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

class MotorSaludTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require __DIR__.'/../../../config/umbrales.php';
    }

    // ---- Evaluador -------------------------------------------------------

    public function test_valor_simple_normal_precaucion_y_critico(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 72]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 110]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 150]));
    }

    public function test_limites_exactos_son_inclusivos(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 50]));
        $this->assertSame(Estado::Normal, $e->evaluar('frecuencia_cardiaca', ['valor' => 100]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 101]));
        $this->assertSame(Estado::Precaucion, $e->evaluar('frecuencia_cardiaca', ['valor' => 120]));
        $this->assertSame(Estado::Critico, $e->evaluar('frecuencia_cardiaca', ['valor' => 121]));
    }

    public function test_presion_compuesta_toma_el_peor_componente(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame(Estado::Normal, $e->evaluar('presion_arterial', ['sistolica' => 118, 'diastolica' => 76]));
        // Sistólica normal, diastólica en precaución -> precaución.
        $this->assertSame(Estado::Precaucion, $e->evaluar('presion_arterial', ['sistolica' => 118, 'diastolica' => 92]));
        // Sistólica crítica manda aunque la diastólica sea normal.
        $this->assertSame(Estado::Critico, $e->evaluar('presion_arterial', ['sistolica' => 185, 'diastolica' => 80]));
    }

    public function test_evaluar_sin_componente_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EvaluadorEstado($this->config))->evaluar('presion_arterial', ['sistolica' => 120]);
    }

    public function test_indicador_desconocido_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new EvaluadorEstado($this->config))->evaluar('inexistente', ['valor' => 1]);
    }

    public function test_tendencias(): void
    {
        $e = new EvaluadorEstado($this->config);

        $this->assertSame('subiendo', $e->tendencia([70, 72, 80, 88, 95, 100]));
        $this->assertSame('bajando', $e->tendencia([8, 7.5, 6, 5, 4.5, 4]));
        $this->assertSame('estable', $e->tendencia([72, 73, 72, 71, 72, 73]));
        $this->assertSame('estable', $e->tendencia([70, 100]), 'poca historia no marca tendencia');
    }

    // ---- Validador -------------------------------------------------------

    public function test_validador_acepta_medicion_correcta(): void
    {
        $v = new ValidadorMedicion($this->config);
        $this->assertSame([], $v->validar('presion_arterial', ['sistolica' => 120, 'diastolica' => 80]));
    }

    public function test_validador_detecta_imposibles_faltantes_y_no_numericos(): void
    {
        $v = new ValidadorMedicion($this->config);

        $this->assertNotEmpty($v->validar('temperatura', ['valor' => 80]), 'imposible');
        $this->assertNotEmpty($v->validar('temperatura', []), 'faltante');
        $this->assertNotEmpty($v->validar('temperatura', ['valor' => 'abc']), 'no numérico');
        $this->assertNotEmpty($v->validar('presion_arterial', ['sistolica' => 80, 'diastolica' => 90]), 'diastólica >= sistólica');
    }

    public function test_validador_detecta_duplicados(): void
    {
        $v = new ValidadorMedicion($this->config);
        $previas = [['instante' => 1000, 'valores' => ['valor' => 72]]];

        $this->assertTrue($v->esDuplicada(['valor' => 72], 1030, $previas));
        $this->assertFalse($v->esDuplicada(['valor' => 72], 1200, $previas), 'fuera de la ventana');
        $this->assertFalse($v->esDuplicada(['valor' => 73], 1030, $previas), 'otro valor');
    }

    // ---- Línea base ------------------------------------------------------

    public function test_linea_base_requiere_historial_minimo(): void
    {
        $this->assertNull((new LineaBasePersonal())->calcular([70, 71], 72));
    }

    public function test_linea_base_agrava_un_normal_muy_alejado_de_su_habito(): void
    {
        $lb = new LineaBasePersonal();
        $historial = [58, 60, 59, 61, 60, 59, 60]; // astronauta con pulso bajo habitual

        $base = $lb->calcular($historial, 95); // 95 lpm es normal en población, pero atípico para él
        $this->assertGreaterThanOrEqual(2.0, abs($base['z']));
        $this->assertSame(Estado::Precaucion, $lb->ajustar(Estado::Normal, $base));
    }

    public function test_linea_base_no_degrada_un_estado_grave(): void
    {
        $lb = new LineaBasePersonal();
        $base = $lb->calcular([60, 61, 60, 62, 61], 61);

        $this->assertSame(Estado::Critico, $lb->ajustar(Estado::Critico, $base));
        $this->assertSame(Estado::Normal, $lb->ajustar(Estado::Normal, $base));
    }

    public function test_linea_base_constante_no_produce_z_infinito(): void
    {
        $base = (new LineaBasePersonal())->calcular([60, 60, 60, 60, 60], 60);
        $this->assertEquals(0.0, $base['z']);
    }

    // ---- Escalamiento ----------------------------------------------------

    public function test_escalamiento_por_estado(): void
    {
        $r = new ReglasEscalamiento();

        $this->assertSame('ninguno', $r->decidir(Estado::Normal)['accion']);
        $this->assertSame('recomendar', $r->decidir(Estado::Precaucion, 'estable')['accion']);
        $this->assertSame('alertar_tripulacion', $r->decidir(Estado::Precaucion, 'subiendo')['accion']);
        $this->assertSame('contactar_tierra', $r->decidir(Estado::Critico)['accion']);
    }

    public function test_critico_con_retardo_alto_se_atiende_primero_a_bordo(): void
    {
        $r = new ReglasEscalamiento();

        $cerca = $r->decidir(Estado::Critico, 'estable', 0);
        $marte = $r->decidir(Estado::Critico, 'estable', 20);

        $this->assertFalse($cerca['a_bordo']);
        $this->assertTrue($marte['a_bordo']);
        $this->assertTrue($cerca['encolar_tierra']);
        $this->assertTrue($marte['encolar_tierra']);
    }

    public function test_un_normal_nunca_se_encola_a_tierra(): void
    {
        $this->assertFalse((new ReglasEscalamiento())->decidir(Estado::Normal)['encolar_tierra']);
    }

    // ---- Recomendaciones -------------------------------------------------

    public function test_recomendaciones_especificas_y_generales(): void
    {
        $c = new CatalogoRecomendaciones();

        $this->assertStringContainsString('reposo', $c->para('frecuencia_cardiaca', Estado::Precaucion));
        $this->assertStringContainsString('flight surgeon', $c->para('frecuencia_cardiaca', Estado::Critico));
        $this->assertNotSame('', $c->para('animo', Estado::Normal));
    }

    // ---- Índice por sistema ---------------------------------------------

    public function test_indice_por_sistema(): void
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
        $this->assertNull($i['oseo']['puntaje'], 'sin datos');
    }

    public function test_un_critico_no_se_oculta_por_el_promedio(): void
    {
        $i = (new IndiceSistemaCorporal($this->config))->calcular([
            'frecuencia_cardiaca' => Estado::Normal,
            'presion_arterial' => Estado::Critico,
        ]);

        $this->assertSame(Estado::Critico, $i['cardiovascular']['estado']);
        $this->assertLessThanOrEqual(40, $i['cardiovascular']['puntaje']);
    }
}
