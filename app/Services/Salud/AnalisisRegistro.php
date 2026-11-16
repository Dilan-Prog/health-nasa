<?php

namespace App\Services\Salud;

/**
 * Lógica compartida de los formularios de registro (modal "Registrar medición" y pantalla Registrar):
 * texto escrito -> valores numéricos, validación (valores imposibles) y estado de UI en vivo.
 *
 * Estados de UI: ok | warn | crit (= Estado::Normal | Precaucion | Critico). Los errores de captura
 * no tienen estado: se devuelven en 'error'.
 */
class AnalisisRegistro
{
    public const IMPOSIBLE = 'Value outside the possible range, check the measurement';

    private const ESTADO_UI = ['normal' => 'ok', 'precaucion' => 'warn', 'critico' => 'crit'];

    private ValidadorMedicion $validador;
    private EvaluadorEstado $evaluador;

    private array $umbrales;

    public function __construct(?array $umbrales = null, private ?array $panel = null)
    {
        $this->umbrales = $umbrales ??= config('umbrales');
        $this->panel ??= config('panel.indicadores');
        $this->validador = new ValidadorMedicion($umbrales);
        $this->evaluador = new EvaluadorEstado($umbrales);
    }

    public static function make(): self
    {
        return new self();
    }

    /** Normaliza coma decimal y signos menos tipográficos; null si no es un número. */
    public function numero(string $texto): ?float
    {
        $t = trim(str_replace(["\u{2212}", "\u{2013}", ','], ['-', '-', '.'], $texto));

        return preg_match('/^-?\d+(?:\.\d+)?$/', $t) ? (float) $t : null;
    }

    /** Las pérdidas se guardan con signo: un valor positivo se interpreta como magnitud de pérdida. */
    public function valorSimple(string $clave, float $n): array
    {
        if (($this->panel[$clave]['perdida'] ?? false) && $n > 0) {
            $n = -$n;
        }

        return ['valor' => $n];
    }

    /**
     * Texto de un campo -> componentes de umbrales; null si está vacío. Si no se entiende: ['formato' => mensaje].
     *
     * @return array{valores?: array, formato?: string}|null
     */
    public function parsear(string $clave, string $texto): ?array
    {
        $t = trim(str_replace(["\u{2212}", "\u{2013}", ','], ['-', '-', '.'], $texto));
        if ($t === '') {
            return null;
        }
        if ($clave === 'presion_arterial') {
            if (! preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', $t, $m)) {
                return ['formato' => 'Enter blood pressure as 128/84'];
            }

            return ['valores' => ['sistolica' => (float) $m[1], 'diastolica' => (float) $m[2]]];
        }
        $n = $this->numero($t);
        if ($n === null) {
            return ['formato' => 'Enter a number only'];
        }

        return ['valores' => $this->valorSimple($clave, $n)];
    }

    /**
     * Valida y evalúa unos valores ya numéricos.
     *
     * @return array{error: ?string, estado: ?string}
     */
    public function evaluar(string $clave, array $valores): array
    {
        if ($this->validador->validar($clave, $valores) !== []) {
            return ['error' => self::IMPOSIBLE, 'estado' => null];
        }

        return ['error' => null, 'estado' => self::ESTADO_UI[$this->evaluador->evaluar($clave, $valores)->value]];
    }

    /** Explicación breve del estado en vivo ("128/84 supera tu máximo de 120/80"). */
    public function mensaje(string $clave, array $valores, string $estado): string
    {
        if ($estado === 'ok') {
            return ($this->panel[$clave]['perdida'] ?? false) ? 'Within your limit' : 'Within your range';
        }
        $cfg = $this->panel[$clave];
        $lim = $cfg['limites'];
        if ($cfg['perdida'] ?? false) {
            $n = abs((float) $valores['valor']);

            return 'Loss of '.$this->fmt($n)." exceeds your limit of {$lim['max']} {$cfg['unidad']}";
        }
        $p = (float) ($valores['valor'] ?? $valores['sistolica']);
        $txt = isset($valores['sistolica'])
            ? $this->fmt($valores['sistolica']).'/'.$this->fmt($valores['diastolica'])
            : $this->fmt($p);
        $hi = isset($valores['sistolica'])
            ? ($valores['sistolica'] > $this->umbrales['indicadores'][$clave]['componentes']['sistolica']['normal'][1]
                || $valores['diastolica'] > $this->umbrales['indicadores'][$clave]['componentes']['diastolica']['normal'][1])
            : $p > $lim['max'];

        return $hi
            ? "{$txt} exceeds your maximum of ".($lim['max_txt'] ?? $lim['max'])
            : "{$txt} is below your minimum of ".($lim['min_txt'] ?? $lim['min']);
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
