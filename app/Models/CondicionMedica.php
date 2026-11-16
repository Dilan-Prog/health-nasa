<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CondicionMedica extends Model
{
    protected $table = 'condiciones_medicas';

    protected $fillable = ['astronauta_id', 'nombre', 'estado', 'desde', 'notas'];

    protected $casts = ['desde' => 'date'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
