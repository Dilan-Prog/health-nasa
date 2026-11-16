<?php

namespace Tests\Feature;

use App\Models\Requisito;
use App\Services\Salud\ResolvedorRequisito;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Requirements are identified by a stable `clave`, never by their (translatable) text. */
class RequirementKeyTest extends TestCase
{
    use DatabaseTransactions;

    private const EXPECTED_ROWS = 57;

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

    private function migration(): object
    {
        return require base_path('database/migrations/2026_11_21_000001_add_clave_to_requisitos.php');
    }

    public function test_the_key_column_exists_and_is_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('requisitos', 'clave'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        $first = Requisito::first();
        Requisito::create([
            'clave' => $first->clave, 'texto' => 'duplicate', 'dominio' => 'x', 'tipo' => 'norma',
        ]);
    }

    public function test_all_57_rows_have_a_distinct_key_of_the_form_type_domain_slug(): void
    {
        $this->assertSame(self::EXPECTED_ROWS, Requisito::count());
        $this->assertSame(0, Requisito::whereNull('clave')->count());
        $this->assertSame(self::EXPECTED_ROWS, Requisito::distinct()->count('clave'));

        foreach (Requisito::all() as $r) {
            $this->assertStringStartsWith($r->tipo.'.'.$r->dominio.'.', $r->clave);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $ids = Requisito::orderBy('clave')->pluck('id', 'clave')->all();

        $this->seed(RequisitosSeeder::class);
        $this->seed(RequisitosSeeder::class);

        $this->assertSame(self::EXPECTED_ROWS, Requisito::count());
        $this->assertSame($ids, Requisito::orderBy('clave')->pluck('id', 'clave')->all());
    }

    public function test_the_seeder_rewrites_text_of_an_existing_key_without_creating_a_new_row(): void
    {
        $row = Requisito::where('clave', 'norma.radiacion.gcr_espacio_libre')->first();
        $row->update(['texto' => 'Old wording in Spanish']);

        $this->seed(RequisitosSeeder::class);

        $this->assertSame(self::EXPECTED_ROWS, Requisito::count());
        $fresh = $row->fresh();
        $this->assertSame($row->id, $fresh->id);
        $this->assertSame('GCR in free space', $fresh->texto);
    }

    public function test_the_resolver_finds_the_row_by_key_even_if_the_text_changes(): void
    {
        $r = ResolvedorRequisito::para('radiacion_diaria');
        $this->assertSame('norma.radiacion.gcr_espacio_libre', $r->clave);
        $this->assertSame('GCR in free space', $r->texto);

        Requisito::where('clave', 'norma.radiacion.gcr_espacio_libre')->update(['texto' => 'Any other wording']);
        ResolvedorRequisito::olvidar();

        $r = ResolvedorRequisito::para('radiacion_diaria');
        $this->assertSame('norma.radiacion.gcr_espacio_libre', $r->clave);
        $this->assertSame('Any other wording', $r->texto);
    }

    public function test_every_key_referenced_by_config_exists_in_the_table(): void
    {
        $keys = [];
        foreach (config('trazabilidad.indicadores') as $e) {
            $e && $keys[] = $e['clave'];
        }
        foreach (config('trazabilidad.referencias') as $e) {
            $keys[] = $e['clave'];
        }
        foreach (config('trazabilidad.condiciones') as $porCampo) {
            foreach ($porCampo as $porValor) {
                foreach ($porValor as $e) {
                    $keys[] = $e['clave'];
                }
            }
        }
        foreach (config('ventanas.reglas') as $regla) {
            $keys[] = $regla['requisito']['clave'];
        }

        $this->assertNotEmpty($keys);
        foreach (array_unique($keys) as $k) {
            $this->assertTrue(Requisito::where('clave', $k)->exists(), "Missing requirement key: $k");
        }
    }

    public function test_the_seeded_text_is_english_and_the_source_is_kept(): void
    {
        $r = Requisito::where('clave', 'norma.hueso.dmo_cadera_columna')->first();
        $this->assertSame('Hip and spine BMD relative to the pre-mission value', $r->texto);
        $this->assertSame('V1 4027', $r->codigo);
        $this->assertSame('% of pre-mission', $r->unidad);
        $this->assertStringContainsString('nasa-std-3001-vol-1-rev-c', $r->fuente_url);
        $this->assertFalse($r->validado);
    }

    public function test_the_migration_assigns_keys_in_place_without_recreating_rows(): void
    {
        $mapa = $this->migration()->mapa();
        $this->assertCount(self::EXPECTED_ROWS, $mapa);

        // Reproduce the pre-migration state: original Spanish text and no key.
        $ids = Requisito::pluck('id', 'clave')->all();
        foreach ($mapa as [$tipo, $dominio, $texto, $clave]) {
            $this->assertArrayHasKey($clave, $ids, "Migration key not produced by the seeder: $clave");
            DB::table('requisitos')->where('clave', $clave)->update(['texto' => $texto, 'clave' => null]);
        }
        $this->assertSame(self::EXPECTED_ROWS, Requisito::whereNull('clave')->count());

        $unmapped = $this->migration()->asignarClaves();

        $this->assertSame([], $unmapped);
        $this->assertSame(self::EXPECTED_ROWS, Requisito::count());
        $this->assertSame($ids, Requisito::pluck('id', 'clave')->all(), 'Row ids must be preserved');
    }

    public function test_the_migration_reports_rows_it_cannot_map(): void
    {
        // Pre-migration state: every row has its original Spanish text and no key, except one with unknown text.
        foreach ($this->migration()->mapa() as [, , $texto, $clave]) {
            DB::table('requisitos')->where('clave', $clave)->update(['texto' => $texto, 'clave' => null]);
        }
        Requisito::where('texto', 'Dosis efectiva de carrera')->update(['texto' => 'Unknown text']);

        $unmapped = $this->migration()->asignarClaves();

        $this->assertCount(1, $unmapped);
        $this->assertStringContainsString('Unknown text', $unmapped[0]);
    }
}
