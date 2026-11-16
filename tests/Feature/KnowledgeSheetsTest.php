<?php

namespace Tests\Feature;

use App\Services\Ia\LectorFichas;
use RuntimeException;
use Tests\TestCase;

/** The knowledge sheets and config/patrones.php must always stay in sync: a missing or malformed sheet makes these tests fail. */
class KnowledgeSheetsTest extends TestCase
{
    public function test_patterns_match_the_seven_simulation_scenarios(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(config('simulacion.escenarios')), array_keys(config('patrones')));
    }

    public function test_each_pattern_has_the_contract_structure(): void
    {
        foreach (config('patrones') as $key => $p) {
            foreach (['nombre', 'ficha', 'indicadores', 'acciones_guia', 'confianza', 'ventana_min', 'senales', 'minimo'] as $field) {
                $this->assertArrayHasKey($field, $p, "{$key}: missing '{$field}'");
            }
            $this->assertIsInt($p['confianza']);
            $this->assertGreaterThanOrEqual(0, $p['confianza']);
            $this->assertLessThanOrEqual(100, $p['confianza']);
            $this->assertGreaterThan(0, $p['ventana_min']);
            $this->assertGreaterThanOrEqual(1, $p['minimo']);
            $this->assertLessThanOrEqual(count($p['senales']), $p['minimo']);
            $this->assertTrue(
                array_filter($p['senales'], fn ($s) => $s['requerida']) !== [] || ($p['origen_senales'] ?? null) === 'calidad',
                "{$key}: there must be a required signal (or be a quality pattern)"
            );
            foreach ($p['senales'] as $s) {
                $this->assertArrayHasKey($s['indicador'], config('umbrales.indicadores'), "{$key}: nonexistent indicator {$s['indicador']}");
                $this->assertContains($s['indicador'], $p['indicadores'], "{$key}: signal {$s['indicador']} is missing from 'indicadores'");
                $this->assertNotEmpty($s['estados']);
                $this->assertIsBool($s['requerida']);
            }
            foreach ($p['acciones_guia'] as $k) {
                $this->assertArrayHasKey($k, config('guia.marcadores'), "{$key}: nonexistent guide action {$k}");
                $this->assertArrayHasKey($k, config('indicadores.marcadores'), "{$key}: nonexistent marker {$k}");
            }
        }
    }

    public function test_pattern_names_use_hypothesis_language(): void
    {
        foreach (config('patrones') as $key => $p) {
            $this->assertStringStartsWith('Possible ', $p['nombre'], $key);
        }
        $this->assertSame('Possible pulse irregularity (requires ECG)', config('patrones.arritmia.nombre'));
    }

    public function test_every_pattern_has_a_valid_sheet(): void
    {
        $sheets = LectorFichas::todas();

        $this->assertCount(count(config('patrones')), $sheets);
        foreach ($sheets as $pattern => $f) {
            $this->assertSame($pattern, $f['patron']);
            $this->assertSame(config("patrones.{$pattern}.ficha"), $f['slug']);
            $this->assertSame('pendiente', $f['revision_medica']);
            $this->assertNotEmpty($f['fuentes']);
            $this->assertNotSame('', $f['titulo']);
            foreach (['senales', 'chequear', 'escalar', 'limites'] as $section) {
                $this->assertNotSame('', $f[$section], "{$pattern}: section {$section} is empty");
            }
            $this->assertSame(config("patrones.{$pattern}.nombre"), $f['titulo'], "{$pattern}: the sheet title must be the pattern hypothesis");
        }
    }

    public function test_sheets_contain_no_doses_or_drug_names(): void
    {
        foreach (LectorFichas::todas() as $pattern => $f) {
            $text = implode("\n", [$f['senales'], $f['chequear'], $f['escalar'], $f['limites']]);
            $this->assertDoesNotMatchRegularExpression('/\d+(?:[.,]\d+)?\s?(mg|mcg|µg|ml|UI)\b/iu', $text, "{$pattern}: looks like a dose");
            $this->assertDoesNotMatchRegularExpression('/\b(ibuprofeno|ibuprofen|acetaminof[eé]n|acetaminophen|aspirina|aspirin|paracetamol|zolpidem|melatonina|melatonin|modafinilo|modafinil|amoxicilina|amoxicillin|azitromicina|azithromycin|prometazina|promethazine)\b/iu', $text, "{$pattern}: names a drug");
        }
    }

    public function test_fails_when_a_pattern_sheet_is_missing(): void
    {
        config(['patrones.zzz_test' => ['ficha' => 'this_sheet_does_not_exist']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('this_sheet_does_not_exist');
        LectorFichas::ficha('zzz_test');
    }

    public function test_fails_without_frontmatter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/frontmatter/i');
        LectorFichas::desdeTexto("## Signs to watch\n- x\n", 'x.md');
    }

    public function test_fails_when_frontmatter_is_invalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/frontmatter/i');
        LectorFichas::desdeTexto("---\nslug: [unclosed\n---\n## Signs to watch\n- x\n", 'x.md');
    }

    public function test_fails_when_a_field_or_the_sources_are_missing(): void
    {
        $base = "slug: a\npatron: a\ntitulo: A\nrevision_medica: pendiente\n";
        $body = "## Signs to watch\na\n## What to check\nb\n## When to escalate\nc\n## Limits of what can be stated\nd\n";

        try {
            LectorFichas::desdeTexto("---\n{$base}---\n{$body}", 'x.md');
            $this->fail('Should have failed: the sources are missing');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("'fuentes'", $e->getMessage());
        }

        try {
            LectorFichas::desdeTexto("---\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: pendiente\n---\n{$body}", 'x.md');
            $this->fail('Should have failed: the slug is missing');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("'slug'", $e->getMessage());
        }

        $ok = LectorFichas::desdeTexto("---\n{$base}fuentes: [f]\n---\n{$body}", 'x.md');
        $this->assertSame('a', $ok['senales']);
    }

    public function test_fails_when_a_fixed_section_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('When to escalate');
        LectorFichas::desdeTexto("---\nslug: a\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: pendiente\n---\n## Signs to watch\na\n## What to check\nb\n## Limits of what can be stated\nd\n", 'x.md');
    }

    public function test_requires_pending_medical_review(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('revision_medica');
        LectorFichas::desdeTexto("---\nslug: a\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: aprobada\n---\n## Signs to watch\na\n", 'x.md');
    }
}
