<?php

namespace App\Models;

use App\Casts\EncryptedFlexible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpedienteMedico extends Model
{
    protected $table = 'expedientes_medicos';

    protected $fillable = [
        'astronauta_id', 'aptitud_vuelo', 'restricciones', 'ultimo_examen_en',
        'fc_reposo', 'fc_maxima', 'presion_basal_sis', 'presion_basal_dia', 'spo2_basal',
        'temperatura_basal', 'glucosa_basal', 'vo2max', 'grasa_corporal_pct',
        'densidad_osea_tscore', 'horas_sueno_habituales', 'fumador', 'alcohol',
        'actividad_fisica_semana_min', 'dosis_radiacion_previa_msv', 'linea_base', 'notas',
    ];

    protected $casts = [
        'ultimo_examen_en' => 'date',
        'temperatura_basal' => 'float',
        'vo2max' => 'float',
        'grasa_corporal_pct' => 'float',
        'densidad_osea_tscore' => 'float',
        'horas_sueno_habituales' => 'float',
        'dosis_radiacion_previa_msv' => 'float',
        'fumador' => 'boolean',
        'linea_base' => 'array',
        'restricciones' => EncryptedFlexible::class,
        'notas' => EncryptedFlexible::class,
    ];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }
}
