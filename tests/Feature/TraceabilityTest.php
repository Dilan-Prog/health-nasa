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

/** DatabaseTransactions (not RefreshDatabase): never deletes the local database data. */
class TraceabilityTest extends TestCase
{
    use DatabaseTransactions;

    /** Indicators in config/umbrales.php that have NO requirement in the seeder, with the reason. */
    private const WITHOUT_REQUIREMENT = [
        'saturacion_o2' => 'the seeder has no saturation row',
        'glucosa' => 'the seeder has no glucose row',
        'imc' => 'the seeder has no BMI row',
        'masa_muscular' => 'only "strength" exists, which is a different measure',
        'estres_cognitivo' => 'only the qualitative behavior requirement exists, with no number',
        'animo' => 'the seeder has no mood row',
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

    public static function types(): array
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

    /** @dataProvider types */
    public function test_each_mapped_indicator_resolves_to_the_expected_type(string $indicator, string $type): void
    {
        $this->assertSame($type, ResolvedorRequisito::para($indicator)?->tipo);
    }

    public static function contexts(): array
    {
        return [
            'GCR free space' => ['radiacion_diaria', ['entorno' => 'espacio_libre'], 1.3],
            'GCR surface' => ['radiacion_diaria', ['entorno' => 'superficie'], 0.9],
            'solar event' => ['dosis_acumulada', ['condicion' => 'evento_solar'], 250.0],
            'solar event in the daily' => ['radiacion_diaria', ['condicion' => 'evento_solar'], 250.0],
            'career' => ['dosis_acumulada', [], 600.0],
            'pressure crisis' => ['presion_arterial', ['banda' => 'crisis'], null],
            'temperature jump' => ['temperatura', ['condicion' => 'salto'], null],
        ];
    }

    /** @dataProvider contexts */
    public function test_resolves_by_context(string $indicator, array $context, ?float $value): void
    {
        $r = ResolvedorRequisito::para($indicator, $context);
        $this->assertNotNull($r);
        if ($value !== null) {
            $this->assertSame($value, $r->valor);
        }
    }

    public function test_unknown_band_falls_back_to_the_default_requirement(): void
    {
        $this->assertSame('referencia', ResolvedorRequisito::para('presion_arterial', ['banda' => 'no_existe'])->tipo);
    }

    public function test_bone_loss_is_an_hrp_projection_with_reference_v1_4027(): void
    {
        $source = ResolvedorRequisito::fuente(ResolvedorRequisito::para('perdida_osea'));
        $this->assertSame('hrp', $source['tipo']);
        $this->assertTrue($source['es_proyeccion']);
        $this->assertSame('Projection (HRP)', $source['etiqueta']);

        $reference = ResolvedorRequisito::fuente(ResolvedorRequisito::referenciaDe('perdida_osea'));
        $this->assertSame('V1 4027', $reference['codigo']);
        $this->assertSame('norma', $reference['tipo']);
        $this->assertFalse($reference['es_proyeccion']);
    }

    public function test_source_of_a_standard_is_not_a_projection_and_null_gives_null(): void
    {
        $f = ResolvedorRequisito::fuente(ResolvedorRequisito::para('dosis_acumulada'));
        $this->assertFalse($f['es_proyeccion']);
        $this->assertSame('NASA-STD-3001 Vol. 1 Rev. C', $f['etiqueta']);
        $this->assertFalse($f['validado']);
        $this->assertNull(ResolvedorRequisito::fuente(null));
    }

    public function test_every_threshold_rule_has_a_requirement_except_the_declared_ones(): void
    {
        $indicators = array_keys(config('umbrales.indicadores'));
        foreach ($indicators as $i) {
            if (array_key_exists($i, self::WITHOUT_REQUIREMENT)) {
                $this->assertNull(ResolvedorRequisito::para($i), "$i should not have a requirement: ".self::WITHOUT_REQUIREMENT[$i]);
            } else {
                $this->assertNotNull(ResolvedorRequisito::para($i), "$i has a threshold but no requirement");
            }
        }
        // The exception list must not keep keys that no longer exist.
        $this->assertSame([], array_diff(array_keys(self::WITHOUT_REQUIREMENT), $indicators));
    }

    public function test_guide_shows_the_chip_the_apollo_range_and_the_bone_reference(): void
    {
        $this->seed(ExpedicionSeeder::class);
        $mission = Mision::where('nombre', ExpedicionSeeder::MISION)->firstOrFail();
        session(['mision_id' => $mission->id]);
        TripulanteActual::fijar(Astronauta::where('mision_id', $mission->id)->firstOrFail()->id);

        Livewire::test(Guia::class)
            ->assertSee('Source: Clinical reference')
            ->assertSee('Source: Projection (HRP)')
            ->assertSee('V1 4027')
            ->assertSee('66 to 144 bpm')
            ->assertSee('~96 bpm')
            ->assertSee('70 to 109 bpm')
            ->assertSee('Example threshold (no associated requirement)');
    }
}
