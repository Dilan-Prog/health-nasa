<?php

namespace App\Support;

/**
 * Músculos de la silueta corporal (vista frontal) y su nombre legible.
 *
 * El nombre se deriva del id con una tabla explícita (`<músculo>[_<n>]_<lado>`): si el id no encaja,
 * nombre() devuelve null en lugar de inventar una etiqueta.
 */
final class MusculosCuerpo
{
    /** Ids de los 50 músculos del SVG, en el orden de capas del archivo original. */
    public const IDS = [
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
    ];

    /** Nombre base de cada músculo, tal como aparece en el id (sin número ni lado). */
    private const BASE = [
        'esternocleidomastoideo' => 'Esternocleidomastoideo',
        'trapecio_sup' => 'Trapecio superior',
        'deltoides_anterior' => 'Deltoides anterior',
        'deltoides_lateral' => 'Deltoides lateral',
        'pectoral_mayor' => 'Pectoral mayor',
        'pectoral_inferior' => 'Pectoral inferior',
        'pectoral_clavicular' => 'Pectoral clavicular',
        'biceps' => 'Bíceps',
        'triceps' => 'Tríceps',
        'recto_abdominal' => 'Recto abdominal',
        'serrato_anterior' => 'Serrato anterior',
        'oblicuo_externo' => 'Oblicuo externo',
        'oblicuo_inferior' => 'Oblicuo inferior',
        'tensor_fascia_lata' => 'Tensor de la fascia lata',
        'braquiorradial' => 'Braquiorradial',
        'flexores_antebrazo' => 'Flexores del antebrazo',
        'extensores_antebrazo' => 'Extensores del antebrazo',
        'recto_femoral' => 'Recto femoral',
        'vasto_lateral' => 'Vasto lateral',
        'vasto_medial' => 'Vasto medial',
        'gemelo_medial' => 'Gemelo medial',
        'tibial_anterior' => 'Tibial anterior',
    ];

    private const LADO = ['izq' => 'izquierdo', 'der' => 'derecho'];

    /** Nombre legible ("Pectoral mayor izquierdo") o null si el id no tiene el formato esperado. */
    public static function nombre(string $id): ?string
    {
        if (! preg_match('/^([a-z_]+?)(?:_(\d+))?_(izq|der)$/', $id, $m) || ! isset(self::BASE[$m[1]])) {
            return null;
        }

        $nombre = self::BASE[$m[1]].($m[2] !== '' ? " (porción {$m[2]})" : '');

        return $nombre.' '.self::LADO[$m[3]];
    }

    /** @return array<string, string> id => nombre legible, para todos los músculos. */
    public static function catalogo(): array
    {
        return array_combine(self::IDS, array_map(fn (string $id) => self::nombre($id), self::IDS));
    }
}
