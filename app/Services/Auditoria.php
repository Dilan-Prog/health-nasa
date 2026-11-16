<?php

namespace App\Services;

use App\Models\AuditoriaAcceso;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** Registra accesos a pantallas sensibles. Nunca rompe la pantalla: un fallo se reporta y se ignora. */
class Auditoria
{
    public const ROL_ANONIMO = 'anonimo';

    public static function registrar(string $accion, string $recurso, ?int $recursoId = null, ?int $astronautaId = null): ?AuditoriaAcceso
    {
        try {
            $user = Auth::user();
            $request = request();
            $ip = $request?->ip();
            $ahora = now();

            $mismo = AuditoriaAcceso::query()
                ->where('accion', $accion)->where('recurso', $recurso)
                ->when($recursoId === null, fn ($q) => $q->whereNull('recurso_id'), fn ($q) => $q->where('recurso_id', $recursoId))
                ->when($astronautaId === null, fn ($q) => $q->whereNull('astronauta_id'), fn ($q) => $q->where('astronauta_id', $astronautaId))
                ->where('ocurrido_en', '>=', $ahora->copy()->subMinutes(max(0, (int) config('auditoria.ventana_dedup_min', 5))));
            // Sin sesión se distingue por IP; con sesión, por usuario.
            $user ? $mismo->where('user_id', $user->id) : $mismo->whereNull('user_id')->where('ip', $ip);

            if ($existente = $mismo->first()) {
                return $existente;
            }

            return AuditoriaAcceso::create([
                'user_id' => $user?->id,
                'rol' => $user ? ($user->rol ?? 'tripulante') : self::ROL_ANONIMO,
                'accion' => $accion,
                'recurso' => $recurso,
                'recurso_id' => $recursoId,
                'astronauta_id' => $astronautaId,
                'ip' => $ip,
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'ocurrido_en' => $ahora,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
