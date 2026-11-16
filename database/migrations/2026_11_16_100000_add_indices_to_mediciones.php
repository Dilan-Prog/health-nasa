<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Acelera las lecturas por tripulante/indicador/fecha y la poda por origen y antigüedad. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->index(['astronauta_id', 'indicador', 'medido_en'], 'mediciones_astro_ind_fecha_idx');
            $t->index(['origen', 'medido_en'], 'mediciones_origen_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->dropIndex('mediciones_astro_ind_fecha_idx');
            $t->dropIndex('mediciones_origen_fecha_idx');
        });
    }
};
