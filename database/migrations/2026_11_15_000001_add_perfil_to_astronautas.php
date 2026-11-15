<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->string('rol')->nullable();
            $t->unsignedTinyInteger('edad')->nullable();
            $t->decimal('altura_m', 3, 2)->nullable();
            $t->decimal('peso_kg', 4, 1)->nullable();
            $t->string('grupo_sanguineo', 4)->nullable();
            $t->unsignedSmallInteger('evas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->dropColumn(['rol', 'edad', 'altura_m', 'peso_kg', 'grupo_sanguineo', 'evas']);
        });
    }
};
