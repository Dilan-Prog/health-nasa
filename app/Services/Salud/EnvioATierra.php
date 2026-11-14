<?php

namespace App\Services\Salud;

use App\Models\AlertaSaliente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Vacía la cola de alertas hacia tierra cuando hay enlace. Sin enlace, las alertas esperan. */
class EnvioATierra
{
    private const CLAVE = 'tierra.enlace';

    public function enlaceActivo(): bool
    {
        return (bool) Cache::get(self::CLAVE, config('tierra.enlace'));
    }

    public function alternarEnlace(): bool
    {
        $nuevo = ! $this->enlaceActivo();
        Cache::forever(self::CLAVE, $nuevo);

        return $nuevo;
    }

    public function pendientes(): int
    {
        return AlertaSaliente::where('estado', 'pendiente')->count();
    }

    /** @return int cantidad de alertas enviadas en esta pasada */
    public function enviarPendientes(): int
    {
        if (! $this->enlaceActivo()) {
            return 0;
        }

        $enviadas = 0;
        foreach (AlertaSaliente::where('estado', 'pendiente')->orderBy('id')->get() as $alerta) {
            if ($this->enviar($alerta)) {
                $alerta->update(['estado' => 'enviada', 'enviada_en' => now()]);
                $enviadas++;
            } else {
                // Si el enlace se cae a mitad de camino, se conserva el orden: las demás esperan.
                break;
            }
        }

        return $enviadas;
    }

    private function enviar(AlertaSaliente $alerta): bool
    {
        $url = config('tierra.url');
        if (! $url) {
            Log::info('Alerta a tierra (simulada)', $alerta->carga);

            return true;
        }

        try {
            return Http::timeout(config('tierra.timeout'))->post($url, $alerta->carga)->successful();
        } catch (Throwable $e) {
            Log::warning('Falló el envío a tierra: '.$e->getMessage());

            return false;
        }
    }
}
