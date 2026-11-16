<?php

namespace App\Services\Salud;

use App\Events\MedicionRegistrada;
use App\Models\AlertaSaliente;
use App\Models\Astronauta;
use App\Models\Evaluacion;
use App\Models\Medicion;
use App\Models\RutinaEjecucion;
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

        $contexto = $this->contexto($astronauta, $clave, $historial, $actual, $instante);
        $estado = $this->evaluador->evaluar($clave, $valores, $contexto);
        if ($usarLineaBase) { // se desactiva al sembrar datos de ejemplo con estados fijos
            $estado = $this->lineaBase->ajustar($estado, $this->lineaBase->calcular($historial, $actual));
        }
        $tendencia = $this->evaluador->tendencia(array_slice([...$historial, $actual], -8));
        $careLevel = (int) ($astronauta->misionDeVuelo?->care_level ?? 3);
        $decision = $this->reglas->decidir($estado, $tendencia, $astronauta->retardoMin(), $careLevel);

        $banda = $this->evaluador->banda($clave, $valores);
        $requisito = ResolvedorRequisito::para($clave, array_filter([
            'entorno' => $clave === 'radiacion_diaria' ? $this->evaluador->tipoLimiteRadiacion($astronauta->entorno) : null,
            'banda' => $this->evaluador->claveBandaPresion($clave, $valores),
            'fase' => $astronauta->misionDeVuelo?->mission_phase,
        ], fn ($v) => $v !== null));
        $fuente = ResolvedorRequisito::fuente($requisito);

        $evaluacion = DB::transaction(function () use ($astronauta, $clave, $valores, $origen, $instante, $estado, $tendencia, $decision, $banda, $requisito, $fuente) {
            $medicion = $astronauta->mediciones()->create([
                'indicador' => $clave, 'valores' => $valores, 'medido_en' => $instante, 'origen' => $origen,
            ]);
            $evaluacion = Evaluacion::create([
                'medicion_id' => $medicion->id,
                'estado' => $estado->value,
                'tendencia' => $tendencia,
                'accion' => $decision['accion'],
                'requisito_id' => $requisito?->id,
                'recomendacion' => $this->catalogo->para($clave, $estado, $banda),
            ]);
            if ($decision['encolar_tierra'] && ! ($origen === 'simulado' && $this->alertaReciente($astronauta, $clave))) {
                AlertaSaliente::create([
                    'evaluacion_id' => $evaluacion->id,
                    'astronauta_id' => $astronauta->id,
                    'carga' => [
                        'astronauta' => $astronauta->nombre,
                        'indicador' => $this->config['indicadores'][$clave]['nombre'],
                        'valores' => $valores,
                        'estado' => $estado->value,
                        'accion' => $decision['accion'],
                        'requisito' => $fuente ? ($fuente['codigo'] ?? $fuente['texto']) : null,
                        'revision' => $fuente['revision'] ?? null,
                        'tipo_fuente' => $fuente['tipo'] ?? null,
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

    /** Solo simulación: ya hay un aviso del mismo tripulante e indicador dentro de la ventana (por medición de la evaluación, no por texto de la carga). */
    private function alertaReciente(Astronauta $astronauta, string $clave): bool
    {
        $min = (int) config('tierra.ventana_dedup_min', 10);

        return AlertaSaliente::where('created_at', '>=', now()->subMinutes($min))
            ->whereHas('evaluacion.medicion', fn ($q) => $q->where('astronauta_id', $astronauta->id)->where('indicador', $clave))
            ->exists();
    }

    /**
     * Contexto que necesita el evaluador: entorno (radiación) y, para temperatura, base personal
     * (pre-vuelo si existe; si no, la media móvil del historial) y ejercicio reciente.
     */
    private function contexto(Astronauta $astronauta, string $clave, array $historial, float $actual, CarbonInterface $instante): array
    {
        $ctx = ['entorno' => $astronauta->entorno];
        if ($clave === 'temperatura') {
            $ctx['base_temperatura'] = DesviacionBase::valorBase($astronauta, 'temperatura_basal')
                ?? ($this->lineaBase->calcular($historial, $actual)['media'] ?? null);
            $ventana = (int) ($this->config['temperatura_salto']['ventana_ejercicio_min'] ?? 60);
            $ctx['ejercicio_reciente'] = RutinaEjecucion::where('estado', 'done')
                ->whereBetween('hecha_en', [$instante->copy()->subMinutes($ventana), $instante])
                ->whereHas('rutina', fn ($q) => $q->where('astronauta_id', $astronauta->id)->where('kind', 'ej'))
                ->exists();
        }

        return $ctx;
    }

    private function principal(array $valores): float
    {
        return (float) ($valores['valor'] ?? $valores['sistolica']);
    }
}
