<?php

namespace App\Console\Commands;

use App\Casts\EncryptedFlexible;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CifrarExpediente extends Command
{
    protected $signature = 'expediente:cifrar {--confirmar : Escribe los cambios (sin esta opción solo simula)}';

    protected $description = 'Cifra en la base los datos sensibles del expediente que aún estén en texto plano (idempotente).';

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
        $this->line($escribir ? 'Modo ESCRITURA: se cifrarán los valores en texto plano.' : 'SIMULACIÓN: no se escribe nada (use --confirmar).');

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
            $this->line(sprintf('  %-26s %d fila(s) %s', $tabla, $n, $escribir ? 'cifrada(s)' : 'por cifrar'));
        }

        $this->info($escribir ? "Listo: $total fila(s) cifrada(s)." : "Simulación: $total fila(s) por cifrar.");

        return self::SUCCESS;
    }
}
