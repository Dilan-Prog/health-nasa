<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Dark theme by default, high contrast and the theme control (checks on the HTML and the stylesheets). */
class ThemeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_layout_sets_the_theme_before_first_paint_and_offers_the_three_modes(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("localStorage.getItem('tema')", $html);
        $this->assertStringContainsString("setAttribute('data-theme'", $html);
        // the theme script comes before the Vite styles
        $this->assertLessThan(strpos($html, '<body'), strpos($html, "getItem('tema')"));
        foreach (['dark', 'light', 'hc'] as $t) {
            $this->assertStringContainsString('data-tema="'.$t.'"', $html);
        }
        $this->assertStringContainsString('data-tema-ciclo', $html);
        $this->assertStringContainsString('aria-pressed', $html);
    }

    public function test_tokens_define_dark_by_default_light_and_high_contrast(): void
    {
        $css = file_get_contents(resource_path('css/design/tokens.css'));
        $this->assertMatchesRegularExpression('/:root\s*\{\s*color-scheme:\s*dark;/', $css);
        $this->assertStringContainsString(':root[data-theme="light"]', $css);
        $this->assertStringContainsString(':root[data-theme="hc"]', $css);
        foreach (['--bg', '--surface', '--text', '--muted', '--primary', '--on-primary', '--ok-text', '--warn-text', '--crit-text', '--crit-solid', '--focus'] as $v) {
            $this->assertSame(3, preg_match_all('/'.preg_quote($v, '/').':/', $css), "$v must exist in all three themes");
        }
    }

    public function test_styles_leave_no_surfaces_or_text_with_hard_coded_colors(): void
    {
        $allowed = ['tokens.css', 'fonts.css', 'silueta-corporal.css'];
        foreach (array_merge(glob(resource_path('css/*.css')), glob(resource_path('css/design/*.css'))) as $f) {
            if (in_array(basename($f), $allowed, true) || str_starts_with(basename($f), 'medico')) {
                continue; // medico.css belongs to another session
            }
            $this->assertDoesNotMatchRegularExpression('/(?<![-\w])(background|color|border[\w-]*|fill|stroke)\s*:[^;{}]*(oklch\(|#[0-9a-fA-F]{3,8}\b)/', file_get_contents($f), basename($f).' has hard-coded colors');
        }
    }

    public function test_small_controls_are_at_least_44px(): void
    {
        $alertsCss = file_get_contents(resource_path('css/alertas.css'));
        $indicatorCss = file_get_contents(resource_path('css/design/indicador.css'));
        $this->assertMatchesRegularExpression('/\.al-filtro \{[^}]*min-height: 44px/', $alertsCss);
        $this->assertMatchesRegularExpression('/\.al-vista \{[^}]*min-height: 44px/', $alertsCss);
        $this->assertMatchesRegularExpression('/\.ind-enlace \{[^}]*min-height: 44px/', $indicatorCss);
        $this->assertMatchesRegularExpression('/\.ind-chip[^{]*\{[^}]*min-height: 44px/', $indicatorCss);
    }
}
