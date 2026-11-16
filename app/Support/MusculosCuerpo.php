<?php

namespace App\Support;

/**
 * Músculos de la silueta corporal (vistas frontal, lateral y posterior) y su nombre legible.
 *
 * El nombre se deriva del id con una tabla explícita (`<músculo>[_<n>]_<lado>`): si el id no encaja,
 * nombre() devuelve null en lugar de inventar una etiqueta. Un mismo id (p. ej. `biceps_der`) puede
 * aparecer en varias vistas: resaltarlo lo resalta en todas.
 */
final class MusculosCuerpo
{
    public const VISTAS = ['frontal' => 'Front', 'lateral' => 'Right side', 'lateral_izq' => 'Left side', 'posterior' => 'Back'];

    /** Ids de los músculos de cada vista, en el orden de capas del SVG original. */
    public const POR_VISTA = [
        'frontal' => [
            'esternocleidomastoideo_der',
            'esternocleidomastoideo_izq',
            'trapecio_sup_der',
            'trapecio_sup_izq',
            'deltoides_anterior_der',
            'deltoides_anterior_izq',
            'deltoides_lateral_der',
            'deltoides_lateral_izq',
            'pectoral_mayor_der',
            'pectoral_mayor_izq',
            'pectoral_inferior_der',
            'pectoral_inferior_izq',
            'biceps_der',
            'biceps_izq',
            'triceps_der',
            'triceps_izq',
            'recto_abdominal_1_der',
            'recto_abdominal_1_izq',
            'recto_abdominal_2_der',
            'recto_abdominal_2_izq',
            'recto_abdominal_3_der',
            'recto_abdominal_3_izq',
            'recto_abdominal_4_der',
            'recto_abdominal_4_izq',
            'serrato_anterior_der',
            'serrato_anterior_izq',
            'oblicuo_externo_der',
            'oblicuo_externo_izq',
            'oblicuo_inferior_der',
            'oblicuo_inferior_izq',
            'tensor_fascia_lata_der',
            'tensor_fascia_lata_izq',
            'braquiorradial_der',
            'braquiorradial_izq',
            'flexores_antebrazo_der',
            'flexores_antebrazo_izq',
            'extensores_antebrazo_der',
            'extensores_antebrazo_izq',
            'recto_femoral_der',
            'recto_femoral_izq',
            'vasto_lateral_der',
            'vasto_lateral_izq',
            'vasto_medial_der',
            'vasto_medial_izq',
            'gemelo_medial_der',
            'gemelo_medial_izq',
            'tibial_anterior_der',
            'tibial_anterior_izq',
            'pectoral_clavicular_der',
            'pectoral_clavicular_izq',
        ],
        'lateral' => [
            'trapecio_sup_der',
            'esternocleidomastoideo_der',
            'deltoides_anterior_der',
            'deltoides_lateral_der',
            'deltoides_posterior_der',
            'infraespinoso_der',
            'redondo_mayor_der',
            'pectoral_clavicular_der',
            'pectoral_mayor_der',
            'pectoral_menor_der',
            'coracobraquial_der',
            'triceps_largo_der',
            'biceps_der',
            'flexores_antebrazo_der',
            'braquiorradial_der',
            'dorsal_ancho_der',
            'serrato_1_der',
            'serrato_2_der',
            'serrato_3_der',
            'oblicuo_externo_der',
            'gluteo_mayor_der',
            'isquiotibiales_der',
            'cuadriceps_der',
            'gemelo_der',
            'soleo_der',
            'tibial_anterior_der',
        ],
        'lateral_izq' => [
            'trapecio_sup_izq',
            'esternocleidomastoideo_izq',
            'deltoides_anterior_izq',
            'deltoides_lateral_izq',
            'deltoides_posterior_izq',
            'infraespinoso_izq',
            'redondo_mayor_izq',
            'pectoral_clavicular_izq',
            'pectoral_mayor_izq',
            'pectoral_menor_izq',
            'coracobraquial_izq',
            'triceps_largo_izq',
            'biceps_izq',
            'flexores_antebrazo_izq',
            'braquiorradial_izq',
            'dorsal_ancho_izq',
            'serrato_1_izq',
            'serrato_2_izq',
            'serrato_3_izq',
            'oblicuo_externo_izq',
            'gluteo_mayor_izq',
            'isquiotibiales_izq',
            'cuadriceps_izq',
            'gemelo_izq',
            'soleo_izq',
            'tibial_anterior_izq',
        ],
        'posterior' => [
            'trapecio_sup_der',
            'trapecio_sup_izq',
            'trapecio_medio_der',
            'trapecio_medio_izq',
            'infraespinoso_der',
            'infraespinoso_izq',
            'dorsal_ancho_der',
            'dorsal_ancho_izq',
            'lumbar_der',
            'lumbar_izq',
            'deltoides_posterior_der',
            'deltoides_posterior_izq',
            'triceps_der',
            'triceps_izq',
            'braquiorradial_der',
            'braquiorradial_izq',
            'extensores_antebrazo_der',
            'extensores_antebrazo_izq',
            'oblicuo_externo_der',
            'oblicuo_externo_izq',
            'gluteo_medio_der',
            'gluteo_medio_izq',
            'gluteo_mayor_der',
            'gluteo_mayor_izq',
            'biceps_femoral_der',
            'biceps_femoral_izq',
            'semitendinoso_der',
            'semitendinoso_izq',
            'gemelo_lateral_der',
            'gemelo_lateral_izq',
            'gemelo_medial_der',
            'gemelo_medial_izq',
        ],
    ];

    /** Nombre base de cada músculo, tal como aparece en el id (sin número ni lado). [nombre, plural?] */
    private const BASE = [
        'esternocleidomastoideo' => ['Esternocleidomastoideo'],
        'trapecio_sup' => ['Trapecio superior'],
        'trapecio_medio' => ['Trapecio medio'],
        'deltoides_anterior' => ['Deltoides anterior'],
        'deltoides_lateral' => ['Deltoides lateral'],
        'deltoides_posterior' => ['Deltoides posterior'],
        'infraespinoso' => ['Infraespinoso'],
        'redondo_mayor' => ['Redondo mayor'],
        'dorsal_ancho' => ['Dorsal ancho'],
        'lumbar' => ['Lumbar'],
        'pectoral_mayor' => ['Pectoral mayor'],
        'pectoral_menor' => ['Pectoral menor'],
        'pectoral_inferior' => ['Pectoral inferior'],
        'pectoral_clavicular' => ['Pectoral clavicular'],
        'coracobraquial' => ['Coracobraquial'],
        'biceps' => ['Bíceps'],
        'triceps' => ['Tríceps'],
        'triceps_largo' => ['Tríceps (cabeza larga)'],
        'recto_abdominal' => ['Recto abdominal'],
        'serrato_anterior' => ['Serrato anterior'],
        'serrato' => ['Serrato'],
        'oblicuo_externo' => ['Oblicuo externo'],
        'oblicuo_inferior' => ['Oblicuo inferior'],
        'tensor_fascia_lata' => ['Tensor de la fascia lata'],
        'braquiorradial' => ['Braquiorradial'],
        'flexores_antebrazo' => ['Flexores del antebrazo'],
        'extensores_antebrazo' => ['Extensores del antebrazo'],
        'gluteo_mayor' => ['Glúteo mayor'],
        'gluteo_medio' => ['Glúteo medio'],
        'recto_femoral' => ['Recto femoral'],
        'vasto_lateral' => ['Vasto lateral'],
        'vasto_medial' => ['Vasto medial'],
        'cuadriceps' => ['Cuádriceps'],
        'biceps_femoral' => ['Bíceps femoral'],
        'semitendinoso' => ['Semitendinoso'],
        'isquiotibiales' => ['Isquiotibiales', true],
        'gemelo' => ['Gemelo'],
        'gemelo_medial' => ['Gemelo medial'],
        'gemelo_lateral' => ['Gemelo lateral'],
        'soleo' => ['Sóleo'],
        'tibial_anterior' => ['Tibial anterior'],
    ];

    private const LADO = ['izq' => 'izquierdo', 'der' => 'derecho'];

    /** English base name per BASE key, for display (the Spanish names above are compared by other code and stay as they are). [name, plural?] */
    private const BASE_EN = [
        'esternocleidomastoideo' => 'Sternocleidomastoid',
        'trapecio_sup' => 'Upper trapezius', 'trapecio_medio' => 'Middle trapezius',
        'deltoides_anterior' => 'Anterior deltoid', 'deltoides_lateral' => 'Lateral deltoid', 'deltoides_posterior' => 'Posterior deltoid',
        'infraespinoso' => 'Infraspinatus', 'redondo_mayor' => 'Teres major', 'dorsal_ancho' => 'Latissimus dorsi', 'lumbar' => 'Lumbar',
        'pectoral_mayor' => 'Pectoralis major', 'pectoral_menor' => 'Pectoralis minor', 'pectoral_inferior' => 'Lower pectoralis',
        'pectoral_clavicular' => 'Clavicular pectoralis', 'coracobraquial' => 'Coracobrachialis',
        'biceps' => 'Biceps', 'triceps' => 'Triceps', 'triceps_largo' => 'Triceps (long head)',
        'recto_abdominal' => 'Rectus abdominis', 'serrato_anterior' => 'Serratus anterior', 'serrato' => 'Serratus',
        'oblicuo_externo' => 'External oblique', 'oblicuo_inferior' => 'Lower oblique', 'tensor_fascia_lata' => 'Tensor fasciae latae',
        'braquiorradial' => 'Brachioradialis', 'flexores_antebrazo' => 'Forearm flexors', 'extensores_antebrazo' => 'Forearm extensors',
        'gluteo_mayor' => 'Gluteus maximus', 'gluteo_medio' => 'Gluteus medius',
        'recto_femoral' => 'Rectus femoris', 'vasto_lateral' => 'Vastus lateralis', 'vasto_medial' => 'Vastus medialis', 'cuadriceps' => 'Quadriceps',
        'biceps_femoral' => 'Biceps femoris', 'semitendinoso' => 'Semitendinosus', 'isquiotibiales' => 'Hamstrings',
        'gemelo' => 'Gastrocnemius', 'gemelo_medial' => 'Medial gastrocnemius', 'gemelo_lateral' => 'Lateral gastrocnemius',
        'soleo' => 'Soleus', 'tibial_anterior' => 'Tibialis anterior',
    ];

    private const LADO_EN = ['izq' => 'left', 'der' => 'right'];

    /** @return list<string> Todos los ids distintos, de cualquier vista. */
    public static function ids(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::POR_VISTA))));
    }

    /** Nombre legible ("Pectoral mayor izquierdo") o null si el id no tiene el formato esperado. */
    public static function nombre(string $id): ?string
    {
        if (! preg_match('/^([a-z_]+?)(?:_(\d+))?_(izq|der)$/', $id, $m) || ! isset(self::BASE[$m[1]])) {
            return null;
        }

        [$base, $plural] = self::BASE[$m[1]] + [1 => false];
        $lado = self::LADO[$m[3]].($plural ? 's' : '');

        return $base.($m[2] !== '' ? " (porción {$m[2]})" : '').' '.$lado;
    }

    /** English display name ("Pectoralis major left") or null if the id has no expected format. nombre() (Spanish) is unchanged. */
    public static function nombreEn(string $id): ?string
    {
        if (! preg_match('/^([a-z_]+?)(?:_(\d+))?_(izq|der)$/', $id, $m) || ! isset(self::BASE_EN[$m[1]])) {
            return null;
        }

        return self::BASE_EN[$m[1]].($m[2] !== '' ? " (portion {$m[2]})" : '').' '.self::LADO_EN[$m[3]];
    }

    /** @return array<string, string> id => nombre legible, para todos los músculos. */
    public static function catalogo(): array
    {
        $ids = self::ids();

        return array_combine($ids, array_map(fn (string $id) => self::nombre($id), $ids));
    }
}
