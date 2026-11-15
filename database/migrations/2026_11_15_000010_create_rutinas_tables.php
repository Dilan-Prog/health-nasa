<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla Rutinas: plan recurrente por tripulante (rutinas), su cumplimiento por día (rutina_ejecuciones)
 * y el diario de actividades hechas (actividades: historial reciente y progreso del día).
 * El estado pendiente/atrasada NO se guarda: se deriva de la hora UTC actual frente a la hora de la rutina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rutinas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('nombre', 120);
            $t->string('tipo', 40);
            $t->string('icono', 40);
            $t->string('kind', 4)->default('bien'); // ej | med | bien
            $t->time('hora');
            $t->unsignedSmallInteger('cantidad')->default(0); // minutos (mililitros si el tipo es Hidratación)
            $t->json('dias'); // días ISO de la semana: 1 = lunes … 7 = domingo
            $t->boolean('activa')->default(true);
            $t->string('indicador_clave', 40)->nullable(); // clave de config/panel.php que motiva la rutina
            $t->timestamps();
            $t->index(['astronauta_id', 'activa']);
        });

        Schema::create('rutina_ejecuciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rutina_id')->constrained('rutinas')->cascadeOnDelete();
            $t->date('fecha');
            $t->string('estado', 8); // done | skipped
            $t->timestamp('hecha_en')->nullable();
            $t->string('nota', 200)->nullable();
            $t->timestamps();
            $t->unique(['rutina_id', 'fecha']);
        });

        Schema::create('actividades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->foreignId('rutina_id')->nullable()->constrained('rutinas')->nullOnDelete();
            $t->string('tipo', 40);
            $t->string('nombre', 160);
            $t->dateTime('inicio_en');
            $t->unsignedInteger('cantidad'); // minutos o mililitros según `unidad`
            $t->string('unidad', 4)->default('min'); // min | ml
            $t->string('origen', 16)->default('manual'); // manual | recordatorio
            $t->string('intensidad', 8)->nullable();
            $t->string('nota', 200)->nullable();
            $t->timestamps();
            $t->index(['astronauta_id', 'inicio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
        Schema::dropIfExists('rutina_ejecuciones');
        Schema::dropIfExists('rutinas');
    }
};
