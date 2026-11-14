<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Astronauta extends Model
{
    use HasFactory;

    protected $table = 'astronautas';

    protected $fillable = ['user_id', 'nombre', 'mision', 'entorno'];

    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class);
    }

    /** Retardo de comunicación (min, un trayecto) según el entorno de la misión. */
    public function retardoMin(): int
    {
        return (int) config('umbrales.retardo_min.'.$this->entorno, 0);
    }
}
