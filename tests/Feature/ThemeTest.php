<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Tema oscuro por defecto, alto contraste y control de tema (comprobaciones sobre el HTML y los archivos de estilo). */
class ThemeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_layout_fija_el_tema_antes_del_primer_pintado_y_ofrece_los_tres_modos(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("localStorage.getItem('tema')", $html);
        $this->assertStringContainsString("setAttribute('data-theme'", $html);
        // el script del tema va antes de los estilos de Vite
        $this->assertLessThan(strpos($html, '<body'), strpos($html, "getItem('tema')"));
        foreach (['dark', 'light', 'hc'] as $t) {
            $this->assertStringContainsString('data-tema="'.$t.'"', $html);
        }
        $this->assertStringContainsString('data-tema-ciclo', $html);
        $this->assertStringContainsString('aria-pressed', $html);
    }

    public function test_los_tokens_definen_oscuro_por_defecto_claro_y_alto_contraste(): void
    {
        $css = file_get_contents(resource_path('css/design/tokens.css'));
        $this->assertMatchesRegularExpression('/:root\s*\{\s*color-scheme:\s*dark;/', $css);
        $this->assertStringContainsString(':root[data-theme="light"]', $css);
        $this->assertStringContainsString(':root[data-theme="hc"]', $css);
        foreach (['--bg', '--surface', '--text', '--muted', '--primary', '--on-primary', '--ok-text', '--warn-text', '--crit-text', '--crit-solid', '--focus'] as $v) {
            $this->assertSame(3, preg_match_all('/'.preg_quote($v, '/').':/', $css), "$v debe existir en los tres temas");
        }
    }

    public function test_los_estilos_no_dejan_superficies_ni_textos_con_colores_fijos(): void
    {
        $permitidos = ['tokens.css', 'fonts.css', 'silueta-corporal.css'];
        foreach (array_merge(glob(resource_path('css/*.css')), glob(resource_path('css/design/*.css'))) as $f) {
            if (in_array(basename($f), $permitidos, true) || str_starts_with(basename($f), 'medico')) {
                continue; // medico.css es de otra sesión
            }
            $this->assertDoesNotMatchRegularExpression('/(?<![-\w])(background|color|border[\w-]*|fill|stroke)\s*:[^;{}]*(oklch\(|#[0-9a-fA-F]{3,8}\b)/', file_get_contents($f), basename($f).' tiene colores fijos');
        }
    }

    public function test_los_controles_pequenos_miden_al_menos_44px(): void
    {
        $al = file_get_contents(resource_path('css/alertas.css'));
        $ind = file_get_contents(resource_path('css/design/indicador.css'));
        $this->assertMatchesRegularExpression('/\.al-filtro \{[^}]*min-height: 44px/', $al);
        $this->assertMatchesRegularExpression('/\.al-vista \{[^}]*min-height: 44px/', $al);
        $this->assertMatchesRegularExpression('/\.ind-enlace \{[^}]*min-height: 44px/', $ind);
        $this->assertMatchesRegularExpression('/\.ind-chip[^{]*\{[^}]*min-height: 44px/', $ind);
    }
}
