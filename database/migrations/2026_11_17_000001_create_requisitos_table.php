<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('texto', 500);
            $table->string('dominio', 60);
            $table->string('operador', 20)->nullable();
            $table->decimal('valor', 12, 3)->nullable();
            $table->decimal('valor_max', 12, 3)->nullable();
            $table->string('unidad', 40)->nullable();
            $table->string('revision')->nullable();
            $table->string('fuente_url', 500)->nullable();
            $table->string('tipo', 20); // norma|hrp|guia|referencia
            $table->boolean('validado')->default(false);
            $table->timestamps();

            $table->index(['tipo', 'dominio']);
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos');
    }
};
