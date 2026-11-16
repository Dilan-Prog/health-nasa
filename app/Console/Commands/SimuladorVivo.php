<?php

namespace App\Console\Commands;

use App\Models\SimulacionMotor;
use App\Services\Salud\ControlSimulacion;
use Illuminate\Console\Command;
use Throwable;

class SimuladorVivo extends Command
{
    protected $signature = 'salud:simulador-vivo {--once : Run a single pass and exit}';

    protected $description = 'Generates live simulated measurements for active crew members (controlled from /simulacion). Ctrl+C to stop.';

    private bool $detener = false;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Live simulation is blocked in production.');

            return self::FAILURE;
        }

        if (! SimulacionMotor::actual()->activa) {
            $this->warn('The simulation is off: turn it on at /simulacion. The command will keep running.');
        }

        if ($this->option('once')) {
            $this->pasada();

            return self::SUCCESS;
        }

        $this->instalarSenales();
        $this->info('Live simulator started. Ctrl+C to stop.');

        while (! $this->detener) {
            $this->pasada();
            $espera = max(1, (int) SimulacionMotor::actual()->intervalo);
            for ($i = 0; $i < $espera * 10 && ! $this->detener; $i++) {
                usleep(100_000);
            }
        }

        $this->info('Live simulator stopped.');

        return self::SUCCESS;
    }

    private function pasada(): void
    {
        try {
            $r = ControlSimulacion::tick();
            $this->line(sprintf('[%s] active=%d generated=%d skipped=%d errors=%d', now()->format('H:i:s'), $r['activos'], $r['generadas'], $r['omitidas'], $r['errores']));
        } catch (Throwable $e) {
            $this->error('['.now()->format('H:i:s').'] pass failed: '.$e->getMessage());
        }
    }

    private function instalarSenales(): void
    {
        $parar = function () {
            $this->detener = true;
        };
        if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, $parar);
            pcntl_signal(SIGTERM, $parar);
        } elseif (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler($parar);
        }
    }
}
