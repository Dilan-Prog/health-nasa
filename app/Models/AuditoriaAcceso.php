<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro de acceso: solo metadatos (quién, qué recurso, cuándo). Sin valores médicos. */
class AuditoriaAcceso extends Model
{
    protected $table = 'auditoria_accesos';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['ocurrido_en' => 'datetime'];
}
