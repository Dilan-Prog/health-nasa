<?php

/*
 | Traceability: which requirement (table `requisitos`, RequisitosSeeder) each indicator hangs from.
 | Resolved by App\Services\Salud\ResolvedorRequisito. Each entry is identified by the stable `clave` of the
 | seeder row (tipo.dominio.slug), never by its visible text; no row or code is invented. An entry set to null,
 | or whose row does not exist in the DB, resolves to null: the indicator has "no associated requirement".
 |
 | None of this is clinically validated: the indicator -> requirement link is a project criterion
 | (a project assumption, not the standard's) and the numbers are still example values.
 */

$k = fn (string $clave) => ['clave' => $clave];

$gcrLibre = $k('norma.radiacion.gcr_espacio_libre');
$gcrSuperficie = $k('norma.radiacion.gcr_superficie');
$evento = $k('norma.radiacion.dosis_evento_spe');
$temperatura = $k('referencia.temperatura.central_orbita');

return [
    // Default requirement of each indicator (key of config/umbrales.php).
    'indicadores' => [
        'radiacion_diaria' => $gcrLibre,
        'dosis_acumulada' => $k('norma.radiacion.dosis_carrera'),
        // An observed HRP rate: cited as "Projection (HRP)", never as a requirement.
        'perdida_osea' => $k('hrp.hueso.perdida_observada'),
        'presion_arterial' => $k('referencia.presion_arterial.normal'),
        'temperatura' => $temperatura,
        'frecuencia_cardiaca' => $k('referencia.pulso.apolo_rango'),
        'hidratacion' => $k('guia.hidratacion.diaria_baja'),
        'vision_sans' => $k('guia.vision.grosor_retiniano'),
        'sueno' => $k('guia.sueno.menor_6h_48h'),

        // NO row in the seeder (none are invented): they return null.
        'saturacion_o2' => null,
        'glucosa' => null,
        'imc' => null,
        'masa_muscular' => null,    // the seeder only has "fuerza" (strength), which is a different measure
        'estres_cognitivo' => null, // only the qualitative "conducta" requirement exists, with no number to link
        'animo' => null,
    ],

    // The "real" requirement that accompanies an indicator whose main link is not one (bone loss card).
    'referencias' => [
        'perdida_osea' => $k('norma.hueso.dmo_cadera_columna'), // V1 4027
    ],

    // Entries by context (new rules): indicator => context field => value => entry.
    // Band names are those of the seeder's blood-pressure reference; if T2 uses others they do not resolve
    // and the indicator's default requirement is used.
    'condiciones' => [
        'radiacion_diaria' => [
            'entorno' => ['espacio_libre' => $gcrLibre, 'superficie' => $gcrSuperficie],
            'condicion' => ['evento_solar' => $evento],
        ],
        'dosis_acumulada' => [
            'condicion' => ['evento_solar' => $evento],
        ],
        'presion_arterial' => [
            'banda' => [
                'normal' => $k('referencia.presion_arterial.normal'),
                'elevada' => $k('referencia.presion_arterial.elevada'),
                'hipertension_1' => $k('referencia.presion_arterial.hipertension_1'),
                'hipertension_2' => $k('referencia.presion_arterial.hipertension_2'),
                'crisis' => $k('referencia.presion_arterial.crisis'),
            ],
        ],
        'temperatura' => [
            'condicion' => ['salto' => $temperatura],
        ],
    ],
];
