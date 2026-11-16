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

    /** Group label shown to the user, keyed by the stored (Spanish) group name. */
    public const ETIQUETAS_GRUPO = [
        'Mareo por adaptación' => 'Space adaptation (motion sickness)',
        'Sueño y fatiga' => 'Sleep & fatigue',
        'Analgesia e inflamación' => 'Pain & inflammation',
        'Antimicrobianos y antivirales' => 'Antimicrobials & antivirals',
        'Emergencias' => 'Emergency',
    ];

    /** Medication label shown to the user, keyed by the stored (Spanish) name. */
    public const ETIQUETAS_NOMBRE = [
        'prometazina' => 'promethazine', 'meclizina' => 'meclizine', 'ondansetrón' => 'ondansetron',
        'melatonina' => 'melatonin', 'zolpidem' => 'zolpidem', 'zaleplon' => 'zaleplon',
        'modafinilo' => 'modafinil', 'cafeína' => 'caffeine',
        'ibuprofeno' => 'ibuprofen', 'acetaminofén' => 'acetaminophen', 'aspirina' => 'aspirin', 'ketorolaco' => 'ketorolac',
        'amoxicilina/clavulanato' => 'amoxicillin/clavulanate', 'azitromicina' => 'azithromycin',
        'ciprofloxacino' => 'ciprofloxacin', 'valaciclovir' => 'valacyclovir',
        'epinefrina' => 'epinephrine', 'oxígeno al 100 %' => '100 % oxygen', 'enoxaparina' => 'enoxaparin',
    ];

    /** English label for display; the stored value is not changed. Unknown values are returned as they are. */
    public static function etiquetaGrupo(string $grupo): string
    {
        return self::ETIQUETAS_GRUPO[$grupo] ?? $grupo;
    }

    /** English label for display; the stored value is not changed. Unknown values are returned as they are. */
    public static function etiquetaNombre(string $nombre): string
    {
        return self::ETIQUETAS_NOMBRE[$nombre] ?? $nombre;
    }

    /** English label for the stored PENDIENTE value (the stored/compared value stays in Spanish). */
    public static function etiquetaPendiente(?string $valor): string
    {
        return $valor === self::PENDIENTE ? 'Pending medical verification' : (string) $valor;
    }

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
            return ['sin', 'NO DATE', 'help'];
        }
        $cad = $this->caducidad->copy()->startOfDay();
        if ($cad->lt($hoy)) {
            return ['vencido', 'EXPIRED', 'error'];
        }
        if ($cad->lte($hoy->copy()->addDays(self::DIAS_VENCE_PRONTO))) {
            return ['pronto', 'EXPIRES SOON', 'warning'];
        }

        return ['vigente', 'IN DATE', 'check_circle'];
    }
}
