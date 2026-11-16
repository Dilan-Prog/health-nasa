<?php

namespace App\Models;

use App\Support\MusculosCuerpo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zona de dolor marcada en la silueta (un músculo de MusculosCuerpo) con intensidad 1-10. */
class ZonaDolor extends Model
{
    protected $table = 'zonas_dolor';

    public const TIPOS = ['Punzante', 'Sordo', 'Quemante', 'Calambre', 'Rigidez'];
    public const DESDE = ['Hoy', '1–3 días', 'Más de 3 días'];
    /** Pain type label shown to the user, keyed by the stored (Spanish) value. */
    public const ETIQUETAS_TIPO = ['Punzante' => 'Sharp', 'Sordo' => 'Dull', 'Quemante' => 'Burning', 'Calambre' => 'Cramp', 'Rigidez' => 'Stiffness'];
    /** Onset label shown to the user, keyed by the stored (Spanish) value. */
    public const ETIQUETAS_DESDE = ['Hoy' => 'Today', '1–3 días' => '1–3 days', 'Más de 3 días' => 'More than 3 days'];

    public static function etiquetaTipo(string $tipo): string
    {
        return self::ETIQUETAS_TIPO[$tipo] ?? $tipo;
    }

    public static function etiquetaDesde(string $desde): string
    {
        return self::ETIQUETAS_DESDE[$desde] ?? $desde;
    }

    /** Intensidad a partir de la cual el registro se considera de aviso a tierra. */
    public const INTENSIDAD_ALERTA = 8;

    protected $fillable = ['registro_id', 'astronauta_id', 'musculo', 'intensidad', 'tipo', 'desde', 'nota'];

    protected $casts = ['intensidad' => 'integer'];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(RegistroSalud::class, 'registro_id');
    }

    public function astronauta(): BelongsTo
    {
        return $this->belongsTo(Astronauta::class);
    }

    /** Verde (1) -> rojo (10), como `intColor` del diseño. */
    public static function color(int $n): string
    {
        return 'oklch(0.68 0.17 '.round(150 - ($n - 1) * (125 / 9)).')';
    }

    public static function palabra(int $n): string
    {
        return $n <= 3 ? 'Leve' : ($n <= 6 ? 'Moderado' : ($n <= 8 ? 'Fuerte' : 'Muy fuerte'));
    }

    /** Estado de UI de la intensidad: >=8 crit, >=4 warn, resto ok. */
    public static function estado(int $n): string
    {
        return $n >= self::INTENSIDAD_ALERTA ? 'crit' : ($n >= 4 ? 'warn' : 'ok');
    }

    public static function nombre(string $musculo): string
    {
        return MusculosCuerpo::nombre($musculo) ?? $musculo;
    }
}
