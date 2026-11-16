<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasoClinico extends Model
{
    protected $table = 'casos_clinicos';

    protected $fillable = ['astronauta_id', 'patron', 'severidad', 'evidencia', 'estado', 'detectado_en', 'escalado_en', 'cerrado_en', 'simulacion_evento_id'];

    protected $casts = ['evidencia' => 'array', 'detectado_en' => 'datetime', 'escalado_en' => 'datetime', 'cerrado_en' => 'datetime'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function scopeAbiertos($q)
    {
        return $q->where('estado', 'abierto');
    }
}
