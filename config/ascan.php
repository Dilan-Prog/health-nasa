<?php

/*
 | Ficha ASCAN: pruebas del primer examen anual. Solo se REGISTRAN (resultado, fecha, estado); el sistema no las interpreta.
 | Estructura: grupos => [titulo, pruebas => clave => nombre]. La clave se guarda en examenes_ascan.prueba.
 */

return [
    'grupos' => [
        [
            'titulo' => 'Blood and immunity',
            'pruebas' => [
                'grupo_abo_rh' => 'ABO and Rh blood group',
                'electroforesis_proteinas' => 'Serum protein electrophoresis',
                'ig_g' => 'Immunoglobulin G (IgG), quantitative',
                'ig_a' => 'Immunoglobulin A (IgA), quantitative',
                'ig_m' => 'Immunoglobulin M (IgM), quantitative',
                'g6pd' => 'G6PD',
                'lipoproteina_a' => 'Lipoprotein(a)',
            ],
        ],
        [
            'titulo' => 'Infections and vaccines (antibodies)',
            'pruebas' => [
                'cmv_igg' => 'Cytomegalovirus IgG',
                'ebv_ebna' => 'Epstein-Barr virus: nuclear antigen (EBNA)',
                'ebv_vca' => 'Epstein-Barr virus: viral capsid antigen (VCA)',
                'herpes_simple_1_2' => 'Herpes simplex 1/2',
                'toxoplasma' => 'Toxoplasma',
                'varicela_zoster' => 'Varicella-zoster',
                'sarampion' => 'Measles antibodies',
                'paperas' => 'Mumps antibodies',
                'rubeola' => 'Rubella antibodies',
                'hepatitis_a' => 'Hepatitis A',
                'h_pylori_aliento' => 'H. pylori breath test',
            ],
        ],
        [
            'titulo' => 'Allergy and urinary risk',
            'pruebas' => [
                'immunocap_epitelio_raton' => 'ImmunoCAP (IgE): mouse epithelium',
                'immunocap_orina_raton' => 'ImmunoCAP (IgE): mouse urine',
                'calculos_urinarios' => 'Urinary stone risk',
            ],
        ],
        [
            'titulo' => 'Venous thromboembolism panel',
            'pruebas' => [
                'tev_cardiolipina_igg' => 'Cardiolipin IgG',
                'tev_b2gp1_igm' => 'β2-glycoprotein 1 IgM',
                'tev_b2gp1_igg' => 'β2-glycoprotein 1 IgG',
                'tev_resistencia_pca' => 'Activated protein C resistance',
                'tev_protrombina_20210' => 'Prothrombin 20210 G/A mutation',
                'tev_proteina_c' => 'Protein C',
                'tev_proteina_s' => 'Protein S',
                'tev_antitrombina' => 'Antithrombin',
                'tev_antifosfolipidos' => 'Antiphospholipid antibodies',
                'tev_factor_v_leiden' => 'Factor V Leiden',
            ],
        ],
    ],
];
