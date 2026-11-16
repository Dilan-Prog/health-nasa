<?php

namespace App\Services\Ia;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lee y valida las fichas de síntoma (conocimiento/sintomas/<slug>.md): frontmatter YAML y un cuerpo libre opcional (notas).
 * Lanza RuntimeException con el motivo exacto si algo falta: una ficha incompleta nunca llega a la orientación.
 * (LectorFichas exige las cuatro secciones de los patrones y el campo 'patron', por eso aquí hay un parser propio pequeño.)
 */
class LectorFichasSintomas
{
    public const URGENCIAS = ['emergencia', 'urgente', 'autocuidado', 'observacion'];

    public const ORIGENES = ['documento', 'ejemplo_simulado'];

    public const TIPOS = ['sintoma', 'problema'];

    /** @var array<string, array>|null fichas por slug (caché estática de la petición) */
    private static ?array $cache = null;

    /** @return array<string, array> todas las fichas, por slug */
    public static function todas(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $fichas = [];
        $archivos = glob(self::carpeta().DIRECTORY_SEPARATOR.'*.md') ?: [];
        sort($archivos);
        foreach ($archivos as $ruta) {
            $ficha = self::desdeTexto((string) file_get_contents($ruta), $ruta);
            if ($ficha['slug'] !== basename($ruta, '.md')) {
                throw new RuntimeException("La ficha {$ruta} declara el slug '{$ficha['slug']}', distinto del nombre del archivo.");
            }
            $fichas[$ficha['slug']] = $ficha;
        }

        return self::$cache = $fichas;
    }

    public static function ficha(string $slug): array
    {
        $todas = self::todas();
        if (! isset($todas[$slug])) {
            throw new RuntimeException("Falta la ficha de síntoma '{$slug}' en ".self::carpeta().'.');
        }

        return $todas[$slug];
    }

    /** @return array<int, array> fichas cuyo `sintomas_clave` incluye la clave del analizador */
    public static function porSintoma(string $clave): array
    {
        return array_values(array_filter(self::todas(), fn (array $f) => in_array($clave, $f['sintomas_clave'], true)));
    }

    public static function carpeta(): string
    {
        return base_path('conocimiento'.DIRECTORY_SEPARATOR.'sintomas');
    }

    public static function limpiarCache(): void
    {
        self::$cache = null;
    }

    public static function desdeTexto(string $texto, string $ruta): array
    {
        $texto = str_replace("\r\n", "\n", ltrim($texto, "\u{FEFF}"));
        if (! preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $texto, $m)) {
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

        foreach (['slug', 'titulo'] as $campo) {
            if (! isset($meta[$campo]) || ! is_string($meta[$campo]) || trim($meta[$campo]) === '') {
                throw new RuntimeException("Falta el campo '{$campo}' en el frontmatter de {$ruta}.");
            }
        }
        self::enumerado($meta, 'tipo', self::TIPOS, $ruta);
        self::enumerado($meta, 'urgencia_base', self::URGENCIAS, $ruta);
        self::enumerado($meta, 'origen', self::ORIGENES, $ruta);
        if (($meta['revision_medica'] ?? null) !== 'pendiente') {
            throw new RuntimeException("revision_medica de {$ruta} debe ser 'pendiente' hasta que lo valide un médico.");
        }

        // Listas de textos. Pueden estar vacías salvo las que la ficha necesita para existir.
        $minimo = ['sintomas_clave' => 1, 'fuentes' => 1, 'escalar_si' => 1];
        foreach (['sintomas_clave', 'regiones', 'banderas_rojas', 'grupos_botiquin_permitidos', 'escalar_si', 'fuentes'] as $campo) {
            $lista = $meta[$campo] ?? null;
            if (! is_array($lista) || array_filter($lista, fn ($v) => ! is_string($v) || trim($v) === '') !== []) {
                throw new RuntimeException("El campo '{$campo}' de {$ruta} debe ser una lista de textos.");
            }
            if (count($lista) < ($minimo[$campo] ?? 0)) {
                throw new RuntimeException("El campo '{$campo}' de {$ruta} no puede estar vacío.");
            }
        }

        $acciones = $meta['acciones'] ?? null;
        if (! is_array($acciones) || $acciones === []) {
            throw new RuntimeException("El campo 'acciones' de {$ruta} debe ser una lista no vacía.");
        }
        foreach ($acciones as $a) {
            if (! is_array($a)) {
                throw new RuntimeException("Una acción de {$ruta} no es un mapa (id, texto, fuente).");
            }
            foreach (['id', 'texto'] as $campo) {
                if (! isset($a[$campo]) || ! is_string($a[$campo]) || trim($a[$campo]) === '') {
                    throw new RuntimeException("Una acción de {$ruta} no tiene '{$campo}'.");
                }
            }
            $f = $a['fuente'] ?? null;
            if (! is_array($f) || trim((string) ($f['etiqueta'] ?? '')) === '' || trim((string) ($f['texto'] ?? '')) === ''
                || ! in_array($f['origen'] ?? null, self::ORIGENES, true)) {
                throw new RuntimeException("La acción '{$a['id']}' de {$ruta} necesita fuente con etiqueta, texto y origen (documento|ejemplo_simulado).");
            }
        }

        $seguimiento = $meta['seguimiento'] ?? null;
        if (! is_array($seguimiento) || $seguimiento === []) {
            throw new RuntimeException("El campo 'seguimiento' de {$ruta} debe ser una lista no vacía.");
        }
        foreach ($seguimiento as $s) {
            if (! is_array($s) || ! isset($s['texto']) || ! is_string($s['texto']) || trim($s['texto']) === ''
                || (isset($s['indicador']) && ! is_string($s['indicador']))) {
                throw new RuntimeException("Un elemento de 'seguimiento' de {$ruta} necesita 'texto' (e 'indicador' opcional).");
            }
        }

        return [
            'slug' => $meta['slug'],
            'tipo' => $meta['tipo'],
            'titulo' => $meta['titulo'],
            'sintomas_clave' => array_values($meta['sintomas_clave']),
            'regiones' => array_values($meta['regiones']),
            'banderas_rojas' => array_values($meta['banderas_rojas']),
            'urgencia_base' => $meta['urgencia_base'],
            'grupos_botiquin_permitidos' => array_values($meta['grupos_botiquin_permitidos']),
            'acciones' => array_values(array_map(fn ($a) => [
                'id' => $a['id'], 'texto' => $a['texto'],
                'fuente' => ['etiqueta' => $a['fuente']['etiqueta'], 'texto' => $a['fuente']['texto'], 'origen' => $a['fuente']['origen']],
            ], $acciones)),
            'seguimiento' => array_values(array_map(fn ($s) => ['texto' => $s['texto'], 'indicador' => $s['indicador'] ?? null], $seguimiento)),
            'escalar_si' => array_values($meta['escalar_si']),
            'revision_medica' => $meta['revision_medica'],
            'origen' => $meta['origen'],
            'fuentes' => array_values($meta['fuentes']),
            'notas' => trim($m[2]),
        ];
    }

    private static function enumerado(array $meta, string $campo, array $validos, string $ruta): void
    {
        if (! in_array($meta[$campo] ?? null, $validos, true)) {
            throw new RuntimeException("El campo '{$campo}' de {$ruta} debe ser uno de: ".implode(', ', $validos).'.');
        }
    }
}
