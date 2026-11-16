<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera la poda por origen y antigüedad. El índice (astronauta_id, indicador, medido_en)
 * ya lo crea otra migración, por eso aquí no se repite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->index(['origen', 'medido_en'], 'mediciones_origen_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->dropIndex('mediciones_origen_fecha_idx');
        });
    }
};
