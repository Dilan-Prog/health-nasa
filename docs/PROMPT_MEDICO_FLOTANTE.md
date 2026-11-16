# Prompt: Médico de vuelo flotante (IA local SIMULADA) en todas las vistas

**La IA local real y n8n quedan como "Próximamente".** Este prompt construye la experiencia completa con una **IA simulada**: detecta patrones a partir de la simulación de los tripulantes y habla por un círculo flotante. Cuando exista la IA real, solo se cambia la implementación que genera el mensaje (ver "Costura para la IA real").

**Requisitos previos:** Niveles 1, 2 y 3A terminados (están). El 3C (IA real + n8n) **no se ejecuta**: su prompt (`docs/PROMPT_NIVEL3C_IA_LOCAL.md`) queda como hoja de ruta. De ese documento solo se reutiliza el diseño de la detección de patrones y de las fichas.
Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests. Texto de las fuentes en `docs/fuentes/`.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP), 100 % local. Orienta, no diagnostica ni receta. Responde en español, con cambios mínimos y verificados.

## Qué quiero (resumen)
Un **círculo flotante con el icono del médico de vuelo** (el icono `stethoscope` de Material Symbols, ya usado en la config de Misión) **presente en todas las vistas**. Al pulsarlo se abre un **rectángulo flotante grande** con la conversación del Médico de vuelo. Cuando la simulación provoque un patrón (arritmia, hipoxia, fiebre, deshidratación, estrés agudo, falta de sueño, fallo de sensor) en el tripulante actual:
1. El rectángulo se **abre solo**, de forma autónoma, con un mensaje dirigido a ese tripulante: qué se detectó, **qué hacer** (las acciones de la Guía), su **historial de 7 días**, lo relevante de su **expediente** y su **historial de tratamientos**.
2. En el Panel, la sección **"Qué hacer ahora"** muestra la misma orientación en una tarjeta propia, con las sugerencias que ya existen.
3. Todo lleva el aviso fijo: **"Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes."**
4. En el flotante se ve que **la IA local (Ollama) y n8n son "Próximamente"**.

## Reglas no negociables
- **Lenguaje de hipótesis, nunca de diagnóstico:** "posible hipoxia", "posible irregularidad del pulso (requiere ECG)", "hipótesis a descartar". Aunque el mensaje hable al tripulante, **no uses "tienes X"**. Siempre recomienda confirmar con el médico de vuelo.
- **Ni dosis, ni medicamentos, ni tratamientos que recete el sistema.** Las acciones son medidas de conducta, repetir mediciones, revisar el dispositivo, reposo, hidratación, avisar a tierra. Los medicamentos del expediente se **muestran** como contexto ("medicación registrada"), nunca se recomiendan ni se modifican.
- **Todo contenido médico sale de documentos** (`docs/fuentes/`, `config/guia.php`, las fichas de `conocimiento/`), con su fuente citada y marcado `revision_medica: pendiente`. **No inventes contenido médico ni cifras.** Si un documento no cubre algo, escribe "Pendiente de fuente verificable".
- Los umbrales nuevos son "supuesto del proyecto, no de la norma"; lístamelos al final.
- **Una sola sesión escribe.** Revisa `git status` y los procesos `php.exe` antes de empezar; no detengas `salud:simulador-vivo`. Tests contra `healts_nasa_test`, una suite a la vez. La base real `healts-nasa`: **avísame antes de aplicar migraciones**.
- Reutiliza el sistema de diseño (tokens, tema claro, oscuro y alto contraste, objetivos de 44 px). Sin librerías nuevas. Commits pequeños por tema con solo tus archivos.

## Tareas, en este orden

### 1. Casos y detector de patrones (base, sin IA)
Sigue el diseño de las secciones C1 de `docs/PROMPT_NIVEL3C_IA_LOCAL.md`, pero solo lo necesario:
- Migración `casos_clinicos` (tripulante, patrón, severidad precaución|crítico, evidencia JSON con los valores y la ventana usados, estado, `detectado_en`, `cerrado_en`, `simulacion_evento_id` nullable).
- `config/patrones.php` como **datos**: un patrón es una combinación de **estados de indicadores que ya existen** (precaución o crítico según los umbrales actuales) dentro de una ventana de minutos, para no inventar umbrales numéricos nuevos. Cubre los siete escenarios de `config/simulacion.php`: `fiebre`, `hipoxia`, `deshidratacion`, `estres`, `arritmia`, `falta_sueno`, `fallo_sensor`. Con una lectura cada pocos minutos no se puede hablar de arritmia: el caso se llama **"posible irregularidad del pulso (requiere ECG)"**. `fallo_sensor` se apoya en las mediciones marcadas "sospechosas" del 3A y recomienda revisar el dispositivo.
- `DetectorPatrones`: un caso abierto por tripulante y patrón, sube la severidad si empeora y se cierra cuando las señales vuelven a normal. Debe ser barato: se invoca solo cuando entra una medición en precaución o crítico, con un pequeño enfriamiento por tripulante (la simulación mete decenas de mediciones por minuto).
- Un patrón crítico escala a tierra por la ruta que ya existe, sin esperar a nada.
- Tests por patrón (señales → caso esperado), sin duplicados, cierre, y que no dispare con datos sospechosos.

### 2. Fichas de conocimiento
Carpeta `conocimiento/` con una ficha `.md` por patrón y frontmatter YAML (`slug`, `patron`, `titulo`, `fuentes`, `revision_medica: pendiente`) y secciones fijas: señales a vigilar, qué chequear, cuándo escalar, límites de lo que puede afirmarse. Solo lo que sustenten los documentos (p. ej. hipoxia: sección 1.6 de `salud_astronautas_y_monitoreo.txt`; temperatura: el criterio es un salto sobre la propia media y > 40 °C es esperable en ejercicio). `LectorFichas` valida el frontmatter, y un test falla si falta la ficha de un patrón.

### 3. Orientador simulado, con costura para la IA real
- Interfaz `Orientador` (entrada: caso y contexto; salida: `Orientacion` con `hallazgo`, `acciones`, `historial_7d`, `expediente_relevante`, `tratamientos`, `fuentes`, `confianza`, `origen`) y su única implementación activa, **`OrientadorSimulado`**: **determinista y basada en plantillas**, sin ningún modelo de lenguaje. `config/ia.php` con `modo = simulado` y un modo `ia_local` que **no existe todavía** (debe responder "Próximamente"; no lo implementes).
- El contenido se compone así:
  - **Hallazgo y qué hacer:** de la ficha del patrón y de las **acciones de la Guía** (`config/guia.php`): "sugiere todas las acciones de la guía" que correspondan al patrón y a los indicadores implicados. Cada acción cita su fuente.
  - **Historial de 7 días:** de `DatosIndicador` (serie diaria) para los indicadores del patrón, con el valor actual frente a su base.
  - **Expediente relevante:** del expediente del tripulante (alergias, condiciones médicas, antecedentes familiares, medicación registrada), mostrando **solo lo pertinente al patrón** y "sin antecedentes relacionados" si no hay nada. Está cifrado: usa los modelos (que descifran) y registra el acceso con `Auditoria::registrar`. Respeta los roles cuando el login esté activo.
  - **Historial de tratamientos (solo lectura):** lo que ya existe en el proyecto, sin inventar datos clínicos: consultas a tierra previas del tripulante, votos de "me ayudó / no me ayudó" (`OrientacionFeedback`) y la medicación registrada en el expediente. Sin datos: "Sin tratamientos registrados". Se usa para matizar el texto ("la última vez avisaste a tierra y marcaste que te ayudó"), nunca para recomendar un fármaco.
  - **Confianza:** la de la regla del detector, mostrada como simulada.
- **Filtro de seguridad determinista** antes de mostrar cualquier mensaje: rechaza dosis y unidades de dosis, nombres de medicamentos del botiquín como recomendación y lenguaje de diagnóstico definitivo. Si falla, muestra el texto por reglas del catálogo con el aviso. Con tests para cada causa.

### 4. El círculo flotante y el rectángulo
Componente Livewire `MedicoFlotante`, incluido en `resources/views/layouts/app.blade.php`, de modo que aparezca en **todas** las vistas con layout (no en el login). Las páginas se recargan completas (no hay `wire:navigate`), así que **el estado debe guardarse en la sesión**.
- **Círculo:** abajo a la derecha, objetivo táctil ≥ 44 px, icono `stethoscope`, etiqueta accesible "Médico de vuelo", insignia con los casos sin leer, y estilo de emergencia (rojo, pulso suave, que respete `prefers-reduced-motion`) cuando haya un caso crítico. No debe tapar controles importantes: contempla el pie de la página.
- **Rectángulo:** panel grande (en escritorio unos 420 px de ancho y hasta ~70 % del alto; en móvil, hoja inferior casi a pantalla completa). Encabezado "Médico de vuelo · IA local (simulada)" con las etiquetas **"Simulado"** y **"IA local y n8n: Próximamente"**. Una lista de mensajes ordenada con el más reciente arriba. Cada mensaje: patrón y severidad, hallazgo en hipótesis, **qué hacer** (pasos), **historial de 7 días** (minigráfica por indicador y valores), **expediente relevante**, **historial de tratamientos**, fuentes como chips (`<x-fuente-chip>`) y **confianza**. Botones: "Entendido", "Avisar a tierra" (reutiliza el flujo existente de la pantalla) y "Abrir Médico de vuelo". Pie fijo con el aviso: **"Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado, a partir de la simulación de los tripulantes. Orienta, no diagnostica."**
- **Apertura automática:** cuando aparece un caso **nuevo** (o empeora) del tripulante actual, el rectángulo se abre solo, **una vez por caso** (la sesión recuerda cuáles ya se mostraron y cuáles descartó el usuario). Si el usuario lo cierra, no se reabre por ese caso salvo que escale a crítico. Usa el mecanismo de tiempo real que ya existe (`resources/js/app.js` escucha `MedicionRegistrada` por Reverb); si Reverb no está disponible, `wire:poll` con intervalo razonable y **consultas baratas** (comprueba solo si hay casos nuevos, sin recalcular el orientador cada vez).
- **Accesibilidad:** `role="dialog"` con `aria-live` (polite; assertive solo en crítico), se cierra con Esc, el foco vuelve al círculo al cerrar, navegable con teclado, contraste en los tres temas. En la apertura automática no robes el foco de un campo en el que el usuario esté escribiendo.
- Sin tripulante seleccionado: el círculo se muestra, con un mensaje "Elige un tripulante".

### 5. "Qué hacer ahora" en el Panel
En `Panel` (la sección "Qué hacer ahora"), cuando el tripulante actual tenga un caso abierto, muestra **encima de las tarjetas de siempre** una tarjeta del caso con el hallazgo en hipótesis, los pasos, el historial de 7 días y un botón que abre el rectángulo flotante con ese caso. Las sugerencias que ya existen se conservan. Reutiliza el mismo `Orientador` para que Panel y flotante digan lo mismo.

### 6. Hoja de ruta visible
Añade a la hoja de ruta "Próximamente" (Guía y README) **"IA local con Ollama"** y **"Orquestación con n8n"**. Si `config/proximamente.php` exige un `sistema` válido en cada entrada, no fuerces estas dos ahí: crea una sección propia "Plataforma" y adapta el test a propósito. Mantén `docs/PROMPT_NIVEL3C_IA_LOCAL.md` como referencia, con una línea al inicio que diga "Aplazado: Próximamente".

### 7. Pruebas y cierre
- Tests Livewire del flotante: aparece en el layout; abre automáticamente con un caso nuevo y solo una vez; el descarte persiste en la sesión; reabre al escalar a crítico; muestra el aviso de simulación y "Próximamente"; no muestra datos de otro tripulante; registra el acceso al expediente en la auditoría; sin dosis ni medicamentos recomendados.
- Test de integración: `salud:simulador-vivo --once` (o el motor de simulación) con el escenario `hipoxia` activo → caso → flotante y tarjeta del Panel. Si no es práctico, simula las mediciones en el test.
- Suite completa en verde. Verifica en el navegador (`/`, `/indicador`, `/guia`, `/tripulacion`) que el círculo aparece, que el rectángulo se abre y que no tapa contenido, en tema claro y oscuro y en pantalla estrecha.

## Costura para la IA real (no la implementes)
`Orientador` es la única pieza que cambiará: cuando exista Ollama con n8n, se añadirá `OrientadorN8n` (contrato en `docs/PROMPT_NIVEL3C_IA_LOCAL.md`) y se elegirá con `config('ia.modo')`. El flotante, el Panel, el detector y el filtro de seguridad no cambian.

## Definición de terminado
- El círculo aparece en todas las vistas; al lanzar `arritmia` o `hipoxia` desde la simulación, el rectángulo se abre solo con la orientación, el historial de 7 días, lo relevante del expediente y el historial de tratamientos, y el Panel muestra la tarjeta en "Qué hacer ahora".
- Todo texto está en lenguaje de hipótesis, sin dosis ni recomendación de fármacos, con fuentes citadas y el aviso de simulación visible.
- La IA local y n8n figuran como "Próximamente"; no hay llamadas a Ollama ni a n8n.
- Suite en verde. Resumen final con supuestos introducidos, lo que no pudiste verificar y cualquier decisión que tomaste por tu cuenta.
