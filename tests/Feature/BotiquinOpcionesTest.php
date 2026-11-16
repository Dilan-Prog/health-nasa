<?php

namespace Tests\Feature;

use App\Services\Ia\BotiquinOpciones;
use Carbon\Carbon;
use Tests\TestCase;

/** Puro: contexto como array, sin BD. */
class BotiquinOpcionesTest extends TestCase
{
    private function item(int $id, string $nombre, string $grupo, int $stock = 10, ?string $caduca = '2099-01-01', ?string $contra = null): array
    {
        return ['id' => $id, 'nombre' => $nombre, 'grupo' => $grupo, 'stock' => $stock, 'caduca' => $caduca,
            'vigente' => $caduca === null || $caduca >= '2026-01-01', 'contraindicaciones' => $contra];
    }

    private function ctx(array $botiquin, array $alergias = [], array $medicacion = []): array
    {
        return ['expediente' => ['alergias' => $alergias, 'medicacion' => $medicacion], 'botiquin' => $botiquin];
    }

    private function inventario(): array
    {
        return [
            $this->item(1, 'ibuprofeno', 'Analgesia e inflamación'),
            $this->item(2, 'acetaminofén', 'Analgesia e inflamación'),
            $this->item(3, 'aspirina', 'Analgesia e inflamación'),
            $this->item(4, 'ketorolaco', 'Analgesia e inflamación'),
            $this->item(5, 'azitromicina', 'Antimicrobianos y antivirales'),
            $this->item(6, 'epinefrina', 'Emergencias'),
            $this->item(7, 'prometazina', 'Mareo por adaptación'),
        ];
    }

    private function nombres(array $ops): array
    {
        return array_values(array_filter(array_column($ops, 'nombre')));
    }

    public function test_maximo_tres_opciones(): void
    {
        $ops = BotiquinOpciones::para($this->ctx($this->inventario()), ['Analgesia e inflamación'], false);
        $this->assertSame(['ibuprofeno', 'acetaminofén', 'aspirina'], $this->nombres($ops));
    }

    public function test_el_maximo_es_configurable(): void
    {
        config(['botiquin_clases.max_opciones' => 1]);
        $ops = BotiquinOpciones::para($this->ctx($this->inventario()), ['Analgesia e inflamación'], false);
        $this->assertCount(1, $ops);
    }

    public function test_vacio_en_emergencia_o_sin_grupos(): void
    {
        $c = $this->ctx($this->inventario());
        $this->assertSame([], BotiquinOpciones::para($c, ['Analgesia e inflamación'], true));
        $this->assertSame([], BotiquinOpciones::para($c, [], false));
    }

    public function test_excluye_sin_stock_y_caducados(): void
    {
        $inv = [
            $this->item(1, 'ibuprofeno', 'Analgesia e inflamación', 0),
            $this->item(2, 'acetaminofén', 'Analgesia e inflamación', 5, '2020-01-01'),
            $this->item(3, 'aspirina', 'Analgesia e inflamación', 5, '2099-01-01'),
        ];
        $ops = BotiquinOpciones::para($this->ctx($inv), ['Analgesia e inflamación'], false, Carbon::parse('2026-06-01'));
        $this->assertSame(['aspirina'], $this->nombres($ops));
    }

    public function test_solo_de_los_grupos_permitidos(): void
    {
        $ops = BotiquinOpciones::para($this->ctx($this->inventario()), ['Mareo por adaptación'], false);
        $this->assertSame(['prometazina'], $this->nombres($ops));
    }

    public function test_grupos_vedados_dan_nota_de_requiere_medico_y_no_opcion(): void
    {
        $ops = BotiquinOpciones::para($this->ctx($this->inventario()), ['Antimicrobianos y antivirales', 'Emergencias'], false);
        $this->assertCount(1, $ops);
        $this->assertTrue($ops[0]['requiere_medico']);
        $this->assertNull($ops[0]['nombre']);
        $this->assertStringContainsString('Requiere al médico de vuelo', $ops[0]['nota']);
        $this->assertStringNotContainsString('azitromicina', json_encode($ops, JSON_UNESCAPED_UNICODE));
    }

    public function test_alergia_por_nombre(): void
    {
        $ops = BotiquinOpciones::para(
            $this->ctx($this->inventario(), [['sustancia' => 'Acetaminofén', 'reaccion' => 'urticaria']]),
            ['Analgesia e inflamación'], false
        );
        $this->assertNotContains('acetaminofén', $this->nombres($ops));
        $this->assertContains('ibuprofeno', $this->nombres($ops));
    }

    public function test_alergia_por_clase_excluye_toda_la_clase(): void
    {
        $ops = BotiquinOpciones::para(
            $this->ctx($this->inventario(), [['sustancia' => 'AINEs', 'reaccion' => 'asma']]),
            ['Analgesia e inflamación'], false
        );
        $this->assertSame(['acetaminofén'], $this->nombres($ops));
    }

    public function test_alergia_a_un_miembro_excluye_su_clase(): void
    {
        $ops = BotiquinOpciones::para(
            $this->ctx($this->inventario(), [['sustancia' => 'ibuprofeno', 'reaccion' => 'rash']]),
            ['Analgesia e inflamación'], false
        );
        $this->assertSame(['acetaminofén'], $this->nombres($ops));
    }

    public function test_medicacion_activa_del_mismo_grupo_avisa(): void
    {
        $ops = BotiquinOpciones::para(
            $this->ctx($this->inventario(), [], [['nombre' => 'Naproxeno', 'motivo' => 'x'], ['nombre' => 'Ibuprofeno', 'motivo' => 'dolor lumbar']]),
            ['Analgesia e inflamación'], false
        );
        $this->assertSame('Ya tienes registrado Ibuprofeno; consulta antes de combinar.', $ops[0]['aviso_medicacion']);
        // Mismo grupo: todas las opciones del grupo llevan el aviso.
        $this->assertStringContainsString('Ibuprofeno', (string) $ops[1]['aviso_medicacion']);
    }

    public function test_medicacion_de_la_misma_clase_avisa(): void
    {
        $ops = BotiquinOpciones::para(
            $this->ctx([$this->item(1, 'aspirina', 'Analgesia e inflamación')], [], [['nombre' => 'Ketorolaco', 'motivo' => '']]),
            ['Analgesia e inflamación'], false
        );
        $this->assertStringContainsString('Ya tienes registrado Ketorolaco', (string) $ops[0]['aviso_medicacion']);
    }

    public function test_sin_medicacion_no_hay_aviso_y_sin_dosis_con_contraindicaciones_sin_verificar(): void
    {
        $ops = BotiquinOpciones::para($this->ctx([$this->item(1, 'ibuprofeno', 'Analgesia e inflamación', 5, '2099-01-01', 'Pendiente de verificación médica')]), ['Analgesia e inflamación'], false);
        $this->assertNull($ops[0]['aviso_medicacion']);
        $this->assertStringContainsString('Sin verificar', $ops[0]['contraindicaciones']);
        $this->assertStringContainsString('Sin dosis', $ops[0]['nota']);
        $this->assertDoesNotMatchRegularExpression('/\d+\s*(mg|ml|mcg)/i', json_encode($ops));
        $this->assertSame(['id', 'nombre', 'grupo', 'nota', 'contraindicaciones', 'aviso_medicacion'], array_keys($ops[0]));
    }

    public function test_contraindicacion_propia_del_item_se_marca_sin_verificar(): void
    {
        $ops = BotiquinOpciones::para($this->ctx([$this->item(1, 'ibuprofeno', 'Analgesia e inflamación', 5, '2099-01-01', 'Úlcera gástrica')]), ['Analgesia e inflamación'], false);
        $this->assertSame('Úlcera gástrica. Sin verificar (pendiente de revisión médica)', $ops[0]['contraindicaciones']);
    }

    public function test_texto_del_bloque(): void
    {
        $this->assertSame(
            'Opciones del botiquín para consultar con el médico de vuelo. Contraindicaciones sin verificar. Sin dosis.',
            BotiquinOpciones::ENCABEZADO
        );
    }
}
