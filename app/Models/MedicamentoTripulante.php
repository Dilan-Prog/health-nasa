<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de lo que el tripulante toma (informativo). NO es una prescripción. */
class MedicamentoTripulante extends Model
{
    protected $table = 'medicamentos_tripulante';

    protected $fillable = ['astronauta_id', 'nombre', 'dosis_texto', 'frecuencia', 'motivo', 'desde', 'activo'];

    protected $casts = ['desde' => 'date', 'activo' => 'boolean'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
