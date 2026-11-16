<?php

namespace App\Console\Commands;

use App\Models\SimulacionMotor;
use App\Services\Salud\ControlSimulacion;
use Illuminate\Console\Command;
use Throwable;

class SimuladorVivo extends Command
{
    protected $signature = 'salud:simulador-vivo {--once : Una sola pasada y salir}';

    protected $description = 'Genera mediciones simuladas en vivo para los tripulantes activos (se controla desde /simulacion). Ctrl+C para detener.';

    private bool $detener = false;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('La simulación en vivo está bloqueada en producción.');

            return self::FAILURE;
        }

        if (! SimulacionMotor::actual()->activa) {
            $this->warn('La simulación está apagada: enciéndela en /simulacion. El comando seguirá latiendo.');
        }

        if ($this->option('once')) {
            $this->pasada();

            return self::SUCCESS;
        }

        $this->instalarSenales();
        $this->info('Simulador en vivo iniciado. Ctrl+C para detener.');

        while (! $this->detener) {
            $this->pasada();
            $espera = max(1, (int) SimulacionMotor::actual()->intervalo);
            for ($i = 0; $i < $espera * 10 && ! $this->detener; $i++) {
                usleep(100_000);
            }
        }

        $this->info('Simulador en vivo detenido.');

        return self::SUCCESS;
    }

    private function pasada(): void
    {
        try {
            $r = ControlSimulacion::tick();
            $this->line(sprintf('[%s] activos=%d generadas=%d omitidas=%d errores=%d', now()->format('H:i:s'), $r['activos'], $r['generadas'], $r['omitidas'], $r['errores']));
        } catch (Throwable $e) {
            $this->error('['.now()->format('H:i:s').'] fallo en la pasada: '.$e->getMessage());
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
