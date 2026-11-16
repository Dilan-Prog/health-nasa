---
slug: arritmia
patron: arritmia
titulo: Posible irregularidad del pulso (requiere ECG)
fuentes:
  - "docs/fuentes/variacion_dosis_efectiva.txt, sección Frecuencia cardíaca (datos de las misiones Apolo)"
  - "docs/fuentes/datos_medicos.txt, sección 1 (captura de ECG por wearables)"
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 3.4 (ruido en ECG por interferencias)"
revision_medica: pendiente
---

## Señales a vigilar
- Frecuencia cardíaca en precaución o crítico según los umbrales del proyecto.
- Como apoyo, estrés cognitivo alterado en la misma ventana.
- Contexto histórico de la referencia: en las misiones Apolo el pulso fue de 66 a 144 lpm; un aumento aislado puede tener componentes ortostáticos y psicológicos.

## Qué chequear
- Que la lectura no sea un artefacto del sensor (interferencias o mal contacto con la piel): una lectura sospechosa no dispara este patrón.
- Si el pulso se mantiene alterado en lecturas sucesivas o fue un valor aislado.
- Contactar con tierra y, si el equipo está disponible, registrar un ECG (la guía de referencia lista el ECG por wearable como fuente de datos). Que el ECG sea necesario para valorar el ritmo es un criterio del proyecto.

## Cuándo escalar
- El sistema envía el caso a tierra cuando su severidad es crítica (regla del proyecto, no de la norma).
- Criterios clínicos adicionales de escalado: Pendiente de fuente verificable.

## Límites de lo que puede afirmarse
- Con una lectura cada pocos minutos no se puede hablar de arritmia: solo de una posible irregularidad del pulso que requiere ECG.
- Los documentos de referencia no definen criterios de arritmia: Pendiente de fuente verificable.
