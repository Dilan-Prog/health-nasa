<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamenAscan extends Model
{
    protected $table = 'examenes_ascan';

    protected $fillable = ['astronauta_id', 'prueba', 'resultado', 'fecha', 'estado'];

    protected $casts = ['fecha' => 'date'];

    public const ESTADOS = ['pendiente', 'normal', 'anormal'];
}
