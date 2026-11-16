<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Auditoría de accesos: solo quién accedió a qué y cuándo. Nunca se guardan valores médicos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_accesos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('rol', 30);
            $t->string('accion', 30);
            $t->string('recurso', 40);
            $t->unsignedBigInteger('recurso_id')->nullable();
            $t->unsignedBigInteger('astronauta_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('ocurrido_en');
            $t->index('ocurrido_en');
            $t->index(['user_id', 'accion', 'recurso', 'recurso_id', 'ocurrido_en'], 'auditoria_dedup_idx');
            $t->index('astronauta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_accesos');
    }
};
