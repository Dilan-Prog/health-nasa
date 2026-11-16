<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Etiqueta de verdad: cuándo empezó y terminó cada escenario inyectado a un tripulante. */
class SimulacionEvento extends Model
{
    protected $table = 'simulacion_eventos';

    protected $fillable = ['astronauta_id', 'escenario', 'inicio_en', 'fin_en', 'motivo_fin'];

    protected $casts = ['inicio_en' => 'datetime', 'fin_en' => 'datetime'];
}
