<?php

namespace App\Services\Ia;

use App\Models\BotiquinItem;
use Throwable;

/**
 * Filtro determinista que se aplica a cada mensaje antes de mostrarlo. Rechaza:
 *  - dosis y unidades de dosis (mg, mcg, ml, comprimidos…),
 *  - nombres de medicamentos del botiquín usados como recomendación,
 *  - lenguaje de diagnóstico definitivo ("tienes", "padeces", "diagnóstico:"…).
 *
 * Excepción: una línea que empieza por «Medicación registrada:» es contexto del expediente (lo que el tripulante
 * ya toma, solo lectura) y puede nombrar el medicamento. Esa línea sigue sin poder llevar dosis ni diagnóstico.
 */
class FiltroSeguridad
{
    public const DOSIS = 'dosis';
    public const MEDICAMENTO = 'medicamento';
    public const DIAGNOSTICO = 'diagnostico';

    public const ETIQUETA_MEDICACION = 'Medicación registrada:';

    /** Cantidad + unidad de dosis. No rechaza mg/dL ni mg/L (unidad de medida de un indicador). */
    private const RE_DOSIS = '/\b\d+(?:[.,]\d+)?\s*(?:mg|mcg|µg|μg|ug|g|ml|cc|ui|iu|comprimidos?|pastillas?|tabletas?|capsulas?|gotas?|ampollas?|sobres?|cucharadas?|inhalaciones|puffs?)\b(?!\s*\/\s*(?:dl|l)\b)/u';

    /** "dosis" como pauta de fármaco; no la dosis de radiación (indicador). */
    private const RE_DOSIS_PALABRA = '/\bdosis\b(?!\s+(?:acumulada|diaria|de\s+radiacion|de\s+mision|de\s+carrera))/u';

    private const RE_DIAGNOSTICO = [
        '/\b(?:tienes|padeces|sufres|presentas)\b/u',
        '/\b(?:padece|ha\s+sido\s+diagnosticad[oa]|esta\s+diagnosticad[oa]|se\s+diagnostica)\b/u',
        '/\bdiagnostic[oa]\s*(?:definitiv[oa]\s*)?:/u',
        '/\bdiagnosticad[oa]\b/u',
        '/\b(?:estas|esta)\s+(?:enferm[oa]|infectad[oa]|hipoxic[oa]|deshidratad[oa])\b/u',
        '/\bconfirmad[oa]\s+(?:de|como|con)\b/u',
        '/\bprescri\w+\b/u',
        '/\btratamiento\s+con\b/u',
    ];

    /** @var array<int, string>|null nombres normalizados */
    private ?array $medicamentos = null;

    /** @return string|null causa del rechazo (dosis | medicamento | diagnostico) o null si el texto es válido. */
    public function revisar(string $texto): ?string
    {
        $t = self::normalizar($texto);
        $contexto = str_starts_with($t, self::normalizar(self::ETIQUETA_MEDICACION));

        if (preg_match(self::RE_DOSIS, $t) === 1 || preg_match(self::RE_DOSIS_PALABRA, $t) === 1) {
            return self::DOSIS;
        }
        foreach (self::RE_DIAGNOSTICO as $re) {
            if (preg_match($re, $t) === 1) {
                return self::DIAGNOSTICO;
            }
        }
        if (! $contexto) {
            foreach ($this->nombresMedicamentos() as $nombre) {
                if (preg_match('/(?<![a-z0-9])'.preg_quote($nombre, '/').'(?![a-z0-9])/u', $t) === 1) {
                    return self::MEDICAMENTO;
                }
            }
        }

        return null;
    }

    public function permitido(string $texto): bool
    {
        return $this->revisar($texto) === null;
    }

    /** Primera causa de rechazo de cualquiera de los textos, o null si todos pasan. */
    public function revisarTodos(array $textos): ?string
    {
        foreach ($textos as $texto) {
            if (is_string($texto) && ($causa = $this->revisar($texto)) !== null) {
                return $causa;
            }
        }

        return null;
    }

    /** Minúsculas, sin tildes y con espacios simples. */
    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower($texto);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    /** @return array<int, string> */
    private function nombresMedicamentos(): array
    {
        if ($this->medicamentos !== null) {
            return $this->medicamentos;
        }
        $nombres = array_merge(...array_values(BotiquinItem::GRUPOS));
        try {
            $nombres = array_merge($nombres, BotiquinItem::query()->pluck('nombre')->all());
        } catch (Throwable) {
            // Sin BD: basta la lista fija de la guía.
        }
        $nombres = array_map(fn ($n) => self::normalizar((string) $n), $nombres);

        return $this->medicamentos = array_values(array_unique(array_filter($nombres, fn ($n) => $n !== '')));
    }
}
