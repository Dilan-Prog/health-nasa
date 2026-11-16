<?php

namespace App\Services\Ia;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lee y valida las fichas de conocimiento (conocimiento/<slug>.md): frontmatter YAML y cuatro secciones fijas.
 * Lanza RuntimeException con el motivo exacto si falta algo: una ficha incompleta nunca llega a la orientación.
 */
class LectorFichas
{
    /** Clave devuelta => cabeceras aceptadas (la primera es la canónica en inglés; la segunda, el respaldo en español). */
    private const SECCIONES = [
        'senales' => ['Signs to watch', 'Señales a vigilar'],
        'chequear' => ['What to check', 'Qué chequear'],
        'escalar' => ['When to escalate', 'Cuándo escalar'],
        'limites' => ['Limits of what can be stated', 'Límites de lo que puede afirmarse'],
    ];

    /** @return array{slug:string, patron:string, titulo:string, fuentes:array<int,string>, revision_medica:string, senales:string, chequear:string, escalar:string, limites:string} */
    public static function ficha(string $patron): array
    {
        $slug = config("patrones.{$patron}.ficha") ?? $patron;
        $ruta = self::carpeta().DIRECTORY_SEPARATOR.$slug.'.md';
        if (! is_file($ruta)) {
            throw new RuntimeException("Falta la ficha de conocimiento '{$slug}' del patrón '{$patron}' ({$ruta}).");
        }

        $ficha = self::desdeTexto((string) file_get_contents($ruta), $ruta);
        if ($ficha['patron'] !== $patron) {
            throw new RuntimeException("La ficha {$ruta} declara el patrón '{$ficha['patron']}', no '{$patron}'.");
        }

        return $ficha;
    }

    /** @return array<string, array> fichas por clave de patrón (todas las de config/patrones.php) */
    public static function todas(): array
    {
        $fichas = [];
        foreach (array_keys((array) config('patrones')) as $patron) {
            $fichas[$patron] = self::ficha($patron);
        }

        return $fichas;
    }

    public static function carpeta(): string
    {
        return base_path('conocimiento');
    }

    public static function desdeTexto(string $texto, string $ruta): array
    {
        $texto = str_replace("\r\n", "\n", ltrim($texto, "\u{FEFF}"));
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $texto, $m)) {
            throw new RuntimeException("La ficha {$ruta} no tiene frontmatter YAML (bloque entre '---').");
        }

        try {
            $meta = Yaml::parse($m[1]);
        } catch (ParseException $e) {
            throw new RuntimeException("Frontmatter inválido en {$ruta}: ".$e->getMessage(), 0, $e);
        }
        if (! is_array($meta)) {
            throw new RuntimeException("Frontmatter inválido en {$ruta}: no es un mapa YAML.");
        }

        foreach (['slug', 'patron', 'titulo', 'revision_medica'] as $campo) {
            if (! isset($meta[$campo]) || ! is_string($meta[$campo]) || trim($meta[$campo]) === '') {
                throw new RuntimeException("Falta el campo '{$campo}' en el frontmatter de {$ruta}.");
            }
        }
        if (! isset($meta['fuentes']) || ! is_array($meta['fuentes']) || $meta['fuentes'] === []
            || array_filter($meta['fuentes'], fn ($f) => ! is_string($f) || trim($f) === '') !== []) {
            throw new RuntimeException("El campo 'fuentes' de {$ruta} debe ser una lista no vacía de textos.");
        }
        if ($meta['revision_medica'] !== 'pendiente') {
            throw new RuntimeException("revision_medica de {$ruta} debe ser 'pendiente' hasta que lo valide un médico.");
        }

        $secciones = self::secciones($m[2]);
        $ficha = [
            'slug' => $meta['slug'],
            'patron' => $meta['patron'],
            'titulo' => $meta['titulo'],
            'fuentes' => array_values($meta['fuentes']),
            'revision_medica' => $meta['revision_medica'],
        ];
        foreach (self::SECCIONES as $clave => $titulos) {
            $contenido = null;
            foreach ($titulos as $titulo) {
                if (isset($secciones[$titulo]) && trim($secciones[$titulo]) !== '') {
                    $contenido = $secciones[$titulo];
                    break;
                }
            }
            if ($contenido === null) {
                throw new RuntimeException("Falta la sección '{$titulos[0]}' / '{$titulos[1]}' (o está vacía) en {$ruta}.");
            }
            $ficha[$clave] = trim($contenido);
        }

        return $ficha;
    }

    /** @return array<string, string> título de sección '## ...' => contenido */
    private static function secciones(string $cuerpo): array
    {
        $partes = preg_split('/^## (.+)$/m', $cuerpo, -1, PREG_SPLIT_DELIM_CAPTURE);
        $secciones = [];
        for ($i = 1; $i < count($partes); $i += 2) {
            $secciones[trim($partes[$i])] = $partes[$i + 1] ?? '';
        }

        return $secciones;
    }
}
