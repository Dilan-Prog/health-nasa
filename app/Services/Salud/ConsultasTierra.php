<?php

namespace App\Services\Salud;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\ConsultaTierra;
use App\Models\OrientacionFeedback;
use InvalidArgumentException;

/** Consultas al médico de vuelo en tierra: envío por la cola existente y avance calculado por tiempo. */
class ConsultasTierra
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private const ATT = [
        ['Mediciones 15 días', 'CSV · 12 KB', 'table_chart'],
        ['Tendencia: pérdida ósea', 'PNG · 84 KB', 'show_chart'],
        ['Registro de sueño', 'CSV · 6 KB', 'bedtime'],
    ];

    public static function enviar(Astronauta $a, string $tipo, array $indicadores, ?string $nota = null, array $adjuntos = []): ConsultaTierra
    {
        if (! in_array($tipo, ['msg', 'video'], true)) {
            throw new InvalidArgumentException('Tipo de consulta no válido: '.$tipo);
        }
        if ($tipo === 'video' && $a->misionDeVuelo?->triajeAutonomo()) {
            throw new InvalidArgumentException('Triaje autónomo local: este nivel de atención no ofrece videoconsulta síncrona. Envía un mensaje asíncrono a tierra.');
        }
        $indicadores = array_values($indicadores);
        if ($indicadores === []) {
            throw new InvalidArgumentException('Hay que indicar al menos un indicador.');
        }

        $enlace = app(EnvioATierra::class)->enlaceActivo();

        $consulta = ConsultaTierra::create([
            'astronauta_id' => $a->id,
            'tipo' => $tipo,
            'titulo' => self::titulo($tipo, $indicadores),
            'nota' => $nota,
            'indicadores' => $indicadores,
            'adjuntos' => array_values($adjuntos),
            'etapa' => 0,
            'fallida' => ! $enlace,
            'tiempos' => [now()->utc()->format('H:i'), '', '', ''],
            'respuesta' => null,
        ]);

        AlertaSaliente::create([
            'astronauta_id' => $a->id,
            'estado' => 'pendiente',
            'carga' => [
                'astronauta' => $a->nombre ?? $a->id,
                'tipo' => $tipo,
                'indicadores' => $indicadores,
                'nota' => $nota,
                'origen' => 'consulta',
            ],
        ]);

        return $consulta;
    }

    public static function lista(Astronauta $a, int $limite = 10): array
    {
        $enlace = app(EnvioATierra::class)->enlaceActivo();
        $retardo = $a->retardoMin();

        return ConsultaTierra::where('astronauta_id', $a->id)
            ->orderByDesc('id')->limit($limite)->get()
            ->map(fn (ConsultaTierra $c) => self::fila($c, $enlace, $retardo))
            ->all();
    }

    public static function reintentar(ConsultaTierra $c): bool
    {
        if (! app(EnvioATierra::class)->enlaceActivo()) {
            return false;
        }
        $tiempos = $c->tiempos ?: ['', '', '', ''];
        $tiempos[1] = now()->utc()->format('H:i');
        $c->forceFill(['fallida' => false, 'etapa' => max(1, (int) $c->etapa), 'tiempos' => $tiempos])->save();

        return true;
    }

    public static function votar(Astronauta $a, string $indicador, ?string $voto): ?string
    {
        $actual = self::voto($a, $indicador);
        if (! in_array($voto, ['up', 'down'], true) || $voto === $actual) {
            OrientacionFeedback::where('astronauta_id', $a->id)->where('indicador', $indicador)->delete();

            return null;
        }
        OrientacionFeedback::updateOrCreate(
            ['astronauta_id' => $a->id, 'indicador' => $indicador],
            ['voto' => $voto]
        );

        return $voto;
    }

    public static function voto(Astronauta $a, string $indicador): ?string
    {
        return OrientacionFeedback::where('astronauta_id', $a->id)->where('indicador', $indicador)->value('voto');
    }

    public static function adjuntosDisponibles(Astronauta $a): array
    {
        return array_map(fn ($x) => ['l' => $x[0], 'd' => $x[1], 'ic' => $x[2]], self::ATT);
    }

    private static function titulo(string $tipo, array $indicadores): string
    {
        if ($tipo === 'video') {
            return 'Videoconsulta · solicitud';
        }
        $et = [];
        foreach (array_slice($indicadores, 0, 2) as $k) {
            $e = config("panel.indicadores.$k.etiqueta") ?? config("indicadores.marcadores.$k.label") ?? (string) $k;
            $et[] = mb_strtolower(trim(preg_replace('/\s*\(.*?\)/u', '', $e)));
        }

        return 'Mensaje · '.implode(' y ', $et);
    }

    private static function fila(ConsultaTierra $c, bool $enlace, int $retardo): array
    {
        $tiempos = $c->tiempos ?: ['', '', '', ''];
        $etapa = (int) $c->etapa;
        $fallida = (bool) $c->fallida;
        $cambio = false;
        $creada = $c->created_at->copy()->utc();
        $trayecto = $retardo > 0 ? $retardo * 60 : 3;

        if (! $enlace && $etapa === 0 && ! $fallida) {
            $fallida = true;
            $cambio = true;
        } elseif ($enlace && ! $fallida) {
            $seg = $creada->diffInSeconds(now(), false);
            $meta = $etapa;
            if ($seg >= 1) {
                $meta = max($meta, 1);
            }
            if ($seg >= $trayecto) {
                $meta = max($meta, 2);
            }
            if ($c->respuesta && $meta >= 2) {
                $meta = 3;
            }
            if ($meta !== $etapa) {
                $momentos = [1 => $creada->copy()->addSecond(), 2 => $creada->copy()->addSeconds($trayecto), 3 => now()->utc()];
                for ($i = $etapa + 1; $i <= $meta; $i++) {
                    if (($tiempos[$i] ?? '') === '') {
                        $tiempos[$i] = $momentos[$i]->format('H:i');
                    }
                }
                $etapa = $meta;
                $cambio = true;
            }
        }

        if ($cambio) {
            $c->forceFill(['etapa' => $etapa, 'fallida' => $fallida, 'tiempos' => $tiempos])->save();
        }

        $etapas = [];
        foreach (ConsultaTierra::ETAPAS as $i => $l) {
            $etapas[] = ['l' => $l, 'hora' => $tiempos[$i] ?? '', 'hecha' => $i <= $etapa];
        }

        $eta = null;
        if ($retardo > 0 && $etapa < 3) {
            $eta = 'Llegada estimada '.$creada->copy()->addMinutes($retardo)->format('H:i')
                .' UTC · respuesta estimada hacia las '.$creada->copy()->addMinutes($retardo * 2 + 10)->format('H:i').' UTC';
        }

        return [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'titulo' => $c->titulo,
            'cuando' => $creada->toDateString() === now()->utc()->toDateString()
                ? 'Hoy '.$creada->format('H:i').' UTC'
                : $creada->day.' '.self::MESES[$creada->month - 1],
            'etapa' => $etapa,
            'etapas' => $etapas,
            'fallida' => $fallida,
            'eta' => $eta,
            'respuesta' => $c->respuesta,
        ];
    }
}
