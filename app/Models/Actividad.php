<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Actividad hecha (diario): alimenta el historial reciente y las metas del día (ejercicio, hidratación, sueño). */
class Actividad extends Model
{
    protected $table = 'actividades';

    protected $fillable = ['astronauta_id', 'rutina_id', 'tipo', 'nombre', 'inicio_en', 'cantidad', 'unidad', 'origen', 'intensidad', 'nota'];

    protected $casts = ['inicio_en' => 'datetime', 'cantidad' => 'integer'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function rutina(): BelongsTo
    {
        return $this->belongsTo(Rutina::class);
    }
}
