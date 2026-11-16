<?php

/*
 | Clases de medicamentos del botiquín para cruzar alergias y medicación activa.
 |
 | EJEMPLO, SIN VALIDAR: agrupación orientativa y deliberadamente conservadora del proyecto (no de la norma ni de
 | los documentos fuente). Pendiente de revisión médica. Solo contiene nombres que existen en BotiquinItem::GRUPOS.
 | clase => nombres de botiquín (en minúsculas; se comparan sin tildes). Una alergia que coincida con el nombre de la
 | clase o con cualquiera de sus nombres excluye TODA la clase (criterio conservador).
 */

return [
    // Supuesto del proyecto, no de la norma: máximo de opciones de botiquín por respuesta.
    'max_opciones' => 3,

    'clases' => [
        'penicilinas' => ['amoxicilina/clavulanato'],
        'macrolidos' => ['azitromicina'],
        'quinolonas' => ['ciprofloxacino'],
        'aines' => ['ibuprofeno', 'aspirina', 'ketorolaco'],
        'salicilatos' => ['aspirina'],
        'paracetamol' => ['acetaminofén'],
        'antihistaminicos' => ['prometazina', 'meclizina'],
        'fenotiazinas' => ['prometazina'],
        'antieméticos' => ['prometazina', 'meclizina', 'ondansetrón'],
        'hipnoticos' => ['zolpidem', 'zaleplon'],
        'estimulantes' => ['cafeína', 'modafinilo'],
        'antivirales' => ['valaciclovir'],
        'heparinas' => ['enoxaparina'],
    ],
];
