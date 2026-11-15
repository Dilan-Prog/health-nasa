<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Astronauta extends Model
{
    use HasFactory;

    protected $table = 'astronautas';

    protected $fillable = [
        'user_id', 'mision_id', 'nombre', 'mision', 'entorno',
        'rol', 'edad', 'altura_m', 'peso_kg', 'grupo_sanguineo', 'evas',
    ];

    protected $casts = ['altura_m' => 'float', 'peso_kg' => 'float'];

    /** Misión a la que pertenece (no se llama `mision` para no chocar con la columna de texto del mismo nombre). */
    public function misionDeVuelo(): BelongsTo
    {
        return $this->belongsTo(Mision::class, 'mision_id');
    }

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
