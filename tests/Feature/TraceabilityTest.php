<?php

namespace Tests\Feature;

use App\Livewire\Guia;
use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\ResolvedorRequisito;
use App\Support\TripulanteActual;
use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** DatabaseTransactions (no RefreshDatabase): nunca borra los datos de la BD local. */
class TrazabilidadTest extends TestCase
{
    use DatabaseTransactions;

    /** Indicadores de config/umbrales.php que NO tienen requisito en el seeder, con el motivo. */
    private const SIN_REQUISITO = [
        'saturacion_o2' => 'el seeder no tiene fila de saturación',
        'glucosa' => 'el seeder no tiene fila de glucosa',
        'imc' => 'el seeder no tiene fila de IMC',
        'masa_muscular' => 'solo existe «fuerza», que es otra medida',
        'estres_cognitivo' => 'solo existe el requisito cualitativo de conducta, sin número',
        'animo' => 'el seeder no tiene fila de ánimo',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RequisitosSeeder::class);
        ResolvedorRequisito::olvidar();
    }

    protected function tearDown(): void
    {
        ResolvedorRequisito::olvidar();
        parent::tearDown();
    }

    public static function tipos(): array
    {
        return [
            'radiacion_diaria' => ['radiacion_diaria', 'norma'],
            'dosis_acumulada' => ['dosis_acumulada', 'norma'],
            'perdida_osea' => ['perdida_osea', 'hrp'],
            'presion_arterial' => ['presion_arterial', 'referencia'],
            'temperatura' => ['temperatura', 'referencia'],
            'frecuencia_cardiaca' => ['frecuencia_cardiaca', 'referencia'],
            'hidratacion' => ['hidratacion', 'guia'],
            'vision_sans' => ['vision_sans', 'guia'],
            'sueno' => ['sueno', 'guia'],
        ];
    }

    /** @dataProvider tipos */
    public function test_cada_indicador_mapeado_resuelve_el_tipo_esperado(string $indicador, string $tipo): void
    {
        $this->assertSame($tipo, ResolvedorRequisito::para($indicador)?->tipo);
    }

    public static function contextos(): array
    {
        return [
            'GCR espacio libre' => ['radiacion_diaria', ['entorno' => 'espacio_libre'], 1.3],
            'GCR superficie' => ['radiacion_diaria', ['entorno' => 'superficie'], 0.9],
            'evento solar' => ['dosis_acumulada', ['condicion' => 'evento_solar'], 250.0],
            'evento solar en la diaria' => ['radiacion_diaria', ['condicion' => 'evento_solar'], 250.0],
            'carrera' => ['dosis_acumulada', [], 600.0],
            'presión crisis' => ['presion_arterial', ['banda' => 'crisis'], null],
            'salto de temperatura' => ['temperatura', ['condicion' => 'salto'], null],
        ];
    }

    /** @dataProvider contextos */
    public function test_resuelve_por_contexto(string $indicador, array $contexto, ?float $valor): void
    {
        $r = ResolvedorRequisito::para($indicador, $contexto);
        $this->assertNotNull($r);
        if ($valor !== null) {
            $this->assertSame($valor, $r->valor);
        }
    }

    public function test_banda_desconocida_cae_al_requisito_por_defecto(): void
    {
        $this->assertSame('referencia', ResolvedorRequisito::para('presion_arterial', ['banda' => 'no_existe'])->tipo);
    }

    public function test_perdida_osea_es_proyeccion_hrp_con_referencia_v1_4027(): void
    {
        $fuente = ResolvedorRequisito::fuente(ResolvedorRequisito::para('perdida_osea'));
        $this->assertSame('hrp', $fuente['tipo']);
        $this->assertTrue($fuente['es_proyeccion']);
        $this->assertSame('Proyección (HRP)', $fuente['etiqueta']);

        $ref = ResolvedorRequisito::fuente(ResolvedorRequisito::referenciaDe('perdida_osea'));
        $this->assertSame('V1 4027', $ref['codigo']);
        $this->assertSame('norma', $ref['tipo']);
        $this->assertFalse($ref['es_proyeccion']);
    }

    public function test_fuente_de_una_norma_no_es_proyeccion_y_null_da_null(): void
    {
        $f = ResolvedorRequisito::fuente(ResolvedorRequisito::para('dosis_acumulada'));
        $this->assertFalse($f['es_proyeccion']);
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C', $f['etiqueta']);
        $this->assertFalse($f['validado']);
        $this->assertNull(ResolvedorRequisito::fuente(null));
    }

    public function test_toda_regla_con_umbral_tiene_requisito_salvo_las_declaradas(): void
    {
        $indicadores = array_keys(config('umbrales.indicadores'));
        foreach ($indicadores as $i) {
            if (array_key_exists($i, self::SIN_REQUISITO)) {
                $this->assertNull(ResolvedorRequisito::para($i), "$i no debería tener requisito: ".self::SIN_REQUISITO[$i]);
            } else {
                $this->assertNotNull(ResolvedorRequisito::para($i), "$i tiene umbral y no tiene requisito");
            }
        }
        // La lista de excepciones no puede quedarse con claves que ya no existen.
        $this->assertSame([], array_diff(array_keys(self::SIN_REQUISITO), $indicadores));
    }

    public function test_la_guia_muestra_el_chip_el_rango_de_apolo_y_la_referencia_de_hueso(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mision = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mision->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mision->id)->firstOrFail()->id);

        Livewire::test(Guia::class)
            ->assertSee('Fuente: Referencia clínica')
            ->assertSee('Fuente: Proyección (HRP)')
            ->assertSee('V1 4027')
            ->assertSee('66 a 144 lpm')
            ->assertSee('~96 lpm')
            ->assertSee('70 a 109 lpm')
            ->assertSee('Umbral de ejemplo (sin requisito asociado)');
    }
}
