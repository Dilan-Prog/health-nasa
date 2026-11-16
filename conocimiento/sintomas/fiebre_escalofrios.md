---
slug: fiebre_escalofrios
tipo: sintoma
titulo: Posible fiebre o escalofríos
sintomas_clave: [fiebre, escalofrios]
regiones: []
banderas_rojas: [confusion, convulsion, dificultad_respirar]
urgencia_base: observacion
grupos_botiquin_permitidos: []
acciones:
  - id: fiebre.medir
    texto: "Mide tu temperatura con el equipo de la tripulación, en reposo, y compárala con tu propia base."
    fuente: {etiqueta: "conocimiento/fiebre.md", texto: "El criterio de la referencia es el aumento frente a la propia media, no un umbral terrestre fijo", origen: documento}
  - id: fiebre.reposo_hidratacion
    texto: "Reposa y mantente hidratado; evita abrigarte en exceso."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: fiebre.ejercicio_reciente
    texto: "Si hiciste ejercicio hace poco, espera un rato en reposo y mide de nuevo antes de interpretar el valor."
    fuente: {etiqueta: "conocimiento/fiebre.md", texto: "Una temperatura alta tras ejercicio no equivale a fiebre en microgravedad", origen: documento}
  - id: fiebre.avisar
    texto: "Avisa al médico de vuelo para que valore otros síntomas infecciosos; el sistema no identifica la causa."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.5", texto: "La respuesta inmunitaria puede modificarse en vuelo; los criterios clínicos concretos están pendientes", origen: documento}
seguimiento:
  - {texto: "Repetir la medición de temperatura tras un rato de reposo.", indicador: temperatura}
  - {texto: "Anotar otros síntomas que aparezcan (tos, dolor de garganta, malestar general)."}
escalar_si:
  - "Si la temperatura sigue elevada tras reposo o sube de forma brusca respecto a tu base."
  - "Si hay confusión, falta de aire o convulsión (emergencia)."
  - "Criterios clínicos adicionales de escalado: Pendiente de fuente verificable."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "conocimiento/fiebre.md (ficha del patrón de fiebre), que cita docs/fuentes/variacion_dosis_efectiva.txt, sección Temperatura."
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.5 (Sistema inmunológico)."
  - "Los escalofríos no aparecen en los documentos; esa parte es ejemplo simulado. Sin opciones de botiquín: Pendiente de fuente verificable."
---

Los antimicrobianos y antivirales nunca se sugieren como autoatención: solo requieren al médico de vuelo.
