<?php

namespace App\Services\I18n;

/**
 * Detects visible Spanish left in the project (the UI is US English only).
 *
 * Application code is checked by string LITERALS only (never comments or identifiers); tests are
 * checked more strictly (comments, docblocks, class and method names too). Detection is heuristic:
 * accented characters / inverted punctuation, Spanish function words (several in one string, or one
 * strong word in a longer string), a list of Spanish interface words and a small Spanish vocabulary.
 * Known false positives are silenced through the allowlist file `i18n-allowlist.txt`.
 */
class SpanishDetector
{
    /** Characters that practically only occur in Spanish text in this project. */
    private const ACCENTS = '/[áéíóúñÁÉÍÓÚÑ¿¡]/u';

    /** Spanish interface words (single labels). Ambiguous English words ("Error", "Normal"...) are excluded on purpose. */
    private const UI_WORDS = [
        'guardar', 'cancelar', 'enviar', 'cerrar', 'volver', 'siguiente', 'anterior', 'buscar', 'todos', 'todas',
        'hoy', 'ayer', 'cargando', 'aceptar', 'editar', 'eliminar', 'añadir', 'agregar', 'borrar', 'confirmar',
        'continuar', 'inicio', 'ayuda', 'salir', 'aplicar', 'limpiar', 'actualizar', 'abrir', 'mostrar', 'ocultar',
        'cambios', 'guardado', 'cerrado', 'nuevo', 'nueva', 'sí', 'tripulación', 'tripulante', 'alertas', 'registrar',
    ];

    /** Spanish words that are never English: one hit in a string of 3+ words is enough. */
    private const STRONG = [
        'del', 'una', 'unos', 'unas', 'está', 'están', 'para', 'sobre', 'tus', 'sus', 'que', 'por', 'desde', 'hasta',
        'cuando', 'también', 'más', 'pero', 'muy', 'esta', 'este', 'estos', 'estas', 'entre', 'según', 'sino',
        'porque', 'donde', 'cada', 'otro', 'otra',
    ];

    /** Spanish function words that can also be English words or fragments: two hits are required. */
    private const WEAK = [
        'de', 'la', 'el', 'los', 'las', 'un', 'con', 'sin', 'son', 'tu', 'su', 'mi', 'y', 'en', 'es', 'se', 'lo', 'al', 'no',
    ];

    /** Small Spanish vocabulary of the domain (identifier words in tests, short labels). No English homographs. */
    private const VOCAB = [
        'tripulante', 'tripulantes', 'tripulacion', 'tripulación', 'alerta', 'alertas', 'expediente', 'ficha', 'mision', 'misión',
        'medico', 'médico', 'vuelo', 'dolor', 'fiebre', 'tos', 'sueno', 'sueño', 'sangre', 'oxigeno', 'oxígeno', 'saturacion',
        'frecuencia', 'cardiaca', 'cardíaca', 'respiratoria', 'presion', 'presión', 'arterial', 'hoy', 'ayer', 'manana', 'mañana',
        'semana', 'dias', 'días', 'dia', 'día', 'horas', 'valores', 'resumen', 'historial', 'rutina', 'rutinas',
        'requisitos', 'guia', 'guía', 'registrar', 'registro', 'guardar', 'cancelar', 'enviar', 'cerrar', 'volver', 'siguiente',
        'buscar', 'muestra', 'muestran', 'mostrar', 'devuelve', 'devolver', 'crea', 'crear', 'calcula', 'calcular', 'aparece',
        'existe', 'tiene', 'tienen', 'falta', 'ningun', 'ninguno', 'ninguna', 'debe', 'deben', 'puede', 'pueden', 'nuevo', 'nueva',
        'antes', 'despues', 'después', 'siempre', 'nunca', 'todo', 'toda', 'todos', 'todas', 'caso', 'casos', 'usuario',
        'contrasena', 'contraseña', 'acceso', 'sesion', 'sesión', 'orientador', 'botiquin', 'botiquín', 'sintomas', 'síntomas',
        'sintoma', 'síntoma', 'consulta', 'consultas', 'cuando', 'cuándo', 'cabeza', 'estomago', 'estómago', 'garganta',
        'mareo', 'nauseas', 'náuseas', 'gravedad', 'salud', 'tratamiento', 'tratamientos', 'medicamento', 'medicamentos',
        'recomendacion', 'recomendaciones', 'evidencia', 'fuentes', 'confianza', 'empeoro', 'empeoró', 'astronauta', 'astronautas',
        'cuenta', 'datos', 'aviso', 'mediciones', 'medicion', 'sistema', 'sistemas', 'corazon', 'corazón', 'pulmon',
        'pulmón', 'rinon', 'riñón', 'hueso', 'huesos', 'musculo', 'músculo', 'cerebro', 'ojos', 'oidos', 'oídos',
    ];

    /** @var array<int, array{path: string, pattern: string, reason: string}> */
    private array $allowlist;

    /** @var array<string, true> */
    private array $uiSet;

    /** @var array<string, true> */
    private array $strongSet;

    /** @var array<string, true> */
    private array $weakSet;

    /** @var array<string, true> */
    private array $vocabSet;

    /**
     * @param  array<int, array{path: string, pattern: string, reason: string}>  $allowlist
     */
    public function __construct(array $allowlist = [])
    {
        $this->allowlist = $allowlist;
        $this->uiSet = array_fill_keys(self::UI_WORDS, true);
        $this->strongSet = array_fill_keys(self::STRONG, true);
        $this->weakSet = array_fill_keys(self::WEAK, true);
        $this->vocabSet = array_fill_keys(self::VOCAB, true);
    }

    // ------------------------------------------------------------------ allowlist

    /**
     * Allowlist file format, one entry per line: `path-or-glob | pattern | reason`.
     * Pattern: `*` (everything in the file), `/regex/` or a plain substring of the finding. `#` starts a comment.
     *
     * @return array<int, array{path: string, pattern: string, reason: string}>
     */
    public static function parseAllowlist(string $contents): array
    {
        $entries = [];
        foreach (preg_split('/\R/', $contents) as $raw) {
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = array_map('trim', explode(' | ', $line, 3));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $entries[] = ['path' => str_replace('\\', '/', $parts[0]), 'pattern' => $parts[1], 'reason' => $parts[2] ?? ''];
        }

        return $entries;
    }

    public static function fromAllowlistFile(string $path): self
    {
        return new self(is_file($path) ? self::parseAllowlist((string) file_get_contents($path)) : []);
    }

    public function isAllowed(string $file, string $text): bool
    {
        foreach ($this->allowlist as $entry) {
            if (! $this->globMatches($entry['path'], $file)) {
                continue;
            }
            $p = $entry['pattern'];
            if ($p === '*') {
                return true;
            }
            if (strlen($p) > 2 && $p[0] === '/' && preg_match('#^/.+/[a-z]*$#', $p)) {
                if (@preg_match($p, $text) === 1) {
                    return true;
                }

                continue;
            }
            if (str_contains($text, $p)) {
                return true;
            }
        }

        return false;
    }

    private function globMatches(string $glob, string $file): bool
    {
        $re = preg_quote($glob, '#');
        $re = str_replace(['\*\*/', '\*\*', '\*', '\?'], ['(?:.*/)?', '.*', '[^/]*', '[^/]'], $re);

        return preg_match('#^'.$re.'$#', str_replace('\\', '/', $file)) === 1;
    }

    // ------------------------------------------------------------------ text heuristic

    /**
     * Returns the reason when the text looks Spanish, or null.
     * Modes: `text` (visible string or comment line), `name` (words split from an identifier).
     */
    public function isSpanish(string $text, string $mode = 'text'): ?string
    {
        $t = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($t === '') {
            return null;
        }
        if (preg_match(self::ACCENTS, $t)) {
            return 'accent';
        }
        $t = trim(preg_replace('/(?<![\w-])--[\w-]+/', '', $t)); // command options (--days) are identifiers
        if (! preg_match_all('/[A-Za-z]+/', $t, $m)) {
            return null;
        }
        $words = array_map('strtolower', $m[0]);

        if ($mode === 'name') {
            return $this->scoreName($words);
        }

        // Code-looking strings (SQL, snake_case columns, command signatures, member access) are not prose.
        if (preg_match('/\w+_\w+|->|::|\$\w|\{--|^[a-z]+(:[a-z-]+)+\b/', $t)) {
            return null;
        }

        // Without whitespace it is an identifier, key, URL or path, unless it is a capitalized UI word ("Guardar…").
        if (! preg_match('/\s/u', $t)) {
            if (preg_match('/^[A-Z][a-z]+[\p{P}…]*$/u', $t) && isset($this->uiSet[$words[0]]) && $words[0] !== 'nueva' && $words[0] !== 'nuevo') {
                return 'interface word';
            }

            return null;
        }

        $n = count($words);
        $strong = 0;
        $weak = 0;
        $ui = 0;
        $vocab = 0;
        foreach (array_unique($words) as $w) {
            $strong += isset($this->strongSet[$w]) ? 1 : 0;
            $weak += isset($this->weakSet[$w]) ? 1 : 0;
            $ui += isset($this->uiSet[$w]) ? 1 : 0;
            $vocab += isset($this->vocabSet[$w]) ? 1 : 0;
        }

        if ($n <= 4 && ($ui + $vocab) >= 1 && ($ui + $vocab + $strong + $weak) >= ($n >= 3 ? 2 : 1)) {
            return 'interface word';
        }
        if ($strong + $weak >= 2 || ($strong >= 1 && $n >= 3)) {
            return 'function words';
        }
        if ($vocab >= 2 || ($vocab >= 1 && $strong + $weak >= 1)) {
            return 'Spanish vocabulary';
        }

        return null;
    }

    /** @param  array<int, string>  $words */
    private function scoreName(array $words): ?string
    {
        $weak = 0;
        foreach (array_unique($words) as $w) {
            if (isset($this->strongSet[$w]) || isset($this->vocabSet[$w]) || isset($this->uiSet[$w])) {
                return 'Spanish word in name';
            }
            $weak += isset($this->weakSet[$w]) ? 1 : 0;
        }

        return $weak >= 2 ? 'Spanish function words in name' : null;
    }

    /** "itShowsLaFicha_del_tripulante" -> ["it","shows","la","ficha","del","tripulante"]. */
    public static function splitName(string $name): array
    {
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $name);

        return array_values(array_filter(preg_split('/[^A-Za-z]+/', $name), fn ($w) => $w !== ''));
    }

    // ------------------------------------------------------------------ file scanning

    public function area(string $file): string
    {
        $f = str_replace('\\', '/', $file);

        return match (true) {
            str_starts_with($f, 'tests/') => 'tests',
            str_starts_with($f, 'resources/views/') => 'views',
            str_starts_with($f, 'resources/js/') => 'js',
            str_starts_with($f, 'config/') => 'config',
            str_starts_with($f, 'database/seeders/') => 'seeders',
            str_starts_with($f, 'app/Services/') => 'services',
            str_starts_with($f, 'app/Livewire/') => 'livewire',
            str_starts_with($f, 'app/Http/') => 'http',
            str_starts_with($f, 'app/Console/') => 'console',
            str_starts_with($f, 'conocimiento/') => 'conocimiento',
            str_starts_with($f, 'public/') => 'public',
            str_starts_with($f, 'render:') => 'render',
            default => 'other',
        };
    }

    /**
     * Relative paths (forward slashes) of the files in scope.
     *
     * @return array<int, string>
     */
    public function discoverFiles(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $out = [];
        $walk = function (string $dir, array $exts) use ($root, &$out) {
            if (! is_dir($root.'/'.$dir)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile()) {
                    continue;
                }
                $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
                if (preg_match('#(^|/)(node_modules|vendor)/#', $rel) || str_ends_with($rel, '.min.js')) {
                    continue;
                }
                $name = $f->getFilename();
                foreach ($exts as $e) {
                    if (str_ends_with($name, $e)) {
                        $out[] = $rel;
                        break;
                    }
                }
            }
        };
        $walk('resources/views', ['.php']);
        $walk('resources/js', ['.js', '.mjs', '.ts', '.jsx']);
        $walk('config', ['.php']);
        $walk('database/seeders', ['.php']);
        foreach (['app/Services', 'app/Livewire', 'app/Http', 'app/Console'] as $d) {
            $walk($d, ['.php']);
        }
        $walk('conocimiento', ['.md', '.txt', '.json', '.html']);
        $walk('tests', ['.php', '.mjs', '.js']);
        foreach (glob($root.'/public/*.html') ?: [] as $h) {
            $out[] = 'public/'.basename($h);
        }
        if (is_file($root.'/public/manifest.webmanifest')) {
            $out[] = 'public/manifest.webmanifest';
        }
        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * Scans every file of the project and returns the findings that are not allowlisted.
     *
     * @return array<int, array{file: string, line: int, text: string, reason: string}>
     */
    public function scanProject(string $root): array
    {
        $all = [];
        foreach ($this->discoverFiles($root) as $rel) {
            $content = @file_get_contents(rtrim($root, '/\\').'/'.$rel);
            if ($content === false) {
                continue;
            }
            foreach ($this->scan($rel, $content) as $f) {
                $all[] = $f;
            }
        }

        return $all;
    }

    /**
     * Scans one file's content (the allowlist is applied).
     *
     * @return array<int, array{file: string, line: int, text: string, reason: string}>
     */
    public function scan(string $relPath, string $content): array
    {
        $rel = str_replace('\\', '/', $relPath);
        $isTest = str_starts_with($rel, 'tests/') || (bool) preg_match('/\.test\.(m?js|ts)$/', $rel);
        $content = str_replace("\r\n", "\n", $content);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $items = match (true) {
            str_ends_with($rel, '.blade.php') => $this->bladeItems($content, $isTest),
            str_ends_with($rel, '.php') => $this->phpItems($content, $isTest),
            (bool) preg_match('/\.(m?js|ts|jsx)$/', $rel) => $this->jsItems($content, 1, $isTest),
            str_ends_with($rel, '.html') => $this->htmlItems($content),
            str_ends_with($rel, '.json') || str_ends_with($rel, '.webmanifest') => $this->jsonItems($content),
            default => $this->proseItems($content),
        };

        return $this->evaluate($rel, $items);
    }

    /**
     * Visible text of an already rendered HTML page (used by `--render`).
     *
     * @return array<int, array{file: string, line: int, text: string, reason: string}>
     */
    public function scanRenderedHtml(string $label, string $html): array
    {
        return $this->evaluate($label, $this->htmlItems($html));
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: string}>  $items  [line, text, mode]
     * @return array<int, array{file: string, line: int, text: string, reason: string}>
     */
    private function evaluate(string $file, array $items): array
    {
        $out = [];
        foreach ($items as [$line, $text, $mode]) {
            $reason = $this->isSpanish($text, $mode);
            if ($reason === null || $this->isAllowed($file, $text)) {
                continue;
            }
            $out[] = ['file' => $file, 'line' => $line, 'text' => trim(preg_replace('/\s+/u', ' ', $text)), 'reason' => $reason];
        }

        return $out;
    }

    // ------------------------------------------------------------------ extractors (each returns [line, text, mode])

    /** @return array<int, array{0: int, 1: string, 2: string}> */
    private function phpItems(string $code, bool $isTest, int $lineOffset = 0, bool $wrapped = false): array
    {
        $tokens = @token_get_all($wrapped ? '<?php '.$code : $code);
        $items = [];
        $count = count($tokens);
        $sig = function (int $from, int $dir) use ($tokens, $count) {
            for ($j = $from; $j >= 0 && $j < $count; $j += $dir) {
                $tk = $tokens[$j];
                if (is_array($tk) && in_array($tk[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                return $tk;
            }

            return null;
        };

        for ($i = 0; $i < $count; $i++) {
            $tk = $tokens[$i];
            if (! is_array($tk)) {
                continue;
            }
            [$id, $text, $line] = $tk;
            $line += $lineOffset;
            switch ($id) {
                case T_CONSTANT_ENCAPSED_STRING:
                    $value = $this->unquotePhp($text);
                    $next = $sig($i + 1, 1);
                    $isKey = is_array($next) && $next[0] === T_DOUBLE_ARROW;
                    if ($isKey && ! preg_match('/[\s]/u', $value) && ! preg_match(self::ACCENTS, $value)) {
                        break;
                    }
                    $items[] = [$line, $value, 'text'];
                    break;
                case T_ENCAPSED_AND_WHITESPACE:
                    foreach (explode("\n", stripcslashes($text)) as $k => $part) {
                        if (trim($part) !== '') {
                            $items[] = [$line + $k, $part, 'text'];
                        }
                    }
                    break;
                case T_INLINE_HTML:
                    foreach ($this->htmlItems($text, $line) as $it) {
                        $items[] = $it;
                    }
                    break;
                case T_COMMENT:
                case T_DOC_COMMENT:
                    if ($isTest) {
                        foreach (explode("\n", $text) as $k => $part) {
                            $part = trim(preg_replace('#^\s*(/\*+|\*+/|\*|//+|\#)#', '', trim($part)), " \t*/");
                            if ($part !== '') {
                                $items[] = [$line + $k, $part, 'text'];
                            }
                        }
                    }
                    break;
                case T_FUNCTION:
                case T_CLASS:
                case T_TRAIT:
                case T_INTERFACE:
                    if ($isTest) {
                        $nx = $sig($i + 1, 1);
                        if ($id === T_FUNCTION && $nx === '&') {
                            $nx = $sig($i + 2, 1);
                        }
                        if (is_array($nx) && $nx[0] === T_STRING) {
                            $items[] = [$nx[2] + $lineOffset, implode(' ', self::splitName($nx[1])), 'name'];
                        }
                    }
                    break;
            }
        }

        return $items;
    }

    private function unquotePhp(string $literal): string
    {
        $q = $literal[0];
        $body = substr($literal, 1, -1);
        if ($q === "'") {
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $body);
        }
        $body = preg_replace_callback('/\\\\u\{([0-9a-fA-F]+)\}/', fn ($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $body);

        return stripcslashes($body);
    }

    /**
     * Blade: comments, directives and tag attributes are masked/handled, PHP strings inside echoes and
     * @php blocks come from the PHP tokenizer, <script> blocks from the JS tokenizer, the rest is HTML text.
     *
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    private function bladeItems(string $src, bool $isTest): array
    {
        $items = [];
        $mask = fn (string $s) => preg_replace('/[^\n]/u', ' ', $s);
        $lineAt = fn (string $full, int $offset) => substr_count($full, "\n", 0, $offset) + 1;

        // 1. Blade comments.
        $src = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($m) => $mask($m[0]), $src);

        // 2. Raw PHP tags and @php ... @endphp blocks (strings only).
        $php = function (string $re, int $group) use (&$src, &$items, $mask, $lineAt, $isTest) {
            $copy = $src;
            $src = preg_replace_callback($re, function ($m) use ($copy, &$items, $mask, $lineAt, $group) {
                $offset = strpos($copy, $m[0]);
                foreach ($this->phpItems($m[$group], false, $lineAt($copy, (int) $offset) - 1, true) as $it) {
                    $items[] = $it;
                }

                return $mask($m[0]);
            }, $src);
        };
        $php('/<\?php(.*?)\?>/s', 1);
        $php('/@php\b(?!\s*\()(.*?)@endphp/s', 1);

        // 3. <script> (JS strings) and <style> (ignored).
        $copy = $src;
        $src = preg_replace_callback('#<script\b[^>]*>(.*?)</script>#si', function ($m) use ($copy, &$items, $mask, $lineAt, $isTest) {
            $offset = (int) strpos($copy, $m[1]);
            foreach ($this->jsItems($m[1], $lineAt($copy, $offset), $isTest) as $it) {
                $items[] = $it;
            }

            return $mask($m[0]);
        }, $src);
        $src = preg_replace_callback('#<style\b[^>]*>.*?</style>#si', fn ($m) => $mask($m[0]), $src);

        // 4. Echoes {{ }} / {!! !!}: strings inside the expression.
        $copy = $src;
        $pos = 0;
        $src = preg_replace_callback('/\{!!(.*?)!!\}|\{\{(.*?)\}\}/s', function ($m) use ($copy, &$items, $mask, $lineAt, &$pos) {
            $expr = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
            $offset = (int) strpos($copy, $m[0], $pos);
            $pos = $offset + 1;
            foreach ($this->phpItems($expr, false, $lineAt($copy, $offset) - 1, true) as $it) {
                $items[] = $it;
            }

            return $mask($m[0]);
        }, $src);

        // 5. Directives with their arguments (balanced parentheses): strings inside the arguments.
        $copy = $src;
        $pos = 0;
        $src = preg_replace_callback('/(?<![\w@])@(?!media\b|keyframes\b|import\b)([A-Za-z]\w*)(\s*(\((?:[^()]++|(?3))*\)))?/', function ($m) use ($copy, &$items, $mask, $lineAt, &$pos) {
            $offset = (int) strpos($copy, $m[0], $pos);
            $pos = $offset + 1;
            if (isset($m[3]) && $m[3] !== '' && ! in_array($m[1], ['foreach', 'for', 'while', 'forelse', 'isset', 'empty'], true)) {
                foreach ($this->phpItems(substr($m[3], 1, -1), false, $lineAt($copy, $offset) - 1, true) as $it) {
                    $items[] = $it;
                }
            }

            return $mask($m[0]);
        }, $src);

        foreach ($this->htmlItems($src) as $it) {
            $items[] = $it;
        }

        return $items;
    }

    /**
     * Visible text of an HTML page: text nodes plus aria-label, title, placeholder, alt; every plain attribute of
     * components (<x-...>, <livewire:...>) and the strings of Alpine/bound attributes. Ignores <script>/<style>/comments.
     *
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    private function htmlItems(string $html, int $firstLine = 1): array
    {
        $items = [];
        $mask = fn (string $s) => preg_replace('/[^\n]/u', ' ', $s);
        $html = preg_replace_callback('/<!--.*?-->/s', fn ($m) => $mask($m[0]), $html);
        $scripts = [];
        $html = preg_replace_callback('#<script\b[^>]*>(.*?)</script>#si', function ($m) use (&$scripts, $mask, $html) {
            $scripts[] = [substr_count(substr($html, 0, (int) strpos($html, $m[0])), "\n"), $m[1]];

            return $mask($m[0]);
        }, $html);
        $html = preg_replace_callback('#<style\b[^>]*>.*?</style>#si', fn ($m) => $mask($m[0]), $html);

        $parts = preg_split('/(<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        foreach ($parts as [$chunk, $offset]) {
            $line = $firstLine + substr_count($html, "\n", 0, $offset);
            if ($chunk === '') {
                continue;
            }
            if ($chunk[0] !== '<') {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($chunk, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($text !== '' && preg_match('/\p{L}/u', $text)) {
                    $items[] = [$line + (strlen($chunk) - strlen(ltrim($chunk)) > 0 ? substr_count(substr($chunk, 0, strlen($chunk) - strlen(ltrim($chunk))), "\n") : 0), $text, 'text'];
                }

                continue;
            }
            $isComponent = (bool) preg_match('/^<(x-|livewire:)/i', $chunk);
            if (preg_match_all('/([:@]?[\w:.\-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')/s', $chunk, $am, PREG_SET_ORDER)) {
                foreach ($am as $a) {
                    $name = $a[1];
                    $val = ($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? '');
                    $lname = strtolower($name);
                    if (in_array($lname, ['aria-label', 'title', 'placeholder', 'alt'], true) || (preg_match('/^(aria-(label|description)|data-(label|title|text))$/', $lname))) {
                        $items[] = [$line, html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'text'];
                    } elseif ($name[0] === ':' || str_starts_with($lname, 'x-text') || str_starts_with($lname, 'x-html') || str_starts_with($lname, 'x-bind')) {
                        $expr = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        foreach ($isComponent && $name[0] === ':' ? $this->phpItems($expr, false, $line - 1, true) : $this->jsItems($expr, $line, false) as $it) {
                            $items[] = $it;
                        }
                    } elseif ($isComponent && ! preg_match('/^(class|id|style|name|type|wire:|x-|@|key|href|src|target|rel)/', $lname)) {
                        $items[] = [$line, html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'text'];
                    }
                }
            }
        }
        foreach ($scripts as [$off, $js]) {
            foreach ($this->jsItems($js, $firstLine + $off, false) as $it) {
                $items[] = $it;
            }
        }

        return $items;
    }

    /** @return array<int, array{0: int, 1: string, 2: string}> */
    private function jsonItems(string $content): array
    {
        $items = [];
        foreach (explode("\n", $content) as $k => $row) {
            if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"(\s*:)?/u', $row, $m, PREG_SET_ORDER)) {
                foreach ($m as $s) {
                    if (! empty($s[2])) {
                        continue; // key
                    }
                    $val = json_decode('"'.$s[1].'"');
                    $items[] = [$k + 1, is_string($val) ? $val : $s[1], 'text'];
                }
            }
        }

        return $items;
    }

    /** Markdown/plain text knowledge files: every line is prose (front matter and code fences included). */
    private function proseItems(string $content): array
    {
        $items = [];
        foreach (explode("\n", $content) as $k => $row) {
            if (trim($row) !== '') {
                $items[] = [$k + 1, $row, 'text'];
            }
        }

        return $items;
    }

    /**
     * Small JS tokenizer: string literals and template literal text (comments too when $withComments).
     *
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    private function jsItems(string $code, int $firstLine = 1, bool $withComments = false): array
    {
        $out = [];
        $i = 0;
        $line = $firstLine;
        $this->jsScan($code, $i, $line, $out, false, $withComments);

        return $out;
    }

    private function jsScan(string $c, int &$i, int &$line, array &$out, bool $inInterp, bool $withComments): void
    {
        $n = strlen($c);
        $prev = '';
        $depth = 0;
        while ($i < $n) {
            $ch = $c[$i];
            if ($ch === "\n") {
                $line++;
                $i++;

                continue;
            }
            if ($ch === ' ' || $ch === "\t" || $ch === "\r") {
                $i++;

                continue;
            }
            $nx = $c[$i + 1] ?? '';
            if ($ch === '/' && $nx === '/') {
                $end = strpos($c, "\n", $i);
                $end = $end === false ? $n : $end;
                if ($withComments) {
                    $txt = trim(substr($c, $i + 2, $end - $i - 2));
                    if ($txt !== '') {
                        $out[] = [$line, $txt, 'text'];
                    }
                }
                $i = $end;

                continue;
            }
            if ($ch === '/' && $nx === '*') {
                $end = strpos($c, '*/', $i + 2);
                $end = $end === false ? $n : $end;
                $body = substr($c, $i + 2, $end - $i - 2);
                if ($withComments) {
                    foreach (explode("\n", $body) as $k => $part) {
                        $part = trim($part, " \t*\r");
                        if ($part !== '') {
                            $out[] = [$line + $k, $part, 'text'];
                        }
                    }
                }
                $line += substr_count($body, "\n");
                $i = $end + 2;

                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $start = $line;
                $buf = '';
                $i++;
                while ($i < $n && $c[$i] !== $ch && $c[$i] !== "\n") {
                    if ($c[$i] === '\\' && $i + 1 < $n) {
                        $buf .= $this->jsEscape($c, $i);

                        continue;
                    }
                    $buf .= $c[$i++];
                }
                $i++;
                $out[] = [$start, $buf, 'text'];
                $prev = '"';

                continue;
            }
            if ($ch === '`') {
                $i++;
                $buf = '';
                $start = $line;
                while ($i < $n && $c[$i] !== '`') {
                    if ($c[$i] === '\\' && $i + 1 < $n) {
                        $buf .= $this->jsEscape($c, $i);

                        continue;
                    }
                    if ($c[$i] === '$' && ($c[$i + 1] ?? '') === '{') {
                        $this->flushTemplate($buf, $start, $out);
                        $buf = '';
                        $i += 2;
                        $this->jsScan($c, $i, $line, $out, true, $withComments);
                        $start = $line;

                        continue;
                    }
                    if ($c[$i] === "\n") {
                        $this->flushTemplate($buf, $start, $out);
                        $buf = '';
                        $line++;
                        $start = $line;
                        $i++;

                        continue;
                    }
                    $buf .= $c[$i++];
                }
                $this->flushTemplate($buf, $start, $out);
                $i++;
                $prev = '"';

                continue;
            }
            if ($ch === '/' && ($prev === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $prev))) {
                // regular expression literal
                $i++;
                $inClass = false;
                while ($i < $n && $c[$i] !== "\n") {
                    if ($c[$i] === '\\') {
                        $i += 2;

                        continue;
                    }
                    if ($c[$i] === '[') {
                        $inClass = true;
                    } elseif ($c[$i] === ']') {
                        $inClass = false;
                    } elseif ($c[$i] === '/' && ! $inClass) {
                        break;
                    }
                    $i++;
                }
                $i++;
                $prev = 'n';

                continue;
            }
            if ($inInterp) {
                if ($ch === '{') {
                    $depth++;
                } elseif ($ch === '}') {
                    if ($depth === 0) {
                        $i++;

                        return;
                    }
                    $depth--;
                }
            }
            $prev = $ch;
            $i++;
        }
    }

    private function flushTemplate(string $buf, int $line, array &$out): void
    {
        if (trim($buf) !== '') {
            $out[] = [$line, $buf, 'text'];
        }
    }

    private function jsEscape(string $c, int &$i): string
    {
        $e = $c[$i + 1];
        $i += 2;
        if ($e === 'u') {
            if (($c[$i] ?? '') === '{' && ($end = strpos($c, '}', $i)) !== false) {
                $hex = substr($c, $i + 1, $end - $i - 1);
                $i = $end + 1;

                return ctype_xdigit($hex) ? mb_chr(hexdec($hex), 'UTF-8') : '';
            }
            $hex = substr($c, $i, 4);
            if (strlen($hex) === 4 && ctype_xdigit($hex)) {
                $i += 4;

                return mb_chr(hexdec($hex), 'UTF-8');
            }

            return '';
        }
        if ($e === 'x') {
            $hex = substr($c, $i, 2);
            $i += 2;

            return ctype_xdigit($hex) ? mb_chr(hexdec($hex), 'UTF-8') : '';
        }

        return match ($e) {
            'n' => "\n",
            't' => "\t",
            'r' => '',
            default => $e,
        };
    }
}
