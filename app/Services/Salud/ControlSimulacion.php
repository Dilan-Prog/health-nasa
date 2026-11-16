<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use App\Models\SimulacionEvento;
use App\Models\SimulacionMotor;
use App\Models\SimulacionTripulante;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Orquesta la simulación en vivo: control de escenarios (lo usa el panel) y un tick que lo ejecuta (lo llama el comando).
 * Todo el estado vive en la base; el comando solo avanza el reloj virtual y registra por el mismo camino que el reloj real.
 */
class ControlSimulacion
{
    /** Tope de avance virtual por tick, por si el comando estuvo parado (segundos virtuales). */
    public const MAX_AVANCE_S = 6 * 3600;

    public static function iniciar(Astronauta $a, string $clave): SimulacionEvento
    {
        $escenarios = (new SimuladorVivo())->escenarios();
        if (! isset($escenarios[$clave])) {
            throw new InvalidArgumentException("Unknown scenario: {$clave}");
        }
        $t = SimulacionTripulante::de($a);
        if ($t->fuente === 'reloj') {
            throw new InvalidArgumentException('This crew member uses the real clock; it cannot be simulated.');
        }

        if ($t->escenario) {
            self::cerrarEvento($a, 'cancelado');
        }

        $t->update([
            'escenario' => $clave,
            'escenario_inicio_virtual' => $t->virtual_en ?? now(),
            'escenario_dura_s' => (int) ($escenarios[$clave]['duracion_min'] * 60),
        ]);

        return SimulacionEvento::create(['astronauta_id' => $a->id, 'escenario' => $clave, 'inicio_en' => now()]);
    }

    public static function cancelar(Astronauta $a): void
    {
        $t = SimulacionTripulante::where('astronauta_id', $a->id)->first();
        if (! $t || ! $t->escenario) {
            return;
        }
        self::cerrarEvento($a, 'cancelado');
        self::limpiarEscenario($t);
    }

    /** Segundos REALES que faltan para que termine el escenario; null si no hay. */
    public static function restanteReal(SimulacionTripulante $t, SimulacionMotor $m): ?int
    {
        if (! $t->escenario || ! $t->escenario_dura_s || ! $t->escenario_inicio_virtual) {
            return null;
        }
        $transcurrido = $t->virtual_en ? max(0, $t->escenario_inicio_virtual->diffInSeconds($t->virtual_en, false)) : 0;
        $restanteVirtual = max(0, $t->escenario_dura_s - $transcurrido);

        return (int) ceil($restanteVirtual / max(1, $m->velocidad));
    }

    /** @return array{generadas:int, omitidas:int, errores:int, activos:int} */
    public static function tick(?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $r = ['generadas' => 0, 'omitidas' => 0, 'errores' => 0, 'activos' => 0];

        $motor = SimulacionMotor::actual();
        $motor->update(['latido_en' => $ahora]); // el latido prueba que el comando vive, aunque esté apagada
        if (! $motor->activa) {
            return $r;
        }

        $simulador = new SimuladorVivo();
        $procesador = ProcesadorMediciones::make();

        $filas = SimulacionTripulante::with('astronauta')->where('activa', true)->where('fuente', 'simulado')->orderBy('id')->get();
        foreach ($filas as $t) {
            if (! $t->simulable() || ! $t->astronauta) {
                continue; // un tripulante con reloj nunca se simula
            }
            $r['activos']++;
            try {
                self::avanzarTripulante($t, $motor, $simulador, $procesador, $ahora, $r);
            } catch (Throwable $e) {
                $r['errores']++;
                $t->forceFill(['ultimo_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            }
        }

        return $r;
    }

    private static function avanzarTripulante(SimulacionTripulante $t, SimulacionMotor $motor, SimuladorVivo $sim, ProcesadorMediciones $proc, CarbonInterface $ahora, array &$r): void
    {
        $estado = $t->estado ?? $sim->estadoInicial((int) $t->semilla);

        if (! $t->virtual_en || ! $t->ultimo_tick) { // primer tick: ancla el reloj, sin histórico
            $t->forceFill(['estado' => $estado, 'virtual_en' => $ahora, 'ultimo_tick' => $ahora, 'ultimo_error' => null])->save();

            return;
        }

        $desde = $t->virtual_en->copy();
        $realS = max(0, $t->ultimo_tick->diffInSeconds($ahora, false));
        $avanceS = min((int) round($realS * $motor->velocidad), self::MAX_AVANCE_S);
        $hasta = $desde->copy()->addSeconds($avanceS);

        $escenario = $t->escenario
            ? ['clave' => $t->escenario, 'inicio' => $t->escenario_inicio_virtual, 'dura_s' => (int) $t->escenario_dura_s]
            : null;

        $res = $sim->avanzar($estado, $desde, $hasta, (float) $motor->velocidad, $ahora, $escenario);

        foreach ($res['mediciones'] as $m) {
            try {
                $proc->registrar($t->astronauta, $m->indicador, $m->valores, 'simulado', $m->medidoEn, true);
                $r['generadas']++;
            } catch (ValidationException) {
                $r['omitidas']++;
            }
        }

        if ($res['escenario_terminado'] && $t->escenario) {
            self::cerrarEvento($t->astronauta, 'completo');
            self::limpiarEscenario($t, false);
        }

        $t->forceFill(['estado' => $res['estado'], 'virtual_en' => $hasta, 'ultimo_tick' => $ahora, 'ultimo_error' => null])->save();
    }

    private static function cerrarEvento(Astronauta $a, string $motivo): void
    {
        SimulacionEvento::where('astronauta_id', $a->id)->whereNull('fin_en')
            ->update(['fin_en' => now(), 'motivo_fin' => $motivo]);
    }

    private static function limpiarEscenario(SimulacionTripulante $t, bool $guardar = true): void
    {
        $t->forceFill(['escenario' => null, 'escenario_inicio_virtual' => null, 'escenario_dura_s' => null]);
        if ($guardar) {
            $t->save();
        }
    }
}
