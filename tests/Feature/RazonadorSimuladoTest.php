<?php

namespace Tests\Feature;

use App\Services\Ia\FiltroSeguridad;
use App\Services\Ia\RazonadorSimulado;
use Tests\TestCase;

/**
 * Razonador simulado: se prueba con arrays (análisis y contexto como fixtures) contra las fichas reales de
 * conocimiento/sintomas. No toca la base de datos (el filtro tolera su ausencia).
 */
class RazonadorSimuladoTest extends TestCase
{
    private function razonar(array $analisis, array $contexto = [], array $opciones = []): array
    {
        return (new RazonadorSimulado(new FiltroSeguridad()))->razonar($analisis, array_replace_recursive($this->contexto(), $contexto), $opciones);
    }

    private function contexto(): array
    {
        return [
            'astronauta' => ['id' => 1, 'nombre' => 'Tripulante Uno', 'edad' => 38, 'sexo' => 'F'],
            'expediente' => ['alergias' => [], 'condiciones' => [], 'antecedentes' => [], 'medicacion' => []],
            'indicadores' => [
                'frecuencia_cardiaca' => [
                    'etiqueta' => 'Frecuencia cardíaca', 'unidad' => 'lpm', 'valor_texto' => '98 lpm', 'valores' => [98],
                    'estado' => 'warn', 'medido_en' => '2026-11-14T10:00:00Z', 'base_texto' => '64 lpm', 'desviacion_pct' => 53.0,
                    'serie_7d' => [64.0, 70.0, 98.0], 'lecturas_24h' => 12, 'sospechoso' => false, 'direccion' => 'alto',
                ],
                'glucosa' => [
                    'etiqueta' => 'Glucosa', 'unidad' => 'mg/dL', 'valor_texto' => '210 mg/dL', 'valores' => [210],
                    'estado' => 'warn', 'medido_en' => '2026-11-14T10:00:00Z', 'base_texto' => '95 mg/dL', 'desviacion_pct' => 121.0,
                    'serie_7d' => [], 'lecturas_24h' => 4, 'sospechoso' => false, 'direccion' => 'alto',
                ],
            ],
            'casos' => [], 'sintomas_registrados' => [], 'dolores' => [], 'consultas_previas' => [],
            'rutina' => ['ejercicio_intenso_reciente' => false],
            'mision' => ['care_level' => 'L2', 'fase' => 'tránsito', 'dia' => 140, 'eventos_proximos' => []],
            'botiquin' => [
                ['id' => 1, 'nombre' => 'Prometazina', 'grupo' => 'Mareo por adaptación', 'stock' => 5, 'caduca' => null, 'vigente' => true],
                ['id' => 2, 'nombre' => 'Meclizina', 'grupo' => 'Mareo por adaptación', 'stock' => 5, 'caduca' => null, 'vigente' => true],
                ['id' => 3, 'nombre' => 'Ibuprofeno', 'grupo' => 'Analgesia e inflamación', 'stock' => 5, 'caduca' => null, 'vigente' => true],
            ],
            'usados' => [['etiqueta' => 'Indicadores', 'detalle' => 'frecuencia cardíaca y glucosa']],
        ];
    }

    private function s(string $clave, string $etiqueta, ?string $region = null, ?string $lado = null, ?string $calidad = null): array
    {
        return ['clave' => $clave, 'etiqueta' => $etiqueta, 'region' => $region, 'lado' => $lado, 'calidad' => $calidad,
            'intensidad' => null, 'duracion_min' => null, 'confianza' => 80];
    }

    private function analisis(array $sintomas, array $banderas = [], string $texto = 'texto'): array
    {
        return ['texto' => $texto, 'entendido' => true, 'confianza' => 80, 'sintomas' => $sintomas, 'negados' => [], 'banderas_rojas' => $banderas];
    }

    private function mareo(): array
    {
        return $this->analisis([$this->s('mareo', 'Mareo')]);
    }

    private function todoElTexto(array $r): string
    {
        return json_encode($r, JSON_UNESCAPED_UNICODE);
    }

    public function test_bandera_roja_domina_dolor_en_pecho_izquierdo(): void
    {
        // Sin que el analizador marque bandera: la regla del pecho la detecta igualmente. Con alergias y antecedentes a favor de autocuidado.
        $r = $this->razonar($this->analisis([$this->s('dolor', 'Dolor punzante · pecho izquierdo', 'pecho', 'izquierdo', 'punzante')]));

        $this->assertSame('emergencia', $r['urgencia']);
        $this->assertTrue($r['escalar_ahora']);
        $this->assertSame([], $r['botiquin_opciones']);
        $this->assertNotEmpty($r['medidas_inmediatas']);
        $this->assertStringContainsString('Detén la actividad', implode(' ', $r['medidas_inmediatas']));
        $this->assertStringContainsString('saturación', implode(' ', $r['medidas_inmediatas']));
        $this->assertCount(1, $r['acciones']); // solo "avisar a tierra": sin autocuidado
        $this->assertStringContainsString('confirma', mb_strtolower($this->todoElTexto($r)));
    }

    public function test_bandera_roja_del_analisis_domina_aunque_el_sintoma_sea_leve(): void
    {
        $r = $this->razonar($this->analisis([$this->s('mareo', 'Mareo')], ['desmayo']));

        $this->assertSame('emergencia', $r['urgencia']);
        $this->assertTrue($r['escalar_ahora']);
        $this->assertSame([], $r['botiquin_opciones']);
    }

    public function test_alergia_excluye_opciones_del_botiquin(): void
    {
        $sin = $this->razonar($this->mareo());
        $this->assertContains('Prometazina', array_column($sin['botiquin_opciones'], 'nombre'));

        $con = $this->razonar($this->mareo(), ['expediente' => ['alergias' => [['sustancia' => 'Prometazina', 'reaccion' => 'urticaria']]]]);
        $this->assertNotContains('Prometazina', array_column($con['botiquin_opciones'], 'nombre'));
        $this->assertNotEmpty($con['hipotesis'][0]['por_que']);
        $this->assertStringContainsString('alergia', mb_strtolower(implode(' ', $con['hipotesis'][0]['por_que'])));
    }

    public function test_medicacion_activa_se_menciona(): void
    {
        $r = $this->razonar($this->mareo(), ['expediente' => ['medicacion' => [['nombre' => 'Losartán', 'motivo' => 'presión']]]]);

        $this->assertStringContainsString('Medicación registrada: Losartán', implode(' ', $r['hipotesis'][0]['por_que']));
        $this->assertStringContainsString('medicación activa', $r['conclusion']);
    }

    public function test_antecedente_relacionado_sube_la_urgencia_un_nivel(): void
    {
        $base = $this->razonar($this->mareo());
        $this->assertSame('autocuidado', $base['urgencia']);

        $con = $this->razonar($this->mareo(), ['expediente' => ['antecedentes' => [['condicion' => 'Vértigo', 'parentesco' => 'madre']]]]);
        $this->assertSame('urgente', $con['urgencia']);
        $texto = implode(' ', $con['hipotesis'][0]['por_que']);
        $this->assertStringContainsString('Figura en tu expediente un antecedente de Vértigo', $texto);
        $this->assertGreaterThan($base['hipotesis'][0]['confianza'], $con['hipotesis'][0]['confianza']);
    }

    public function test_eva_proxima_se_menciona(): void
    {
        $r = $this->razonar($this->mareo(), ['mision' => ['eventos_proximos' => [['nombre' => 'EVA', 'dia' => 149, 'en_dias' => 3]]]]);

        $this->assertSame('Esto podría afectar tu EVA del día 149: coméntalo con el médico de vuelo.', $r['eva_proxima']);
        $this->assertStringContainsString('EVA del día 149', $r['conclusion']);

        $lejos = $this->razonar($this->mareo(), ['mision' => ['eventos_proximos' => [['nombre' => 'EVA', 'dia' => 300, 'en_dias' => 160]]]]);
        $this->assertNull($lejos['eva_proxima']);
    }

    public function test_empeoro_sube_la_urgencia_y_igual_la_mantiene(): void
    {
        $base = $this->razonar($this->mareo());
        $this->assertSame('autocuidado', $base['urgencia']);

        $emp = $this->razonar($this->mareo(), [], ['evolucion' => 'empeoro', 'urgencia_previa' => 'autocuidado']);
        $this->assertSame('urgente', $emp['urgencia']);
        $this->assertStringContainsString('empeoró', $emp['conclusion']);
        $this->assertNotSame($base['conclusion'], $emp['conclusion']); // no repite el mensaje anterior

        $emp2 = $this->razonar($this->mareo(), [], ['evolucion' => 'empeoro', 'urgencia_previa' => 'urgente']);
        $this->assertSame('emergencia', $emp2['urgencia']);
        $this->assertTrue($emp2['escalar_ahora']);
        $this->assertSame([], $emp2['botiquin_opciones']);

        $igual = $this->razonar($this->mareo(), [], ['evolucion' => 'igual', 'urgencia_previa' => 'urgente']);
        $this->assertSame('urgente', $igual['urgencia']);
        $this->assertStringContainsString('persist', implode(' ', $igual['escalar_si']));
    }

    /**
     * Supuesto del proyecto (config/razonador.php, multiples_regiones): mareo + síntomas en 2 o más regiones => al menos 'urgente'.
     */
    public function test_sintomas_multiples_con_mareo_son_urgentes_y_no_se_ignora_ninguno(): void
    {
        $r = $this->razonar($this->analisis([
            $this->s('mareo', 'Mareo'),
            $this->s('dolor', 'Dolor punzante · tríceps derecho', 'triceps', 'derecho', 'punzante'),
            $this->s('dolor', 'Dolor punzante · pie izquierdo', 'pie', 'izquierdo', 'punzante'),
        ]));

        $this->assertSame('urgente', $r['urgencia']);
        $this->assertFalse($r['escalar_ahora']);
        $this->assertGreaterThanOrEqual(3, count($r['hipotesis']));
        $cubiertos = array_merge(...array_column($r['hipotesis'], 'sintomas'));
        $this->assertContains('mareo', $cubiertos);
        $this->assertContains('dolor', $cubiertos);
        $fichas = array_column($r['hipotesis'], 'ficha');
        $this->assertContains('mareo_vertigo', $fichas);
        $this->assertContains('dolor_muscular_localizado', $fichas);
        $this->assertContains('dolor_extremidades', $fichas);
        $this->assertStringContainsString('varias regiones', $r['conclusion']);
        $this->assertNotEmpty($r['botiquin_opciones']); // grupo de mareo, no emergencia
    }

    public function test_direccion_de_glucosa_alta_no_propone_subirla(): void
    {
        $r = $this->razonar($this->analisis([], [], 'mi glucosa está alta'));

        $this->assertTrue($r['alcance']);
        $this->assertStringContainsString('por encima de tu rango', $r['hipotesis'][0]['titulo']);
        $this->assertSame('glucosa', $r['seguimiento'][0]['indicador']);
        $this->assertDoesNotMatchRegularExpression('/\b(sube|subir|aumenta|aumentar)\b.{0,20}glucosa/iu', $this->todoElTexto($r));

        $baja = $this->razonar($this->analisis([], [], 'mi glucosa'), ['indicadores' => ['glucosa' => ['direccion' => 'bajo', 'valor_texto' => '55 mg/dL', 'serie_7d' => []]]]);
        $this->assertStringContainsString('por debajo de tu rango', $baja['hipotesis'][0]['titulo']);
    }

    public function test_no_repite_las_acciones_del_panel(): void
    {
        $normal = $this->razonar($this->mareo());
        $ids = array_column($normal['acciones'], 'id');
        $this->assertContains('mareo.reposo', $ids);
        $this->assertFalse($normal['panel_vigente']);

        $r = $this->razonar($this->mareo(), [], ['acciones_panel' => ['mareo.reposo', 'mareo.hidratacion']]);
        $ids2 = array_column($r['acciones'], 'id');
        $this->assertNotContains('mareo.reposo', $ids2);
        $this->assertNotContains('mareo.hidratacion', $ids2);
        $this->assertTrue($r['panel_vigente']);
    }

    public function test_ejercicio_intenso_baja_la_confianza_de_palpitaciones(): void
    {
        $an = $this->analisis([$this->s('palpitaciones', 'Palpitaciones')]);
        $normal = $this->razonar($an);
        $intenso = $this->razonar($an, ['rutina' => ['ejercicio_intenso_reciente' => true]]);

        $this->assertLessThan($normal['hipotesis'][0]['confianza'], $intenso['hipotesis'][0]['confianza']);
        $this->assertStringContainsString('ejercicio intenso', implode(' ', $intenso['hipotesis'][0]['por_que']));
        // El "por qué" cita datos reales del contexto.
        $this->assertStringContainsString('subió de 64 a 98 lpm', implode(' ', $normal['hipotesis'][0]['por_que']));
    }

    public function test_fuera_de_alcance(): void
    {
        $r = $this->razonar(['texto' => 'cuál es la capital de Francia', 'entendido' => false, 'confianza' => 0, 'sintomas' => [], 'negados' => [], 'banderas_rojas' => []]);

        $this->assertFalse($r['alcance']);
        $this->assertStringContainsString('síntomas e indicadores', $r['conclusion']);
        $this->assertSame([], $r['hipotesis']);
        $this->assertSame([], $r['botiquin_opciones']);
        $this->assertFalse($r['escalar_ahora']);
    }

    public function test_ninguna_salida_contiene_dosis_frecuencia_via_ni_diagnostico(): void
    {
        $filtro = new FiltroSeguridad();
        $casos = [
            $this->mareo(),
            $this->analisis([$this->s('dolor', 'Dolor punzante · pecho izquierdo', 'pecho', 'izquierdo')]),
            $this->analisis([$this->s('palpitaciones', 'Palpitaciones')]),
            $this->analisis([$this->s('dolor_cabeza', 'Dolor de cabeza')]),
            $this->analisis([$this->s('fiebre', 'Fiebre'), $this->s('fatiga', 'Fatiga'), $this->s('nauseas', 'Náuseas')]),
        ];
        foreach ($casos as $an) {
            $r = $this->razonar($an, ['expediente' => ['medicacion' => [['nombre' => 'Losartán', 'motivo' => 'presión']]]]);
            $textos = [$r['conclusion'], ...$r['medidas_inmediatas'], ...$r['escalar_si'], ...array_column($r['acciones'], 'texto'), ...array_column($r['seguimiento'], 'texto')];
            foreach ($r['hipotesis'] as $h) {
                $textos[] = $h['titulo'];
                array_push($textos, ...$h['por_que']);
            }
            foreach ($textos as $t) {
                $this->assertNull($filtro->revisar($t), 'Texto rechazado por el filtro: '.$t);
            }
            $this->assertDoesNotMatchRegularExpression('/\b(tienes|padeces)\b/iu', implode(' ', $textos));
            $this->assertStringContainsString('médico de vuelo', $r['conclusion']);
            foreach ($r['botiquin_opciones'] as $o) {
                $this->assertNull($filtro->revisar((string) ($o['nota'] ?? '')));
            }
        }
    }

    public function test_cada_accion_y_fuente_declara_su_origen(): void
    {
        $r = $this->razonar($this->mareo());

        foreach ($r['acciones'] as $a) {
            $this->assertNotEmpty($a['fuente']['etiqueta']);
            $this->assertContains($a['fuente']['origen'], ['documento', 'ejemplo_simulado']);
        }
        foreach ($r['fuentes'] as $f) {
            $this->assertContains($f['origen'], ['documento', 'ejemplo_simulado']);
        }
    }

    public function test_si_el_filtro_rechaza_cae_al_texto_por_reglas_con_aviso(): void
    {
        $razonador = new class(new FiltroSeguridad()) extends RazonadorSimulado {
            protected function fichasPorSintoma(string $clave): array
            {
                return [[
                    'slug' => 'x', 'titulo' => 'Tienes un mareo', 'sintomas_clave' => ['mareo'], 'regiones' => [], 'urgencia_base' => 'autocuidado',
                    'grupos_botiquin_permitidos' => [], 'origen' => 'ejemplo_simulado', 'fuentes' => [],
                    'acciones' => [['id' => 'x.1', 'texto' => 'Toma 500 mg ahora.', 'fuente' => 'Ejemplo simulado (sin validar)']],
                    'seguimiento' => [], 'escalar_si' => [],
                ]];
            }
        };
        $r = $razonador->razonar($this->mareo(), $this->contexto(), []);

        $this->assertSame('autocuidado', $r['urgencia']);
        $this->assertStringContainsString('filtro de seguridad', $r['conclusion']);
        $this->assertStringContainsString('Esta es una versión simulada', $r['conclusion']);
        $this->assertSame(['confirmar_medico_vuelo'], array_column($r['acciones'], 'id'));
    }

    public function test_una_accion_con_dosis_se_descarta_sola_y_el_resto_se_conserva(): void
    {
        $razonador = new class(new FiltroSeguridad()) extends RazonadorSimulado {
            protected function fichasPorSintoma(string $clave): array
            {
                return [[
                    'slug' => 'x', 'titulo' => 'Mareo', 'sintomas_clave' => ['mareo'], 'regiones' => [], 'urgencia_base' => 'autocuidado',
                    'grupos_botiquin_permitidos' => [], 'origen' => 'ejemplo_simulado', 'fuentes' => [],
                    'acciones' => [
                        ['id' => 'x.1', 'texto' => 'Toma 500 mg ahora.', 'fuente' => 'Ejemplo simulado (sin validar)'],
                        ['id' => 'x.2', 'texto' => 'Reposa con la vista en un punto fijo.', 'fuente' => 'Ejemplo simulado (sin validar)'],
                    ],
                    'seguimiento' => [], 'escalar_si' => [],
                ]];
            }
        };
        $ids = array_column($razonador->razonar($this->mareo(), $this->contexto(), [])['acciones'], 'id');

        $this->assertNotContains('x.1', $ids);
        $this->assertContains('x.2', $ids);
    }

    public function test_nada_de_otro_tripulante(): void
    {
        $ctx = $this->contexto();
        $ctx['expediente']['condiciones'] = [['nombre' => 'Asma', 'estado' => 'activa']];
        $razonador = new RazonadorSimulado(new FiltroSeguridad());
        $r = $razonador->razonar($this->analisis([$this->s('falta_aire', 'Falta de aire')]), $ctx, []);

        // El razonador solo lee el contexto recibido: no hay acceso a modelos ni a otros tripulantes.
        $this->assertStringNotContainsString('Otro Tripulante', $this->todoElTexto($r));
        $this->assertSame('emergencia', $r['urgencia']);
    }

    public function test_toda_bandera_del_analizador_mapea_a_una_de_ficha_o_a_emergencia_directa(): void
    {
        $analizador = ['dolor_toracico', 'falta_aire', 'desmayo', 'dolor_cabeza_subito_intenso', 'confusion', 'debilidad_lado',
            'perdida_vision', 'hemorragia_importante', 'vomito_persistente', 'convulsion'];
        $deFichas = [];
        foreach (\App\Services\Ia\LectorFichasSintomas::todas() as $f) {
            array_push($deFichas, ...(array) ($f['banderas_rojas'] ?? []));
        }
        $directas = (array) config('razonador.banderas_directas');

        foreach ($analizador as $b) {
            $c = RazonadorSimulado::canonica($b);
            $this->assertTrue(in_array($c, $deFichas, true) || in_array($c, $directas, true), "Bandera sin mapa: $b");
            $r = $this->razonar($this->analisis([$this->s('mareo', 'Mareo')], [$b]));
            $this->assertSame('emergencia', $r['urgencia'], "No escala: $b");
            $this->assertTrue($r['escalar_ahora']);
        }
        $this->assertSame('dificultad_respirar', RazonadorSimulado::canonica('falta_aire'));
        $this->assertSame('dolor_toracico', RazonadorSimulado::canonica('dolor_toracico'));
    }

    public function test_ignora_claves_fuera_del_contrato_del_contexto(): void
    {
        $r = $this->razonar($this->mareo(), ['votos' => [['x' => 1]]]);
        $this->assertSame('autocuidado', $r['urgencia']);
    }
}
