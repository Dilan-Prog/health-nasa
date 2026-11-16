---
slug: dolor_cabeza
tipo: sintoma
titulo: Dolor de cabeza
sintomas_clave: [dolor_cabeza]
regiones: [cabeza]
banderas_rojas: [dolor_cabeza_subito, confusion, perdida_vision, debilidad_lateral, convulsion]
urgencia_base: autocuidado
grupos_botiquin_permitidos: ["Analgesia e inflamación"]
acciones:
  - id: dolor_cabeza.reposo
    texto: "Reposa en un lugar tranquilo, con poca luz y poco ruido, y evita esfuerzos."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_cabeza.ambiente
    texto: "Comprueba con el equipo de la cabina que la ventilación y el aire del módulo son los habituales, y avisa si algo no cuadra."
    fuente: {etiqueta: "salud_astronautas_y_monitoreo.txt, 1.6", texto: "Fatiga y dolor de cabeza pueden aparecer cuando la oxigenación o la ventilación son inadecuadas", origen: documento}
  - id: dolor_cabeza.hidratacion
    texto: "Revisa tu hidratación, tu descanso y si te saltaste alguna comida; anota cuándo empezó."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general conservadora", origen: ejemplo_simulado}
  - id: dolor_cabeza.pantallas
    texto: "Si has estado mucho tiempo con pantallas o lectura, descansa la vista y observa si cambia algo."
    fuente: {etiqueta: "Ejemplo simulado (sin validar)", texto: "Orientación general; el documento (1.4, SANS) solo menciona dificultad para enfocar", origen: ejemplo_simulado}
seguimiento:
  - {texto: "Reevaluar tras reposar y beber agua; anotar si mejora o empeora."}
  - {texto: "Registrar la saturación de oxígeno si el dolor se acompaña de cansancio o falta de aire.", indicador: saturacion_o2}
escalar_si:
  - "Si el dolor es súbito e intenso, o el peor que has sentido (bandera roja: emergencia)."
  - "Si se acompaña de confusión, pérdida o cambios de visión, debilidad o entumecimiento de un lado, vómito persistente o convulsión (emergencia)."
  - "Si no cede, empeora o se repite varios días."
revision_medica: pendiente
origen: ejemplo_simulado
fuentes:
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.6 (Sistema respiratorio y oxigenación): dolor de cabeza con oxigenación o ventilación inadecuadas."
  - "docs/fuentes/salud_astronautas_y_monitoreo.txt, sección 1.1 (Sistema cardiovascular): los líquidos se desplazan hacia el tórax y la cabeza. No describe el dolor de cabeza; Pendiente de fuente verificable."
  - "Ejemplo simulado (sin validar): el resto de la orientación es general y conservadora."
---

El único respaldo documental es el vínculo con la ventilación y la oxigenación. Resto: ejemplo simulado.
