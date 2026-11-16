<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astronauta;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Recibe mediciones del reloj. Autenticación: `Authorization: Bearer <token>` (ver `php artisan reloj:token`).
 * El tripulante sale del token, nunca del cuerpo de la petición.
 */
class MedicionesController extends Controller
{
    /** Indicadores que el reloj puede enviar. */
    public const INDICADORES = ['frecuencia_cardiaca', 'saturacion_o2', 'temperatura', 'sueno'];

    public function store(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $astronauta = $token ? Astronauta::where('token_dispositivo', hash('sha256', $token))->first() : null;
        // Atajo de pruebas: sin token y solo en local, usa el tripulante fijado en RELOJ_SIN_TOKEN_ASTRONAUTA.
        if (! $token && app()->environment('local') && config('mision.reloj_sin_token_astronauta')) {
            $astronauta = Astronauta::find(config('mision.reloj_sin_token_astronauta'));
        }
        if (! $astronauta) {
            return response()->json(['mensaje' => 'Token inválido.'], 401);
        }

        $datos = $request->validate([
            'mediciones' => ['required', 'array', 'min:1', 'max:200'],
            'mediciones.*.indicador' => ['required', 'string', 'in:'.implode(',', self::INDICADORES)],
            'mediciones.*.valor' => ['required', 'numeric'],
            'mediciones.*.medido_en' => ['required', 'date'],
        ]);

        $procesador = ProcesadorMediciones::make();
        $items = collect($datos['mediciones'])->sortBy('medido_en')->values();
        $resultado = ['guardadas' => 0, 'duplicadas' => 0, 'rechazadas' => []];

        foreach ($items as $i => $m) {
            $instante = Carbon::parse($m['medido_en']);
            if ($instante->isAfter(now()->addMinutes(5))) {
                $resultado['rechazadas'][] = ['indice' => $i, 'motivo' => 'La fecha está en el futuro.'];

                continue;
            }
            try {
                $procesador->registrar($astronauta, $m['indicador'], ['valor' => (float) $m['valor']], 'reloj', $instante);
                $resultado['guardadas']++;
            } catch (ValidationException $e) {
                $motivo = implode(' ', $e->errors()['valores'] ?? ['Rechazada.']);
                if (str_contains($motivo, 'duplicada')) {
                    $resultado['duplicadas']++; // reenvío de la cola offline: no es un error
                } else {
                    $resultado['rechazadas'][] = ['indice' => $i, 'motivo' => $motivo];
                }
            }
        }

        return response()->json($resultado, 200);
    }
}
