<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Astronauta extends Model
{
    use HasFactory;

    protected $table = 'astronautas';

    protected $fillable = [
        'user_id', 'mision_id', 'nombre', 'mision', 'entorno',
        'rol', 'edad', 'altura_m', 'peso_kg', 'grupo_sanguineo', 'evas',
        'fecha_nacimiento', 'sexo', 'nacionalidad', 'agencia', 'fecha_ingreso', 'estado_servicio',
    ];

    protected $casts = [
        'altura_m' => 'float',
        'peso_kg' => 'float',
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
    ];

    /** Token en claro, solo disponible en memoria justo después del alta (nunca se guarda). */
    public ?string $tokenPlano = null;

    /** Misión a la que pertenece (no se llama `mision` para no chocar con la columna de texto del mismo nombre). */
    public function misionDeVuelo(): BelongsTo
    {
        return $this->belongsTo(Mision::class, 'mision_id');
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class);
    }

    public function expediente(): HasOne
    {
        return $this->hasOne(ExpedienteMedico::class);
    }

    public function alergias(): HasMany
    {
        return $this->hasMany(Alergia::class);
    }

    public function condiciones(): HasMany
    {
        return $this->hasMany(CondicionMedica::class);
    }

    public function medicamentos(): HasMany
    {
        return $this->hasMany(MedicamentoTripulante::class);
    }

    public function antecedentes(): HasMany
    {
        return $this->hasMany(AntecedenteFamiliar::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoEmergencia::class);
    }

    /** Edad en años: se calcula desde fecha_nacimiento; si no hay, usa la columna `edad`. */
    public function edadCalculada(): ?int
    {
        if ($this->fecha_nacimiento) {
            return (int) $this->fecha_nacimiento->diffInYears(now());
        }

        return $this->edad !== null ? (int) $this->edad : null;
    }

    /** Índice de masa corporal (kg/m²) con 1 decimal; null si falta talla o peso. */
    public function imc(): ?float
    {
        $h = (float) $this->altura_m;
        $p = (float) $this->peso_kg;

        return $h > 0 && $p > 0 ? round($p / ($h * $h), 1) : null;
    }

    /** Retardo de comunicación (min, un trayecto) según el entorno de la misión. */
    public function retardoMin(): int
    {
        return (int) config('umbrales.retardo_min.'.$this->entorno, 0);
    }
}
