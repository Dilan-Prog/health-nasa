<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('misiones', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->unique();
            // estacion | nave | base_lunar | base_marciana (claves de config/umbrales.php -> retardo_min)
            $t->string('entorno')->default('estacion');
            $t->date('inicio_en')->nullable();
            $t->unsignedSmallInteger('duracion_dias')->default(180);
            $t->timestamps();
        });

        // Se añade una columna nullable: los astronautas ya existentes siguen funcionando sin misión.
        Schema::table('astronautas', function (Blueprint $t) {
            $t->foreignId('mision_id')->nullable()->after('user_id')->constrained('misiones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('mision_id');
        });
        Schema::dropIfExists('misiones');
    }
};
