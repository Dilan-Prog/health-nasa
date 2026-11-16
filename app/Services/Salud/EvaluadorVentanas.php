<?php

namespace App\Services\Salud;

use App\Models\Astronauta;
use App\Models\Medicion;
use App\Models\Requisito;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Reglas de ventana (config/ventanas.php): miran las últimas N lecturas de un indicador y pueden elevar el estado.
 * Supuestos del proyecto, no de la norma. Orienta, no diagnostica: la acción es preventiva, sin fármacos.
 */
class EvaluadorVentanas
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('ventanas');
    }

    /** Reglas activas de un indicador (las «próximamente» o desactivadas se ignoran). @return array<string, array> */
    public function reglasDe(string $clave): array
    {
        return array_filter(
            $this->config['reglas'] ?? [],
            fn (array $r) => ($r['activa'] ?? false) && $r['indicador'] === $clave
        );
    }

    /** Indicadores con alguna regla activa. @return array<int, string> */
    public function indicadores(): array
    {
        return array_values(array_unique(array_column(array_filter($this->config['reglas'] ?? [], fn ($r) => $r['activa'] ?? false), 'indicador')));
    }

    public function aplicaA(string $clave): bool
    {
        return $this->reglasDe($clave) !== [];
    }

    /**
     * Evalúa la lectura que se va a guardar junto con las previas (una consulta pequeña, solo si hay regla).
     *
     * @return array|null resultado de la regla que más eleva, o null si ninguna se cumple
     */
    public function evaluar(Astronauta $a, string $clave, array $valores, CarbonInterface $instante): ?array
    {
        $reglas = $this->reglasDe($clave);
        if ($reglas === []) {
            return null;
        }
        $n = max(array_map(fn ($r) => (int) $r['lecturas'], $reglas));
        $previas = $n > 1 ? $this->previas($a, $clave, $instante, $n - 1) : [];

        return $this->resolver($reglas, [...$previas, ['valores' => $valores, 'en' => $instante]]);
    }

    /** Estado actual de las reglas de un indicador con sus últimas lecturas guardadas (tarjeta del Médico de vuelo). */
    public function evaluarActual(Astronauta $a, string $clave): ?array
    {
        $reglas = $this->reglasDe($clave);
        if ($reglas === []) {
            return null;
        }
        $n = max(array_map(fn ($r) => (int) $r['lecturas'], $reglas));
        $serie = $a->mediciones()->where('indicador', $clave)->orderByDesc('medido_en')->limit($n)->get()->reverse()->values()
            ->map(fn (Medicion $m) => ['valores' => $m->valores, 'en' => $m->medido_en])->all();

        return $this->resolver($reglas, $serie);
    }

    /**
     * Núcleo puro (sin BD): ¿se cumple la regla con esta serie, de la más antigua a la más reciente?
     * Cuenta solo la cola de la serie: las últimas N lecturas deben cumplir el umbral y no distar más de max_hueco_h.
     *
     * @param  array<int, array{valores: array, en: CarbonInterface}>  $serie
     */
    public function cumple(array $regla, array $serie): bool
    {
        $n = (int) $regla['lecturas'];
        if ($n < 1 || count($serie) < $n) {
            return false; // datos faltantes: no se asume nada
        }
        $cola = array_slice($serie, -$n);
        $maxHueco = (float) ($regla['max_hueco_h'] ?? 36) * 3600;
        $anterior = null;
        foreach ($cola as $l) {
            $v = $l['valores'][$regla['componente']] ?? null;
            if ($v === null || ! $this->compara((float) $v, $regla['operador'], (float) $regla['umbral'])) {
                return false;
            }
            $t = CarbonImmutable::instance($l['en'])->getTimestamp();
            if ($anterior !== null && ($t - $anterior > $maxHueco || $t <= $anterior)) {
                return false; // lectura repetida o salto de más de un día: la cadena diaria no es continua
            }
            $anterior = $t;
        }

        return true;
    }

    private function resolver(array $reglas, array $serie): ?array
    {
        $mejor = null;
        foreach ($reglas as $id => $regla) {
            if (! $this->cumple($regla, $serie)) {
                continue;
            }
            $estado = Estado::from($regla['eleva_a']);
            if ($mejor === null || $estado->severidad() > $mejor['estado']->severidad()) {
                $mejor = [
                    'regla' => $id,
                    'nombre' => $regla['nombre'],
                    'estado' => $estado,
                    'accion' => $regla['accion'],
                    'etiqueta' => 'Preventiva',
                    'texto' => str_replace('{n}', (string) $regla['lecturas'], $regla['texto'] ?? $regla['nombre']),
                    'recomendacion' => $regla['recomendacion'] ?? null,
                    'lecturas' => (int) $regla['lecturas'],
                    'pasos' => $regla['pasos'] ?? [],
                    'indicador' => $regla['indicador'],
                    'ultima' => end($serie) ?: null,
                    'requisito_def' => $regla['requisito'],
                ];
            }
        }

        return $mejor;
    }

    /** Fila de `requisitos` en la que se apoya la regla (null si no existe en la BD). */
    public function requisito(array $resultado): ?Requisito
    {
        $d = $resultado['requisito_def'];

        return Requisito::where('tipo', $d['tipo'])->where('dominio', $d['dominio'])->where('texto', $d['texto'])->first();
    }

    /** @return array<int, array{valores: array, en: CarbonInterface}> de la más antigua a la más reciente */
    private function previas(Astronauta $a, string $clave, CarbonInterface $instante, int $max): array
    {
        return $a->mediciones()->where('indicador', $clave)->where('medido_en', '<', $instante)
            ->orderByDesc('medido_en')->limit($max)->get()->reverse()->values()
            ->map(fn (Medicion $m) => ['valores' => $m->valores, 'en' => $m->medido_en])->all();
    }

    private function compara(float $v, string $op, float $umbral): bool
    {
        return match ($op) {
            '<' => $v < $umbral,
            '<=' => $v <= $umbral,
            '>' => $v > $umbral,
            '>=' => $v >= $umbral,
            default => false,
        };
    }
}
