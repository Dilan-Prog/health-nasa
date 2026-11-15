<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Mision extends Model
{
    protected $table = 'misiones';

    protected $fillable = ['nombre', 'entorno', 'inicio_en', 'duracion_dias'];

    protected $casts = ['inicio_en' => 'date'];

    /** Tripulación de la misión (N astronautas). */
    public function tripulantes(): HasMany
    {
        return $this->hasMany(Astronauta::class, 'mision_id');
    }

    /** Todas las mediciones de la tripulación, a través de sus astronautas. */
    public function mediciones(): HasManyThrough
    {
        return $this->hasManyThrough(Medicion::class, Astronauta::class, 'mision_id', 'astronauta_id');
    }
}
