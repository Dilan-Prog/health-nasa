<?php

namespace App\Services\Salud;

/** Agrega los estados por sistema corporal; alimenta la figura humana. */
class IndiceSistemaCorporal
{
    public function __construct(private array $config)
    {
    }

    /**
     * @param array<string, Estado> $estadosPorIndicador clave de indicador => último estado
     * @return array<string, array{puntaje: int|null, estado: Estado|null}> por sistema.
     *         puntaje 0-100 (100 = todo normal); null si el sistema no tiene datos.
     */
    public function calcular(array $estadosPorIndicador): array
    {
        $porSistema = [];
        foreach ($estadosPorIndicador as $clave => $estado) {
            $sistema = $this->config['indicadores'][$clave]['sistema'] ?? null;
            if ($sistema !== null) {
                $porSistema[$sistema][] = $estado;
            }
        }

        $resultado = [];
        foreach ($this->config['sistemas'] as $sistema) {
            $estados = $porSistema[$sistema] ?? [];
            if ($estados === []) {
                $resultado[$sistema] = ['puntaje' => null, 'estado' => null];
                continue;
            }
            $media = array_sum(array_map(fn (Estado $e) => 100 - 50 * $e->severidad(), $estados)) / count($estados);
            $peor = array_reduce($estados, fn (Estado $c, Estado $e) => $c->peor($e), Estado::Normal);
            // Un crítico nunca queda oculto por el promedio.
            $puntaje = (int) round($peor === Estado::Critico ? min($media, 40) : $media);
            $resultado[$sistema] = ['puntaje' => $puntaje, 'estado' => $peor];
        }

        return $resultado;
    }
}
