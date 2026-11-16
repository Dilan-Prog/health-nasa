<?php

namespace App\Models;

use App\Casts\EncryptedFlexible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alergia extends Model
{
    protected $table = 'alergias';

    protected $fillable = ['astronauta_id', 'sustancia', 'reaccion', 'gravedad'];

    protected $casts = ['sustancia' => EncryptedFlexible::class, 'reaccion' => EncryptedFlexible::class];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
