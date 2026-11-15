<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rutina extends Model
{
    protected $table = 'rutinas';

    protected $fillable = ['astronauta_id', 'nombre', 'tipo', 'icono', 'kind', 'hora', 'cantidad', 'dias', 'activa', 'indicador_clave'];

    protected $casts = ['dias' => 'array', 'activa' => 'boolean', 'cantidad' => 'integer'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(RutinaEjecucion::class);
    }

    /** Hora en formato HH:MM (la columna `time` llega como HH:MM:SS). */
    public function getHoraAttribute($v): string
    {
        return substr((string) $v, 0, 5);
    }

    /** $diaIso: 1 = lunes … 7 = domingo. */
    public function aplicaEn(int $diaIso): bool
    {
        return in_array($diaIso, array_map('intval', $this->dias ?? []), true);
    }
}
