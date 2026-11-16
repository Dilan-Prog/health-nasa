# Prompt de diseño: pantalla "Flight surgeon / Médico de vuelo"

Pégalo en Claude Design, dentro del proyecto "Panel rediseño" (para que use su sistema de diseño).

---

Rediseña la pantalla **Flight surgeon** ("Médico de vuelo") del proyecto Panel rediseño. Hoy es una página sencilla con dos tarjetas ("Alertas que se compartirán" y "Mensajes") y un botón "Solicitar videoconsulta". Quiero una interfaz mucho mejor que sirva de base a un **asistente médico local con IA**, sin romper la coherencia con el resto de las pantallas.

## Reglas de diseño
- Usa **exactamente el sistema de diseño existente**: sidebar, tipografías, etiquetas en mayúsculas pequeñas, tarjetas, botón primario azul y los colores de estado (normal / precaución / crítico) ya definidos en Panel, Indicador y Sistemas. No inventes un estilo nuevo.
- Idioma de la interfaz: español. Datos de ejemplo de la pantalla actual: Dilan García, día 142/180, órbita 408 km, última lectura 07:31 UTC, alertas "Pérdida ósea (cadera) −1.6 %/mes" y "Sueño 5.4 h".
- Estructura HTML/CSS simple y portable a Blade/Livewire (sin frameworks pesados ni librerías externas), con CSS por variables.
- Mantén la selección de tripulante y la barra lateral tal como están.

## Principios del producto (deben verse en la interfaz)
1. **La IA sugiere, nunca diagnostica ni decide.** Cada sugerencia muestra "Orientación, no diagnóstico" y recomienda consultar al médico de vuelo.
2. **Las alertas de emergencia no esperan a la IA.** Vienen de reglas fijas y se muestran primero, etiquetadas "Regla de emergencia" (no "IA").
3. **Todo es local y sin internet.** Muestra el estado del asistente: "IA local activa", "Analizando…", o "IA no disponible: solo reglas".
4. **Toda sugerencia cita fuente y confianza.** No hay dosis ni tratamientos sin ficha verificada; lo que falte se marca "Pendiente de revisión médica".
5. **El retardo de comunicación cambia la acción.** En estación orbital (retardo bajo) se ofrece videoconsulta; en base lunar o marciana (retardo de minutos) solo mensaje asíncrono con tiempo estimado de respuesta.

## Estructura de la pantalla (escritorio 1440 px)
**Cabecera:** título "Médico de vuelo", nombre y contacto de la doctora, ventana de disponibilidad, y chips de estado: retardo de comunicación (ej. "Retardo 1 min"), estado de la IA, y "Datos: últimos 15 días".

**Zona A, "Resumen de hoy":** cuadrícula compacta con los indicadores del tripulante (valor, estado con color, minigráfica de 15 días). Los críticos primero. Un clic en un indicador abre el detalle.

**Zona B, "Orientación del asistente":** pila de tarjetas ordenadas por severidad. Cada tarjeta tiene:
- Hallazgo (una frase) y chip de severidad.
- Sugerencia en 1 a 3 pasos.
- Barra de confianza con porcentaje.
- Fuentes como chips clicables (ej. "Protocolo: pérdida ósea").
- Estado del filtro de seguridad: "Verificado contra botiquín" o "Escalado a humano".
- Botones: "Me ayudó", "No me ayudó", "Enviar al médico de vuelo".
- Variante de tarjeta de **emergencia** (roja, sin IA, con acción inmediata "Contactar a tierra ahora").

**Zona C, "Contactar":** lo que hoy son las dos tarjetas, mejorado:
- Alertas que se compartirán, con casillas para incluir o quitar cada una.
- Botón "Solicitar videoconsulta" solo si el retardo es bajo; si no, "Enviar mensaje" con "Respuesta estimada en X min".
- Borrador del mensaje autogenerado y editable, con adjuntos de las mediciones.
- Línea de tiempo de cada envío: En cola → Enviado → Recibido → Respondido.

**Zona D, "Seguimiento":** tras seguir una sugerencia, recordatorios de escala de dolor 0 a 10 (antes, a los 30 min y a los 60 min) y campo de efectos adversos. Debajo, lista de consultas de los últimos 15 días.

## Estados que debo ver (como variantes)
1. Normal, con alertas y sugerencias.
2. Emergencia: banner rojo a todo el ancho, y la IA pasa a segundo plano.
3. IA analizando: esqueleto de carga con "Analizando en el equipo, puede tardar unos segundos".
4. IA no disponible: solo reglas, con aviso claro y sin tarjetas de IA.
5. Sin alertas: estado vacío tranquilizador.
6. Entorno con retardo alto (sin videoconsulta, con tiempo de espera visible).
7. Envío fallido o sin enlace: mensaje en cola con opción de reintentar.

## Entregables
- Escritorio (1440) y móvil (390) de los estados 1, 2 y 4 como mínimo; el resto, como variantes de los componentes.
- Lista de componentes nuevos (tarjeta de orientación, barra de confianza, chip de fuente, línea de tiempo de envío, banner de emergencia) con sus variantes.
- Notas breves de interacción (qué pasa al hacer clic, orden de prioridad, teclado).
- Accesibilidad: el color nunca es la única señal de severidad (usa icono y texto), contraste AA y foco visible.

No uses datos reales de medicación ni dosis; si hace falta ejemplo, usa texto marcador "[pendiente de revisión médica]".
