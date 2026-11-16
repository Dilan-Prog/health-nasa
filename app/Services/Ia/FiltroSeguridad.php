<?php

namespace App\Services\Ia;

use App\Models\BotiquinItem;
use Throwable;

/**
 * Filtro determinista que se aplica a cada mensaje antes de mostrarlo. Rechaza:
 *  - dosis y unidades de dosis (mg, mcg, ml, comprimidos…),
 *  - frecuencias, duración y vía de administración,
 *  - nombres de medicamentos del botiquín, salvo dentro de `botiquin_opciones` (ver revisarOpcionesBotiquin),
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
    public const FRECUENCIA = 'frecuencia';
    public const DURACION = 'duracion';
    public const VIA = 'via';
    /** Opción de botiquín que incumple una condición (grupo, stock, caducidad, alergia, emergencia, máximo). */
    public const OPCION_BOTIQUIN = 'opcion_botiquin';

    public const ETIQUETA_MEDICACION = 'Medicación registrada:';

    /** Frecuencias de administración. No se rechaza "cada hora" a secas (hay texto de hidratación del catálogo). */
    private const RE_FRECUENCIA = [
        '/\bcada\s+(?:\d+|una|dos|tres|cuatro|seis|ocho|doce|veinticuatro)\s*(?:h|hs|horas?|dias?)\b/u',
        '/\b(?:\d+|una|dos|tres|cuatro)\s+veces\s+(?:al|por|a\s+la|cada)\s+(?:dia|semana|hora|noche|manana)\b/u',
        '/\bveces\s+al\s+dia\b/u',
        '/\buna\s+vez\s+(?:al|por)\s+(?:dia|semana)\b/u',
        '/\bpor\s+la\s+(?:manana|noche|tarde)\b/u',
        '/\b(?:antes|despues)\s+de\s+(?:cada\s+)?(?:comer|comidas?|dormir|acostarte)\b/u',
        '/\b(?:en\s+ayunas|al\s+acostarte)\b/u',
    ];

    /** Duración del tratamiento (días/semanas/meses; no minutos de un ejercicio de respiración). */
    private const RE_DURACION = [
        '/\b(?:durante|por|hasta\s+por)\s+(?:\d+|un|una|dos|tres|cuatro|cinco|siete|diez)\s+(?:dias?|semanas?|meses|mes)\b/u',
        '/\bdurante\s+(?:varios|unos)\s+(?:dias|semanas)\b/u',
    ];

    /** Vía de administración. */
    private const RE_VIA = [
        '/\bvia\s+(?:oral|intravenosa|intramuscular|subcutanea|sublingual|topica|inhalada|rectal|nasal|endovenosa)\b/u',
        '/\bpor\s+via\b/u',
        '/\b(?:sublingual|intravenos[oa]|intramuscular|subcutane[oa]|endovenos[oa])\b/u',
    ];

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

        return $this->causa($t, str_starts_with($t, self::normalizar(self::ETIQUETA_MEDICACION)));
    }

    /**
     * Causa de rechazo de un texto ya normalizado. Con $permitirMedicamentos=true se admiten nombres de fármacos
     * (solo dentro de botiquin_opciones o de la línea de medicación registrada); el resto de reglas siguen vigentes.
     */
    private function causa(string $t, bool $permitirMedicamentos): ?string
    {
        // "Sin dosis" es la advertencia obligatoria de las opciones de botiquín, no una pauta.
        $sinAviso = str_replace('sin dosis', '', $t);
        if (preg_match(self::RE_DOSIS, $t) === 1 || preg_match(self::RE_DOSIS_PALABRA, $sinAviso) === 1) {
            return self::DOSIS;
        }
        foreach ([self::FRECUENCIA => self::RE_FRECUENCIA, self::DURACION => self::RE_DURACION, self::VIA => self::RE_VIA] as $causa => $res) {
            foreach ($res as $re) {
                if (preg_match($re, $t) === 1) {
                    return $causa;
                }
            }
        }
        foreach (self::RE_DIAGNOSTICO as $re) {
            if (preg_match($re, $t) === 1) {
                return self::DIAGNOSTICO;
            }
        }
        if (! $permitirMedicamentos) {
            foreach ($this->nombresMedicamentos() as $nombre) {
                if (preg_match('/(?<![a-z0-9])'.preg_quote($nombre, '/').'(?![a-z0-9])/u', $t) === 1) {
                    return self::MEDICAMENTO;
                }
            }
        }

        return null;
    }

    /**
     * Valida la sección estructurada `botiquin_opciones`: ÚNICO lugar donde se admiten nombres del botiquín.
     * Falla si hay opciones en emergencia, más del máximo, grupo no permitido o vedado, nombre ajeno al botiquín del
     * contexto, sin stock o caducado, alergia (por nombre o clase), o si algún texto lleva dosis/frecuencia/
     * duración/vía/diagnóstico.
     *
     * @param  array<int, array>  $opciones
     * @return string|null causa (OPCION_BOTIQUIN, DOSIS, FRECUENCIA, DURACION, VIA, DIAGNOSTICO, MEDICAMENTO) o null si es válida
     */
    public function revisarOpcionesBotiquin(array $opciones, array $contexto, array $gruposPermitidos, bool $emergencia): ?string
    {
        if ($opciones === []) {
            return null;
        }
        if ($emergencia) {
            return self::OPCION_BOTIQUIN;
        }
        $max = max(0, (int) config('botiquin_clases.max_opciones', 3));
        $reales = array_filter($opciones, fn ($o) => empty($o['requiere_medico']));
        if (count($reales) > $max) {
            return self::OPCION_BOTIQUIN;
        }
        $alergias = (array) ($contexto['expediente']['alergias'] ?? []);
        $inventario = [];
        foreach ((array) ($contexto['botiquin'] ?? []) as $item) {
            $inventario[self::normalizar((string) ($item['nombre'] ?? ''))] = $item;
        }

        foreach ($opciones as $o) {
            if (! is_array($o)) {
                return self::OPCION_BOTIQUIN;
            }
            $textos = [(string) ($o['nombre'] ?? ''), (string) ($o['nota'] ?? ''), (string) ($o['contraindicaciones'] ?? '')];
            if (! empty($o['requiere_medico'])) {
                // Nota "requiere al médico de vuelo": no nombra fármacos.
                if ($o['nombre'] !== null && trim((string) $o['nombre']) !== '') {
                    return self::OPCION_BOTIQUIN;
                }
                if (($causa = $this->revisarTodos([$o['nota'] ?? ''])) !== null) {
                    return $causa;
                }

                continue;
            }

            $nombre = self::normalizar((string) ($o['nombre'] ?? ''));
            $grupo = (string) ($o['grupo'] ?? '');
            $item = $inventario[$nombre] ?? null;
            if ($nombre === '' || $item === null
                || in_array($grupo, BotiquinOpciones::GRUPOS_VEDADOS, true)
                || ! in_array($grupo, $gruposPermitidos, true)
                || ($item['grupo'] ?? null) !== $grupo
                || ! BotiquinOpciones::disponible($item)
                || BotiquinOpciones::esAlergico($nombre, $alergias)) {
                return self::OPCION_BOTIQUIN;
            }
            // Textos de la opción: el nombre propio puede aparecer; ningún otro fármaco. Dosis y demás, nunca.
            foreach ($textos as $texto) {
                $sinNombre = str_replace($nombre, '', self::normalizar($texto));
                if (($causa = $this->causa($sinNombre, false)) !== null) {
                    return $causa;
                }
            }
            // El aviso de medicación puede nombrar lo que el tripulante ya tiene registrado (solo lectura).
            if (isset($o['aviso_medicacion']) && is_string($o['aviso_medicacion'])
                && ($causa = $this->causa(str_replace('ya tienes registrado', 'registrado', self::normalizar($o['aviso_medicacion'])), true)) !== null) {
                return $causa;
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
