<?php

namespace App\Services\Ia;

/**
 * Acciones concretas (alimentación, hidratación, ejercicio, descanso) para cada indicador alterado, distintas de las del
 * Panel y coherentes con la DIRECCIÓN del problema (glucosa alta vs baja, presión alta vs baja…).
 * Las reglas son datos (config/recomendaciones.php). Puro: trabaja sobre el array de contexto (ver ContextoTripulante).
 *
 * @phpstan-type Accion array{id:string,texto:string,fuente:array{etiqueta:string,texto:string,origen:string},indicador:string}
 */
class RecomendacionesEspecificas
{
    /**
     * @param array    $contexto contexto del tripulante (usa 'indicadores' y 'rutina')
     * @param string[] $idsPanel ids de AccionesPanel ya mostrados en "Qué hacer ahora"
     * @return array{acciones: array<int, array>, panel_vigente: bool}
     */
    public static function para(array $contexto, array $idsPanel = []): array
    {
        $cfg = config('recomendaciones');
        $vars = self::variables($contexto, $cfg);
        $vistosIds = array_flip($idsPanel);
        $textosVistos = [];
        $acciones = [];
        $panelVigente = false;

        $alterados = [];
        foreach ($contexto['indicadores'] ?? [] as $clave => $ind) {
            // Una lectura sospechosa no justifica recomendaciones: primero hay que repetirla.
            if (in_array($ind['estado'] ?? null, ['warn', 'crit'], true) && empty($ind['sospechoso'])) {
                $alterados[] = [$clave, $ind];
            }
        }
        usort($alterados, fn ($a, $b) => (($b[1]['estado'] ?? '') === 'crit') <=> (($a[1]['estado'] ?? '') === 'crit'));

        foreach ($alterados as [$clave, $ind]) {
            $porIndicador = 0;
            foreach (self::reglasDe($cfg, $clave, $ind['direccion'] ?? null) as $regla) {
                if (! self::cumple($regla['si'] ?? null, $vars, $cfg)) {
                    continue;
                }
                $texto = self::rellenar($regla['texto'], $vars);
                $id = isset($regla['panel'])
                    ? AccionesPanel::id($clave, $regla['panel'])
                    : 'rec.'.$clave.'.'.$regla['id'];

                if (isset($vistosIds[$id])) {
                    $panelVigente = true; // ya está en el Panel: se dice una sola vez ("lo del Panel sigue vigente")
                    continue;
                }
                $norm = FiltroSeguridad::normalizar($texto);
                if (isset($textosVistos[$norm]) || self::idYaEn($acciones, $id)) {
                    continue;
                }
                if ($porIndicador >= $cfg['max_por_indicador'] || count($acciones) >= $cfg['max_total']) {
                    continue;
                }
                $textosVistos[$norm] = true;
                $porIndicador++;
                $acciones[] = [
                    'id' => $id,
                    'texto' => $texto,
                    'fuente' => $cfg['fuentes'][$regla['fuente']] ?? $cfg['fuentes']['pendiente'],
                    'indicador' => $clave,
                ];
            }
        }

        return ['acciones' => $acciones, 'panel_vigente' => $panelVigente];
    }

    /** Reglas aplicables según la dirección (o la implícita del indicador); sin dirección conocida, solo las neutrales. */
    private static function reglasDe(array $cfg, string $clave, ?string $direccion): array
    {
        $reglas = $cfg['reglas'][$clave] ?? [];
        $direccion = in_array($direccion, ['alto', 'bajo'], true) ? $direccion : ($cfg['direccion_implicita'][$clave] ?? null);

        return array_merge($direccion ? ($reglas[$direccion] ?? []) : [], $reglas['cualquiera'] ?? []);
    }

    private static function idYaEn(array $acciones, string $id): bool
    {
        foreach ($acciones as $a) {
            if ($a['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /** Datos de la rutina real del tripulante para condiciones y marcadores. */
    private static function variables(array $contexto, array $cfg): array
    {
        $rutina = $contexto['rutina'] ?? null;
        $metaDia = (int) ($rutina['meta_min_dia'] ?? $cfg['meta_dia_defecto_min']);
        $ejecuciones = (array) ($rutina['ejecuciones'] ?? []);
        $resistidas = array_filter($ejecuciones, fn ($e) => ($e['tipo'] ?? null) === $cfg['tipo_resistido']);
        $sueno = $rutina['sueno_h_ultimo'] ?? null;

        return [
            'hay_rutina' => $rutina !== null && array_key_exists('ejercicio_min_semana', $rutina),
            'min_semana' => (int) ($rutina['ejercicio_min_semana'] ?? 0),
            'meta_dia' => $metaDia,
            'meta_semana' => $metaDia * $cfg['dias_ejercicio_min'],
            'n_resistido' => count($resistidas),
            'sueno' => $sueno === null ? null : (float) $sueno,
            'sueno_h' => $sueno === null ? '' : rtrim(rtrim(number_format((float) $sueno, 1, '.', ''), '0'), '.'),
        ];
    }

    private static function cumple(?string $cond, array $v, array $cfg): bool
    {
        return match ($cond) {
            null => true,
            'sin_rutina' => ! $v['hay_rutina'],
            'bajo_meta' => $v['hay_rutina'] && $v['min_semana'] < $v['meta_semana'],
            'cumple_meta' => $v['hay_rutina'] && $v['min_semana'] >= $v['meta_semana'],
            'sin_resistido' => $v['hay_rutina'] && $v['n_resistido'] === 0,
            'con_resistido' => $v['hay_rutina'] && $v['n_resistido'] > 0,
            'sueno_bajo' => $v['sueno'] !== null && $v['sueno'] < $cfg['sueno_min_h'],
            default => false,
        };
    }

    private static function rellenar(string $texto, array $v): string
    {
        return strtr($texto, [
            '{min_semana}' => (string) $v['min_semana'],
            '{meta_semana}' => (string) $v['meta_semana'],
            '{meta_dia}' => (string) $v['meta_dia'],
            '{n_resistido}' => (string) $v['n_resistido'],
            '{sueno_h}' => $v['sueno_h'],
        ]);
    }
}
