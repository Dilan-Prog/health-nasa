<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaBase extends Model
{
    protected $table = 'linea_base';

    protected $fillable = ['astronauta_id', 'metrica', 'valor', 'unidad', 'ventana_desde', 'ventana_hasta', 'fuente'];

    protected $casts = ['valor' => 'float', 'ventana_desde' => 'date', 'ventana_hasta' => 'date'];

    public const METRICAS = ['vo2max', 'dmo_cadera', 'dmo_columna', 'dmo_cuello_femoral', 'grosor_retina_um', 'kcal_objetivo', 'temperatura_basal', 'fuerza'];
}
