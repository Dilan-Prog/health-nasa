<?php

namespace App\Models;

use App\Casts\EncryptedFlexible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactoEmergencia extends Model
{
    protected $table = 'contactos_emergencia';

    protected $fillable = ['astronauta_id', 'nombre', 'parentesco', 'telefono', 'principal'];

    protected $casts = ['principal' => 'boolean', 'nombre' => EncryptedFlexible::class, 'telefono' => EncryptedFlexible::class];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
