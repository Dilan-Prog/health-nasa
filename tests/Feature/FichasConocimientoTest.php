<?php

namespace Tests\Feature;

use App\Services\Ia\LectorFichas;
use RuntimeException;
use Tests\TestCase;

/** Las fichas y config/patrones.php deben ir siempre de la mano: si falta una ficha o está mal formada, este test falla. */
class FichasConocimientoTest extends TestCase
{
    public function test_los_patrones_son_los_siete_escenarios_de_la_simulacion(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(config('simulacion.escenarios')), array_keys(config('patrones')));
    }

    public function test_cada_patron_tiene_la_estructura_del_contrato(): void
    {
        foreach (config('patrones') as $clave => $p) {
            foreach (['nombre', 'ficha', 'indicadores', 'acciones_guia', 'confianza', 'ventana_min', 'senales', 'minimo'] as $campo) {
                $this->assertArrayHasKey($campo, $p, "{$clave}: falta '{$campo}'");
            }
            $this->assertIsInt($p['confianza']);
            $this->assertGreaterThanOrEqual(0, $p['confianza']);
            $this->assertLessThanOrEqual(100, $p['confianza']);
            $this->assertGreaterThan(0, $p['ventana_min']);
            $this->assertGreaterThanOrEqual(1, $p['minimo']);
            $this->assertLessThanOrEqual(count($p['senales']), $p['minimo']);
            $this->assertTrue(
                array_filter($p['senales'], fn ($s) => $s['requerida']) !== [] || ($p['origen_senales'] ?? null) === 'calidad',
                "{$clave}: debe haber una señal requerida (o ser de calidad)"
            );
            foreach ($p['senales'] as $s) {
                $this->assertArrayHasKey($s['indicador'], config('umbrales.indicadores'), "{$clave}: indicador inexistente {$s['indicador']}");
                $this->assertContains($s['indicador'], $p['indicadores'], "{$clave}: la señal {$s['indicador']} falta en 'indicadores'");
                $this->assertNotEmpty($s['estados']);
                $this->assertIsBool($s['requerida']);
            }
            foreach ($p['acciones_guia'] as $k) {
                $this->assertArrayHasKey($k, config('guia.marcadores'), "{$clave}: acción de guía inexistente {$k}");
                $this->assertArrayHasKey($k, config('indicadores.marcadores'), "{$clave}: marcador inexistente {$k}");
            }
        }
    }

    public function test_los_nombres_usan_lenguaje_de_hipotesis(): void
    {
        foreach (config('patrones') as $clave => $p) {
            $this->assertStringStartsWith('Posible ', $p['nombre'], $clave);
        }
        $this->assertSame('Posible irregularidad del pulso (requiere ECG)', config('patrones.arritmia.nombre'));
    }

    public function test_existe_una_ficha_valida_por_cada_patron(): void
    {
        $fichas = LectorFichas::todas();

        $this->assertCount(count(config('patrones')), $fichas);
        foreach ($fichas as $patron => $f) {
            $this->assertSame($patron, $f['patron']);
            $this->assertSame(config("patrones.{$patron}.ficha"), $f['slug']);
            $this->assertSame('pendiente', $f['revision_medica']);
            $this->assertNotEmpty($f['fuentes']);
            $this->assertNotSame('', $f['titulo']);
            foreach (['senales', 'chequear', 'escalar', 'limites'] as $seccion) {
                $this->assertNotSame('', $f[$seccion], "{$patron}: sección {$seccion} vacía");
            }
            $this->assertSame(config("patrones.{$patron}.nombre"), $f['titulo'], "{$patron}: el título de la ficha debe ser la hipótesis del patrón");
        }
    }

    public function test_las_fichas_no_contienen_dosis_ni_farmacos(): void
    {
        foreach (LectorFichas::todas() as $patron => $f) {
            $texto = implode("\n", [$f['senales'], $f['chequear'], $f['escalar'], $f['limites']]);
            $this->assertDoesNotMatchRegularExpression('/\d+(?:[.,]\d+)?\s?(mg|mcg|µg|ml|UI)\b/iu', $texto, "{$patron}: parece una dosis");
            $this->assertDoesNotMatchRegularExpression('/\b(ibuprofeno|acetaminof[eé]n|aspirina|paracetamol|zolpidem|melatonina|modafinilo|amoxicilina|azitromicina|prometazina)\b/iu', $texto, "{$patron}: nombra un medicamento");
        }
    }

    public function test_falla_si_falta_la_ficha_de_un_patron(): void
    {
        config(['patrones.zzz_prueba' => ['ficha' => 'no_existe_esta_ficha']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no_existe_esta_ficha');
        LectorFichas::ficha('zzz_prueba');
    }

    public function test_falla_si_no_hay_frontmatter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('frontmatter');
        LectorFichas::desdeTexto("## Señales a vigilar\n- x\n", 'x.md');
    }

    public function test_falla_si_el_frontmatter_es_invalido(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Frontmatter inválido');
        LectorFichas::desdeTexto("---\nslug: [sin cerrar\n---\n## Señales a vigilar\n- x\n", 'x.md');
    }

    public function test_falla_si_falta_un_campo_o_las_fuentes(): void
    {
        $base = "slug: a\npatron: a\ntitulo: A\nrevision_medica: pendiente\n";
        $cuerpo = "## Señales a vigilar\na\n## Qué chequear\nb\n## Cuándo escalar\nc\n## Límites de lo que puede afirmarse\nd\n";

        try {
            LectorFichas::desdeTexto("---\n{$base}---\n{$cuerpo}", 'x.md');
            $this->fail('Debió fallar: faltan las fuentes');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("'fuentes'", $e->getMessage());
        }

        try {
            LectorFichas::desdeTexto("---\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: pendiente\n---\n{$cuerpo}", 'x.md');
            $this->fail('Debió fallar: falta el slug');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("'slug'", $e->getMessage());
        }

        $ok = LectorFichas::desdeTexto("---\n{$base}fuentes: [f]\n---\n{$cuerpo}", 'x.md');
        $this->assertSame('a', $ok['senales']);
    }

    public function test_falla_si_falta_una_seccion_fija(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cuándo escalar');
        LectorFichas::desdeTexto("---\nslug: a\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: pendiente\n---\n## Señales a vigilar\na\n## Qué chequear\nb\n## Límites de lo que puede afirmarse\nd\n", 'x.md');
    }

    public function test_exige_revision_medica_pendiente(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('revision_medica');
        LectorFichas::desdeTexto("---\nslug: a\npatron: a\ntitulo: A\nfuentes: [f]\nrevision_medica: aprobada\n---\n## Señales a vigilar\na\n", 'x.md');
    }
}
