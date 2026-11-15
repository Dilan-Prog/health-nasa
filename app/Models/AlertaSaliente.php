<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaSaliente extends Model
{
    protected $table = 'alertas_salientes';

    protected $fillable = ['evaluacion_id', 'astronauta_id', 'carga', 'estado', 'enviada_en'];

    protected $casts = ['carga' => 'array', 'enviada_en' => 'datetime'];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }
}
