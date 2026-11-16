<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ficha ASCAN: pruebas del examen anual. Solo se registran; el sistema no las interpreta. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examenes_ascan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('prueba', 120);
            $t->string('resultado', 255)->nullable();
            $t->date('fecha')->nullable();
            $t->string('estado', 10)->default('pendiente'); // pendiente | normal | anormal
            $t->timestamps();
            $t->unique(['astronauta_id', 'prueba']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examenes_ascan');
    }
};
