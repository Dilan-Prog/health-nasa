---
slug: fallo_sensor
patron: fallo_sensor
titulo: Posible fallo de sensor
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 3 (efectos del ambiente espacial sobre los dispositivos electrónicos: 3.1, 3.3, 3.4 y 3.6)"
revision_medica: pendiente
---

## Señales a vigilar
- Lecturas marcadas como sospechosas por la calidad de señal del proyecto (salto brusco, señal plana o valor fuera del rango posible).
- La fuente indica que en el monitoreo puede haber datos corruptos, reinicios, pérdida de mediciones o falsas alarmas por radiación, interferencias electromagnéticas, temperatura o baterías.

## Qué chequear
- El dispositivo, no la salud: la fijación del equipo y el contacto confiable entre sensor y piel (sección 3.3 de la fuente).
- Cables y conectores sueltos o desconectados y el nivel de la batería (secciones 3.3 y 3.6).
- Posibles interferencias de otros equipos (sección 3.4).
- Repetir la medición cuando el dispositivo esté revisado.

## Cuándo escalar
- Una lectura sospechosa no avisa a tierra por sí sola. Si además cruza un umbral crítico, el aviso crítico original se mantiene (regla del proyecto, no de la norma).
- Criterios de sustitución o reparación del equipo: Pendiente de fuente verificable.

## Límites de lo que puede afirmarse
- Solo una posible falla del dispositivo (hipótesis); no se puede descartar que la lectura anómala sea real.
- Los umbrales de salto y señal plana son supuestos del proyecto, no de la norma.
