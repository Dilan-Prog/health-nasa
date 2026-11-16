<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Línea base pre-vuelo del tripulante (por defecto L-180 a L-30 días respecto al inicio de la misión). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linea_base', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('metrica', 40);   // vo2max | dmo_cadera | dmo_columna | dmo_cuello_femoral | grosor_retina_um | kcal_objetivo | temperatura_basal | fuerza
            $t->decimal('valor', 12, 3);
            $t->string('unidad', 20)->nullable();
            $t->date('ventana_desde')->nullable();
            $t->date('ventana_hasta')->nullable();
            $t->string('fuente', 120)->nullable();
            $t->timestamps();
            $t->unique(['astronauta_id', 'metrica']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linea_base');
    }
};
