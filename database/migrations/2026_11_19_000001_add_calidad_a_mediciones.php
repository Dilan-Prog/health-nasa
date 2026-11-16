<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->string('calidad', 12)->default('ok'); // ok | sospechosa
            $t->string('calidad_motivo', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mediciones', function (Blueprint $t) {
            $t->dropColumn(['calidad', 'calidad_motivo']);
        });
    }
};
