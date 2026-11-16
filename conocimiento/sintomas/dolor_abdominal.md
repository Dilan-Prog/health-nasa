---
slug: dolor_abdominal
tipo: sintoma
titulo: Dolor abdominal
sintomas_clave: [dolor]
regiones: [abdomen, estomago, vientre]
banderas_rojas: [vomito_persistente, hemorragia, confusion]
urgencia_base: observacion
grupos_botiquin_permitidos: []
acciones:
  - id: dolor_abdominal.reposo
    texto: "Reposa en una postura cómoda y evita comidas pesadas mientras dure el dolor."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_abdominal.describir
    texto: "Anota dónde duele, si es continuo o va y viene, y si se relaciona con comer o con el movimiento."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_abdominal.hidratacion
    texto: "Si puedes, toma pequeños sorbos de agua y registra tu hidratación."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_abdominal.avisar
    texto: "Comenta el síntoma con el médico de vuelo: el sistema no puede distinguir su causa."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Pendiente de fuente verificable: no hay apartado de dolor abdominal en los documentos", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Reevaluar tras reposar: anotar si el dolor cede, sigue igual o empeora."}
  - {texto: "Registrar tu hidratación.", indicador: hidratacion}
escalar_si:
  - "Si el dolor es muy intenso, empeora o no cede con el reposo."
  - "Si hay vómito que no cede, sangre en el vómito o en las heces, o abdomen muy duro (emergencia)."
  - "Si se acompaña de fiebre o confusión."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "Ejemplo simulado (sin validar): los documentos de referencia no tratan el dolor abdominal. Pendiente de fuente verificable."
---

Sin opciones del botiquín: un analgésico podría ocultar la evolución del dolor. Decisión del proyecto, a validar por un médico.
