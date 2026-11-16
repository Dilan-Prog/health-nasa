<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Registro agrupador de la pantalla Registrar: síntomas, nota, origen y zonas de dolor de una sola toma. */
class RegistroSalud extends Model
{
    protected $table = 'registros_salud';

    public const SINTOMAS = ['Mareo', 'Dolor de cabeza', 'Náuseas', 'Falta de aire', 'Fatiga', 'Alteraciones del sueño'];

    /** Symptom label shown to the user, keyed by the stored (Spanish) value. */
    public const ETIQUETAS_SINTOMA = [
        'Mareo' => 'Dizziness', 'Dolor de cabeza' => 'Headache', 'Náuseas' => 'Nausea',
        'Falta de aire' => 'Shortness of breath', 'Fatiga' => 'Fatigue', 'Alteraciones del sueño' => 'Sleep disturbances',
    ];

    /** English label for display; the stored value is not changed. */
    public static function etiquetaSintoma(string $sintoma): string
    {
        return self::ETIQUETAS_SINTOMA[$sintoma] ?? $sintoma;
    }

    /** clave guardada => [etiqueta, icono] */
    public const ORIGENES = ['manual' => ['Manual', 'edit'], 'dispositivo' => ['Device', 'bluetooth'], 'simulado' => ['Simulated', 'science']];

    protected $fillable = ['astronauta_id', 'origen', 'sintomas', 'nota', 'aviso_tierra', 'registrado_en'];

    protected $casts = ['sintomas' => 'array', 'aviso_tierra' => 'boolean', 'registrado_en' => 'datetime'];

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    public function zonas(): HasMany
    {
        return $this->hasMany(ZonaDolor::class, 'registro_id');
    }
}
