<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión de alertas del tripulante (pantalla Alertas): estado (nueva/vista/escalada/resuelta) y nivel de escalamiento
 * de la última alerta de cada indicador, más una bitácora de acciones. Las alertas en sí se derivan de las mediciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_gestion', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('indicador', 40);
            $t->timestamp('medido_en')->nullable();   // medición a la que corresponde la gestión
            $t->string('severidad', 4);               // crit | warn
            $t->string('valor', 40)->nullable();
            $t->text('razon')->nullable();
            $t->string('estado', 10)->default('nueva'); // nueva | vista | escalada | resuelta
            $t->unsignedTinyInteger('nivel')->default(0); // 0 autocuidado · 1 comandante · 2 flight surgeon
            $t->timestamps();
            $t->unique(['astronauta_id', 'indicador']);
        });

        Schema::create('alertas_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('indicador', 40)->nullable();
            $t->string('mensaje');
            $t->string('icono', 40);
            $t->string('severidad', 4);               // crit | warn | ok
            $t->timestamps();
            $t->index(['astronauta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_eventos');
        Schema::dropIfExists('alertas_gestion');
    }
};
