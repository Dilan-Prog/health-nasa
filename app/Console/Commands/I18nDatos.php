<?php

namespace App\Console\Commands;

use App\Services\I18n\DataI18nMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Translates, in place, the system-generated Spanish text already stored. Simulation by default; --confirmar writes. */
class I18nDatos extends Command
{
    protected $signature = 'i18n:datos
        {--confirmar : Actually updates the rows; without this option it only reports what would change}';

    protected $description = 'Updates in place the stored system-generated Spanish texts to English (simulation by default; --confirmar to write).';

    public function handle(DataI18nMigrator $migrador): int
    {
        $aplicar = (bool) $this->option('confirmar');
        $this->line('Database: '.DB::connection()->getDatabaseName().' | map version '.\App\Services\I18n\DataTextMaps::VERSION);

        $r = $migrador->ejecutar($aplicar);

        $filas = [];
        foreach ($r['entries'] as $e) {
            if ($e['rows'] === 0) {
                continue;
            }
            $filas[] = [
                $e['table'],
                $e['column'].($e['key'] !== null ? ' ['.$e['key'].']' : ''),
                $e['rows'],
                $this->corto($e['example'][0] ?? '').'  ->  '.$this->corto($e['example'][1] ?? ''),
            ];
        }

        if ($filas === []) {
            $this->info('Nothing to change: no stored Spanish text matches the maps.');
        } else {
            $this->table(['Table', 'Column', 'Values', 'Example (before -> after)'], $filas);
        }

        $sinMapear = [];
        foreach ($r['entries'] as $e) {
            foreach ($e['unmapped'] as $texto => $n) {
                $sinMapear[] = [$e['table'], $e['column'].($e['key'] !== null ? ' ['.$e['key'].']' : ''), $n, $this->corto((string) $texto)];
            }
        }
        if ($sinMapear !== []) {
            $this->warn('Unmapped (left untouched, still Spanish):');
            $this->table(['Table', 'Column', 'Count', 'Text'], $sinMapear);
        }

        $this->line('Not touched by design:');
        foreach ($r['notes'] as $nota) {
            $this->line(' - '.$nota);
        }

        $total = (int) $r['changed'];
        if ($aplicar) {
            $this->info("Updated values: {$total}");
        } else {
            $this->info("Values that would change: {$total} (simulation: nothing was written; use --confirmar)");
        }

        return self::SUCCESS;
    }

    private function corto(string $texto): string
    {
        return mb_strlen($texto) > 70 ? mb_substr($texto, 0, 67).'...' : $texto;
    }
}
