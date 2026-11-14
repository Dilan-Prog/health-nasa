<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('astronautas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nombre');
            $t->string('mision')->nullable();
            // estacion | nave | base_lunar | base_marciana (ver config/umbrales.php -> retardo_min)
            $t->string('entorno')->default('estacion');
            $t->timestamps();
        });

        Schema::create('mediciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('indicador', 40);          // clave de config/umbrales.php
            $t->json('valores');                  // {"valor":72} o {"sistolica":120,"diastolica":80}
            $t->timestamp('medido_en');
            $t->string('origen', 12)->default('manual'); // manual | simulado
            $t->timestamps();
            $t->index(['astronauta_id', 'indicador', 'medido_en']);
        });

        Schema::create('evaluaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('medicion_id')->constrained('mediciones')->cascadeOnDelete();
            $t->string('estado', 12);             // normal | precaucion | critico
            $t->string('tendencia', 10)->default('estable');
            $t->string('accion', 24);             // ninguno | recomendar | alertar_tripulacion | contactar_tierra
            $t->text('recomendacion');
            $t->timestamps();
            $t->index('estado');
        });

        Schema::create('alertas_salientes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $t->json('carga');                    // resumen que viaja a tierra
            $t->string('estado', 10)->default('pendiente'); // pendiente | enviada
            $t->timestamp('enviada_en')->nullable();
            $t->timestamps();
            $t->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_salientes');
        Schema::dropIfExists('evaluaciones');
        Schema::dropIfExists('mediciones');
        Schema::dropIfExists('astronautas');
    }
};
