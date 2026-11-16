<?php

/*
 | Calidad de la señal: marca "sospechosa" una lectura que probablemente es un error del sensor
 | (la radiación, las interferencias y las baterías pueden dar lecturas erróneas; docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 3).
 | La lectura SE GUARDA SIEMPRE; solo se etiqueta. Una sospechosa no genera aviso a tierra por sí sola,
 | no entra en la línea base móvil, pero si además cruza un umbral crítico el estado crítico se mantiene.
 |
 | TODOS los números de este archivo son SUPUESTOS DEL PROYECTO, no de la norma: la fuente describe el riesgo,
 | no da umbrales de salto ni de señal plana. Pendiente de validar con el médico de vuelo.
 |
 |  salto_max    diferencia absoluta máxima plausible entre dos lecturas consecutivas buenas (unidad del indicador).
 |  ventana_min  el salto solo se evalúa si la última lectura buena es de hace menos de estos minutos.
 |  plana_n      N lecturas idénticas seguidas (la actual incluida) = señal plana; solo indicadores continuos.
 */

return [
    'indicadores' => [
        'frecuencia_cardiaca' => ['salto_max' => 40, 'ventana_min' => 120, 'plana_n' => 6],
        'saturacion_o2' => ['salto_max' => 8, 'ventana_min' => 120, 'plana_n' => 6],
        'temperatura' => ['salto_max' => 1.5, 'ventana_min' => 180, 'plana_n' => 6],
        'presion_arterial' => ['salto_max' => 60, 'ventana_min' => 180],
    ],

    // Fuera del rango fisiológico posible (p. ej. SpO₂ 0 %): solo en lecturas simuladas se guarda como sospechosa;
    // el registro manual y la API siguen rechazándolo con error de validación.
    'aceptar_fuera_de_rango_automatico' => true,
];
