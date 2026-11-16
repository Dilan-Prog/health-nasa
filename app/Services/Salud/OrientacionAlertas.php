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
                    'finding' => "{$i['etiqueta']} fuera de rango: {$i['valor']} {$i['unidad']}.",
                    'steps' => $i['acciones'] ?: ['Comenta este valor con el médico de vuelo en la próxima ventana.'],
                    'pend' => null, 'conf' => null, 'safe' => 'none',
                    'src' => [['l' => 'Umbral: '.$i['rango'], 't' => 'Regla fija del monitor. Se activa al salir del rango normal.', 'ic' => 'rule']],
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

    private static function esEmergencia(array $i, array $em): bool
    {
        return $i['clave'] === $em['indicador'] && self::numero($i['valor']) < $em['umbral'];
    }

    private static function numero(string $v): float
    {
        return (float) str_replace(ResumenTripulante::MENOS, '-', explode('/', $v)[0]);
    }
}
