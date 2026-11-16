<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla Médico de vuelo: mensajes y videoconsultas hacia tierra (con su avance En cola → Enviado → Recibido →
 * Respondido) y el voto (útil / no útil) sobre cada orientación del asistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_tierra', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('tipo', 6);                       // msg | video
            $t->string('titulo');
            $t->text('nota')->nullable();                // texto libre del tripulante
            $t->json('indicadores')->nullable();         // claves de indicador compartidas
            $t->json('adjuntos')->nullable();            // [{l, d, ic}]
            $t->unsignedTinyInteger('etapa')->default(0); // 0 en cola · 1 enviado · 2 recibido · 3 respondido
            $t->boolean('fallida')->default(false);      // sin enlace al intentar enviar
            $t->json('tiempos')->nullable();             // hora UTC (HH:MM) de cada etapa
            $t->text('respuesta')->nullable();
            $t->timestamps();
            $t->index(['astronauta_id', 'created_at']);
        });

        Schema::create('orientacion_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('indicador', 40);
            $t->string('voto', 4);                       // up | down
            $t->timestamps();
            $t->unique(['astronauta_id', 'indicador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientacion_feedback');
        Schema::dropIfExists('consultas_tierra');
    }
};
