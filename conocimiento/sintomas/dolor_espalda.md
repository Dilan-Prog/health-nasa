---
slug: dolor_espalda
tipo: problema
titulo: Dolor de espalda
sintomas_clave: [dolor]
regiones: [espalda, columna, lumbar, dorsal, cervical]
banderas_rojas: [entumecimiento, debilidad_lateral, dolor_toracico, hemorragia]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Analgesia e inflamación"]
acciones:
  - id: dolor_espalda.postura
    texto: "Busca una postura neutra y sujeta tu cuerpo para que la espalda no cargue tensión; evita torsiones bruscas."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.2", texto: "Expansión de discos intervertebrales y alargamiento temporal de la columna, que puede acompañarse de dolor de espalda", origen: documento}
  - id: dolor_espalda.movilidad
    texto: "Alterna reposo con movilidad suave y estiramientos cómodos, sin forzar."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_espalda.revisar_rutina
    texto: "Revisa si apareció tras un entrenamiento resistivo o un cambio de postura de trabajo y coméntalo con el médico de vuelo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "El documento (datos_medicos.txt, 4.A) trata el ejercicio resistivo diario; la relación con el dolor es orientación general", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Registrar la zona y la intensidad en el mapa de dolor."}
  - {texto: "Reevaluar tras reposar: anotar si mejora, sigue igual o empeora."}
escalar_si:
  - "Si hay entumecimiento, hormigueo o pérdida de fuerza en brazos o piernas (emergencia si es repentino o de un lado)."
  - "Si el dolor es muy intenso, empeora o se acompaña de dolor de pecho."
  - "Si no cede en varios días."
revision_medica: pendiente
origen: documento
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.2 (Sistema musculoesquelético): alargamiento temporal de la columna y dolor de espalda."
  - "Las medidas de postura y los criterios de escalado son orientación general: Pendiente de fuente verificable."
---

El documento respalda la relación entre la elongación de la columna en microgravedad y el dolor de espalda; las medidas concretas son ejemplo simulado. Opciones de analgesia solo a consultar, sin pautas de uso.
