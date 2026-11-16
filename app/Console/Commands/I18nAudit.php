<?php

namespace App\Console\Commands;

use App\Models\Astronauta;
use App\Services\I18n\SpanishDetector;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Fails (exit 1) while visible Spanish remains in the project. See docs/GLOSSARY_EN.md and i18n-allowlist.txt.
 *
 * `--render` requests every named GET route through the HTTP kernel and checks the visible text of the HTML
 * (it reads the configured database: run it against the test database, e.g. DB_DATABASE=healts_nasa_test).
 */
class I18nAudit extends Command
{
    protected $signature = 'i18n:audit {--render} {--json}';

    protected $description = 'Fail if Spanish text is still visible in the UI, tests, config, seeders or knowledge files';

    public function handle(): int
    {
        $detector = SpanishDetector::fromAllowlistFile(base_path('i18n-allowlist.txt'));
        $findings = $detector->scanProject(base_path());
        $errors = [];

        if ($this->option('render')) {
            [$rendered, $errors] = $this->renderRoutes($detector);
            $findings = array_merge($findings, $rendered);
        }

        $byArea = [];
        $byFile = [];
        foreach ($findings as $f) {
            $area = $detector->area($f['file']);
            $byArea[$area] = ($byArea[$area] ?? 0) + 1;
            $byFile[$f['file']][] = $f;
        }
        ksort($byArea);
        ksort($byFile);

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => count($findings),
                'by_area' => $byArea,
                'files' => array_map('count', $byFile),
                'findings' => $findings,
                'render_errors' => $errors,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $findings === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($byFile as $file => $list) {
            $this->line($file.' ('.count($list).')');
            foreach ($list as $f) {
                $this->line(sprintf('  %s:%d: «%s»', $file, $f['line'], mb_strimwidth($f['text'], 0, 120, '…')));
            }
        }
        foreach ($errors as $e) {
            $this->warn('render skipped: '.$e);
        }
        $this->newLine();
        $this->line('By area:');
        foreach ($byArea as $area => $n) {
            $this->line(sprintf('  %-14s %d', $area, $n));
        }
        $this->line('Total findings: '.count($findings).' in '.count($byFile).' file(s)');

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: array<int, array{file: string, line: int, text: string, reason: string}>, 1: array<int, string>} */
    private function renderRoutes(SpanishDetector $detector): array
    {
        config(['mision.exigir_login' => false]);
        $first = Astronauta::query()->orderBy('id')->first();
        $kernel = app(Kernel::class);
        $findings = [];
        $errors = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! in_array('GET', $route->methods(), true) || str_starts_with($name, 'livewire.') || str_starts_with($name, 'ignition.') || $name === 'sanctum.csrf-cookie') {
                continue;
            }
            $uri = $route->uri();
            if (preg_match('/\{[^}?]+\}/', $uri)) {
                if (! $first) {
                    $errors[] = "{$name} (no demo crew member)";

                    continue;
                }
                $uri = preg_replace('/\{[^}?]+\??\}/', (string) $first->getKey(), $uri);
            }
            $uri = preg_replace('/\/\{[^}]+\?\}/', '', $uri);

            try {
                $response = $kernel->handle(Request::create('/'.ltrim($uri, '/'), 'GET'));
                $status = $response->getStatusCode();
            } catch (\Throwable $e) {
                $errors[] = "{$name}: ".$e->getMessage();

                continue;
            }
            if ($status >= 400) {
                $errors[] = "{$name}: HTTP {$status}";

                continue;
            }
            if (! str_contains((string) $response->headers->get('Content-Type'), 'html')) {
                continue;
            }
            foreach ($detector->scanRenderedHtml('render:/'.ltrim($uri, '/'), (string) $response->getContent()) as $f) {
                $findings[] = $f;
            }
        }

        return [$findings, $errors];
    }
}
