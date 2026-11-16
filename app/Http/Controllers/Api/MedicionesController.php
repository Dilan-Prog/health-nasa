<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Astronauta;
use App\Services\Salud\ProcesadorMediciones;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Services\Salud\CodigoErrorMedicion;
use App\Services\Salud\MedicionInvalidaException;
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
        $astronauta = null;
        $secreto = null;

        if ($token) {
            $hash = hash('sha256', $token);
            $astronauta = Astronauta::where('token_dispositivo', $hash)->first();
            if ($astronauta) {
                if ($astronauta->token_vence_en && $astronauta->token_vence_en->isPast()) {
                    return $this->vencido();
                }
                $secreto = $astronauta->secreto_firma;
            } elseif ($astronauta = Astronauta::where('token_previo', $hash)->first()) {
                // Token anterior: solo vale durante la gracia posterior a regenerar.
                if (! $astronauta->token_previo_vence_en || $astronauta->token_previo_vence_en->isPast()) {
                    return $this->vencido();
                }
                $secreto = $astronauta->secreto_firma_previo;
            }
        } elseif (app()->environment('local') && config('mision.reloj_sin_token_astronauta')) {
            // Atajo de pruebas: sin token y solo en local. Debe estar vacío fuera de pruebas; cada uso queda en el log.
            $astronauta = Astronauta::find(config('mision.reloj_sin_token_astronauta'));
            if ($astronauta) {
                Log::warning('API reloj: uso del atajo sin token (RELOJ_SIN_TOKEN_ASTRONAUTA).', ['ip' => $request->ip(), 'astronauta_id' => $astronauta->id]);
            }
        }
        if (! $astronauta) {
            return response()->json(['mensaje' => 'Invalid token.'], 401);
        }

        // La firma se comprueba si está exigida o si el dispositivo la envía. El atajo sin token no tiene secreto ni firma.
        if ($token && (config('mision.reloj_exigir_firma') || $request->hasHeader('X-Firma'))) {
            if (($error = $this->firmaInvalida($request, $secreto)) !== null) {
                return response()->json(['mensaje' => $error], 401);
            }
        }

        $datos = $request->validate([
            'mediciones' => ['required', 'array', 'min:1', 'max:200'],
            'mediciones.*.indicador' => ['required', 'string', 'in:'.implode(',', self::INDICADORES)],
            'mediciones.*.valor' => ['required', 'numeric'],
            'mediciones.*.medido_en' => ['required', 'date'],
            'bateria' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        if (isset($datos['bateria'])) {
            $astronauta->forceFill(['bateria_pct' => (int) round($datos['bateria']), 'bateria_en' => now()])->save();
        }

        $procesador = ProcesadorMediciones::make();
        $items = collect($datos['mediciones'])->sortBy('medido_en')->values();
        $resultado = ['guardadas' => 0, 'duplicadas' => 0, 'rechazadas' => []];

        foreach ($items as $i => $m) {
            $instante = Carbon::parse($m['medido_en']);
            if ($instante->isAfter(now()->addMinutes(5))) {
                $resultado['rechazadas'][] = ['indice' => $i, 'motivo' => 'The timestamp is in the future.'];

                continue;
            }
            try {
                $procesador->registrar($astronauta, $m['indicador'], ['valor' => (float) $m['valor']], 'reloj', $instante);
                $resultado['guardadas']++;
            } catch (ValidationException $e) {
                $motivo = implode(' ', $e->errors()['valores'] ?? ['Rejected.']);
                if ($e instanceof MedicionInvalidaException && $e->tiene(CodigoErrorMedicion::Duplicate)) {
                    $resultado['duplicadas']++; // reenvío de la cola offline: no es un error
                } else {
                    $resultado['rechazadas'][] = ['indice' => $i, 'motivo' => $motivo];
                }
            }
        }

        return response()->json($resultado, 200);
    }

    private function vencido(): JsonResponse
    {
        return response()->json(['mensaje' => 'Token expired. Request a new token (php artisan reloj:token).'], 401);
    }

    /** Devuelve el motivo del rechazo o null si la firma es válida. Firma = HMAC-SHA256(secreto, marca.cuerpo). */
    private function firmaInvalida(Request $request, ?string $secretoCifrado): ?string
    {
        $firma = (string) $request->header('X-Firma');
        $marca = (string) $request->header('X-Marca-Tiempo');
        if ($firma === '' || $marca === '' || ! ctype_digit($marca)) {
            return 'Missing signature or timestamp (X-Firma, X-Marca-Tiempo).';
        }
        if (abs(time() - (int) $marca) > (int) config('mision.reloj_firma_tolerancia_s', 300)) {
            return 'Timestamp is stale or out of range.';
        }
        try {
            $secreto = $secretoCifrado ? Crypt::decryptString($secretoCifrado) : null;
        } catch (DecryptException) {
            $secreto = null;
        }
        if (! $secreto) {
            return 'This device has no signing secret: regenerate the token.';
        }
        $esperada = 'sha256='.hash_hmac('sha256', $marca.'.'.$request->getContent(), $secreto);
        if (! hash_equals($esperada, strtolower($firma))) {
            return 'Invalid signature.';
        }

        return null;
    }
}
