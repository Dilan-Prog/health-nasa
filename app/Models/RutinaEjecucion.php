<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RutinaEjecucion extends Model
{
    protected $table = 'rutina_ejecuciones';

    protected $fillable = ['rutina_id', 'fecha', 'estado', 'hecha_en', 'nota'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'hecha_en' => 'datetime'];

    public function rutina(): BelongsTo
    {
        return $this->belongsTo(Rutina::class);
    }
}
