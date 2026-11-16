<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fila única (id 1) con el interruptor general, la velocidad y el latido del comando salud:simulador-vivo. */
class SimulacionMotor extends Model
{
    protected $table = 'simulacion_motor';

    protected $fillable = ['activa', 'velocidad', 'intervalo', 'latido_en'];

    protected $casts = ['activa' => 'boolean', 'velocidad' => 'integer', 'intervalo' => 'integer', 'latido_en' => 'datetime'];

    public const VELOCIDADES = [1, 60, 360];

    public static function actual(): self
    {
        return static::find(1) ?? static::forceCreate(['id' => 1, 'activa' => false, 'velocidad' => 60, 'intervalo' => 5]);
    }

    /** El comando está vivo si hubo latido en los últimos max(3 × intervalo, 15) s. */
    public function corriendo(): bool
    {
        return $this->latido_en !== null
            && $this->latido_en->gte(now()->subSeconds(max(15, 3 * $this->intervalo)));
    }
}
