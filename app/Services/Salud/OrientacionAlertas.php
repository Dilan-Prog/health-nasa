<?php

namespace App\Services\Salud;

use App\Models\Astronauta;

/**
 * Tarjetas de orientación de "Médico de vuelo" a partir de los indicadores en 'warn'/'crit'.
 * Contenido de config/medico.php (ejemplo): orienta, no diagnostica.
 */
class OrientacionAlertas
{
    /** @return array<int, array> la emergencia primero, luego crit y warn (por confianza). */
    public static function para(Astronauta $a): array
    {
        $cfg = config('medico');
        $lib = $cfg['libreria'];
        $em = $cfg['emergencia'];
        $tarjetas = [];

        foreach (self::incidencias($a) as $i) {
            $clave = $i['clave'];
            $base = [
                'id' => $clave,
                'sev' => $i['estado'],
                'time' => ($i['medido_en']?->copy()->utc() ?? now('UTC'))->format('H:i').' UTC',
                'etiqueta' => $i['etiqueta'],
                'valor' => $i['valor'].' '.$i['unidad'],
                'icono' => $i['icono'],
            ];

            $requisito = ResolvedorRequisito::para($clave);
            $base['fuente'] = ResolvedorRequisito::fuente($requisito);
            // La pérdida ósea es una tasa del HRP (proyección): el requisito real es V1 4027.
            $base['referencia'] = $requisito?->tipo === 'hrp' ? ResolvedorRequisito::fuente(ResolvedorRequisito::referenciaDe($clave)) : null;
            $base['banda'] = self::banda($i);

            if (self::esEmergencia($i, $em)) {
                $tarjetas[] = array_merge($base, [
                    'kind' => 'emerg', 'rule' => $em['regla'], 'sev' => 'crit',
                    'finding' => str_replace('{v}', $i['valor'], $em['finding']),
                    'steps' => $em['steps'], 'pend' => null, 'conf' => null, 'safe' => 'rule', 'src' => $em['src'],
                ]);
            } elseif (isset($lib[$clave])) {
                $l = $lib[$clave];
                $tarjetas[] = array_merge($base, [
                    'kind' => 'ai', 'rule' => null,
                    'finding' => str_replace('{v}', $i['valor'], $l['finding']),
                    'steps' => $l['steps'], 'pend' => $l['pend'] ?? null, 'conf' => $l['conf'], 'safe' => $l['safe'], 'src' => $l['src'],
                ]);
            } else {
                $tarjetas[] = array_merge($base, [
                    'kind' => 'rule', 'rule' => 'R-'.(11 + count($tarjetas)),
                    'finding' => "{$i['etiqueta']} out of range: {$i['valor']} {$i['unidad']}.",
                    'steps' => $i['acciones'] ?: ['Discuss this value with the Flight Surgeon in the next window.'],
                    'pend' => null, 'conf' => null, 'safe' => 'none',
                    'src' => [['l' => 'Threshold: '.$i['rango'], 't' => 'Fixed monitor rule. Triggered when the value leaves the normal range.', 'ic' => 'rule']],
                ]);
            }
        }

        $peso = fn (array $t) => $t['kind'] === 'emerg' ? 0 : ($t['sev'] === 'crit' ? 1 : 2);
        usort($tarjetas, fn ($x, $y) => $peso($x) <=> $peso($y) ?: ($y['conf'] ?? 0) <=> ($x['conf'] ?? 0));

        return $tarjetas;
    }

    /** @return array<int, array{id:string,label:string,val:string,sev:string,locked:bool}> */
    public static function compartibles(Astronauta $a): array
    {
        $em = config('medico.emergencia');
        $lista = array_map(fn (array $i) => [
            'id' => $i['clave'],
            'label' => $i['etiqueta'],
            'val' => $i['valor'].' '.$i['unidad'],
            'sev' => $i['estado'],
            'locked' => self::esEmergencia($i, $em),
        ], self::incidencias($a));
        usort($lista, fn ($x, $y) => ($y['sev'] === 'crit') <=> ($x['sev'] === 'crit'));

        return $lista;
    }

    private static function incidencias(Astronauta $a): array
    {
        return ResumenTripulante::make()->de($a)['incidencias'];
    }

    /** Banda de la regla nueva (EvaluadorEstado::banda, de T2); null mientras no exista. */
    private static function banda(array $i): ?string
    {
        $evaluador = new EvaluadorEstado(config('umbrales'));
        if (! method_exists($evaluador, 'banda')) {
            return null;
        }
        $partes = explode('/', $i['valor']);
        $valores = count($partes) === 2
            ? ['sistolica' => self::numero($partes[0]), 'diastolica' => self::numero($partes[1])]
            : ['valor' => self::numero($i['valor'])];

        return $evaluador->banda($i['clave'], $valores);
    }

    private static function esEmergencia(array $i, array $em): bool
    {
        return $i['clave'] === $em['indicador'] && self::numero($i['valor']) < $em['umbral'];
    }

    private static function numero(string $v): float
    {
        return (float) str_replace(ResumenTripulante::MENOS, '-', explode('/', $v)[0]);
    }
}
