# Prompt de diseño: Médico de vuelo flotante (círculo + panel) y tarjeta "Qué hacer ahora"

Pégalo en Claude Design, dentro del proyecto "Panel rediseño" (para que use su sistema de diseño). Después, `docs/PROMPT_MEDICO_FLOTANTE.md` es el prompt que lo implementa en el código.

---

Diseña el **Médico de vuelo flotante** del proyecto "Panel rediseño": un **círculo flotante** con el icono del médico de vuelo, presente en todas las pantallas, que al pulsarlo (o al detectarse algo) abre un **rectángulo flotante grande** con la orientación del Médico de vuelo. Detrás hay una **IA local que por ahora es SIMULADA**: la interfaz debe dejarlo muy claro. Además, diseña la **tarjeta de caso** que aparece arriba de "Qué hacer ahora" en el Panel.

## Reglas de diseño
- Usa **exactamente el sistema de diseño existente**: barra lateral, tipografías (etiquetas en mayúsculas pequeñas y monoespaciadas), tarjetas, píldoras de estado (BIEN, REVISAR, CRÍTICO), botones, iconos Material Symbols y los **tres temas: claro, oscuro y alto contraste** (selector Oscuro / Claro / Alto). No inventes un estilo nuevo.
- Español. Datos de ejemplo de la pantalla actual: Dilan García, día 143/180, última lectura 03:29 UTC, indicadores presión 117/71, pulso 71, SpO₂ 99 %, glucosa 98, temperatura 36.7.
- Estructura simple y portable a HTML/CSS con variables (Blade y Livewire): sin dependencias externas.
- Objetivos táctiles de al menos 44 px, texto legible a distancia y uso con guantes.

## Principios de producto (deben verse)
1. **IA simulada, sin ambigüedad.** Cada vista del panel lleva la etiqueta "Simulado" y el pie fijo: *"Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes. Orienta, no diagnostica."*
2. **"IA local y n8n: Próximamente"** visible en el encabezado, como una etiqueta discreta, no como botón.
3. **Lenguaje de hipótesis:** "posible hipoxia", "posible irregularidad del pulso (requiere ECG)". Nunca "tienes X". Siempre recomienda confirmar con el médico de vuelo.
4. **Sin dosis ni medicamentos recomendados.** La medicación registrada se muestra solo como contexto.
5. **Una emergencia no espera:** el caso crítico se ve distinto y primero.

## 1. El círculo flotante
- Abajo a la derecha, círculo de ~56 px con el icono `stethoscope` (el de "Videoconsulta con médico de vuelo" en Misión) y etiqueta accesible "Médico de vuelo".
- Estados: **reposo**, **hover/foco**, **con casos sin leer** (insignia con el número), **emergencia** (rojo, pulso suave; dame también la versión sin animación para `prefers-reduced-motion`), **panel abierto** (el círculo pasa a ser el botón de cerrar o se queda anclado).
- No debe tapar controles importantes: comprueba la posición sobre el pie de la pantalla y la tira de tema de la barra lateral. Muéstralo en el Panel, en Indicador y en Guía.

## 2. El rectángulo flotante (panel de conversación)
- **Escritorio:** ~420 px de ancho y hasta ~70 % del alto, anclado sobre el círculo, con sombra y cierre en una esquina. **Móvil (390 px):** hoja inferior casi a pantalla completa con asa de arrastre.
- **Encabezado:** icono, "Médico de vuelo", subtítulo "IA local (simulada)", tripulante actual, etiquetas **Simulado** y **IA local y n8n: Próximamente**, y botón de cerrar.
- **Lista de mensajes**, el más reciente arriba. Cada mensaje (tarjeta de caso) contiene, en este orden:
  1. **Cabecera:** patrón en hipótesis ("Posible irregularidad del pulso"), píldora de severidad (REVISAR o CRÍTICO), hora UTC y chip de origen ("Orientación simulada").
  2. **Hallazgo** en una o dos frases, dirigido al tripulante, en lenguaje de hipótesis.
  3. **Qué hacer ahora:** pasos con casillas, como las tarjetas actuales del Panel.
  4. **Historial de 7 días:** minigráfica por indicador implicado (con su línea de "tu base"), valor actual y rango. Para hipoxia: SpO₂ y pulso; para irregularidad del pulso: pulso.
  5. **De tu expediente** (solo lo pertinente): alergias, condiciones, antecedentes y **medicación registrada** como contexto, o "Sin antecedentes relacionados". Marca visual de que son datos sensibles.
  6. **Historial de tratamientos:** lista breve de solo lectura (consultas a tierra previas, "te ayudó / no te ayudó", medicación registrada) o "Sin tratamientos registrados".
  7. **Fuentes** como chips y **confianza** como barra con la etiqueta "simulada".
  8. **Acciones:** "Entendido", "Avisar a tierra" y "Abrir Médico de vuelo".
- **Pie fijo** con el aviso de IA simulada (ver arriba), siempre visible aunque la lista haga scroll.
- **Contenido largo:** las secciones 4, 5 y 6 se pueden plegar; por defecto abiertas solo "Qué hacer ahora" y el historial.

## 3. Apertura automática (comportamiento a diseñar)
Cuando la simulación provoca un patrón en el tripulante actual, el panel **se abre solo**, una vez por caso. Diseña la transición de entrada (corta, sobria, con versión sin animación), cómo se anuncia (insignia, borde de acento y lectura para lectores de pantalla) y qué pasa si el usuario lo cierra (no se reabre salvo que el caso escale a crítico). No debe quitarle el foco al usuario si está escribiendo.

## 4. Tarjeta de caso en el Panel ("Qué hacer ahora")
Encima de las tarjetas de siempre (Glucosa, Pérdida ósea…), una tarjeta destacada con el mismo contenido resumido: patrón y severidad, hallazgo, tres o cuatro pasos, minigráfica de 7 días y un botón "Ver en el Médico de vuelo" que abre el panel flotante. Que se distinga del resto sin romper la columna.

## Estados que debo ver (como variantes)
1. Panel cerrado en reposo.
2. Panel cerrado con 1 caso sin leer.
3. **Hipoxia** (REVISAR) abierto, con un mensaje completo.
4. **Posible irregularidad del pulso** abierto, con minigráfica del pulso.
5. **Emergencia (CRÍTICO):** círculo rojo, mensaje primero y destacado, acción "Avisar a tierra" prominente.
6. Sin casos: estado vacío tranquilizador ("Sin novedades; sigo atento a tus mediciones").
7. Sin tripulante seleccionado: "Elige un tripulante".
8. Varios mensajes con scroll y secciones plegadas.
9. Móvil (390 px) de los estados 3 y 5.
10. La tarjeta de caso en el Panel.

Cada estado, en **claro, oscuro y alto contraste** (al menos los estados 3 y 5 en los tres).

## Entregables
- Los frames anteriores a 1440 px (escritorio) y 390 px (móvil).
- **Lista de componentes** con sus variantes (círculo, insignia, encabezado del panel, tarjeta de caso, minigráfica, bloque de expediente, bloque de tratamientos, chip de fuente, pie de aviso) y los tokens de color y espaciado que usan.
- **Notas de interacción:** apertura, cierre (Esc), foco, orden de lectura, qué anuncia el lector de pantalla y qué se pliega.
- Accesibilidad: el color nunca es la única señal de severidad (icono más texto), contraste AA en los tres temas y foco visible.

## Texto de ejemplo (para los frames)
- **Hipoxia:** "Posible hipoxia. Tu saturación de O₂ bajó a 93 % y tu pulso subió a 98 lpm en los últimos 20 minutos. Es una hipótesis a descartar, no un diagnóstico." Pasos: "Siéntate y respira con calma; repite la medición en 5 minutos", "Revisa que el sensor esté bien ajustado", "Si no mejora, avisa a tierra".
- **Posible irregularidad del pulso (requiere ECG):** "Tu pulso mostró saltos repetidos en la última hora. Con estas lecturas no puedo hablar de arritmia: hace falta un ECG." Pasos: "Reposa y repite la medición", "Anota si sentiste palpitaciones o mareo", "Avisa al médico de vuelo".
- **Expediente (ejemplo):** "Sin antecedentes cardiovasculares registrados" y "Medicación registrada: [lista de ejemplo]".
- **Tratamientos (ejemplo):** "Hace 3 días avisaste a tierra por glucosa; marcaste que te ayudó."
No uses dosis ni nombres de fármacos como recomendación.
