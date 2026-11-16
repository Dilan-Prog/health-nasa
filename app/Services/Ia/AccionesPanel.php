<?php

namespace App\Services\Ia;

/**
 * Catálogo compartido de acciones del Panel ("Qué hacer ahora") con ids estables.
 *
 * Las acciones del Panel salen de config/panel.php ('acciones' por indicador). El id es determinista:
 * `panel.<indicador>.<hash corto del texto normalizado>`; así el Panel puede exponer lo que muestra y el orientador
 * (RecomendacionesEspecificas / razonador) omite lo repetido sin comparar textos a mano.
 */
class AccionesPanel
{
    public static function id(string $indicador, string $texto): string
    {
        return 'panel.'.$indicador.'.'.substr(sha1(self::clave($texto)), 0, 8);
    }

    /**
     * Ids de las acciones que el Panel muestra.
     *
     * @param array $indicadoresPanel lista de tarjetas/incidencias del Panel (cada una con 'clave' y 'acciones' = textos),
     *                                o un mapa clave => [textos].
     * @return string[]
     */
    public static function idsDe(array $indicadoresPanel): array
    {
        $ids = [];
        foreach ($indicadoresPanel as $k => $item) {
            if (is_array($item) && isset($item['clave'])) {
                $clave = (string) $item['clave'];
                $acciones = (array) ($item['acciones'] ?? []);
            } elseif (is_string($k) && is_array($item)) {
                $clave = $k;
                $acciones = $item;
            } else {
                continue;
            }
            foreach ($acciones as $texto) {
                if (is_string($texto) && $texto !== '') {
                    $ids[] = self::id($clave, $texto);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Ids de lo que el Panel mostraría para este contexto (indicadores en warn/crit con sus acciones de config/panel.php).
     * Alternativa para quien no tenga a mano las tarjetas del Panel.
     *
     * @return string[]
     */
    public static function idsDeContexto(array $contexto): array
    {
        $panel = [];
        foreach ($contexto['indicadores'] ?? [] as $clave => $ind) {
            if (in_array($ind['estado'] ?? null, ['warn', 'crit'], true)) {
                $panel[$clave] = (array) config('panel.indicadores.'.$clave.'.acciones', []);
            }
        }

        return self::idsDe($panel);
    }

    private static function clave(string $texto): string
    {
        $t = FiltroSeguridad::normalizar($texto);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
    }
}
