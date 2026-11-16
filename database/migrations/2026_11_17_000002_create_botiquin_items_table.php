<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('botiquin_items', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->string('grupo', 60);
            $table->unsignedInteger('stock')->default(0);
            $table->string('unidad', 30)->default('unidades'); // unidad de inventario, NO de dosis
            $table->date('caducidad')->nullable();
            $table->string('contraindicaciones', 255)->default('Pendiente de verificación médica');
            $table->string('nota', 255)->nullable();
            $table->boolean('valor_ejemplo')->default(true); // stock/caducidad son de ejemplo hasta que alguien los edite
            $table->timestamps();

            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('botiquin_items');
    }
};
