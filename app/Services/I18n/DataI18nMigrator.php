<?php

namespace App\Services\I18n;

use Database\Seeders\ExpedicionSeeder;
use Database\Seeders\RequisitosSeeder;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;

/**
 * Updates IN PLACE (UPDATE of single columns, never delete/recreate) the system-generated Spanish text that is
 * already stored and visible on screen, using the explicit maps of DataTextMaps. Exact matching only.
 *
 * It never touches text written by users (symptoms, notes, consultas_sintomas, routine notes), proper names,
 * stored lookup keys (misiones.entorno, estado, accion, ...), misiones.nombre or botiquin_items.
 *
 * Idempotent: a value already translated matches nothing, so a second pass changes nothing.
 */
class DataI18nMigrator
{
    /** Chunk size when reading rows. */
    private const CHUNK = 500;

    /** @var array<string, array{table:string, column:string, key:?string, rows:int, scanned:int, example:?array{0:string,1:string}, unmapped:array<string,int>}> */
    private array $informe = [];

    /** @var array<string, string> old requisitos.texto => English text (by clave) */
    private ?array $textosRequisito = null;

    /** @var array<string, array<string, mixed>> seeder rows by clave */
    private ?array $filasSeeder = null;

    /**
     * Runs every target. With $aplicar=false nothing is written.
     *
     * @return array{version:string, applied:bool, changed:int, entries:array<int, array>, notes:array<int, string>}
     */
    public function ejecutar(bool $aplicar): array
    {
        $this->informe = [];

        $correr = function () use ($aplicar) {
            $this->requisitos($aplicar);
            $this->roles($aplicar);
            $this->columna('evaluaciones', 'recomendacion', fn (string $v) => DataTextMaps::RECOMENDACIONES[$v] ?? null, $aplicar);
            $this->columna('mediciones', 'calidad_motivo', fn (string $v) => $this->motivoCalidad($v), $aplicar);
            $this->columna('alertas_eventos', 'mensaje', fn (string $v) => $this->mensajeEvento($v), $aplicar);
            $this->columna('alertas_gestion', 'razon', fn (string $v) => $this->razon($v), $aplicar);
            $this->columna('alertas_gestion', 'valor', fn (string $v) => $this->valor($v), $aplicar);
            $this->columna('consultas_tierra', 'titulo', fn (string $v) => $this->tituloConsulta($v), $aplicar);
            $this->columna('consultas_tierra', 'respuesta', fn (string $v) => null, $aplicar); // scan only: nothing in the code writes it
            $this->cargas($aplicar);
            $this->evidencias();
        };

        if ($aplicar) {
            DB::transaction($correr);
        } else {
            $correr();
        }

        $entradas = array_values($this->informe);

        return [
            'version' => DataTextMaps::VERSION,
            'applied' => $aplicar,
            'changed' => array_sum(array_column($entradas, 'rows')),
            'entries' => $entradas,
            'notes' => self::NOTAS,
        ];
    }

    /** What is deliberately NOT touched (printed by the command). */
    public const NOTAS = [
        'misiones.entorno is a key: not touched. The demo mission name is renamed in place (Expedición 74 -> Expedition 74); other mission names are left as they are.',
        'botiquin_items (nombre, grupo) are not updated: the interface shows them in English through label maps (design decision).',
        'requisitos.dominio and every stored value that acts as a key (estado, accion, origen, severidad, tipo, ...) are keys: not touched.',
        'User-written text is never touched: symptoms, notes, consultas_sintomas, routine notes, crew names and other proper names.',
        'Mission events live in config/mision.php (no stored text) and simulacion_eventos holds only keys: nothing to update.',
        'casos_clinicos.evidencia holds only keys, ids and numbers: it is scanned, but there is no system text to translate.',
        'alertas_salientes.carga.zonas_dolor[].zona is not mapped: it is composed from muscle ids (MusculosCuerpo) with side and number.',
    ];

    // ---------------------------------------------------------------- generic column

    /**
     * @param callable(string): ?string $traducir returns the English text, or null when there is no mapping for that exact text
     */
    private function columna(string $tabla, string $columna, callable $traducir, bool $aplicar): void
    {
        $this->entrada($tabla, $columna, null);
        DB::table($tabla)->select(['id', $columna])->whereNotNull($columna)->orderBy('id')
            ->chunkById(self::CHUNK, function ($filas) use ($tabla, $columna, $traducir, $aplicar) {
                foreach ($filas as $fila) {
                    $valor = (string) $fila->{$columna};
                    $nuevo = $traducir($valor);
                    $this->registrar($tabla, $columna, null, $valor, $nuevo);
                    if ($aplicar && $nuevo !== null && $nuevo !== $valor) {
                        DB::table($tabla)->where('id', $fila->id)->where($columna, $valor)->update([$columna => $nuevo]);
                    }
                }
            });
    }

    // ---------------------------------------------------------------- astronautas.rol

    private function roles(bool $aplicar): void
    {
        $this->columna('astronautas', 'rol', fn (string $v) => ExpedicionSeeder::ROLES_ANTERIORES[$v] ?? null, $aplicar);
        // El nombre de la misión de demostración solo lo busca ExpedicionSeeder::MISION (constante): se renombra en el sitio.
        $this->columna('misiones', 'nombre', fn (string $v) => $v === ExpedicionSeeder::MISION_ANTERIOR ? ExpedicionSeeder::MISION : null, $aplicar);
    }

    // ---------------------------------------------------------------- requisitos (by clave, from RequisitosSeeder)

    private function requisitos(bool $aplicar): void
    {
        $seeder = $this->filasDelSeeder();
        foreach (['texto', 'unidad', 'revision'] as $c) {
            $this->entrada('requisitos', $c, null);
        }

        DB::table('requisitos')->select(['id', 'clave', 'texto', 'unidad', 'revision'])->orderBy('id')
            ->chunkById(self::CHUNK, function ($filas) use ($seeder, $aplicar) {
                foreach ($filas as $fila) {
                    $objetivo = $fila->clave !== null ? ($seeder[$fila->clave] ?? null) : null;
                    $cambios = [];
                    foreach (['texto', 'unidad', 'revision'] as $c) {
                        $actual = $fila->{$c};
                        if ($actual === null) {
                            continue;
                        }
                        $nuevo = null;
                        if ($objetivo !== null && ($objetivo[$c] ?? null) !== null && $objetivo[$c] !== $actual) {
                            $nuevo = match ($c) {
                                'texto' => (DataTextMaps::REQUISITOS_TEXTO_ANTERIOR[$fila->clave] ?? null) === $actual ? $objetivo[$c] : null,
                                'unidad' => (DataTextMaps::UNIDADES[$actual] ?? null) === $objetivo[$c] ? $objetivo[$c] : null,
                                'revision' => (DataTextMaps::REVISIONES[$actual] ?? null) === $objetivo[$c] ? $objetivo[$c] : null,
                            };
                        }
                        $this->registrar('requisitos', $c, null, $actual, $nuevo);
                        if ($nuevo !== null) {
                            $cambios[$c] = $nuevo;
                        }
                    }
                    if ($aplicar && $cambios !== []) {
                        DB::table('requisitos')->where('id', $fila->id)->update($cambios);
                    }
                }
            });
    }

    /** RequisitosSeeder rows by clave (via reflection: the seeder keeps filas() private). @return array<string, array<string, mixed>> */
    private function filasDelSeeder(): array
    {
        if ($this->filasSeeder !== null) {
            return $this->filasSeeder;
        }
        $seeder = new RequisitosSeeder();
        $metodo = new ReflectionMethod($seeder, 'filas');
        $metodo->setAccessible(true);
        $revision = (new ReflectionClass($seeder))->getReflectionConstant('NORMA_REV')?->getValue();

        $out = [];
        foreach ($metodo->invoke($seeder) as $f) {
            $f += ['unidad' => null, 'revision' => null];
            if (($f['tipo'] ?? null) === 'norma') {
                $f['revision'] = $revision;
            }
            $out[$f['clave']] = $f;
        }

        return $this->filasSeeder = $out;
    }

    /** Old requisito text => English text (for the copies stored in alertas_salientes.carga.requisito). @return array<string, string> */
    private function textosRequisito(): array
    {
        if ($this->textosRequisito !== null) {
            return $this->textosRequisito;
        }
        $seeder = $this->filasDelSeeder();
        $out = [];
        foreach (DataTextMaps::REQUISITOS_TEXTO_ANTERIOR as $clave => $es) {
            if (isset($seeder[$clave]) && $seeder[$clave]['texto'] !== $es) {
                $out[$es] = $seeder[$clave]['texto'];
            }
        }

        return $this->textosRequisito = $out;
    }

    // ---------------------------------------------------------------- alertas_salientes.carga (JSON)

    private function cargas(bool $aplicar): void
    {
        $traductores = [
            'indicador' => fn (string $v) => DataTextMaps::ETIQUETAS_ALERTA[$v] ?? DataTextMaps::INDICADORES[$v] ?? null,
            'patron_nombre' => fn (string $v) => DataTextMaps::ETIQUETAS_ALERTA[$v] ?? null,
            'requisito' => fn (string $v) => $this->textosRequisito()[$v] ?? null,
            'revision' => fn (string $v) => DataTextMaps::REVISIONES[$v] ?? null,
            'motivo' => fn (string $v) => $this->razon($v),
            'dato_sospechoso' => fn (string $v) => $this->motivoCalidad($v),
            'valor' => fn (string $v) => $this->valor($v),
        ];
        foreach (array_keys($traductores) as $k) {
            $this->entrada('alertas_salientes', 'carga', $k);
        }

        DB::table('alertas_salientes')->select(['id', 'carga'])->whereNotNull('carga')->orderBy('id')
            ->chunkById(self::CHUNK, function ($filas) use ($traductores, $aplicar) {
                foreach ($filas as $fila) {
                    $carga = json_decode((string) $fila->carga, true);
                    if (! is_array($carga)) {
                        continue;
                    }
                    $cambio = false;
                    foreach ($traductores as $k => $traducir) {
                        if (! isset($carga[$k]) || ! is_string($carga[$k])) {
                            continue;
                        }
                        $nuevo = $traducir($carga[$k]);
                        $this->registrar('alertas_salientes', 'carga', $k, $carga[$k], $nuevo);
                        if ($nuevo !== null && $nuevo !== $carga[$k]) {
                            $carga[$k] = $nuevo;
                            $cambio = true;
                        }
                    }
                    if ($aplicar && $cambio) {
                        DB::table('alertas_salientes')->where('id', $fila->id)
                            ->update(['carga' => json_encode($carga, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                }
            });
    }

    // ---------------------------------------------------------------- casos_clinicos.evidencia (scan only)

    private function evidencias(): void
    {
        $this->entrada('casos_clinicos', 'evidencia', null);
        DB::table('casos_clinicos')->select(['id', 'evidencia'])->whereNotNull('evidencia')->orderBy('id')
            ->chunkById(self::CHUNK, function ($filas) {
                foreach ($filas as $fila) {
                    $datos = json_decode((string) $fila->evidencia, true);
                    if (! is_array($datos)) {
                        continue;
                    }
                    array_walk_recursive($datos, function ($v) {
                        if (is_string($v)) {
                            $this->registrar('casos_clinicos', 'evidencia', null, $v, null);
                        }
                    });
                }
            });
    }

    // ---------------------------------------------------------------- translators

    private function mensajeEvento(string $v): ?string
    {
        if (preg_match('/^(.+) escalada a (.+)$/u', $v, $m)
            && isset(DataTextMaps::INDICADORES[$m[1]], DataTextMaps::NIVELES[$m[2]])) {
            return DataTextMaps::INDICADORES[$m[1]].' escalated to '.DataTextMaps::NIVELES[$m[2]];
        }
        if (preg_match('/^(.+): (reconocida|resuelta|reabierta)$/u', $v, $m) && isset(DataTextMaps::INDICADORES[$m[1]])) {
            return DataTextMaps::INDICADORES[$m[1]].': '.DataTextMaps::VERBOS[$m[2]];
        }

        return null;
    }

    private function razon(string $v): ?string
    {
        return $this->patron(DataTextMaps::RAZONES, $v);
    }

    private function motivoCalidad(string $v): ?string
    {
        return $this->patron(DataTextMaps::MOTIVOS_CALIDAD, $v);
    }

    /** @param array<int, array{0:string,1:string}> $patrones */
    private function patron(array $patrones, string $v): ?string
    {
        foreach ($patrones as [$regex, $plantilla]) {
            if (preg_match($regex, $v, $m)) {
                $args = array_map(fn ($a) => DataTextMaps::valorConUnidad($a), array_slice($m, 1));

                return vsprintf($plantilla, $args);
            }
        }

        return null;
    }

    /** "72 lpm" -> "72 bpm" (exact unit only). */
    private function valor(string $v): ?string
    {
        $nuevo = DataTextMaps::valorConUnidad($v);

        return $nuevo !== $v ? $nuevo : null;
    }

    private function tituloConsulta(string $v): ?string
    {
        if ($v === 'Videoconsulta · solicitud') {
            return 'Video consultation · request';
        }
        if (! preg_match('/^Mensaje · (.+)$/u', $v, $m)) {
            return null;
        }
        $fragmentos = DataTextMaps::fragmentosTitulo();
        $partes = explode(' y ', $m[1]);
        if (count($partes) > 2) {
            return null;
        }
        foreach ($partes as $i => $p) {
            if (isset($fragmentos[$p])) {
                $partes[$i] = $fragmentos[$p];
            } elseif (! preg_match('/^[a-z0-9_]+$/', $p)) { // a bare key (e.g. a pattern key) reads the same in both languages
                return null;
            }
        }

        return 'Message · '.implode(' and ', $partes);
    }

    // ---------------------------------------------------------------- report

    private function entrada(string $tabla, string $columna, ?string $clave): void
    {
        $id = $this->id($tabla, $columna, $clave);
        $this->informe[$id] ??= ['table' => $tabla, 'column' => $columna, 'key' => $clave, 'rows' => 0, 'scanned' => 0, 'example' => null, 'unmapped' => []];
    }

    /** Records one value: translated ($nuevo !== null && differs), or counted as unmapped when it still looks Spanish. */
    private function registrar(string $tabla, string $columna, ?string $clave, string $valor, ?string $nuevo): void
    {
        $id = $this->id($tabla, $columna, $clave);
        $this->entrada($tabla, $columna, $clave);
        $this->informe[$id]['scanned']++;
        if ($nuevo !== null && $nuevo !== $valor) {
            $this->informe[$id]['rows']++;
            $this->informe[$id]['example'] ??= [$valor, $nuevo];

            return;
        }
        if ($nuevo === null && self::pareceEspanol($valor)) {
            $this->informe[$id]['unmapped'][$valor] = ($this->informe[$id]['unmapped'][$valor] ?? 0) + 1;
        }
    }

    private function id(string $tabla, string $columna, ?string $clave): string
    {
        return $tabla.'.'.$columna.($clave !== null ? '.'.$clave : '');
    }

    /** Light heuristic, only used to decide whether an untranslated stored text is worth listing as "unmapped". */
    public static function pareceEspanol(string $texto): bool
    {
        return (bool) preg_match('/[áéíóúñÁÉÍÓÚÑ¿¡]|\b(de|del|la|el|los|las|con|sin|tu|tus|para|por|que|una|está|supera|cerca|mensaje|videoconsulta)\b/iu', $texto);
    }
}
