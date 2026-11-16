---
slug: falta_aire
tipo: sintoma
titulo: Falta de aire o dificultad para respirar
sintomas_clave: [falta_aire]
regiones: [pecho, torax]
banderas_rojas: [dificultad_respirar]
urgencia_base: emergencia
grupos_botiquin_permitidos: []
acciones:
  - id: falta_aire.detener_actividad
    texto: "Detén la actividad, siéntate o reposa y respira con calma."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Medida inmediata general y conservadora", origen: ejemplo_simulado}
  - id: falta_aire.avisar
    texto: "Avisa a tierra ahora con el resumen; la dificultad para respirar se escala siempre."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Regla del proyecto: las banderas rojas se escalan sin autoatención", origen: ejemplo_simulado}
  - id: falta_aire.ambiente
    texto: "Si puedes, comprueba con el equipo de la cabina que el aire y la ventilación del módulo son los habituales."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.6", texto: "La nave debe mantener una mezcla respirable; hipoxia e hipercapnia son riesgos descritos", origen: documento}
  - id: falta_aire.vigilar
    texto: "Vigila tu saturación de oxígeno y tu pulso y anótalos para el médico de vuelo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Medida inmediata general; los umbrales los define el médico de vuelo (Pendiente de fuente verificable)", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Registrar la saturación de oxígeno mientras llega la respuesta de tierra.", indicador: saturacion_o2}
  - {texto: "Registrar el pulso.", indicador: frecuencia_cardiaca}
escalar_si:
  - "Siempre: la dificultad para respirar se escala a tierra de inmediato."
  - "Si hay labios o dedos azulados, confusión o somnolencia marcada."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "Ejemplo simulado (sin validar): regla del proyecto de banderas rojas (docs/PROMPT_ORIENTADOR_SINTOMAS.md, Reglas no negociables)."
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.6 (Sistema respiratorio y oxigenación): hipoxia, hipercapnia y control de la mezcla respirable. No define cuándo escalar; Pendiente de fuente verificable."
---

Bandera roja. Sin autoatención ni opciones del botiquín. La conexión con la hipoxia del simulador es contexto, no un diagnóstico.
