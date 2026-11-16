<?php

namespace App\Console\Commands;

use App\Models\Astronauta;
use App\Models\Mision;
use App\Services\Salud\ProcesadorMediciones;
use App\Services\Salud\SimuladorMediciones;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Crea una misión con N tripulantes y les genera un historial de mediciones simuladas.
 * Cada medición pasa por el motor (validar -> evaluar -> guardar -> encolar alerta), igual que una real.
 */
class SimularMision extends Command
{
    protected $signature = 'salud:simular-mision
        {--nombre=Misión Demo : Mission name}
        {--entorno=estacion : estacion | nave | base_lunar | base_marciana}
        {--tripulantes=4 : Number of crew members (N)}
        {--dias=30 : Days of history to generate}
        {--semilla= : Seed for repeatable results}
        {--reemplazar : If the mission already exists, deletes and recreates it}';

    protected $description = 'Creates a mission with N crew members and their simulated measurement history';

    /** Nombres ficticios. */
    private const NOMBRES = [
        'Cmdte. Elena Vargas', 'Ing. Marco Reyes', 'Dra. Ana Solís', 'Esp. Tomás Herrera',
        'Dra. Lucía Fernández', 'Ing. Diego Morales', 'Esp. Camila Ortega', 'Dr. Mateo Rivas',
    ];

    /** Indicadores donde subir es empeorar: son los que se usan para provocar deriva. */
    private const DERIVABLES = ['presion_arterial', 'glucosa', 'temperatura', 'frecuencia_cardiaca'];

    public function handle(): int
    {
        $config = config('umbrales');
        $nombre = (string) $this->option('nombre');
        $entorno = (string) $this->option('entorno');
        $n = (int) $this->option('tripulantes');
        $dias = (int) $this->option('dias');

        if (! array_key_exists($entorno, $config['retardo_min'])) {
            $this->error("Invalid environment. Use: ".implode(', ', array_keys($config['retardo_min'])));

            return self::FAILURE;
        }
        if ($n < 1 || $n > 50 || $dias < 1 || $dias > 730) {
            $this->error('--tripulantes must be between 1 and 50, and --dias between 1 and 730.');

            return self::FAILURE;
        }

        $existente = Mision::where('nombre', $nombre)->first();
        if ($existente && ! $this->option('reemplazar')) {
            $this->error("Mission \"{$nombre}\" already exists. Use another --nombre or add --reemplazar.");

            return self::FAILURE;
        }
        if ($existente) {
            $existente->tripulantes()->delete(); // borra en cascada sus mediciones y evaluaciones
            $existente->delete();
        }

        if ($this->option('semilla') !== null && $this->option('semilla') !== '') {
            mt_srand((int) $this->option('semilla'));
        }

        $inicio = now()->subDays($dias)->startOfDay();
        $mision = Mision::create([
            'nombre' => $nombre, 'entorno' => $entorno,
            'inicio_en' => $inicio->toDateString(), 'duracion_dias' => max($dias, 180),
        ]);

        $sim = new SimuladorMediciones($config);
        $proc = ProcesadorMediciones::make();
        $claves = array_keys($config['indicadores']);
        $resumen = [];

        for ($i = 0; $i < $n; $i++) {
            $astronauta = Astronauta::create([
                'mision_id' => $mision->id,
                'nombre' => self::NOMBRES[$i] ?? 'Tripulante '.($i + 1),
                'mision' => $nombre,
                'entorno' => $entorno,
            ]);

            // El primero siempre es estable; uno de cada tres empeora un indicador durante la misión.
            $derivaClave = ($i % 3 === 1) ? self::DERIVABLES[mt_rand(0, count(self::DERIVABLES) - 1)] : null;
            $derivaMax = $derivaClave ? 0.9 : 0.0;
            $guardadas = 0;

            for ($d = 0; $d < $dias; $d++) {
                $progreso = $dias > 1 ? max(0.0, min(1.0, ($d - $dias * 0.4) / ($dias * 0.6))) : 0.0;
                foreach ($claves as $k => $clave) {
                    if (($config['indicadores'][$clave]['frecuencia'] ?? 'diaria') === 'semanal' && $d % 7 !== 0) {
                        continue;
                    }
                    $deriva = $clave === $derivaClave ? $derivaMax * $progreso : 0.0;
                    $instante = $inicio->copy()->addDays($d)->setTime(8, 0)->addMinutes($k * 3);
                    try {
                        $proc->registrar($astronauta, $clave, $sim->generar($clave, $deriva), 'simulado', $instante, false);
                        $guardadas++;
                    } catch (ValidationException) {
                        // Duplicado exacto: se omite.
                    }
                }
            }

            $estados = $astronauta->mediciones()->join('evaluaciones', 'evaluaciones.medicion_id', '=', 'mediciones.id')
                ->selectRaw('evaluaciones.estado as estado, count(*) as total')->groupBy('evaluaciones.estado')
                ->pluck('total', 'estado');
            $resumen[] = [
                $astronauta->nombre,
                $derivaClave ? $config['indicadores'][$derivaClave]['nombre'] : '—',
                $guardadas,
                (int) ($estados['normal'] ?? 0),
                (int) ($estados['precaucion'] ?? 0),
                (int) ($estados['critico'] ?? 0),
            ];
        }

        $this->info("Mission \"{$nombre}\" ({$entorno}): {$n} crew members, {$dias} days of history.");
        $this->table(['Crew member', 'Simulated drift', 'Measurements', 'Normal', 'Caution', 'Critical'], $resumen);
        $this->line('Alerts pending to the ground: '.app(\App\Services\Salud\EnvioATierra::class)->pendientes());

        return self::SUCCESS;
    }
}
