<?php

/*
 | Ficha ASCAN: pruebas del primer examen anual. Solo se REGISTRAN (resultado, fecha, estado); el sistema no las interpreta.
 | Estructura: grupos => [titulo, pruebas => clave => nombre]. La clave se guarda en examenes_ascan.prueba.
 */

return [
    'grupos' => [
        [
            'titulo' => 'Sangre e inmunidad',
            'pruebas' => [
                'grupo_abo_rh' => 'Grupo sanguíneo ABO y Rh',
                'electroforesis_proteinas' => 'Electroforesis de proteínas séricas',
                'ig_g' => 'Inmunoglobulina G (IgG) cuantitativa',
                'ig_a' => 'Inmunoglobulina A (IgA) cuantitativa',
                'ig_m' => 'Inmunoglobulina M (IgM) cuantitativa',
                'g6pd' => 'G6PD',
                'lipoproteina_a' => 'Lipoproteína (a)',
            ],
        ],
        [
            'titulo' => 'Infecciones y vacunas (anticuerpos)',
            'pruebas' => [
                'cmv_igg' => 'IgG de citomegalovirus',
                'ebv_ebna' => 'Epstein-Barr: antígeno nuclear',
                'ebv_vca' => 'Epstein-Barr: antígeno de cápside viral',
                'herpes_simple_1_2' => 'Herpes simple 1/2',
                'toxoplasma' => 'Toxoplasma',
                'varicela_zoster' => 'Varicela-zóster',
                'sarampion' => 'Anticuerpos de sarampión',
                'paperas' => 'Anticuerpos de paperas',
                'rubeola' => 'Anticuerpos de rubéola',
                'hepatitis_a' => 'Hepatitis A',
                'h_pylori_aliento' => 'Test de aliento de H. pylori',
            ],
        ],
        [
            'titulo' => 'Alergia y riesgo urinario',
            'pruebas' => [
                'immunocap_epitelio_raton' => 'Immunocap (IgE): epitelio de ratón',
                'immunocap_orina_raton' => 'Immunocap (IgE): orina de ratón',
                'calculos_urinarios' => 'Riesgo de cálculos urinarios',
            ],
        ],
        [
            'titulo' => 'Panel de tromboembolismo venoso',
            'pruebas' => [
                'tev_cardiolipina_igg' => 'Cardiolipina IgG',
                'tev_b2gp1_igm' => 'β2-glicoproteína 1 IgM',
                'tev_b2gp1_igg' => 'β2-glicoproteína 1 IgG',
                'tev_resistencia_pca' => 'Resistencia a proteína C activada',
                'tev_protrombina_20210' => 'Mutación de protrombina 20210 G/A',
                'tev_proteina_c' => 'Proteína C',
                'tev_proteina_s' => 'Proteína S',
                'tev_antitrombina' => 'Antitrombina',
                'tev_antifosfolipidos' => 'Anticuerpos antifosfolípidos',
                'tev_factor_v_leiden' => 'Factor V Leiden',
            ],
        ],
    ],
];
