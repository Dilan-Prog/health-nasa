---
slug: dolor_toracico
tipo: sintoma
titulo: Dolor u opresión en el pecho
sintomas_clave: [presion_pecho, dolor]
regiones: [pecho, torax]
banderas_rojas: [dolor_toracico]
urgencia_base: emergencia
grupos_botiquin_permitidos: []
acciones:
  - id: dolor_toracico.detener_actividad
    texto: "Detén la actividad ahora y siéntate o reposa en una posición cómoda, sin esfuerzo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Medida inmediata general y conservadora (regla del proyecto: dolor en el pecho = emergencia)", origen: ejemplo_simulado}
  - id: dolor_toracico.avisar
    texto: "Avisa a tierra ahora con el resumen de tus síntomas; no esperes a ver si se pasa."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Regla del proyecto: las banderas rojas se escalan sin autoatención", origen: ejemplo_simulado}
  - id: dolor_toracico.vigilar
    texto: "Mientras tanto, vigila tu pulso y tu saturación de oxígeno y anótalos para el médico de vuelo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Medida inmediata general; los umbrales los define el médico de vuelo (Pendiente de fuente verificable)", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Registrar el pulso mientras llega la respuesta de tierra.", indicador: frecuencia_cardiaca}
  - {texto: "Registrar la saturación de oxígeno.", indicador: saturacion_o2}
escalar_si:
  - "Siempre: el dolor u opresión en el pecho se escala a tierra de inmediato."
  - "Si se acompaña de falta de aire, sudoración fría, desmayo o casi desmayo."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "Ejemplo simulado (sin validar): regla del proyecto de banderas rojas (docs/PROMPT_ORIENTADOR_SINTOMAS.md, Reglas no negociables)."
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.1 (Sistema cardiovascular): contexto de la redistribución de líquidos; no define criterios para el dolor torácico. Pendiente de fuente verificable."
---

Bandera roja. Sin autoatención ni opciones del botiquín. Solo medidas inmediatas seguras y aviso a tierra.
