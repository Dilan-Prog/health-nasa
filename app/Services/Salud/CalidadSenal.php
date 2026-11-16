<?php

namespace App\Services\Salud;

/**
 * Detecta lecturas probablemente erróneas del sensor: salto brusco y señal plana (valores en config/calidad.php,
 * supuestos del proyecto). No rechaza nada: devuelve la etiqueta y el motivo para guardarlos junto a la medición.
 */
class CalidadSenal
{
    public const OK = 'ok';
    public const SOSPECHOSA = 'sospechosa';

    public function __construct(private array $config)
    {
    }

    public static function make(): self
    {
        return new self(config('calidad'));
    }

    /** Lecturas previas que hace falta consultar para evaluar el indicador (0 si no se vigila). */
    public function lecturasNecesarias(string $clave): int
    {
        $c = $this->config['indicadores'][$clave] ?? null;

        return $c === null ? 0 : max(1, (int) ($c['plana_n'] ?? 1));
    }

    /**
     * @param array<int, array{instante: int, valor: float, calidad: string}> $previas lecturas anteriores, de la más antigua a la más reciente
     * @return array{calidad: string, motivo: ?string}
     */
    public function evaluar(string $clave, float $valor, int $instante, array $previas): array
    {
        $c = $this->config['indicadores'][$clave] ?? null;
        if ($c === null) {
            return ['calidad' => self::OK, 'motivo' => null];
        }

        // Salto brusco frente a la última lectura buena reciente.
        $buenas = array_values(array_filter($previas, fn ($p) => ($p['calidad'] ?? self::OK) === self::OK));
        $ultima = $buenas === [] ? null : end($buenas);
        if ($ultima !== null && isset($c['salto_max'])
            && ($instante - $ultima['instante']) <= ((int) ($c['ventana_min'] ?? 120)) * 60
            && abs($valor - $ultima['valor']) > $c['salto_max']) {
            return ['calidad' => self::SOSPECHOSA, 'motivo' => sprintf(
                'Abrupt jump: %s vs %s in the previous reading (maximum plausible %s).',
                $this->num($valor), $this->num($ultima['valor']), $this->num($c['salto_max'])
            )];
        }

        // Señal plana: N lecturas idénticas seguidas (la actual incluida).
        $n = (int) ($c['plana_n'] ?? 0);
        if ($n >= 2 && count($previas) >= $n - 1) {
            $ultimas = array_slice($previas, -($n - 1));
            if (count(array_filter($ultimas, fn ($p) => abs($p['valor'] - $valor) < 1e-9)) === $n - 1) {
                return ['calidad' => self::SOSPECHOSA, 'motivo' => "Flat signal: {$n} identical consecutive readings ({$this->num($valor)})."];
            }
        }

        return ['calidad' => self::OK, 'motivo' => null];
    }

    private function num(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
