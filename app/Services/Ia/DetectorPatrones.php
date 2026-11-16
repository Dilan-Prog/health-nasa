<?php

namespace App\Services\Ia;

use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\CasoClinico;
use App\Models\Medicion;
use App\Models\SimulacionEvento;
use App\Services\Salud\CalidadSenal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Detector de patrones (IA local SIMULADA, sin modelo): combina estados de indicadores ya evaluados
 * (config/patrones.php) y mantiene un caso clínico abierto por tripulante y patrón.
 * Orienta, no diagnostica: cada patrón es una hipótesis.
 */
class DetectorPatrones
{
    /** Segundos entre evaluaciones por tripulante desde el gancho. SUPUESTO DEL PROYECTO, NO DE LA NORMA. */
    public const ENFRIAMIENTO_S = 20;

    private const SEVERIDAD = ['normal' => 0, 'precaucion' => 1, 'critico' => 2];

    /**
     * Gancho de ProcesadorMediciones: se llama tras guardar CADA medición. Barato y a prueba de fallos:
     * nunca lanza (el registro de mediciones no puede romperse por el detector).
     */
    public static function alMedir(Astronauta $a, string $indicador, string $estado): void
    {
        try {
            $hayAbiertos = CasoClinico::abiertos()->where('astronauta_id', $a->id)->exists();
            if ($estado === 'normal' && ! $hayAbiertos && ! self::ultimaSospechosa($a, $indicador)) {
                return;
            }

            // Una lectura crítica salta el enfriamiento si el tripulante aún no tiene un caso crítico abierto.
            $saltarEnfriamiento = $estado === 'critico'
                && ! CasoClinico::abiertos()->where('astronauta_id', $a->id)->where('severidad', 'critico')->exists();
            if (! $saltarEnfriamiento && ! Cache::add('patrones.enfriamiento.'.$a->id, 1, self::ENFRIAMIENTO_S)) {
                return;
            }

            self::evaluar($a);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Evaluación síncrona y sin enfriamiento. Devuelve los casos creados, actualizados o cerrados en esta pasada.
     *
     * @return array<int, CasoClinico>
     */
    public static function evaluar(Astronauta $a): array
    {
        $lock = Cache::lock('patrones.evaluar.'.$a->id, 15);
        if (! $lock->get()) {
            return []; // otro proceso ya evalúa a este tripulante
        }

        try {
            return self::evaluarSinBloqueo($a);
        } finally {
            $lock->release();
        }
    }

    /** @return array<int, CasoClinico> */
    private static function evaluarSinBloqueo(Astronauta $a): array
    {
        $patrones = (array) config('patrones');
        $ultima = Medicion::where('astronauta_id', $a->id)->orderByDesc('medido_en')->orderByDesc('id')->first();
        if ($ultima === null || $patrones === []) {
            return [];
        }
        $ref = $ultima->medido_en;
        $ventanaMax = (int) max(array_map(fn ($p) => (int) $p['ventana_min'], $patrones));
        $indicadores = array_values(array_unique(array_merge(...array_values(array_map(fn ($p) => array_column($p["senales"], "indicador"), $patrones)))));

        $filas = Medicion::with('evaluacion')
            ->where('astronauta_id', $a->id)
            ->whereIn('indicador', $indicadores)
            ->where('medido_en', '>=', $ref->copy()->subMinutes($ventanaMax))
            ->where('medido_en', '<=', $ref)
            ->orderBy('medido_en')->orderBy('id')
            ->get()->groupBy('indicador');

        $tocados = [];
        foreach ($patrones as $clave => $def) {
            $deteccion = self::coincidencia($def, $filas, $ref);
            $abierto = CasoClinico::abiertos()->where('astronauta_id', $a->id)->where('patron', $clave)->first();

            if ($deteccion === null) {
                if ($abierto) {
                    $abierto->update(['estado' => 'cerrado', 'cerrado_en' => now()]);
                    $tocados[] = $abierto->fresh();
                }

                continue;
            }

            $evidencia = [
                'ventana_min' => (int) $def['ventana_min'],
                'referencia' => $ref->toIso8601String(),
                'minimo' => (int) $def['minimo'],
                'coinciden' => count($deteccion['senales']),
                'senales' => $deteccion['senales'],
            ];
            if ($abierto === null) {
                $caso = CasoClinico::create([
                    'astronauta_id' => $a->id,
                    'patron' => $clave,
                    'severidad' => $deteccion['severidad'],
                    'evidencia' => $evidencia,
                    'estado' => 'abierto',
                    'detectado_en' => now(),
                    'simulacion_evento_id' => SimulacionEvento::where('astronauta_id', $a->id)
                        ->where('escenario', $clave)->whereNull('fin_en')->latest('id')->value('id'),
                ]);
            } else {
                $caso = $abierto;
                $sube = self::SEVERIDAD[$deteccion['severidad']] > self::SEVERIDAD[$caso->severidad];
                $caso->update(['evidencia' => $evidencia] + ($sube ? ['severidad' => $deteccion['severidad']] : []));
            }

            if ($caso->severidad === 'critico' && $caso->escalado_en === null) {
                self::escalar($caso, $a, $def);
            }
            $tocados[] = $caso->fresh();
        }

        return $tocados;
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Medicion>> $filas por indicador, orden cronológico
     * @return array{severidad:string, senales:array<int, array<string, mixed>>}|null
     */
    private static function coincidencia(array $def, $filas, Carbon $ref): ?array
    {
        $desde = $ref->copy()->subMinutes((int) $def['ventana_min']);
        $porCalidad = ($def['origen_senales'] ?? null) === 'calidad';
        $coinciden = [];
        $requeridasOk = true;
        $peorRequeridas = 0;
        $peorTodas = 0;

        foreach ($def['senales'] as $s) {
            $lecturas = ($filas[$s['indicador']] ?? collect())->filter(fn (Medicion $m) => $m->medido_en >= $desde);
            if ($porCalidad) {
                $m = $lecturas->last();
                $estado = $m && $m->calidad === CalidadSenal::SOSPECHOSA ? 'sospechosa' : null;
            } else {
                // Las lecturas sospechosas no cuentan para ningún patrón clínico.
                $m = $lecturas->filter(fn (Medicion $x) => $x->calidad !== CalidadSenal::SOSPECHOSA && $x->evaluacion)->last();
                $estado = $m?->evaluacion->estado;
            }

            if ($m !== null && $estado !== null && in_array($estado, $s['estados'], true)) {
                $coinciden[] = [
                    'indicador' => $s['indicador'],
                    'estado' => $estado,
                    'valores' => $m->valores,
                    'medicion_id' => $m->id,
                    'medido_en' => $m->medido_en->toIso8601String(),
                    'requerida' => (bool) $s['requerida'],
                ];
                $peso = self::SEVERIDAD[$estado] ?? 1;
                $peorTodas = max($peorTodas, $peso);
                if ($s['requerida']) {
                    $peorRequeridas = max($peorRequeridas, $peso);
                }
            } elseif ($s['requerida']) {
                $requeridasOk = false;
            }
        }

        if (! $requeridasOk || count($coinciden) < max(1, (int) $def['minimo'])) {
            return null;
        }

        $peso = $peorRequeridas > 0 ? $peorRequeridas : $peorTodas;
        $severidad = $def['severidad_fija'] ?? ($peso >= 2 ? 'critico' : 'precaucion');

        return ['severidad' => $severidad, 'senales' => $coinciden];
    }

    /** Cola a tierra por la ruta existente (AlertaSaliente), una sola vez por caso. */
    private static function escalar(CasoClinico $caso, Astronauta $a, array $def): void
    {
        DB::transaction(function () use ($caso, $a, $def) {
            // Si la propia medición crítica ya encoló un aviso reciente a tierra, no se duplica: se enriquece con el patrón.
            $ventana = (int) config('tierra.ventana_dedup_min', 10);
            $previa = AlertaSaliente::where('astronauta_id', $a->id)
                ->where('created_at', '>=', now()->subMinutes($ventana))
                ->latest('id')->get()
                ->first(fn ($x) => ($x->carga['origen'] ?? null) !== 'patron' && ($x->carga['estado'] ?? null) === 'critico');
            if ($previa) {
                $previa->update(['carga' => $previa->carga + ['patron' => $caso->patron, 'caso_id' => $caso->id, 'patron_nombre' => $def['nombre'], 'severidad' => $caso->severidad, 'evidencia' => $caso->evidencia]]);
                $caso->update(['escalado_en' => now()]);

                return;
            }
            AlertaSaliente::create([
                'astronauta_id' => $a->id,
                'carga' => [
                    'origen' => 'patron',
                    'caso_id' => $caso->id,
                    'astronauta' => $a->nombre,
                    'patron' => $caso->patron,
                    'indicador' => $def['nombre'],   // etiqueta que ya muestran las pantallas de la cola a tierra
                    'severidad' => $caso->severidad,
                    'estado' => $caso->severidad,
                    'accion' => 'contactar_tierra',
                    'evidencia' => $caso->evidencia,
                    'medido_en' => $caso->evidencia['referencia'] ?? now()->toIso8601String(),
                ],
            ]);
            $caso->update(['escalado_en' => now()]);
        });
    }

    /** ¿La última lectura de ese indicador está marcada sospechosa? (la única señal que no depende del estado clínico) */
    private static function ultimaSospechosa(Astronauta $a, string $indicador): bool
    {
        return Medicion::where('astronauta_id', $a->id)->where('indicador', $indicador)
            ->orderByDesc('medido_en')->orderByDesc('id')->value('calidad') === CalidadSenal::SOSPECHOSA;
    }
}
