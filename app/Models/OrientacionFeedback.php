<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrientacionFeedback extends Model
{
    protected $table = 'orientacion_feedback';

    protected $fillable = ['astronauta_id', 'indicador', 'voto'];
}
