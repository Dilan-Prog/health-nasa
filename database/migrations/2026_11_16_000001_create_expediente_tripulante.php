<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Expediente médico del tripulante: datos de identidad extra, expediente 1:1 y listas hijas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->date('fecha_nacimiento')->nullable();
            $t->string('sexo', 12)->nullable();
            $t->string('nacionalidad')->nullable();
            $t->string('agencia')->nullable();
            $t->date('fecha_ingreso')->nullable();
            $t->string('estado_servicio', 16)->default('activo');
        });

        Schema::create('expedientes_medicos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->unique()->constrained('astronautas')->cascadeOnDelete();
            $t->string('aptitud_vuelo', 24)->default('pendiente');
            $t->text('restricciones')->nullable();
            $t->date('ultimo_examen_en')->nullable();
            $t->unsignedSmallInteger('fc_reposo')->nullable();
            $t->unsignedSmallInteger('fc_maxima')->nullable();
            $t->unsignedSmallInteger('presion_basal_sis')->nullable();
            $t->unsignedSmallInteger('presion_basal_dia')->nullable();
            $t->unsignedSmallInteger('spo2_basal')->nullable();
            $t->decimal('temperatura_basal', 3, 1)->nullable();
            $t->unsignedSmallInteger('glucosa_basal')->nullable();
            $t->decimal('vo2max', 4, 1)->nullable();
            $t->decimal('grasa_corporal_pct', 4, 1)->nullable();
            $t->decimal('densidad_osea_tscore', 3, 1)->nullable();
            $t->decimal('horas_sueno_habituales', 3, 1)->nullable();
            $t->boolean('fumador')->default(false);
            $t->string('alcohol', 16)->nullable();
            $t->unsignedSmallInteger('actividad_fisica_semana_min')->nullable();
            $t->decimal('dosis_radiacion_previa_msv', 7, 1)->default(0);
            $t->json('linea_base')->nullable();
            $t->text('notas')->nullable();
            $t->timestamps();
        });

        Schema::create('alergias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('sustancia');
            $t->string('reaccion')->nullable();
            $t->string('gravedad', 12)->default('leve');
            $t->timestamps();
        });

        Schema::create('condiciones_medicas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('nombre');
            $t->string('estado', 12)->default('activa');
            $t->date('desde')->nullable();
            $t->text('notas')->nullable();
            $t->timestamps();
        });

        Schema::create('medicamentos_tripulante', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('nombre');
            $t->string('dosis_texto')->nullable();
            $t->string('frecuencia')->nullable();
            $t->string('motivo')->nullable();
            $t->date('desde')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('antecedentes_familiares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('parentesco', 24);
            $t->string('condicion');
            $t->unsignedTinyInteger('edad_aparicion')->nullable();
            $t->timestamps();
        });

        Schema::create('contactos_emergencia', function (Blueprint $t) {
            $t->id();
            $t->foreignId('astronauta_id')->constrained('astronautas')->cascadeOnDelete();
            $t->string('nombre');
            $t->string('parentesco', 24);
            $t->string('telefono', 32);
            $t->boolean('principal')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos_emergencia');
        Schema::dropIfExists('antecedentes_familiares');
        Schema::dropIfExists('medicamentos_tripulante');
        Schema::dropIfExists('condiciones_medicas');
        Schema::dropIfExists('alergias');
        Schema::dropIfExists('expedientes_medicos');
        Schema::table('astronautas', function (Blueprint $t) {
            $t->dropColumn(['fecha_nacimiento', 'sexo', 'nacionalidad', 'agencia', 'fecha_ingreso', 'estado_servicio']);
        });
    }
};
