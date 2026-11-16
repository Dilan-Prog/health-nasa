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
    private const MESES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** Stage labels shown in the UI (the model constant ETAPAS keeps the Spanish text). */
    private const ETAPAS = ['Queued', 'Sent', 'Received', 'Answered'];

    private const ATT = [
        ['Measurements, 15 days', 'CSV · 12 KB', 'table_chart'],
        ['Trend: bone loss', 'PNG · 84 KB', 'show_chart'],
        ['Sleep log', 'CSV · 6 KB', 'bedtime'],
    ];

    public static function enviar(Astronauta $a, string $tipo, array $indicadores, ?string $nota = null, array $adjuntos = []): ConsultaTierra
    {
        if (! in_array($tipo, ['msg', 'video'], true)) {
            throw new InvalidArgumentException('Invalid consultation type: '.$tipo);
        }
        if ($tipo === 'video' && $a->misionDeVuelo?->triajeAutonomo()) {
            throw new InvalidArgumentException('Local autonomous triage: this care level does not offer synchronous video consultation. Send an asynchronous message to the ground.');
        }
        $indicadores = array_values($indicadores);
        if ($indicadores === []) {
            throw new InvalidArgumentException('At least one indicator is required.');
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

        \App\Services\Auditoria::registrar('enviar', 'consultas_tierra', $consulta->id, $a->id);

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
            return 'Video consultation · request';
        }
        $et = [];
        foreach (array_slice($indicadores, 0, 2) as $k) {
            $e = config("panel.indicadores.$k.etiqueta") ?? config("indicadores.marcadores.$k.label") ?? (string) $k;
            $e = trim(preg_replace('/\s*\(.*?\)/u', '', $e));
            $et[] = preg_match('/^\p{Lu}\p{Ll}/u', $e) ? mb_strtolower(mb_substr($e, 0, 1)).mb_substr($e, 1) : $e; // keeps acronyms (SpO₂, BMI)
        }

        return 'Message · '.implode(' and ', $et);
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
        foreach (self::ETAPAS as $i => $l) {
            $etapas[] = ['l' => $l, 'hora' => $tiempos[$i] ?? '', 'hecha' => $i <= $etapa];
        }

        $eta = null;
        if ($retardo > 0 && $etapa < 3) {
            $eta = 'Estimated arrival '.$creada->copy()->addMinutes($retardo)->format('H:i')
                .' UTC · estimated response around '.$creada->copy()->addMinutes($retardo * 2 + 10)->format('H:i').' UTC';
        }

        return [
            'id' => $c->id,
            'tipo' => $c->tipo,
            'titulo' => $c->titulo,
            'cuando' => $creada->toDateString() === now()->utc()->toDateString()
                ? 'Today '.$creada->format('H:i').' UTC'
                : $creada->day.' '.self::MESES[$creada->month - 1],
            'etapa' => $etapa,
            'etapas' => $etapas,
            'fallida' => $fallida,
            'eta' => $eta,
            'respuesta' => $c->respuesta,
        ];
    }
}
