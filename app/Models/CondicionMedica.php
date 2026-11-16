<?php

namespace App\Models;

use App\Casts\EncryptedFlexible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CondicionMedica extends Model
{
    protected $table = 'condiciones_medicas';

    protected $fillable = ['astronauta_id', 'nombre', 'estado', 'desde', 'notas'];

    protected $casts = ['desde' => 'date', 'nombre' => EncryptedFlexible::class, 'notas' => EncryptedFlexible::class];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
