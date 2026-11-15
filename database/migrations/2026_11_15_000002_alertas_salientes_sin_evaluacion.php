<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un aviso manual a tierra (botón "Avisar a tierra" o autochequeo) puede no tener una evaluación a la que colgarse:
 * evaluacion_id pasa a ser opcional y se añade astronauta_id para saber de quién es el aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_salientes', function (Blueprint $t) {
            $t->dropForeign(['evaluacion_id']);
        });
        Schema::table('alertas_salientes', function (Blueprint $t) {
            $t->unsignedBigInteger('evaluacion_id')->nullable()->change();
            $t->foreign('evaluacion_id')->references('id')->on('evaluaciones')->cascadeOnDelete();
            $t->foreignId('astronauta_id')->nullable()->after('evaluacion_id')->constrained('astronautas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertas_salientes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('astronauta_id');
        });
        Schema::table('alertas_salientes', function (Blueprint $t) {
            $t->dropForeign(['evaluacion_id']);
        });
        Schema::table('alertas_salientes', function (Blueprint $t) {
            $t->unsignedBigInteger('evaluacion_id')->nullable(false)->change();
            $t->foreign('evaluacion_id')->references('id')->on('evaluaciones')->cascadeOnDelete();
        });
    }
};
