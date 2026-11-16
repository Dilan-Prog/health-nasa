<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROL_TRIPULANTE = 'tripulante';
    public const ROL_CMO = 'cmo';
    public const ROL_FLIGHT_SURGEON = 'flight_surgeon';
    public const ROLES = [self::ROL_TRIPULANTE, self::ROL_CMO, self::ROL_FLIGHT_SURGEON];

    public const ETIQUETAS_ROL = [
        self::ROL_TRIPULANTE => 'Tripulante',
        self::ROL_CMO => 'Oficial médico (CMO)',
        self::ROL_FLIGHT_SURGEON => 'Flight surgeon',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /** Astronauta enlazado por `astronautas.user_id`. */
    public function astronauta(): HasOne
    {
        return $this->hasOne(Astronauta::class, 'user_id');
    }

    public function esTripulante(): bool
    {
        return ($this->rol ?? self::ROL_TRIPULANTE) === self::ROL_TRIPULANTE;
    }

    public function esCmo(): bool
    {
        return $this->rol === self::ROL_CMO;
    }

    public function esFlightSurgeon(): bool
    {
        return $this->rol === self::ROL_FLIGHT_SURGEON;
    }

    public function etiquetaRol(): string
    {
        return self::ETIQUETAS_ROL[$this->rol ?? self::ROL_TRIPULANTE] ?? 'Tripulante';
    }
}
