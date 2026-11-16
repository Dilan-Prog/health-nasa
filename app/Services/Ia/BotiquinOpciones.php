<?php

namespace App\Services\Ia;

use App\Models\BotiquinItem;
use Carbon\Carbon;

/**
 * Opciones del botiquín para CONSULTAR con el médico de vuelo (simulado). Puro: trabaja con el contexto (arrays).
 * Nunca dosis, frecuencia, duración ni vía. Nunca en emergencia. Nunca antimicrobianos/antivirales ni emergencias
 * como autoatención (solo la nota "requiere al médico de vuelo").
 */
class BotiquinOpciones
{
    public const ENCABEZADO = 'Opciones del botiquín para consultar con el médico de vuelo. Contraindicaciones sin verificar. Sin dosis.';

    public const NOTA_OPCION = 'Opción a consultar con el médico de vuelo. Sin dosis.';

    public const NOTA_REQUIERE_MEDICO = 'Requiere al médico de vuelo: no es de autoatención.';

    /** Grupos que jamás se sugieren como autoatención. */
    public const GRUPOS_VEDADOS = ['Antimicrobianos y antivirales', 'Emergencias'];

    public const SIN_VERIFICAR = 'Sin verificar (pendiente de revisión médica)';

    /**
     * @param  array  $contexto  ver ContextoTripulante (usa 'botiquin' y 'expediente')
     * @param  array<int, string>  $grupos_permitidos  grupos de la ficha del problema
     * @return array<int, array>  opciones (máx. config) y, al final, como mucho una nota con 'requiere_medico' => true
     */
    public static function para(array $contexto, array $grupos_permitidos, bool $emergencia, ?Carbon $hoy = null): array
    {
        if ($emergencia || $grupos_permitidos === []) {
            return [];
        }
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        $max = max(0, (int) config('botiquin_clases.max_opciones', 3));
        $alergias = (array) ($contexto['expediente']['alergias'] ?? []);
        $medicacion = (array) ($contexto['expediente']['medicacion'] ?? []);
        $botiquin = array_values((array) ($contexto['botiquin'] ?? []));

        $opciones = [];
        $nota = null;
        foreach ($grupos_permitidos as $grupo) {
            if (in_array($grupo, self::GRUPOS_VEDADOS, true)) {
                $nota ??= [
                    'id' => 'requiere_medico_vuelo',
                    'nombre' => null,
                    'grupo' => $grupo,
                    'nota' => self::NOTA_REQUIERE_MEDICO,
                    'contraindicaciones' => null,
                    'aviso_medicacion' => null,
                    'requiere_medico' => true,
                ];

                continue;
            }
            foreach ($botiquin as $item) {
                if (count($opciones) >= $max) {
                    break;
                }
                if (($item['grupo'] ?? null) !== $grupo || ! self::disponible($item, $hoy)) {
                    continue;
                }
                if (self::esAlergico((string) ($item['nombre'] ?? ''), $alergias)) {
                    continue;
                }
                $opciones[] = [
                    'id' => $item['id'] ?? null,
                    'nombre' => (string) $item['nombre'],
                    'grupo' => $grupo,
                    'nota' => self::NOTA_OPCION,
                    'contraindicaciones' => self::contraindicaciones($item),
                    'aviso_medicacion' => self::avisoMedicacion((string) $item['nombre'], $grupo, $medicacion),
                ];
            }
        }
        if ($nota !== null) {
            $opciones[] = $nota;
        }

        return $opciones;
    }

    /** Stock > 0 y no caducado. */
    public static function disponible(array $item, ?Carbon $hoy = null): bool
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        if ((int) ($item['stock'] ?? 0) <= 0) {
            return false;
        }
        if (array_key_exists('vigente', $item) && $item['vigente'] === false) {
            return false;
        }
        if (! empty($item['caduca'])) {
            try {
                if (Carbon::parse($item['caduca'])->startOfDay()->lt($hoy)) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    /** ¿La alergia (por nombre o por clase) incluye este medicamento? */
    public static function esAlergico(string $nombre, array $alergias): bool
    {
        $n = FiltroSeguridad::normalizar($nombre);
        foreach ($alergias as $a) {
            $sustancia = FiltroSeguridad::normalizar((string) (is_array($a) ? ($a['sustancia'] ?? '') : $a));
            if ($sustancia === '') {
                continue;
            }
            if (self::coincide($sustancia, $n)) {
                return true;
            }
            foreach (self::clases() as $clase => $nombres) {
                if (! in_array($n, $nombres, true)) {
                    continue;
                }
                // Conservador: alergia a la clase o a cualquiera de sus miembros excluye toda la clase.
                if (self::coincide($sustancia, $clase)) {
                    return true;
                }
                foreach ($nombres as $m) {
                    if (self::coincide($sustancia, $m)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return array<string, array<int, string>> clase normalizada => nombres normalizados */
    public static function clases(): array
    {
        $out = [];
        foreach ((array) config('botiquin_clases.clases', []) as $clase => $nombres) {
            $out[FiltroSeguridad::normalizar((string) $clase)] = array_map(
                fn ($m) => FiltroSeguridad::normalizar((string) $m),
                (array) $nombres
            );
        }

        return $out;
    }

    /** Igualdad o contención (mín. 5 letras) para "penicilina" ~ "penicilinas", "ibuprofeno 400" ~ "ibuprofeno". */
    private static function coincide(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $corto = strlen($a) <= strlen($b) ? $a : $b;
        $largo = $corto === $a ? $b : $a;

        return strlen($corto) >= 5 && preg_match('/(?<![a-z0-9])'.preg_quote($corto, '/').'/u', $largo) === 1;
    }

    private static function contraindicaciones(array $item): string
    {
        $c = trim((string) ($item['contraindicaciones'] ?? ''));
        if ($c === '' || $c === BotiquinItem::PENDIENTE) {
            return self::SIN_VERIFICAR;
        }

        return rtrim($c, '. ').'. '.self::SIN_VERIFICAR;
    }

    private static function avisoMedicacion(string $nombre, string $grupo, array $medicacion): ?string
    {
        $n = FiltroSeguridad::normalizar($nombre);
        $delGrupo = array_map(fn ($m) => FiltroSeguridad::normalizar((string) $m), BotiquinItem::GRUPOS[$grupo] ?? []);
        $delGrupo[] = $n;
        $misClases = array_filter(self::clases(), fn ($ns) => in_array($n, $ns, true));

        $registrados = [];
        foreach ($medicacion as $m) {
            $nm = trim((string) (is_array($m) ? ($m['nombre'] ?? '') : $m));
            $norm = FiltroSeguridad::normalizar($nm);
            if ($norm === '') {
                continue;
            }
            $mismo = false;
            foreach ($delGrupo as $g) {
                if (self::coincide($norm, $g)) {
                    $mismo = true;
                    break;
                }
            }
            if (! $mismo) {
                foreach ($misClases as $clase => $ns) {
                    foreach (array_merge([$clase], $ns) as $x) {
                        if (self::coincide($norm, $x)) {
                            $mismo = true;
                            break 2;
                        }
                    }
                }
            }
            if ($mismo) {
                $registrados[] = $nm;
            }
        }
        $registrados = array_values(array_unique($registrados));

        return $registrados === []
            ? null
            : 'Ya tienes registrado '.implode(', ', $registrados).'; consulta antes de combinar.';
    }
}
