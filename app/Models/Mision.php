<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Mision extends Model
{
    protected $table = 'misiones';

    protected $fillable = ['nombre', 'entorno', 'care_level', 'mission_phase', 'inicio_en', 'duracion_dias'];

    protected $casts = ['inicio_en' => 'date', 'care_level' => 'integer'];

    /** Nivel de atención (NASA-STD-3001 Vol. 1): etiqueta mostrada. Los niveles 4 y 5 comparten triaje autónomo local. */
    public const CARE_LEVELS = [
        3 => 'Nivel 3 · Telemedicina síncrona',
        4 => 'Nivel 4 · Triaje autónomo local',
        5 => 'Nivel 5 · Triaje autónomo local',
    ];

    public const FASES = [
        'prevuelo' => 'Prevuelo',
        'vuelo' => 'Vuelo',
        'posvuelo' => 'Posvuelo',
    ];

    public function nivelAtencion(): int
    {
        return (int) ($this->care_level ?: 3);
    }

    /** Triaje autónomo local (care_level >= 4): sin videoconsulta síncrona con tierra. */
    public function triajeAutonomo(): bool
    {
        return $this->nivelAtencion() >= 4;
    }

    public function etiquetaCareLevel(): string
    {
        return self::CARE_LEVELS[$this->nivelAtencion()] ?? 'Nivel '.$this->nivelAtencion();
    }

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
