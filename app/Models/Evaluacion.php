<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = ['medicion_id', 'requisito_id', 'estado', 'tendencia', 'accion', 'recomendacion'];

    public function medicion(): BelongsTo
    {
        return $this->belongsTo(Medicion::class);
    }

    public function alerta(): HasOne
    {
        return $this->hasOne(AlertaSaliente::class);
    }
}
