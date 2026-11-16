---
slug: dolor_extremidades
tipo: sintoma
titulo: Dolor en brazos, manos, piernas o pies
sintomas_clave: [dolor, entumecimiento]
regiones: [brazo, mano, pierna, pie, rodilla, tobillo, codo, muneca, extremidades]
banderas_rojas: [entumecimiento, debilidad_lateral, hemorragia]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Analgesia e inflamación"]
acciones:
  - id: dolor_extremidades.reposo
    texto: "Descansa la extremidad, mantenla en una postura cómoda y evita apoyarla o cargarla mientras duela."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_extremidades.observar
    texto: "Observa la zona: compara con la otra extremidad el color, la temperatura y la hinchazón, y anota lo que notes."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general; el documento (1.1) solo indica que se investiga el riesgo de alteraciones del flujo venoso y trombosis", origen: ejemplo_simulado}
  - id: dolor_extremidades.avisar_cambios
    texto: "Si notas hinchazón, enrojecimiento o calor en una sola extremidad, avisa al médico de vuelo sin esperar."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Los documentos (1.1 y datos_medicos.txt, sección 3) marcan la trombosis venosa como riesgo vigilado; los signos concretos son orientación general", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Registrar la zona y la intensidad en el mapa de dolor."}
  - {texto: "Reevaluar tras el reposo: anotar si el dolor, la hinchazón o la sensibilidad cambian."}
escalar_si:
  - "Si hay hinchazón, enrojecimiento o calor en una sola extremidad."
  - "Si hay entumecimiento, hormigueo persistente o pérdida de fuerza, sobre todo en un lado del cuerpo (emergencia si es repentino)."
  - "Si el dolor es muy intenso o empeora con el reposo."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.1 (Sistema cardiovascular): alteraciones del flujo venoso y trombosis en investigación."
  - "docs/fuentes/datos_medicos.txt, sección 3 (Motor de reglas): trombosis venosa con severidad roja. No define síntomas de extremidades; Pendiente de fuente verificable."
  - "Ejemplo simulado (sin validar): la orientación sobre dolor de extremidades es general."
---

El documento solo respalda que la trombosis venosa es un riesgo vigilado; las señales de extremidades son ejemplo simulado. Hipótesis a descartar, nunca diagnóstico.
