<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            // Vencimiento del token (null = sin vencimiento, tokens anteriores a esta migración).
            $t->timestamp('token_vence_en')->nullable()->after('token_dispositivo');
            // Token anterior (sha256) y su vencimiento de gracia al regenerar.
            $t->string('token_previo', 64)->nullable()->unique()->after('token_vence_en');
            $t->timestamp('token_previo_vence_en')->nullable()->after('token_previo');
            // Secretos de firma del lote, cifrados con Crypt (actual y el del token previo durante la gracia).
            $t->text('secreto_firma')->nullable()->after('token_previo_vence_en');
            $t->text('secreto_firma_previo')->nullable()->after('secreto_firma');
            // Último nivel de batería informado por el reloj.
            $t->unsignedTinyInteger('bateria_pct')->nullable()->after('secreto_firma_previo');
            $t->timestamp('bateria_en')->nullable()->after('bateria_pct');
        });
    }

    public function down(): void
    {
        Schema::table('astronautas', function (Blueprint $t) {
            $t->dropUnique(['token_previo']);
            $t->dropColumn(['token_vence_en', 'token_previo', 'token_previo_vence_en', 'secreto_firma', 'secreto_firma_previo', 'bateria_pct', 'bateria_en']);
        });
    }
};
