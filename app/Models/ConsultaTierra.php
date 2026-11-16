<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultaTierra extends Model
{
    protected $table = 'consultas_tierra';

    protected $fillable = ['astronauta_id', 'tipo', 'titulo', 'nota', 'indicadores', 'adjuntos', 'etapa', 'fallida', 'tiempos', 'respuesta'];

    protected $casts = ['indicadores' => 'array', 'adjuntos' => 'array', 'tiempos' => 'array', 'etapa' => 'integer', 'fallida' => 'boolean'];

    public const ETAPAS = ['En cola', 'Enviado', 'Recibido', 'Respondido'];
}
