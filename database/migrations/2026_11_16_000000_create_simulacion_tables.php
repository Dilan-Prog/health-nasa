<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulación en vivo (salud:simulador-vivo + pantalla /simulacion). El estado vive en la base, no en memoria:
 * una fila de motor, una fila de estado por tripulante y el registro de escenarios (etiqueta de verdad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulacion_motor', function (Blueprint $t) {
            $t->id();
            $t->boolean('activa')->default(false);          // interruptor general
            $t->unsignedSmallInteger('velocidad')->default(60);   // factor del reloj virtual: 1 | 60 | 360
            $t->unsignedSmallInteger('intervalo')->default(5);    // segundos reales entre ticks
            $t->timestamp('latido_en')->nullable();         // último tick del comando
            $t->timestamps();
        });

        Schema::create('simulacion_tripulantes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->unique()->constrained('astronautas')->cascadeOnDelete();
            $t->boolean('activa')->default(false);
            $t->string('fuente', 10)->default('simulado');  // simulado | reloj (reloj nunca se simula)
            $t->unsignedBigInteger('semilla');
            $t->string('escenario', 40)->nullable();        // clave en config/simulacion.php
            $t->timestamp('escenario_inicio_virtual')->nullable(); // instante virtual en que empezó
            $t->unsignedInteger('escenario_dura_s')->nullable();   // duración total en segundos virtuales
            $t->timestamp('virtual_en')->nullable();        // reloj virtual actual del tripulante
            $t->json('estado')->nullable();                 // estado del ruido AR(1), última lectura por indicador, etc.
            $t->timestamp('ultimo_tick')->nullable();       // hora real del último tick
            $t->text('ultimo_error')->nullable();
            $t->timestamps();
        });

        Schema::create('simulacion_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('escenario', 40);
            $t->timestamp('inicio_en');                     // hora real
            $t->timestamp('fin_en')->nullable();            // null mientras sigue en curso
            $t->string('motivo_fin', 12)->nullable();       // completo | cancelado
            $t->timestamps();
            $t->index(['astronauta_id', 'inicio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulacion_eventos');
        Schema::dropIfExists('simulacion_tripulantes');
        Schema::dropIfExists('simulacion_motor');
    }
};
