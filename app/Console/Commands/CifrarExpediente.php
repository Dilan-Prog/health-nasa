<?php

namespace App\Console\Commands;

use App\Casts\EncryptedFlexible;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CifrarExpediente extends Command
{
    protected $signature = 'expediente:cifrar {--confirmar : Writes the changes (without this option it only simulates)}';

    protected $description = 'Encrypts sensitive medical record data in the database that is still in plain text (idempotent).';

    /** tabla => columnas cifradas (deben coincidir con los casts EncryptedFlexible de los modelos). */
    public const COLUMNAS = [
        'expedientes_medicos' => ['restricciones', 'notas'],
        'alergias' => ['sustancia', 'reaccion'],
        'condiciones_medicas' => ['nombre', 'notas'],
        'medicamentos_tripulante' => ['nombre', 'dosis_texto', 'frecuencia', 'motivo'],
        'antecedentes_familiares' => ['condicion'],
        'contactos_emergencia' => ['nombre', 'telefono'],
    ];

    public function handle(): int
    {
        $escribir = (bool) $this->option('confirmar');
        $this->line($escribir ? 'WRITE mode: plain-text values will be encrypted.' : 'SIMULATION: nothing is written (use --confirmar).');

        $total = 0;
        foreach (self::COLUMNAS as $tabla => $columnas) {
            $n = 0;
            // Query builder: lee el valor crudo de la base, sin pasar por los casts (evita doble cifrado).
            DB::table($tabla)->select(['id', ...$columnas])->orderBy('id')->chunkById(200, function ($filas) use ($tabla, $columnas, $escribir, &$n) {
                foreach ($filas as $fila) {
                    $cambios = [];
                    foreach ($columnas as $col) {
                        $v = $fila->$col;
                        if ($v !== null && ! EncryptedFlexible::estaCifrado((string) $v)) {
                            $cambios[$col] = Crypt::encryptString((string) $v);
                        }
                    }
                    if ($cambios) {
                        $n++;
                        if ($escribir) {
                            DB::table($tabla)->where('id', $fila->id)->update($cambios);
                        }
                    }
                }
            });
            $total += $n;
            $this->line(sprintf('  %-26s %d row(s) %s', $tabla, $n, $escribir ? 'encrypted' : 'to encrypt'));
        }

        $this->info($escribir ? "Done: $total row(s) encrypted." : "Simulation: $total row(s) to encrypt.");

        return self::SUCCESS;
    }
}
