<?php

namespace Tests\Feature;

use App\Models\BotiquinItem;
use App\Services\Ia\FiltroSeguridad;
use App\Services\Ia\LectorFichasSintomas;
use RuntimeException;
use Tests\TestCase;

/** Las fichas de síntoma (conocimiento/sintomas) deben parsear, ser conservadoras y no saltarse el filtro de seguridad. */
class FichasSintomasTest extends TestCase
{
    private const OBLIGATORIAS = [
        'dolor_toracico', 'falta_aire', 'mareo_vertigo', 'dolor_cabeza', 'dolor_muscular_localizado', 'dolor_extremidades',
        'nauseas', 'fiebre_escalofrios', 'fatiga_somnolencia', 'palpitaciones', 'dolor_abdominal', 'dolor_espalda',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        LectorFichasSintomas::limpiarCache();
    }

    public function test_existen_todas_las_fichas_y_parsean(): void
    {
        $todas = LectorFichasSintomas::todas();
        foreach (self::OBLIGATORIAS as $slug) {
            $this->assertArrayHasKey($slug, $todas, "Falta la ficha {$slug}");
            $this->assertSame($slug, LectorFichasSintomas::ficha($slug)['slug']);
        }
    }

    public function test_campos_obligatorios_y_valores_validos(): void
    {
        foreach (LectorFichasSintomas::todas() as $slug => $f) {
            $this->assertContains($f['tipo'], LectorFichasSintomas::TIPOS, $slug);
            $this->assertContains($f['urgencia_base'], LectorFichasSintomas::URGENCIAS, $slug);
            $this->assertContains($f['origen'], LectorFichasSintomas::ORIGENES, $slug);
            $this->assertSame('pendiente', $f['revision_medica'], $slug);
            $this->assertNotEmpty($f['titulo'], $slug);
            $this->assertNotEmpty($f['sintomas_clave'], $slug);
            $this->assertNotEmpty($f['acciones'], $slug);
            $this->assertNotEmpty($f['seguimiento'], $slug);
            $this->assertNotEmpty($f['escalar_si'], $slug);
            $this->assertNotEmpty($f['fuentes'], $slug);
            foreach ($f['seguimiento'] as $s) {
                if ($s['indicador'] !== null) {
                    $this->assertArrayHasKey($s['indicador'], config('indicadores.marcadores'), "{$slug}: indicador {$s['indicador']} no existe en config/indicadores.php");
                }
            }
        }
    }

    public function test_una_ficha_con_origen_documento_cita_un_documento_y_una_accion_documentada(): void
    {
        foreach (LectorFichasSintomas::todas() as $slug => $f) {
            if ($f['origen'] !== 'documento') {
                continue;
            }
            $this->assertNotEmpty(array_filter($f['fuentes'], fn ($t) => str_contains($t, 'docs/fuentes/')), "{$slug}: origen documento sin archivo citado");
            $this->assertNotEmpty(array_filter($f['acciones'], fn ($a) => $a['fuente']['origen'] === 'documento'), "{$slug}: origen documento sin acción respaldada");
        }
    }

    public function test_los_grupos_del_botiquin_existen_y_nunca_son_antimicrobianos_ni_emergencias(): void
    {
        foreach (LectorFichasSintomas::todas() as $slug => $f) {
            foreach ($f['grupos_botiquin_permitidos'] as $grupo) {
                $this->assertArrayHasKey($grupo, BotiquinItem::GRUPOS, "{$slug}: grupo inexistente '{$grupo}'");
                $this->assertNotContains($grupo, ['Antimicrobianos y antivirales', 'Emergencias'], $slug);
            }
        }
    }

    public function test_las_fichas_de_bandera_roja_no_permiten_botiquin(): void
    {
        $rojas = array_filter(LectorFichasSintomas::todas(), fn ($f) => $f['urgencia_base'] === 'emergencia');
        $this->assertArrayHasKey('dolor_toracico', $rojas);
        $this->assertArrayHasKey('falta_aire', $rojas);
        foreach ($rojas as $slug => $f) {
            $this->assertSame([], $f['grupos_botiquin_permitidos'], $slug);
            $this->assertNotEmpty($f['banderas_rojas'], $slug);
        }
    }

    public function test_el_mareo_solo_permite_el_grupo_de_mareo_por_adaptacion(): void
    {
        $this->assertSame(['Mareo por adaptación'], LectorFichasSintomas::ficha('mareo_vertigo')['grupos_botiquin_permitidos']);
        $this->assertSame('documento', LectorFichasSintomas::ficha('mareo_vertigo')['origen']);
        $this->assertSame('documento', LectorFichasSintomas::ficha('dolor_espalda')['origen']);
    }

    public function test_ningun_texto_lleva_dosis_diagnostico_ni_farmacos(): void
    {
        $filtro = new FiltroSeguridad;
        foreach (LectorFichasSintomas::todas() as $slug => $f) {
            $textos = [$f['titulo'], $f['notas'], ...$f['escalar_si'], ...$f['fuentes'],
                ...array_column($f['seguimiento'], 'texto')];
            foreach ($f['acciones'] as $a) {
                $textos[] = $a['texto'];
                $textos[] = $a['fuente']['etiqueta'];
                $textos[] = $a['fuente']['texto'];
            }
            foreach ($textos as $t) {
                $this->assertNull($filtro->revisar($t), "{$slug}: el filtro rechaza «{$t}»");
            }
        }
    }

    public function test_cada_accion_tiene_id_unico_y_fuente(): void
    {
        $ids = [];
        foreach (LectorFichasSintomas::todas() as $slug => $f) {
            foreach ($f['acciones'] as $a) {
                $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+$/', $a['id']);
                $this->assertArrayNotHasKey($a['id'], $ids, "Id repetido {$a['id']}");
                $ids[$a['id']] = true;
                $this->assertNotSame('', trim($a['fuente']['etiqueta']));
                $this->assertNotSame('', trim($a['fuente']['texto']));
                $this->assertContains($a['fuente']['origen'], LectorFichasSintomas::ORIGENES);
            }
        }
    }

    public function test_por_sintoma_devuelve_las_fichas_de_esa_clave(): void
    {
        $this->assertSame(['mareo_vertigo'], array_column(LectorFichasSintomas::porSintoma('mareo'), 'slug'));
        $this->assertContains('dolor_toracico', array_column(LectorFichasSintomas::porSintoma('presion_pecho'), 'slug'));
        $this->assertContains('dolor_espalda', array_column(LectorFichasSintomas::porSintoma('dolor'), 'slug'));
        $this->assertSame([], LectorFichasSintomas::porSintoma('clave_inexistente'));
    }

    public function test_el_lector_rechaza_fichas_mal_formadas(): void
    {
        $this->expectException(RuntimeException::class);
        LectorFichasSintomas::desdeTexto("---\nslug: x\ntitulo: X\n---\n", 'x.md');
    }

    public function test_el_lector_rechaza_revision_medica_distinta_de_pendiente(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('revision_medica');
        LectorFichasSintomas::desdeTexto("---\nslug: x\ntipo: sintoma\ntitulo: X\nurgencia_base: autocuidado\norigen: ejemplo_simulado\nrevision_medica: validada\n---\n", 'x.md');
    }
}
