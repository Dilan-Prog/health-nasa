<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de misión. care_level: 3 telemedicina síncrona · 4 y 5 triaje autónomo local.
 * Valor inicial por entorno (supuesto del proyecto, no de la norma): estacion 3, nave y base_lunar 4, base_marciana 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->unsignedTinyInteger('care_level')->default(3)->after('entorno');
            $t->string('mission_phase', 10)->default('vuelo')->after('care_level'); // prevuelo | vuelo | posvuelo
        });
        DB::table('misiones')->whereIn('entorno', ['nave', 'base_lunar'])->update(['care_level' => 4]);
        DB::table('misiones')->where('entorno', 'base_marciana')->update(['care_level' => 5]);
    }

    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->dropColumn(['care_level', 'mission_phase']);
        });
    }
};
