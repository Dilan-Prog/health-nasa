---
slug: palpitaciones
tipo: sintoma
titulo: Palpitaciones o latidos acelerados
sintomas_clave: [palpitaciones]
regiones: [pecho, corazon]
banderas_rojas: [dolor_toracico, desmayo, dificultad_respirar]
urgencia_base: urgente
grupos_botiquin_permitidos: []
acciones:
  - id: palpitaciones.reposo
    texto: "Detén la actividad, siéntate o reposa y respira con calma."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: palpitaciones.medir
    texto: "Mide tu pulso en reposo y compáralo con tu base; anota si es regular o irregular."
    fuente: {etiqueta: "conocimiento/arritmia.md", texto: "La ficha del patrón de arritmia vigila el pulso frente a la propia base", origen: documento}
  - id: palpitaciones.ejercicio_reciente
    texto: "Si hiciste ejercicio intenso hace poco, ten en cuenta que puede explicar un pulso alto; confírmalo en reposo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: palpitaciones.avisar
    texto: "Avisa al médico de vuelo para que valore si necesita revisión."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora; criterios de escalado: Pendiente de fuente verificable", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Repetir la medición del pulso tras reposar.", indicador: frecuencia_cardiaca}
  - {texto: "Registrar la saturación de oxígeno.", indicador: saturacion_o2}
escalar_si:
  - "Si hay dolor u opresión en el pecho, falta de aire, desmayo o casi desmayo (emergencia)."
  - "Si el pulso sigue irregular o muy alterado tras reposar."
  - "Si las palpitaciones se repiten o duran más de lo habitual para ti."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "conocimiento/arritmia.md (ficha del patrón de arritmia) y su fuente en docs/fuentes."
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.1 (Sistema cardiovascular): cambios en el funcionamiento del corazón en vuelo. No describe las palpitaciones; Pendiente de fuente verificable."
---

Sin opciones del botiquín. Hipótesis a descartar con el médico de vuelo; el sistema no identifica la causa.
