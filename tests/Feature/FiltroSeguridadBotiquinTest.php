<?php

namespace Tests\Feature;

use App\Services\Ia\BotiquinOpciones;
use App\Services\Ia\FiltroSeguridad;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Los nombres del botiquín solo valen dentro de botiquin_opciones; todo lo demás sigue rechazándolos. */
class FiltroSeguridadBotiquinTest extends TestCase
{
    use DatabaseTransactions;

    private const G = 'Analgesia e inflamación';

    private function ctx(array $alergias = []): array
    {
        $item = fn ($id, $n, $g, $stock = 10, $cad = '2099-01-01') => ['id' => $id, 'nombre' => $n, 'grupo' => $g, 'stock' => $stock, 'caduca' => $cad, 'vigente' => $cad >= '2026-01-01'];

        return [
            'expediente' => ['alergias' => $alergias, 'medicacion' => []],
            'botiquin' => [
                $item(1, 'ibuprofeno', self::G),
                $item(2, 'acetaminofén', self::G),
                $item(3, 'aspirina', self::G, 0),
                $item(4, 'ketorolaco', self::G, 5, '2020-01-01'),
                $item(5, 'azitromicina', 'Antimicrobianos y antivirales'),
                $item(6, 'prometazina', 'Mareo por adaptación'),
            ],
        ];
    }

    private function opcion(string $nombre, string $grupo = self::G, array $extra = []): array
    {
        return array_merge(['id' => 1, 'nombre' => $nombre, 'grupo' => $grupo, 'nota' => BotiquinOpciones::NOTA_OPCION,
            'contraindicaciones' => BotiquinOpciones::SIN_VERIFICAR, 'aviso_medicacion' => null], $extra);
    }

    private function revisar(array $ops, array $ctx = null, array $grupos = [self::G], bool $emergencia = false): ?string
    {
        return (new FiltroSeguridad())->revisarOpcionesBotiquin($ops, $ctx ?? $this->ctx(), $grupos, $emergencia);
    }

    public function test_opciones_validas_pasan_incluido_lo_generado_por_botiquin_opciones(): void
    {
        $this->assertNull($this->revisar([$this->opcion('ibuprofeno'), $this->opcion('acetaminofén')]));
        $generadas = BotiquinOpciones::para($this->ctx(), [self::G, 'Antimicrobianos y antivirales'], false);
        $this->assertNotEmpty($generadas);
        $this->assertNull($this->revisar($generadas, null, [self::G, 'Antimicrobianos y antivirales']));
        $this->assertNull($this->revisar([]));
    }

    public function test_mas_de_tres_opciones_falla(): void
    {
        $ctx = $this->ctx();
        $ctx['botiquin'][] = ['id' => 7, 'nombre' => 'zolpidem', 'grupo' => self::G, 'stock' => 5, 'caduca' => '2099-01-01', 'vigente' => true];
        $ops = [$this->opcion('ibuprofeno'), $this->opcion('acetaminofén'), $this->opcion('zolpidem'), $this->opcion('ibuprofeno')];
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar($ops, $ctx));
    }

    public function test_en_emergencia_cualquier_opcion_falla(): void
    {
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('ibuprofeno')], null, [self::G], true));
        $this->assertNull($this->revisar([], null, [self::G], true));
    }

    public function test_grupo_no_permitido_falla(): void
    {
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('prometazina', 'Mareo por adaptación')]));
    }

    public function test_grupos_vedados_fallan_aunque_la_ficha_los_permita(): void
    {
        $g = 'Antimicrobianos y antivirales';
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('azitromicina', $g)], null, [$g]));
    }

    public function test_sin_stock_o_caducado_falla(): void
    {
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('aspirina')]), 'sin stock');
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('ketorolaco')]), 'caducado');
    }

    public function test_nombre_que_no_esta_en_el_botiquin_falla(): void
    {
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('naproxeno')]));
    }

    public function test_alergia_por_nombre_y_por_clase_falla(): void
    {
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('ibuprofeno')], $this->ctx([['sustancia' => 'Ibuprofeno', 'reaccion' => '']])));
        $this->assertSame(FiltroSeguridad::OPCION_BOTIQUIN, $this->revisar([$this->opcion('ibuprofeno')], $this->ctx([['sustancia' => 'AINEs', 'reaccion' => '']])));
        $this->assertNull($this->revisar([$this->opcion('acetaminofén')], $this->ctx([['sustancia' => 'AINEs', 'reaccion' => '']])));
    }

    #[DataProvider('textosProhibidos')]
    public function test_dosis_frecuencia_duracion_o_via_en_una_opcion_falla(string $texto, string $causa): void
    {
        $this->assertSame($causa, $this->revisar([$this->opcion('ibuprofeno', self::G, ['nota' => $texto])]));
    }

    public static function textosProhibidos(): array
    {
        return [
            'mg' => ['Toma 400 mg si hay dolor.', FiltroSeguridad::DOSIS],
            'comprimido' => ['1 comprimido si persiste.', FiltroSeguridad::DOSIS],
            'dosis' => ['La dosis la decide el médico.', FiltroSeguridad::DOSIS],
            'cada 8 horas' => ['Úsalo cada 8 horas.', FiltroSeguridad::FRECUENCIA],
            'veces al dia' => ['Dos veces al día.', FiltroSeguridad::FRECUENCIA],
            'por la manana' => ['Por la mañana.', FiltroSeguridad::FRECUENCIA],
            'durante 3 dias' => ['Durante 3 días.', FiltroSeguridad::DURACION],
            'via oral' => ['Por vía oral.', FiltroSeguridad::VIA],
            'sublingual' => ['Tableta sublingual.', FiltroSeguridad::VIA],
            'diagnostico' => ['Tienes una migraña.', FiltroSeguridad::DIAGNOSTICO],
            'otro farmaco' => ['Combínalo con melatonina.', FiltroSeguridad::MEDICAMENTO],
        ];
    }

    public function test_dosis_en_contraindicaciones_o_aviso_falla(): void
    {
        $this->assertSame(FiltroSeguridad::DOSIS, $this->revisar([$this->opcion('ibuprofeno', self::G, ['contraindicaciones' => 'No más de 600 mg'])]));
        $this->assertSame(FiltroSeguridad::DOSIS, $this->revisar([$this->opcion('ibuprofeno', self::G, ['aviso_medicacion' => 'Ya tienes registrado Naproxeno 500 mg'])]));
        $this->assertNull($this->revisar([$this->opcion('ibuprofeno', self::G, ['aviso_medicacion' => 'Ya tienes registrado Naproxeno; consulta antes de combinar.'])]));
    }

    public function test_nota_requiere_medico_no_nombra_farmacos(): void
    {
        $nota = ['id' => 'requiere_medico_vuelo', 'nombre' => null, 'grupo' => 'Emergencias', 'nota' => BotiquinOpciones::NOTA_REQUIERE_MEDICO,
            'contraindicaciones' => null, 'aviso_medicacion' => null, 'requiere_medico' => true];
        $this->assertNull($this->revisar([$nota]));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $this->revisar([array_merge($nota, ['nota' => 'Requiere al médico de vuelo; quizá epinefrina.'])]));
    }

    public function test_un_farmaco_fuera_de_la_seccion_se_sigue_rechazando(): void
    {
        $f = new FiltroSeguridad();
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $f->revisar('Podrías tomar ibuprofeno.'));
        $this->assertSame(FiltroSeguridad::MEDICAMENTO, $f->revisar('Opciones: acetaminofén'));
        $this->assertFalse($f->permitido('Considera la prometazina.'));
    }

    public function test_el_aviso_obligatorio_y_el_encabezado_no_disparan_el_filtro(): void
    {
        $f = new FiltroSeguridad();
        $this->assertNull($f->revisar(BotiquinOpciones::ENCABEZADO));
        $this->assertSame(
            'Opciones del botiquín para consultar con el médico de vuelo. Contraindicaciones sin verificar. Sin dosis.',
            BotiquinOpciones::ENCABEZADO
        );
    }

    public function test_frecuencias_y_via_se_rechazan_tambien_en_texto_general(): void
    {
        $f = new FiltroSeguridad();
        $this->assertSame(FiltroSeguridad::FRECUENCIA, $f->revisar('Repite cada 8 horas.'));
        $this->assertSame(FiltroSeguridad::FRECUENCIA, $f->revisar('Hazlo 2 veces al día.'));
        $this->assertSame(FiltroSeguridad::DURACION, $f->revisar('Sigue así durante 3 días.'));
        $this->assertSame(FiltroSeguridad::VIA, $f->revisar('Se administra por vía oral.'));
        $this->assertNull($f->revisar('Bebe un vaso de agua cada hora despierto.'), 'hidratación del catálogo');
    }
}
