---
slug: mareo_vertigo
tipo: problema
titulo: Mareo o vértigo
sintomas_clave: [mareo]
regiones: [cabeza]
banderas_rojas: [desmayo, confusion, perdida_vision, debilidad_lateral, dolor_cabeza_subito]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Mareo por adaptación"]
acciones:
  - id: mareo.reposo
    texto: "Detén lo que haces, fija tu cuerpo a un punto estable y reposa con la vista en un punto fijo antes de moverte."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.3", texto: "En microgravedad el cerebro recibe señales vestibulares distintas y necesita adaptarse (mareo espacial, desorientación)", origen: documento}
  - id: mareo.movimientos_lentos
    texto: "Evita los giros bruscos de cabeza y los cambios rápidos de orientación; muévete despacio mientras dure."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Medida general y conservadora; el documento describe el mecanismo pero no esta medida", origen: ejemplo_simulado}
  - id: mareo.posponer_tareas
    texto: "Pospón tareas que exijan coordinación o manejo de herramientas, y coméntalo con el médico de vuelo si coincide con una actividad crítica."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.3", texto: "Dificultad temporal para desplazarse o controlar vehículos y herramientas; alteraciones de la coordinación", origen: documento}
  - id: mareo.hidratacion
    texto: "Revisa que te has hidratado y alimentado hoy; anota qué hacías cuando empezó."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Reevaluar cuando pase un rato de reposo: si sigue igual o empeora, avisa."}
  - {texto: "Registrar la frecuencia cardíaca si notas el pulso alterado.", indicador: frecuencia_cardiaca}
escalar_si:
  - "Si el mareo empeora o no cede con el reposo."
  - "Si aparece desmayo o casi desmayo, confusión, pérdida de visión, debilidad o entumecimiento de un lado del cuerpo, o un dolor de cabeza súbito e intenso (banderas rojas: emergencia)."
  - "Si se acompaña de vómito persistente."
revision_medica: pendiente
origen: documento
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.3 (Sistema nervioso y sensorial): mareo espacial, náuseas y desorientación."
  - "docs/fuentes/datos_medicos.txt, sección 4.B (Botiquín): grupo Mareo por Adaptación Espacial (SAS), solo como opción a consultar con el médico de vuelo."
  - "Los criterios de escalado y de reposo son orientación general: Pendiente de fuente verificable."
---

Solo se mencionan opciones del grupo de mareo por adaptación, sin pautas de uso y siempre a consultar con el médico de vuelo. Con banderas rojas no hay botiquín.
