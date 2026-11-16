<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaGestion extends Model
{
    protected $table = 'alertas_gestion';

    protected $fillable = ['astronauta_id', 'indicador', 'medido_en', 'severidad', 'valor', 'razon', 'estado', 'nivel'];

    protected $casts = ['medido_en' => 'datetime', 'nivel' => 'integer'];
}
