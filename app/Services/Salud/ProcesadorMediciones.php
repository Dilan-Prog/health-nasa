<?php

namespace App\Services\Salud;

use App\Events\MedicionRegistrada;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Medicion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquesta el motor de reglas: valida, evalúa, guarda, encola la alerta a tierra y emite el evento.
 */
class ProcesadorMediciones
{
    private ValidadorMedicion $validador;
    private EvaluadorEstado $evaluador;
    private LineaBasePersonal $lineaBase;
    private ReglasEscalamiento $reglas;
    private CatalogoRecomendaciones $catalogo;

    public function __construct(private array $config)
    {
        $this->validador = new ValidadorMedicion($config);
        $this->evaluador = new EvaluadorEstado($config);
        $this->lineaBase = new LineaBasePersonal();
        $this->reglas = new ReglasEscalamiento();
        $this->catalogo = new CatalogoRecomendaciones();
    }

    public static function make(): self
    {
        return new self(config('umbrales'));
    }

    public function registrar(
        Astronauta $astronauta,
        string $clave,
        array $valores,
        string $origen = 'manual',
        ?CarbonInterface $instante = null,
        bool $emitir = true,
        bool $usarLineaBase = true,
    ): Evaluacion {
        $instante ??= now();

        $errores = $this->validador->validar($clave, $valores);
        if ($errores === []) {
            $previas = $astronauta->mediciones()->where('indicador', $clave)
                ->where('medido_en', '>=', $instante->copy()->subMinute())
                ->get()
                ->map(fn (Medicion $m) => ['instante' => $m->medido_en->timestamp, 'valores' => $m->valores])
                ->all();
            if ($this->validador->esDuplicada($valores, $instante->timestamp, $previas)) {
                $errores[] = 'Medición duplicada: ya se registró el mismo valor hace menos de un minuto.';
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages(['valores' => $errores]);
        }

        $historial = $astronauta->mediciones()->where('indicador', $clave)
            ->where('medido_en', '<', $instante)
            ->orderByDesc('medido_en')->limit(30)->get()->reverse()->values()
            ->map(fn (Medicion $m) => $this->principal($m->valores))->all();
        $actual = $this->principal($valores);

        $estado = $this->evaluador->evaluar($clave, $valores);
        if ($usarLineaBase) { // se desactiva al sembrar datos de ejemplo con estados fijos
            $estado = $this->lineaBase->ajustar($estado, $this->lineaBase->calcular($historial, $actual));
        }
        $tendencia = $this->evaluador->tendencia(array_slice([...$historial, $actual], -8));
        $decision = $this->reglas->decidir($estado, $tendencia, $astronauta->retardoMin());

        $evaluacion = DB::transaction(function () use ($astronauta, $clave, $valores, $origen, $instante, $estado, $tendencia, $decision) {
            $medicion = $astronauta->mediciones()->create([
                'indicador' => $clave, 'valores' => $valores, 'medido_en' => $instante, 'origen' => $origen,
            ]);
            $evaluacion = Evaluacion::create([
                'medicion_id' => $medicion->id,
                'estado' => $estado->value,
                'tendencia' => $tendencia,
                'accion' => $decision['accion'],
                'recomendacion' => $this->catalogo->para($clave, $estado),
            ]);
            if ($decision['encolar_tierra']) {
                AlertaSaliente::create([
                    'evaluacion_id' => $evaluacion->id,
                    'carga' => [
                        'astronauta' => $astronauta->nombre,
                        'indicador' => $this->config['indicadores'][$clave]['nombre'],
                        'valores' => $valores,
                        'estado' => $estado->value,
                        'accion' => $decision['accion'],
                        'medido_en' => $instante->toIso8601String(),
                    ],
                ]);
            }

            return $evaluacion;
        });

        if ($emitir) {
            MedicionRegistrada::dispatch($astronauta->id, $clave, $estado->value);
        }

        return $evaluacion->load('medicion');
    }

    /** Último estado conocido por indicador de un astronauta. @return array<string, Evaluacion> */
    public function ultimas(Astronauta $astronauta): array
    {
        $resultado = [];
        foreach (array_keys($this->config['indicadores']) as $clave) {
            $ev = Evaluacion::whereHas('medicion', fn ($q) => $q->where('astronauta_id', $astronauta->id)->where('indicador', $clave))
                ->with('medicion')->latest('id')->first();
            if ($ev) {
                $resultado[$clave] = $ev;
            }
        }

        return $resultado;
    }

    private function principal(array $valores): float
    {
        return (float) ($valores['valor'] ?? $valores['sistolica']);
    }
}
