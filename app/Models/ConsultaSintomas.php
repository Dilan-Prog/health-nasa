<?php

namespace App\Models;

use App\Casts\EncryptedFlexible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Consulta de síntomas al orientador simulado. `texto` va cifrado en reposo. */
class ConsultaSintomas extends Model
{
    protected $table = 'consultas_sintomas';

    protected $fillable = ['astronauta_id', 'texto', 'entidades', 'urgencia', 'respuesta', 'util'];

    protected $casts = [
        'texto' => EncryptedFlexible::class,
        'entidades' => 'array',
        'respuesta' => 'array',
        'util' => 'boolean',
    ];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
