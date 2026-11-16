<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Inventario informativo del botiquín. Sin dosis, sin indicaciones de uso, sin sugerencias automáticas. */
class BotiquinItem extends Model
{
    public const PENDIENTE = 'Pendiente de verificación médica';

    /** Días de margen para marcar "vence pronto". */
    public const DIAS_VENCE_PRONTO = 90;

    /** Grupos y nombres (solo los de la guía), en orden de presentación. */
    public const GRUPOS = [
        'Mareo por adaptación' => ['prometazina', 'meclizina', 'ondansetrón'],
        'Sueño y fatiga' => ['melatonina', 'zolpidem', 'zaleplon', 'modafinilo', 'cafeína'],
        'Analgesia e inflamación' => ['ibuprofeno', 'acetaminofén', 'aspirina', 'ketorolaco'],
        'Antimicrobianos y antivirales' => ['amoxicilina/clavulanato', 'azitromicina', 'ciprofloxacino', 'valaciclovir'],
        'Emergencias' => ['epinefrina', 'oxígeno al 100 %', 'enoxaparina'],
    ];

    protected $table = 'botiquin_items';

    protected $fillable = ['nombre', 'grupo', 'stock', 'unidad', 'caducidad', 'contraindicaciones', 'nota', 'valor_ejemplo'];

    protected $casts = [
        'stock' => 'integer',
        'caducidad' => 'date',
        'valor_ejemplo' => 'boolean',
    ];

    /** @return array{0: string, 1: string, 2: string} [clave, texto, icono] */
    public function estadoCaducidad(?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        if (! $this->caducidad) {
            return ['sin', 'SIN FECHA', 'help'];
        }
        $cad = $this->caducidad->copy()->startOfDay();
        if ($cad->lt($hoy)) {
            return ['vencido', 'VENCIDO', 'error'];
        }
        if ($cad->lte($hoy->copy()->addDays(self::DIAS_VENCE_PRONTO))) {
            return ['pronto', 'VENCE PRONTO', 'warning'];
        }

        return ['vigente', 'VIGENTE', 'check_circle'];
    }
}
