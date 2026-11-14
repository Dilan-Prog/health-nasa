<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite por Reverb a todos los dispositivos de la tripulación en la red local.
 * ShouldBroadcastNow: no requiere un worker de colas corriendo para la demo.
 */
class MedicionRegistrada implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $astronautaId,
        public string $indicador,
        public string $estado,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tripulacion');
    }

    public function broadcastAs(): string
    {
        return 'MedicionRegistrada';
    }
}
