<?php

namespace Tests\Feature;

use App\Services\I18n\SpanishDetector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Tests of the i18n audit tool. They use synthetic fixtures and never require the repository to be clean.
 *
 * The "repository is clean" assertion lives in a separate test grouped as `i18n-final`. Enable it at the end of the
 * translation with `php artisan test --group=i18n-final`. While the translation is in progress it fails on purpose;
 * exclude it from a normal run with `php artisan test --exclude-group=i18n-final`.
 */
class I18nAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function detector(array $allowlist = []): SpanishDetector
    {
        return new SpanishDetector($allowlist);
    }

    private function texts(array $findings): array
    {
        return array_column($findings, 'text');
    }

    /** @dataProvider spanishStrings */
    public function test_flags_spanish_strings(string $text): void
    {
        $this->assertNotNull($this->detector()->isSpanish($text), "Expected Spanish: {$text}");
    }

    public static function spanishStrings(): array
    {
        return [
            'accent' => ['Presión arterial'],
            'inverted mark' => ['¿Cómo te sientes?'],
            'function words' => ['Esta herramienta orienta, no diagnostica'],
            'two weak words' => ['Resumen de la semana'],
            'interface label' => ['Guardar'],
            'interface phrase' => ['Guardar y cerrar'],
            'ellipsis label' => ['Cargando…'],
            'vocabulary' => ['Ficha del tripulante'],
        ];
    }

    /** @dataProvider englishStrings */
    public function test_does_not_flag_english_or_identifiers(string $text): void
    {
        $this->assertNull($this->detector()->isSpanish($text), "Unexpected finding: {$text}");
    }

    public static function englishStrings(): array
    {
        return [
            'sentence' => ['Guidance only, not a diagnosis.'],
            'label' => ['What to do now'],
            'error' => ['Error'],
            'son' => ['Her son has a mild fever'],
            'la' => ['Los Angeles'],
            'stored key' => ['precaucion'],
            'key list' => ['normal|precaucion|critico'],
            'class' => ['App\\Livewire\\MedicoVuelo'],
            'route' => ['/medico-de-vuelo'],
            'sql' => ['indicador, DATE(medido_en) as dia'],
            'option' => ['--dias must be between 1 and 730'],
            'time' => ['03:29 UTC'],
            'empty' => ['   '],
        ];
    }

    public function test_names_are_split_into_words(): void
    {
        $this->assertSame(['it', 'Shows', 'La', 'Ficha', 'del', 'tripulante'], SpanishDetector::splitName('itShowsLaFicha_del_tripulante'));
        $this->assertSame(['test', 'XML', 'Parser'], SpanishDetector::splitName('testXMLParser'));
    }

    public function test_php_application_checks_literals_only(): void
    {
        $code = <<<'PHP'
<?php
// Este comentario en español no se escanea
class MedicoVuelo
{
    public function guardarCambios(): string
    {
        $clave = 'precaucion';
        return 'Guardar cambios';
    }
}
PHP;
        $findings = $this->detector()->scan('app/Services/Fixture.php', $code);

        $this->assertSame(['Guardar cambios'], $this->texts($findings));
        $this->assertSame(8, $findings[0]['line']);
    }

    public function test_php_array_keys_are_ignored_but_values_are_not(): void
    {
        $code = "<?php\nreturn ['estado' => 'Todos', 'activas' => 'Active'];\n";
        $this->assertSame(['Todos'], $this->texts($this->detector()->scan('config/fixture.php', $code)));
    }

    public function test_php_escaped_accents_are_decoded(): void
    {
        $code = "<?php\n\$x = \"Presi\\u{00F3}n\";\n";
        $this->assertSame(['Presión'], $this->texts($this->detector()->scan('app/Livewire/Fixture.php', $code)));
    }

    public function test_tests_also_check_comments_and_names(): void
    {
        $code = <<<'PHP'
<?php
namespace Tests\Feature;

/** Verifica que el panel muestra la alerta. */
class FichaTripulanteTest
{
    // el sistema no cambia nada
    public function test_muestra_la_ficha(): void
    {
        $this->assertTrue(true);
    }

    public function test_shows_the_crew_card(): void
    {
    }
}
PHP;
        $findings = $this->detector()->scan('tests/Feature/FixtureTest.php', $code);
        $texts = implode('|', $this->texts($findings));

        $this->assertStringContainsString('Verifica que el panel', $texts);
        $this->assertStringContainsString('el sistema no cambia nada', $texts);
        $this->assertStringContainsString('test muestra la ficha', $texts);
        $this->assertStringContainsString('Ficha Tripulante Test', $texts);
        $this->assertStringNotContainsString('shows the crew card', $texts);
    }

    public function test_application_comments_are_not_scanned_but_test_comments_are(): void
    {
        $code = "<?php\n// Nota en español con acentos: sí\n\$a = 'ok';\n";
        $this->assertSame([], $this->detector()->scan('app/Services/Fixture.php', $code));
        $this->assertNotSame([], $this->detector()->scan('tests/Feature/FixtureTest.php', $code));
    }

    public function test_blade_checks_text_attributes_and_echo_strings_but_not_comments_or_directives(): void
    {
        $blade = <<<'BLADE'
{{-- Comentario en español que se ignora --}}
<div class="panel" wire:click="guardar">
    <h1>Resumen del indicador</h1>
    <button title="Cerrar ventana" aria-label="Close">{{ $ok ? 'Guardar' : 'Save' }}</button>
    @if ($estado === 'precaucion')
        <p>All good.</p>
    @endif
    <x-mf-seccion titulo="Dolor de cabeza" />
    <script>const m = 'Cargando datos'; const k = 'hypoxia';</script>
</div>
BLADE;
        $findings = $this->detector()->scan('resources/views/livewire/fixture.blade.php', $blade);
        $texts = $this->texts($findings);

        foreach (['Resumen del indicador', 'Cerrar ventana', 'Guardar', 'Dolor de cabeza', 'Cargando datos'] as $expected) {
            $this->assertContains($expected, $texts);
        }
        $this->assertCount(5, $texts, implode(' | ', $texts));
        $this->assertSame(3, collect($findings)->firstWhere('text', 'Resumen del indicador')['line']);
    }

    public function test_javascript_strings_templates_and_regex(): void
    {
        $js = <<<'JS'
// comentario en español
const a = 'Guardar cambios';
const b = `Hola ${name}, tienes ${count} alertas pendientes`;
const c = /dolor de cabeza/i; // regex is not a string
const d = "Saved";
const e = x / 2 + 'Presión';
JS;
        $texts = $this->texts($this->detector()->scan('resources/js/fixture.js', $js));

        $this->assertContains('Guardar cambios', $texts);
        $this->assertContains('Presión', $texts);
        $this->assertGreaterThanOrEqual(1, count(array_filter($texts, fn ($t) => str_contains($t, 'alertas pendientes'))));
        $this->assertNotContains('Saved', $texts);
        $this->assertCount(0, array_filter($texts, fn ($t) => str_contains($t, 'comentario')));
        $this->assertCount(0, array_filter($texts, fn ($t) => str_contains($t, 'dolor de cabeza')));
    }

    public function test_javascript_tests_also_check_comments(): void
    {
        $js = "// el valor debe ser positivo\nit('works', () => {});\n";
        $texts = $this->texts($this->detector()->scan('tests/js/fixture.test.mjs', $js));

        $this->assertSame(['el valor debe ser positivo'], $texts);
    }

    public function test_json_and_manifest_values_are_checked_but_not_keys(): void
    {
        $json = "{\n  \"nombre\": \"Monitor de salud\",\n  \"lang\": \"en-US\"\n}\n";
        $this->assertSame(['Monitor de salud'], $this->texts($this->detector()->scan('public/manifest.webmanifest', $json)));
    }

    public function test_html_ignores_script_style_and_comments(): void
    {
        $html = "<!-- nota en español -->\n<style>.a{content:'Cerrar'}</style>\n<p>Hello</p>\n<input placeholder=\"Buscar tripulante\" alt=\"x\">\n";
        $this->assertSame(['Buscar tripulante'], $this->texts($this->detector()->scan('public/fixture.html', $html)));
    }

    public function test_allowlist_filters_by_glob_substring_and_regex(): void
    {
        $allowlist = SpanishDetector::parseAllowlist(<<<'TXT'
# comment
config/sintomas.php | * | symptom synonyms
app/Services/Ia/*.php | /^Dolor/ | regex
app/Livewire/**/*.php | Capitán | substring
TXT);
        $detector = $this->detector($allowlist);

        $this->assertCount(3, $allowlist);
        $this->assertSame([], $detector->scan('config/sintomas.php', "<?php\nreturn ['x' => 'Dolor de cabeza'];\n"));
        $this->assertSame([], $detector->scan('app/Services/Ia/Foo.php', "<?php\n\$a = 'Dolor de cabeza';\n"));
        $this->assertNotSame([], $detector->scan('app/Services/Ia/Foo.php', "<?php\n\$a = 'Guardar cambios';\n"));
        $this->assertSame([], $detector->scan('app/Livewire/Sub/Foo.php', "<?php\n\$a = 'Capitán Ríos';\n"));
        $this->assertNotSame([], $detector->scan('config/otro.php', "<?php\nreturn ['x' => 'Dolor de cabeza'];\n"));
    }

    public function test_rendered_html_checks_visible_text_and_accessible_attributes(): void
    {
        $html = '<html><head><title>Panel</title><script>var x = "Hola mundo de prueba";</script></head>'
            .'<body><h1>Crew status</h1><p>Última medición</p><img alt="Silueta del cuerpo" src="a.png"><button aria-label="Cerrar">x</button></body></html>';
        $texts = $this->texts($this->detector()->scanRenderedHtml('render:/panel', $html));

        $this->assertEqualsCanonicalizing(['Última medición', 'Silueta del cuerpo', 'Cerrar'], $texts);
    }

    public function test_scan_project_discovers_files_and_classifies_areas(): void
    {
        $dir = sys_get_temp_dir().'/i18n_audit_'.uniqid();
        mkdir($dir.'/resources/views', 0777, true);
        file_put_contents($dir.'/resources/views/a.blade.php', '<p>Guardar cambios</p><p>Saved</p>');

        $findings = $this->detector()->scanProject($dir);

        $this->assertSame(['Guardar cambios'], $this->texts($findings));
        $this->assertSame('views', $this->detector()->area($findings[0]['file']));

        unlink($dir.'/resources/views/a.blade.php');
        rmdir($dir.'/resources/views');
        rmdir($dir.'/resources');
        rmdir($dir);
    }

    public function test_command_outputs_json_summary_and_exit_code_matches_findings(): void
    {
        $code = Artisan::call('i18n:audit', ['--json' => true]);
        $data = json_decode(Artisan::output(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('by_area', $data);
        $this->assertSame($data['total'] === 0 ? 0 : 1, $code);
    }

    public function test_render_mode_requests_the_named_routes(): void
    {
        $code = Artisan::call('i18n:audit', ['--render' => true, '--json' => true]);
        $data = json_decode(Artisan::output(), true);

        $this->assertContains($code, [0, 1]);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('render_errors', $data);
    }

    /**
     * @group i18n-final
     *
     * Final gate: the whole repository (and the rendered pages) must be free of visible Spanish.
     * Run at the end of the translation with `php artisan test --group=i18n-final`.
     */
    public function test_repository_has_no_visible_spanish(): void
    {
        $code = Artisan::call('i18n:audit', ['--render' => true]);

        $this->assertSame(0, $code, Artisan::output());
    }
}
