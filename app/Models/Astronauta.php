<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * PUNTO CENTRAL de visibilidad. Con `mision.exigir_login` en false devuelve todos (demo sin login).
     * Con login exigido: sin usuario nada; flight_surgeon todo; cmo la tripulación de su misión
     * (la de su astronauta enlazado); tripulante solo el astronauta enlazado a su usuario.
     */
    public function scopeVisiblesPara(Builder $query, ?User $user): Builder
    {
        if (! config('mision.exigir_login', false)) {
            return $query;
        }
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->esFlightSurgeon()) {
            return $query;
        }
        if ($user->esCmo()) {
            $misionId = static::query()->where('user_id', $user->id)->value('mision_id');

            return $misionId ? $query->where('astronautas.mision_id', $misionId) : $query->whereRaw('1 = 0');
        }

        return $query->where('astronautas.user_id', $user->id);
    }

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
