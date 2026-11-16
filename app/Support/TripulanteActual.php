<?php

namespace App\Support;

use App\Models\Astronauta;
use App\Models\Mision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Tripulante y misión "actuales" de la sesión. Se guardan `astronauta_id` y `mision_id`;
 * si no hay valor o ya no es válido, se cae al primero de la tripulación / a la última misión con tripulantes.
 */
class TripulanteActual
{
    /** Misión fijada en sesión (si existe y tiene tripulantes) o la de mayor id con tripulantes. */
    public static function mision(): ?Mision
    {
        $fijada = (int) session('mision_id', 0);
        if ($fijada > 0) {
            $m = Mision::query()->whereHas('tripulantes', fn ($q) => $q->visiblesPara(Auth::user()))->find($fijada);
            if ($m) {
                return $m;
            }
        }

        return Mision::query()->whereHas('tripulantes', fn ($q) => $q->visiblesPara(Auth::user()))->orderByDesc('id')->first();
    }

    /** Tripulación de la misión actual, ordenada por id. */
    public static function tripulacion(): Collection
    {
        $mision = static::mision();
        if (! $mision) {
            return new Collection();
        }

        return Astronauta::visiblesPara(Auth::user())->where('astronautas.mision_id', $mision->id)->orderBy('astronautas.id')->get();
    }

    public static function obtener(): ?Astronauta
    {
        $tripulacion = static::tripulacion();
        $id = (int) session('astronauta_id', 0);

        return $tripulacion->firstWhere('id', $id) ?? $tripulacion->first();
    }

    /** Fija el tripulante; si no pertenece a la misión actual se ignora. */
    public static function fijar(int $id): void
    {
        if (static::tripulacion()->contains('id', $id)) {
            session(['astronauta_id' => $id]);
        }
    }
}
