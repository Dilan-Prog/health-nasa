<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla Registrar: un "registro" agrupa las mediciones guardadas a la vez (mismo instante) con los síntomas
 * opcionales, la nota libre y el origen, y las zonas de dolor marcadas en la silueta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_salud', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('origen', 12)->default('manual');   // manual | dispositivo | simulado
            $t->json('sintomas')->nullable();               // ["Mareo", "Fatiga"]
            $t->text('nota')->nullable();
            $t->boolean('aviso_tierra')->default(false);    // se encoló un aviso a tierra con este registro
            $t->timestamp('registrado_en');                 // mismo instante que las mediciones del registro
            $t->timestamps();
            $t->index(['astronauta_id', 'registrado_en']);
        });

        Schema::create('zonas_dolor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('registro_id')->constrained('registros_salud')->cascadeOnDelete();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('musculo', 60);                      // id de App\Support\MusculosCuerpo
            $t->unsignedTinyInteger('intensidad');          // 1-10
            $t->string('tipo', 20);                         // Punzante | Sordo | Quemante | Calambre | Rigidez
            $t->string('desde', 20);                        // Hoy | 1–3 días | Más de 3 días
            $t->text('nota')->nullable();
            $t->timestamps();
            $t->index(['astronauta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_dolor');
        Schema::dropIfExists('registros_salud');
    }
};
