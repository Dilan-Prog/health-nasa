---
slug: nauseas
tipo: problema
titulo: Náuseas
sintomas_clave: [nauseas, vomito]
regiones: [abdomen, estomago]
banderas_rojas: [vomito_persistente, hemorragia, dolor_toracico, confusion]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Mareo por adaptación"]
acciones:
  - id: nauseas.reposo
    texto: "Reposa, fija el cuerpo a un punto estable y mira a un punto fijo; evita movimientos bruscos de la cabeza."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.3", texto: "Mareo espacial, náuseas y desorientación por adaptación vestibular", origen: documento}
  - id: nauseas.comidas_suaves
    texto: "Si te sientes capaz, toma pequeños sorbos de agua y comidas suaves en poca cantidad."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: nauseas.ambiente
    texto: "Comprueba si el malestar coincide con un cambio de actividad, un olor inusual o la ventilación del módulo, y avísalo si algo no cuadra."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Reevaluar tras reposar: anotar si las náuseas ceden, siguen o aparece vómito."}
  - {texto: "Registrar tu hidratación si no logras beber.", indicador: hidratacion}
escalar_si:
  - "Si aparece vómito que no cede (bandera roja: emergencia)."
  - "Si hay dolor de pecho, confusión, dolor abdominal intenso o sangre en el vómito (emergencia)."
  - "Si no logras beber o las náuseas empeoran."
revision_medica: pendiente
origen: documento
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.3 (Sistema nervioso y sensorial): mareo espacial y náuseas."
  - "docs/fuentes/datos_medicos.txt, sección 4.B (Botiquín): grupo Mareo por Adaptación Espacial (SAS), solo como opción a consultar."
  - "Los criterios de escalado son orientación general: Pendiente de fuente verificable."
---

El documento relaciona náuseas con el mareo por adaptación espacial; solo ese grupo del botiquín se puede mencionar, sin pautas de uso y a consultar con el médico de vuelo.
