<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medicion extends Model
{
    protected $table = 'mediciones';

    protected $fillable = ['astronauta_id', 'indicador', 'valores', 'medido_en', 'origen'];

    protected $casts = ['valores' => 'array', 'medido_en' => 'datetime'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(Evaluacion::class);
    }
}
