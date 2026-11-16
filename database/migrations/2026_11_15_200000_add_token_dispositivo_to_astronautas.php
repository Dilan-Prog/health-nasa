<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Token (guardado como hash SHA-256) con el que el reloj/teléfono de un tripulante envía mediciones. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->string('token_dispositivo', 64)->nullable()->unique()->after('evas');
        });
    }

    public function down(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->dropColumn('token_dispositivo');
        });
    }
};
