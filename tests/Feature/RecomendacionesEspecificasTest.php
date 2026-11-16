<?php

namespace Tests\Feature;

use App\Services\Ia\AccionesPanel;
use App\Services\Ia\FiltroSeguridad;
use App\Services\Ia\RecomendacionesEspecificas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Contexto como array (fixture): no depende de ContextoTripulante. DatabaseTransactions: el filtro consulta el botiquín. */
class RecomendacionesEspecificasTest extends TestCase
{
    use DatabaseTransactions;

    private function ctx(array $indicadores, ?array $rutina = null): array
    {
        $ind = [];
        foreach ($indicadores as $clave => $x) {
            [$estado, $dir] = is_array($x) ? $x : [$x, null];
            $ind[$clave] = ['etiqueta' => $clave, 'estado' => $estado, 'direccion' => $dir, 'sospechoso' => false];
        }

        return ['indicadores' => $ind, 'rutina' => $rutina];
    }

    private function rutina(int $minSemana, array $ejecuciones = [], ?float $sueno = null): array
    {
        return ['ejercicio_min_semana' => $minSemana, 'meta_min_dia' => 150, 'sueno_h_ultimo' => $sueno,
            'ejecuciones' => $ejecuciones, 'ejercicio_intenso_reciente' => false];
    }

    private function ids(array $r): array
    {
        return array_column($r['acciones'], 'id');
    }

    private function textos(array $r): string
    {
        return FiltroSeguridad::normalizar(implode(' | ', array_column($r['acciones'], 'texto')));
    }

    public function test_glucosa_alta_no_recomienda_subirla(): void
    {
        $r = RecomendacionesEspecificas::para($this->ctx(['glucosa' => ['warn', 'alto']]));

        $this->assertContains('rec.glucosa.comidas_regulares', $this->ids($r));
        $this->assertNotContains('rec.glucosa.comer_pronto', $this->ids($r));
        $this->assertStringNotContainsString('carbohidratos de absorcion', $this->textos($r));
        $this->assertStringContainsString('azucares simples', $this->textos($r));
    }

    public function test_glucosa_baja_no_recomienda_bajarla(): void
    {
        $r = RecomendacionesEspecificas::para($this->ctx(['glucosa' => ['crit', 'bajo']]));

        $this->assertContains('rec.glucosa.comer_pronto', $this->ids($r));
        $this->assertNotContains('rec.glucosa.comidas_regulares', $this->ids($r));
        $this->assertNotContains('rec.glucosa.mover_tras_comer', $this->ids($r));
        $this->assertStringNotContainsString('azucares simples', $this->textos($r));
    }

    public function test_direccion_desconocida_solo_da_pautas_neutrales(): void
    {
        $r = RecomendacionesEspecificas::para($this->ctx(['glucosa' => ['warn', null], 'presion_arterial' => ['warn', null]]));

        $this->assertSame(['rec.glucosa.horarios_estables'], $this->ids($r));
    }

    public function test_presion_alta_y_baja_van_en_sentidos_opuestos(): void
    {
        $alta = RecomendacionesEspecificas::para($this->ctx(['presion_arterial' => ['warn', 'alto']]));
        $baja = RecomendacionesEspecificas::para($this->ctx(['presion_arterial' => ['warn', 'bajo']]));

        $this->assertContains('rec.presion_arterial.menos_sal', $this->ids($alta));
        $this->assertNotContains('rec.presion_arterial.postura_lenta', $this->ids($alta));
        $this->assertContains('rec.presion_arterial.postura_lenta', $this->ids($baja));
        $this->assertNotContains('rec.presion_arterial.menos_sal', $this->ids($baja));
        $this->assertStringNotContainsString('sal anadida', $this->textos($baja));
    }

    public function test_masa_muscular_usa_la_rutina_real(): void
    {
        // Por debajo de la meta (150 min/día x 6 días = 900) y sin ejercicio resistido registrado.
        $r = RecomendacionesEspecificas::para($this->ctx(['masa_muscular' => 'warn'], $this->rutina(300)));
        $t = $this->textos($r);

        $this->assertStringContainsString('300 min', $t);
        $this->assertStringContainsString('900 min', $t);
        $this->assertStringContainsString('multiarticulares', $t);
        $this->assertStringContainsString('sentadillas', $t);
        $this->assertStringNotContainsString('cumples la meta', $t);
        $this->assertSame('documento', $r['acciones'][0]['fuente']['origen']);
        $this->assertStringContainsString('datos_medicos', $r['acciones'][0]['fuente']['etiqueta']);
    }

    public function test_masa_muscular_con_meta_cumplida_pide_recalibrar_en_vez_de_mas_volumen(): void
    {
        $ej = [['nombre' => 'ARED', 'fecha' => '2026-11-14', 'tipo' => 'Ejercicio resistido', 'duracion_min' => 45]];
        $r = RecomendacionesEspecificas::para($this->ctx(['masa_muscular' => 'warn'], $this->rutina(950, $ej)));

        $this->assertContains('rec.masa_muscular.recalibrar_musculo', $this->ids($r));
        $this->assertNotContains('rec.masa_muscular.completar_meta_musculo', $this->ids($r));
        $this->assertNotContains('rec.masa_muscular.resistido_musculo', $this->ids($r));
    }

    public function test_masa_muscular_menciona_sueno_corto_de_la_rutina(): void
    {
        $r = RecomendacionesEspecificas::para($this->ctx(['masa_muscular' => 'warn'], $this->rutina(950, [], 5.5)));

        // El tope por indicador deja fuera lo menos prioritario, así que se comprueba con el tope ampliado.
        config(['recomendaciones.max_por_indicador' => 10, 'recomendaciones.max_total' => 10]);
        $r = RecomendacionesEspecificas::para($this->ctx(['masa_muscular' => 'warn'], $this->rutina(950, [], 5.5)));
        $this->assertStringContainsString('dormiste 5.5 h', $this->textos($r));
    }

    public function test_no_repite_lo_que_el_panel_ya_muestra(): void
    {
        $ej = [['nombre' => 'ARED', 'fecha' => '2026-11-14', 'tipo' => 'Ejercicio resistido', 'duracion_min' => 45]];
        $ctx = $this->ctx(['masa_muscular' => 'warn'], $this->rutina(300, $ej));
        config(['recomendaciones.max_por_indicador' => 10, 'recomendaciones.max_total' => 10]);

        $sin = RecomendacionesEspecificas::para($ctx);
        $this->assertFalse($sin['panel_vigente']);
        $idPanel = AccionesPanel::id('masa_muscular', 'Registra series y cargas');
        $this->assertContains($idPanel, $this->ids($sin));

        $con = RecomendacionesEspecificas::para($ctx, AccionesPanel::idsDeContexto($ctx));
        $this->assertNotContains($idPanel, $this->ids($con));
        $this->assertTrue($con['panel_vigente']);
        $this->assertNotEmpty($con['acciones'], 'sigue habiendo acciones nuevas');
    }

    public function test_todo_cubierto_marca_panel_vigente_y_no_duplica(): void
    {
        config(['recomendaciones.reglas' => ['glucosa' => ['alto' => [
            ['id' => 'x', 'texto' => 'Anota tu comida.', 'fuente' => 'ejemplo', 'panel' => 'Anota tu última comida y la hora'],
        ]]]]);
        $ctx = $this->ctx(['glucosa' => ['warn', 'alto']]);

        $r = RecomendacionesEspecificas::para($ctx, AccionesPanel::idsDe([['clave' => 'glucosa', 'acciones' => ['Anota tu última comida y la hora']]]));

        $this->assertSame([], $r['acciones']);
        $this->assertTrue($r['panel_vigente']);
    }

    public function test_sin_duplicados_de_id_ni_de_texto(): void
    {
        $ctx = $this->ctx([
            'glucosa' => ['warn', 'alto'], 'presion_arterial' => ['crit', 'alto'], 'masa_muscular' => 'warn',
            'perdida_osea' => 'warn', 'hidratacion' => 'warn', 'sueno' => 'warn',
        ], $this->rutina(100, [], 5.0));
        config(['recomendaciones.max_por_indicador' => 10, 'recomendaciones.max_total' => 50]);

        $r = RecomendacionesEspecificas::para($ctx, AccionesPanel::idsDeContexto($ctx));

        $ids = $this->ids($r);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $textos = array_map(fn ($a) => FiltroSeguridad::normalizar($a['texto']), $r['acciones']);
        $this->assertSame($textos, array_values(array_unique($textos)));
        foreach ($r['acciones'] as $a) {
            $this->assertNotEmpty($a['fuente']['etiqueta']);
            $this->assertContains($a['fuente']['origen'], ['documento', 'ejemplo_simulado']);
            $this->assertNotEmpty($a['indicador']);
        }
    }

    public function test_los_topes_limitan_la_cantidad(): void
    {
        $ctx = $this->ctx(['glucosa' => ['warn', 'alto'], 'presion_arterial' => ['warn', 'alto'], 'masa_muscular' => 'warn', 'sueno' => 'warn'], $this->rutina(100));
        $r = RecomendacionesEspecificas::para($ctx);

        $this->assertLessThanOrEqual(config('recomendaciones.max_total'), count($r['acciones']));
        foreach (array_count_values(array_column($r['acciones'], 'indicador')) as $n) {
            $this->assertLessThanOrEqual(config('recomendaciones.max_por_indicador'), $n);
        }
    }

    public function test_indicadores_ok_o_sospechosos_no_generan_nada(): void
    {
        $this->assertSame(['acciones' => [], 'panel_vigente' => false], RecomendacionesEspecificas::para($this->ctx(['glucosa' => 'ok', 'masa_muscular' => 'ok'])));
        $this->assertSame(['acciones' => [], 'panel_vigente' => false], RecomendacionesEspecificas::para(['indicadores' => ['glucosa' => ['estado' => null]]]));

        $sosp = $this->ctx(['glucosa' => ['warn', 'alto']]);
        $sosp['indicadores']['glucosa']['sospechoso'] = true;
        $this->assertSame([], RecomendacionesEspecificas::para($sosp)['acciones']);
    }

    public function test_ningun_texto_de_ninguna_regla_tiene_dosis_ni_farmacos_ni_diagnostico(): void
    {
        $filtro = new FiltroSeguridad();
        $rutinas = [
            $this->rutina(100, [], 5.0),
            $this->rutina(950, [['nombre' => 'ARED', 'fecha' => '2026-11-14', 'tipo' => 'Ejercicio resistido', 'duracion_min' => 45]], 8.0),
            null,
        ];
        config(['recomendaciones.max_por_indicador' => 50, 'recomendaciones.max_total' => 500]);

        $n = 0;
        foreach (array_keys(config('recomendaciones.reglas')) as $clave) {
            foreach ([null, 'alto', 'bajo'] as $dir) {
                foreach ($rutinas as $rutina) {
                    $r = RecomendacionesEspecificas::para($this->ctx([$clave => ['warn', $dir]], $rutina));
                    foreach ($r['acciones'] as $a) {
                        $n++;
                        $this->assertNull($filtro->revisar($a['texto']), "{$clave}/{$dir}: {$a['texto']}");
                        $this->assertDoesNotMatchRegularExpression('/\b\d+\s*(?:mg|mcg|ml|g|comprimidos?|pastillas?)\b/iu', $a['texto']);
                    }
                }
            }
        }
        $this->assertGreaterThan(20, $n);
    }

    public function test_ids_del_panel_son_deterministas_y_distintos_por_indicador(): void
    {
        $a = AccionesPanel::id('glucosa', 'Bebe un vaso de agua');
        $this->assertSame($a, AccionesPanel::id('glucosa', '  bebe un VASO de agua. '));
        $this->assertNotSame($a, AccionesPanel::id('presion_arterial', 'Bebe un vaso de agua'));
        $this->assertMatchesRegularExpression('/^panel\.glucosa\.[0-9a-f]{8}$/', $a);

        $ids = AccionesPanel::idsDe([['clave' => 'glucosa', 'acciones' => ['Bebe un vaso de agua', 'Repite la medición en 15 min']]]);
        $this->assertCount(2, $ids);
        $this->assertContains($a, $ids);
        $this->assertSame($ids, AccionesPanel::idsDe(['glucosa' => ['Bebe un vaso de agua', 'Repite la medición en 15 min']]));
    }
}
