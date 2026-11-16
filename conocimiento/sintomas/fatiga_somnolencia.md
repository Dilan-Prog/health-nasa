---
slug: fatiga_somnolencia
tipo: problema
titulo: Fatiga o somnolencia
sintomas_clave: [fatiga]
regiones: []
banderas_rojas: [desmayo, confusion, dificultad_respirar, dolor_toracico]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Sueño y fatiga"]
acciones:
  - id: fatiga.descanso
    texto: "Prioriza un descanso protegido y evita iniciar tareas críticas hasta recuperarte."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.9", texto: "El sueño alterado produce fatiga, menor concentración y posibles errores operativos", origen: documento}
  - id: fatiga.higiene_sueno
    texto: "Cuida tu rutina de sueño: procura poca luz, poco ruido y menos pantallas en el momento de descansar."
    fuente: {etiqueta: "datos_medicos.txt, sección 3", texto: "El motor de reglas recomienda higiene del sueño ante fatiga cognitiva; los detalles concretos son orientación general", origen: documento}
  - id: fatiga.comunicar_tareas
    texto: "Si hay una tarea crítica próxima, coméntalo con el médico de vuelo para valorar si conviene posponerla."
    fuente: {etiqueta: "datos_medicos.txt, sección 3", texto: "Fatiga cognitiva crítica: bloquear tareas críticas (EVA, robótica)", origen: documento}
  - id: fatiga.revisar_ambiente
    texto: "Si además hay dolor de cabeza, comprueba la ventilación y el aire de la cabina."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.6", texto: "Fatiga y dolor de cabeza con oxigenación o ventilación inadecuadas", origen: documento}
seguimiento:
  - {texto: "Registrar las horas de sueño de las últimas noches.", indicador: sueno}
  - {texto: "Registrar la saturación de oxígeno si el cansancio es inusual.", indicador: saturacion_o2}
escalar_si:
  - "Si la somnolencia es extrema, aparece de forma repentina o se acompaña de confusión (emergencia)."
  - "Si se acompaña de falta de aire, dolor de pecho o desmayo (emergencia)."
  - "Si dura varios días pese a descansar."
revision_medica: pendiente
origen: documento
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.9 (Sueño y ritmo circadiano): alteraciones de sueño, fatiga y somnolencia diurna."
  - "docs/fuentes/datos_medicos.txt, secciones 3 y 4.B: fatiga cognitiva crítica y grupo Trastornos del Sueño y Fatiga del botiquín (solo opción a consultar)."
  - "Los criterios de escalado son orientación general: Pendiente de fuente verificable."
---

Solo se menciona el grupo de sueño y fatiga del botiquín, sin pautas de uso y siempre a consultar con el médico de vuelo.
