<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    public const TIPOS = [
        'norma' => 'NASA-STD-3001 Vol. 1 Rev. C',
        'hrp' => 'HRP (observation)',
        'guia' => 'Internal guide (Medical data)',
        'referencia' => 'Clinical reference',
    ];

    protected $table = 'requisitos';

    protected $fillable = [
        'clave', 'codigo', 'texto', 'dominio', 'operador', 'valor', 'valor_max',
        'unidad', 'revision', 'fuente_url', 'tipo', 'validado',
    ];

    protected $casts = [
        'valor' => 'float',
        'valor_max' => 'float',
        'validado' => 'boolean',
    ];

    public function scopeTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo);
    }

    public function scopeNorma(Builder $q): Builder
    {
        return $q->where('tipo', 'norma');
    }

    public function scopeHrp(Builder $q): Builder
    {
        return $q->where('tipo', 'hrp');
    }

    public function scopeGuia(Builder $q): Builder
    {
        return $q->where('tipo', 'guia');
    }

    public function scopeReferencia(Builder $q): Builder
    {
        return $q->where('tipo', 'referencia');
    }

    public function scopeDominio(Builder $q, string $dominio): Builder
    {
        return $q->where('dominio', $dominio);
    }

    /** Etiqueta visible de la fuente según el tipo. */
    public function getFuenteEtiquetaAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? (string) $this->tipo;
    }
}
