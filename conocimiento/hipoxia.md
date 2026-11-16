---
slug: hipoxia
patron: hipoxia
titulo: Posible hipoxia
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.6 (Sistema respiratorio y oxigenación)"
  - "docs/fuentes/datos_medicos.txt, sección 2 (Radiación y cabina: presión parcial de CO2, O2 y humedad)"
revision_medica: pendiente
---

## Señales a vigilar
- Saturación de O₂ en precaución o crítico según los umbrales actuales del proyecto (valores de ejemplo, no validados).
- Como apoyo, pulso alterado y estrés cognitivo alterado dentro de la misma ventana.
- La fuente describe la hipoxia como disminución del oxígeno disponible en los tejidos, con fatiga, dolor de cabeza y disminución de la concentración cuando la oxigenación o la ventilación son inadecuadas.

## Qué chequear
- Que la lectura de saturación no sea un fallo del oxímetro (ver la ficha de fallo de sensor): una lectura marcada como sospechosa no dispara este patrón.
- Los síntomas que menciona la fuente: fatiga, dolor de cabeza, menor concentración.
- La presión parcial de O₂ y de CO₂ de la cabina, que la guía de referencia lista como variable ambiental a monitorizar.
- Comparar con la propia línea base del tripulante.

## Cuándo escalar
- El sistema envía el caso a tierra cuando su severidad es crítica (regla del proyecto, no de la norma).
- Criterios clínicos adicionales de escalado: Pendiente de fuente verificable.

## Límites de lo que puede afirmarse
- Solo se puede hablar de una posible hipoxia (hipótesis). El sistema no mide oxígeno en tejidos ni diagnostica.
- La fuente no define cifras de saturación para esta hipótesis: Pendiente de fuente verificable.
- El CO₂ no es un indicador de la aplicación; la hipercapnia que cita la fuente no puede evaluarse aquí.
