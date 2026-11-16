<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulacionTripulante extends Model
{
    protected $table = 'simulacion_tripulantes';

    protected $fillable = ['astronauta_id', 'activa', 'fuente', 'semilla', 'escenario', 'escenario_inicio_virtual',
        'escenario_dura_s', 'virtual_en', 'estado', 'ultimo_tick', 'ultimo_error'];

    protected $casts = [
        'activa' => 'boolean', 'semilla' => 'integer', 'escenario_dura_s' => 'integer', 'estado' => 'array',
        'escenario_inicio_virtual' => 'datetime', 'virtual_en' => 'datetime', 'ultimo_tick' => 'datetime',
    ];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    /** Fila del tripulante (se crea con semilla estable derivada de su id si no existe). */
    public static function de(Astronauta $a): self
    {
        return static::firstOrCreate(['astronauta_id' => $a->id], ['semilla' => crc32('astro-'.$a->id)]);
    }

    /** Un tripulante con fuente reloj o con token de dispositivo marcado como reloj nunca se simula. */
    public function simulable(): bool
    {
        return $this->activa && $this->fuente === 'simulado';
    }
}
