<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medicion extends Model
{
    protected $table = 'mediciones';

    protected $fillable = ['astronauta_id', 'indicador', 'valores', 'medido_en', 'origen', 'calidad', 'calidad_motivo'];

    protected $casts = ['valores' => 'array', 'medido_en' => 'datetime'];

    /** Mediciones de los últimos N días (por defecto `mision.dias_historial`). */
    public function scopeRecientes(Builder $q, ?int $dias = null): Builder
    {
        return $q->where('medido_en', '>=', now()->subDays($dias ?? (int) config('mision.dias_historial', 15)));
    }

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(Evaluacion::class);
    }
}
