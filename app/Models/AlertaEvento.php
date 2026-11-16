<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaEvento extends Model
{
    protected $table = 'alertas_eventos';

    protected $fillable = ['astronauta_id', 'indicador', 'mensaje', 'icono', 'severidad'];
}
