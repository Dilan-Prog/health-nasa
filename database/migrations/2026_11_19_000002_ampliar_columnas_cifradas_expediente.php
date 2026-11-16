<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El cifrado (App\Casts\EncryptedFlexible) alarga el valor guardado: las columnas string se quedan cortas.
 * Se pasan a TEXT conservando su nulabilidad. SQL directo para no depender de doctrine/dbal.
 */
return new class extends Migration
{
    /** tabla => [columna => nullable] */
    private const COLUMNAS = [
        'alergias' => ['sustancia' => false, 'reaccion' => true],
        'condiciones_medicas' => ['nombre' => false, 'notas' => true],
        'medicamentos_tripulante' => ['nombre' => false, 'dosis_texto' => true, 'frecuencia' => true, 'motivo' => true],
        'antecedentes_familiares' => ['condicion' => false],
        'contactos_emergencia' => ['nombre' => false, 'telefono' => false],
        'expedientes_medicos' => ['restricciones' => true, 'notas' => true],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            foreach ($columnas as $col => $nullable) {
                DB::statement("ALTER TABLE `$tabla` MODIFY `$col` TEXT ".($nullable ? 'NULL' : 'NOT NULL'));
            }
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: volver a string podría truncar valores cifrados.
    }
};
