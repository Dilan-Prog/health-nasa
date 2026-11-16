<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cada evaluación cita el requisito (norma, proyección HRP, guía o referencia) con el que se evaluó. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $t) {
            $t->foreignId('requisito_id')->nullable()->after('medicion_id')->constrained('requisitos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $t) {
            $t->dropConstrainedForeignId('requisito_id');
        });
    }
};
