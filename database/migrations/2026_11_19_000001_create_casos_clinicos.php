<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Casos que abre el detector de patrones (hipótesis a partir de señales; no son diagnósticos). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_clinicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('patron', 40);                  // clave de config/patrones.php
            $t->string('severidad', 10);               // precaucion | critico
            $t->json('evidencia')->nullable();         // valores y ventana usados
            $t->string('estado', 10)->default('abierto'); // abierto | cerrado
            $t->timestamp('detectado_en');
            $t->timestamp('escalado_en')->nullable();  // cuándo se encoló el aviso a tierra
            $t->timestamp('cerrado_en')->nullable();
            $t->unsignedBigInteger('simulacion_evento_id')->nullable();
            $t->timestamps();
            $t->index(['astronauta_id', 'estado']);
            $t->index(['astronauta_id', 'patron', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_clinicos');
    }
};
