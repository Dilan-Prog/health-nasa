<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Consultas de síntomas al orientador simulado: el texto libre va cifrado (cast EncryptedFlexible en el modelo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_sintomas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->text('texto');                       // cifrado
            $t->json('entidades')->nullable();      // análisis estructurado (síntomas, negados, banderas rojas)
            $t->string('urgencia', 20);             // emergencia | urgente | autocuidado | observacion
            $t->json('respuesta')->nullable();      // resultado del razonador
            $t->boolean('util')->nullable();        // "Me ayudó" / "No me ayudó"
            $t->timestamps();
            $t->index(['astronauta_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas_sintomas');
    }
};
