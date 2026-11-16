<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntecedenteFamiliar extends Model
{
    protected $table = 'antecedentes_familiares';

    protected $fillable = ['astronauta_id', 'parentesco', 'condicion', 'edad_aparicion'];

    protected $casts = ['edad_aparicion' => 'integer'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
