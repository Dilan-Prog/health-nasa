# Prompt: interfaz y tests 100 % en inglés (lo que ve y ejecuta la NASA)

Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests. El público es la NASA: **todo lo que aparece en pantalla debe estar en inglés técnico, correcto y coherente**.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP). Todo está simulado y el sistema orienta, no diagnostica. Responde al usuario en español; el contenido de la interfaz va en inglés. Cambios mínimos y verificados.

## Alcance: SOLO lo que se ve
**Sí se traduce** (cualquier texto que un usuario pueda ver o escuchar con un lector de pantalla):
- Vistas Blade (`resources/views/**`), componentes y layouts: títulos, etiquetas, botones, estados vacíos, placeholders, `aria-label`, `title`, texto solo para lectores de pantalla, `<title>`, atributos `lang`.
- Textos generados en PHP que llegan a pantalla: componentes Livewire (mensajes, flashes, etiquetas, validación, nombres de atributos), servicios que producen recomendaciones, alertas, orientaciones y avisos.
- Contenido en `config/**` que se muestra: indicadores, unidades, sistemas, guía, peligros, panel, médico, misión (eventos), rutinas, simulación (nombres de escenarios), próximamente, ASCAN, expediente, ventanas, calidad, trazabilidad (textos de requisitos), fichas de `conocimiento/**`.
- **Datos de demostración que se ven**: seeders (`database/seeders/**`) y los datos que ya están guardados en la base y se muestran (textos de recomendaciones, requisitos, botiquín, roles y misiones de los tripulantes, eventos).
- JavaScript de la interfaz (`resources/js/**`): etiquetas, tooltips, textos de gráficas, formatos de fecha y número.
- Páginas estáticas: `public/offline.html`, `public/body-preview.html`, `public/manifest.webmanifest` (nombre, descripción, idioma), mensajes de `public/sw.js` si se muestran.
- El analizador de síntomas: el usuario escribirá sus síntomas **en inglés** (ver "Trampas críticas", punto 4).
- **Los tests, completos** (`tests/**` y los de JavaScript): la NASA los ejecutará para probar el sistema y verá su salida (`php artisan test`, `--testdox`). Se traducen los **nombres de los tests, los nombres de las clases y archivos de test, los comentarios y docblocks de los tests, los mensajes de aserción, los datos de ejemplo y los textos que comparan** (ver tarea 8).

**NO se toca** (déjalo exactamente como está):
- Nombres internos: claves de config, columnas y tablas, valores guardados como `normal|precaucion|critico`, `ok|warn|crit`, `activas|graves|resueltas`, claves de escenarios (`fiebre`, `hipoxia`…), nombres de clases, métodos, rutas con nombre, clases CSS e ids HTML.
- **Las URLs** (`/tripulacion`, `/indicador`…): se quedan como están. Anótalo en el informe como pendiente opcional.
- Comentarios del código **de la aplicación** (los de los tests sí se traducen), nombres de clases y métodos de la aplicación, README, `docs/**` (incluidos los prompts y los PDF), `docs/fuentes/**`.
- Texto que escribió el usuario (síntomas, notas, nombres propios de los tripulantes).
- Nombres de iconos (Material Symbols: `info`, `stethoscope`…): ya son inglés y son código.

## Reglas de trabajo
- **Una sola sesión escribe.** Antes de empezar: `git status` y procesos `php.exe`. No detengas `salud:simulador-vivo`.
- Tests contra `healts_nasa_test`, una suite a la vez. **La base real `healts-nasa`: no la modifiques sin avisarme** (ver tarea 5, que cambia datos guardados).
- **Nada clínico está validado.** Traduce con fidelidad; no añadas, quites ni "mejores" contenido médico. Si dudas de un término clínico o aeroespacial, déjalo en `docs/TRANSLATION_REVIEW.md` (término, contexto, opciones) para que lo revise una persona.
- No uses el logo ni el emblema de la NASA, ni nada que sugiera patrocinio. El **Aviso legal** ("Legal notice") debe decir en inglés que es un prototipo educativo con datos simulados, que no es consejo médico y que **no está afiliado ni respaldado por la NASA**. Citar NASA-STD-3001 y el HRP como fuentes públicas está bien.
- Commits pequeños por tema, **con mensajes en inglés**, con solo tus archivos.

## Trampas críticas (haz esto PRIMERO, antes de traducir nada)
Hay lógica que **depende del texto en español**; al traducir se rompería **sin error visible**. Desacóplala antes:
1. **`ResolvedorRequisito` + `config/trazabilidad.php` + `config/ventanas.php`** buscan la fila de `requisitos` por la tupla `tipo|dominio|texto`, donde `texto` es el texto en español. Al traducir los textos del seeder, **`requisito_id` dejaría de resolverse** y las tarjetas perderían su cita. Solución: añadir a `requisitos` una **clave estable** (`clave`, única, p. ej. `norma.radiacion.gcr_espacio_libre`), asignarla a las 57 filas existentes **sin borrarlas ni recrearlas** (las evaluaciones las referencian), y resolver por `clave`. Es una migración aditiva: **avísame antes de aplicarla en la base real**.
2. **`MedicionesController` (≈ línea 92)** cuenta como duplicada una medición si el mensaje contiene `'duplicada'`, y **`ProcesadorMediciones` (≈ línea 65)** reconoce un error con `'es imposible'`. Cámbialos a **códigos** (excepción tipada o código de error) y traduce los mensajes después. Test que lo demuestre: un duplicado sigue contando como duplicado.
3. **`FiltroSeguridad`:** las expresiones de "lenguaje de diagnóstico" (`tienes`, `padeces`, `sufres`, `presentas`, `diagnosticado`…) y `ETIQUETA_MEDICACION` ("Medicación registrada:") son español. Tras traducir, deben detectar el **equivalente en inglés**: `you have`, `you are suffering from`, `you suffer from`, `you present with`, `diagnosed with`, `is diagnosed`, `diagnosis:`, `definitive diagnosis`, etc., y la etiqueta nueva ("Registered medication:"). Mantén las expresiones españolas también (defensa en profundidad). Tests con frases en ambos idiomas.
4. **Analizador de síntomas y su léxico** (localízalo: `AnalizadorSintomas`, `config/sintomas.php` o `conocimiento/sintomas`): hoy interpreta español. Debe entender **inglés** (sinónimos, negaciones "no / don't have", lateralidad left/right, regiones, calidad "sharp, stabbing, pressing, burning, dull", intensidad "mild, severe, 1–10", duración "for 20 minutes"). Conserva los sinónimos en español como secundarios. Mantén intacta la lógica de banderas rojas. Tests con estas frases: *"I feel a sharp pain in my left chest"* → emergencia sin botiquín; *"I feel dizzy with a severe stabbing pain in my right triceps and left foot"* → la urgencia que ya tiene definida.
5. Busca otras comparaciones contra texto visible: `grep` de `str_contains`, `str_starts_with`, `strpos`, `preg_match`, `==` y `in_array` sobre cadenas con letras o espacios, y de `where('texto'`, `where('nombre'`, `where('grupo'` sobre contenido traducible (p. ej. grupos del botiquín en `BotiquinOpciones`, nombres de medicamentos en `FiltroSeguridad`). Lista cada hallazgo y su solución en el informe final.

## Guía de estilo (inglés)
- **Inglés estadounidense**, registro técnico y claro, sin coloquialismos. Frases cortas.
- Estilo de títulos: *Sentence case* ("What to do now"). Las etiquetas pequeñas en mayúsculas ya las pone el CSS: escribe el texto normal.
- Estados de severidad unificados: **NORMAL / CAUTION / CRITICAL** (verde, ámbar, rojo). En el Panel hoy aparecen BIEN/REVISAR/CRÍTICO y en Sistemas NORMAL/PRECAUCIÓN/CRÍTICO: unifica en los tres anteriores. Acción "preventiva" → **Preventive**.
- Hipótesis, nunca diagnóstico: "possible hypoxia", "hypothesis to rule out". Avisos fijos de la interfaz: **"Guidance only, not a diagnosis."** y, en la IA simulada: *"This is a simulated version of a local AI. It can make mistakes. Everything is simulated, based on the simulated crew data. It does not provide real medical advice."*
- Horas en **UTC, 24 h** ("03:29 UTC"). Fechas legibles en inglés ("15 Nov 2026") y **punto decimal**, **coma de miles** ("1,000"). Mantén el signo menos tipográfico "−" que ya se usa. **La entrada numérica debe seguir aceptando coma y punto** (hay tests que lo exigen).
- Unidades: `lpm`→`bpm`; `%/mes`→`%/month`; `% misión`→`% of mission`; `mSv/día`→`mSv/day`; `L/día`→`L/day`; `kg/m²`, `mmHg`, `mg/dL`, `°C`, `mSv`, `h`, `D` (dioptrías) sin cambios.
- Términos NASA en su forma oficial: CMO (Crew Medical Officer), Flight Surgeon, EVA, SAS (Space Adaptation Syndrome), SANS (Spaceflight Associated Neuro-ocular Syndrome), GCR, SPE, BMD, VO₂max, ARED, T2, CEVIS, LBNP, PVT, LEO, Care Level 3–5.
- Los riesgos HRP usan el **nombre oficial en inglés** de NASA: *Risk of Altered Immune System Responses; Risk of Altered Sensorimotor and Vestibular Function; Risk of Cardiovascular Adaptations; Risk of Decompression Sickness; Risk of Host-Microorganism Interactions; Risk of Hypoxia; Risk of Inadequate Food and Nutrition; Risk of Injury and Compromised Performance from Spacewalks (EVA); Risk of Reduced Cardiorespiratory and Musculoskeletal Fitness; Risk of Spaceflight Associated Neuro-ocular Syndrome (SANS); Risk of Spaceflight-Induced Bone Changes; Risk of Behavioral Changes and Psychiatric Disorders; Risk of Inadequate Teamwork; Risk of Inadequate Sleep and Irregular Schedules; Risk of Inadequate Systems Operations Support; Risk of Injury from Dynamic Loads; Risk of Radiation-Induced Cancers.* Los 5 peligros: *Space Radiation; Isolation and Confinement; Distance from Earth; Gravity Fields; Hostile/Closed Environments.*
- Textos de requisitos: **traducción fiel** del español actual; no los presentes como cita literal de la norma (el estándar original está en inglés y no lo tienes); mantén el enlace a la fuente oficial. Marca la sección "paraphrased from the project's reference document".

## Glosario base (crea `docs/GLOSSARY_EN.md` como fuente única y ampliala)
**Navegación y pantallas:** Panel → Dashboard · Registrar → Log · Indicador → Indicators · Sistemas → Body systems · Historial → History · Alertas → Alerts · Guía → Guide · Rutinas → Routines · Misión → Mission · Estación y tierra → Station & ground · Tripulación → Crew · Simulación → Simulation · Cola a tierra → Ground queue · Médico de vuelo → Flight Surgeon · Botiquín → Medical kit · Aviso legal → Legal notice · Acceso → Sign in · Cerrar sesión → Sign out · Auditoría → Audit log · Apoyo → Support · Tema Oscuro/Claro/Alto → Dark / Light / High contrast.
**Panel:** Tripulante → Crew member · Día de misión → Mission day · Órbita → Orbit · Última lectura → Last reading · Altura/Peso/Sangre → Height/Weight/Blood type · Qué hacer ahora → What to do now · Enlace activo → Link active · Respuesta de tierra en ~1 min → Ground response in ~1 min · Repetir medición → Repeat measurement · Ver guía → View guide · Avisar a tierra → Alert ground · Tendencia 7 días → 7-day trend · Tu base → Your baseline · Hoy → Today · Mín/Máx → Min/Max · "Esta herramienta orienta, no diagnostica. Ante cualquier duda, contacta a tierra." → "This tool provides guidance only; it does not diagnose. If in doubt, contact the ground."
**Estados y marcas:** Normal → Normal · Precaución → Caution · Crítico → Critical · Sospechoso → Suspect data · Simulado → Simulated · Próximamente → Coming soon · Sin cobertura todavía → No coverage yet · Falta medir → Not yet measured · Valor de ejemplo → Example value (unvalidated) · Pendiente de revisión médica → Pending medical review · Pendiente de verificación médica → Pending medical verification · Fuentes → Sources · Requisito → Requirement · Revisión → Revision · Norma / Guía / HRP / Referencia → Standard / Guide / HRP / Clinical reference.
**Indicadores:** Presión arterial → Blood pressure · Frecuencia cardíaca → Heart rate · Saturación O₂ → SpO₂ · Glucosa → Glucose · Temperatura → Temperature · IMC → BMI · Pérdida ósea (cadera) → Bone loss (hip) · Masa muscular → Muscle mass · Radiación diaria → Daily radiation · Dosis acumulada → Cumulative dose · Hidratación → Hydration · Sueño → Sleep · Estrés / cognitivo → Stress / cognitive · Visión (SANS) → Vision (SANS) · Ánimo → Mood.
**Sistemas (11):** Cardiovascular · Respiratory · Musculoskeletal · Sensory & vestibular · Ocular (SANS) · Immune · Hematologic · Mental health · Sleep & circadian rhythm · Metabolism · Radiation.
**Roles y misión:** Tripulante / CMO / Flight Surgeon → Crew member / Crew Medical Officer (CMO) / Flight Surgeon · Nivel de atención → Care level · Fases prevuelo/vuelo/posvuelo → Pre-flight / In-flight / Post-flight · Entornos: estación → Space station (LEO), nave → Spacecraft (transit), base lunar → Lunar base, base marciana → Mars base · "Especialista de misión" → Mission Specialist.
**Botiquín (nombres y grupos):** prometazina → promethazine · meclizina → meclizine · ondansetrón → ondansetron · melatonina → melatonin · zolpidem · zaleplón → zaleplon · modafinilo → modafinil · cafeína → caffeine · ibuprofeno → ibuprofen · acetaminofén → acetaminophen · aspirina → aspirin · ketorolaco → ketorolac · amoxicilina/clavulanato → amoxicillin/clavulanate · azitromicina → azithromycin · ciprofloxacino → ciprofloxacin · valaciclovir → valacyclovir · epinefrina → epinephrine · oxígeno al 100 % → 100 % oxygen · enoxaparina → enoxaparin. Grupos: Mareo por adaptación → Space adaptation (motion sickness) · Sueño y fatiga → Sleep & fatigue · Analgesia e inflamación → Pain & inflammation · Antimicrobianos y antivirales → Antimicrobials & antivirals · Emergencias → Emergency.
**Médico flotante:** Médico de vuelo (simulado) → Flight Surgeon (simulated) · Entendí → What I understood · Hipótesis a descartar → Hypotheses to rule out · Del botiquín → From the medical kit · Seguimiento → Follow-up · Cuándo escalar → When to escalate · Datos que usé → Data used · Avisar a tierra ahora → Alert ground now · Me ayudó / No me ayudó → It helped / It did not help.
Completa el glosario con todo término nuevo que encuentres y respétalo en todo el proyecto.

## Tareas, en este orden

### 1. Desacoplar la lógica del texto (sección "Trampas críticas")
Hazlo entera, con tests que prueben que el comportamiento no cambia (requisito resuelto, duplicado contado, filtro de diagnóstico en inglés, analizador en inglés). Commit propio. Tras este paso la suite debe seguir en verde **antes** de traducir.

### 2. Idioma y formatos
`<html lang="en">` en layouts (`app`, `login`) y páginas estáticas; `manifest.webmanifest` (`lang`, `name`, `short_name`, `description`); `<title>` de cada página; `config/app.php` ya tiene `locale` y `fallback_locale` en `en`: confírmalo y que Carbon, validación de Laravel y fechas salgan en inglés. Revisa `resources/js/**` por `toLocaleString('es…')`, `Intl` y formatos de fecha y número; fija inglés de EE. UU. con punto decimal.

### 3. Contenido de configuración y fichas
Traduce los textos visibles de todos los `config/*.php` y de `conocimiento/**` (frontmatter y cuerpo), sin tocar claves ni estructuras. Misma terminología del glosario. Marca sin tocar los flags `valor_ejemplo`, `revision_medica` y los campos de fuente.

### 4. Textos generados por servicios
`CatalogoRecomendaciones`, `OrientacionAlertas`, `EvaluadorVentanas`, `CalidadSenal`, `ResumenTripulante`, `ResolvedorRequisito`, `AccionesPanel`, `DetectorPatrones`, `RecomendacionesEspecificas`, `BotiquinOpciones`, `OrientadorSimulado`/`OrientadorConversacional`/`OrientadorProximamente`, avisos del flotante, mensajes de `ProcesadorMediciones` y `ValidadorMedicion` (los que llegan a pantalla y a la respuesta de la API). Tonos y avisos según la guía de estilo.

### 5. Datos guardados que se ven
- **Seeders** (`AstronautasSeeder`, `ExpedicionSeeder`, `HistorialExpedicionSeeder`, `RutinasSeeder`, `BotiquinSeeder`, `RequisitosSeeder`, etc.): roles, misiones, eventos, expediente de demostración, botiquín, requisitos. Deben ser **idempotentes** (que repetirlos no duplique).
- **Comando `i18n:datos`**, con **simulación por defecto** y `--confirmar` para escribir: actualiza **en el sitio** (nunca borra y recrea filas) los textos ya guardados que se ven en pantalla: `evaluaciones.recomendacion` (solo hay un conjunto pequeño de textos distintos: mapea cada uno de español a inglés), `requisitos` (por la nueva `clave`), `botiquin_items` (nombre, grupo, contraindicaciones, nota), `misiones` y `astronautas` (rol, nombre de misión), eventos y cualquier texto generado por el sistema en `alertas_salientes.carga`, `consultas_tierra` y `casos_clinicos`. **No toques** texto escrito por usuarios (síntomas, notas, `consultas_sintomas`). Debe ser idempotente y mostrar un resumen de lo que cambiaría.
- **Ejecútalo solo sobre la base de pruebas.** Sobre la real: **avísame y espera mi confirmación**.

### 6. Vistas Blade, componentes Livewire y JavaScript
Recorre `resources/views/**` y los componentes de `app/Livewire/**`: títulos, botones, estados vacíos, placeholders, `aria-label`, `title`, texto para lectores de pantalla, mensajes de validación y sus nombres de atributo, el flotante del Médico de vuelo y todas las pantallas (Dashboard, Crew, Indicators, Log, Routines, Alerts, Guide, Mission, Flight Surgeon, History, Body systems, Medical kit, Ground queue, Audit log, Simulation, Sign in, Legal notice). En `resources/js/**` traduce los textos de la pantalla Indicators (modos, filtros, ejes, tooltips, "Todos / Solo atención / Solo graves", "Hora / Día / Semana"…). Páginas estáticas `public/offline.html` y `public/body-preview.html`.
**Cuidado con el diseño:** el inglés tiene otra longitud. Comprueba que nada se corta ni desborda en botones fijos, píldoras, pestañas, chips de 44 px, tarjetas de Sistemas y el flotante, también a 390 px de ancho y en los tres temas.
Tras cambiar JavaScript, `node --test resources/js/indicador/modelo.test.mjs` y regenera los assets con `npm run build` (`public/build` está ignorado por git: no lo commitees).

### 7. Herramienta de auditoría `i18n:audit`
Comando y test que **fallan si queda español visible**. Debe:
- Escanear las superficies del alcance (vistas, JS, config, seeders, textos de servicios y componentes, `conocimiento/`, `public/*.html`, `manifest` **y `tests/**` con los tests de JavaScript**). En la aplicación detecta por **literales de cadena** (no comentarios ni identificadores); **en los tests también revisa comentarios, docblocks y nombres de métodos y clases** (separando `snake_case` y `CamelCase` en palabras). Detecta: caracteres `áéíóúñÁÉÍÓÚÑ¿¡`, palabras funcionales españolas (de, del, la, el, los, las, un, una, que, con, para, por, sin, sobre, está, son, tu, tus, su, sus, mi) y una lista de palabras de interfaz (Guardar, Cancelar, Enviar, Cerrar, Volver, Siguiente, Anterior, Buscar, Todos, Hoy…).
- Usar una **lista de permitidos** versionada (`i18n-allowlist.txt`, con ruta, patrón y motivo) para identificadores, claves y los sinónimos en español del léxico de síntomas.
- Modo `--render`: pide **cada ruta** (con un tripulante de demostración) y revisa el **texto visible** del HTML, ignorando `<script>` y `<style>`.
- Mostrar un recuento por archivo y salir con error si hay hallazgos.

### 8. Tests completos en inglés
Hay unos 60 archivos de test más los de JavaScript. Todo lo que aparece cuando alguien los ejecuta debe estar en inglés:
- **Nombres de métodos de test** (`test_ruta_auditoria_solo_flight_surgeon` → `test_audit_route_is_flight_surgeon_only`): frases cortas y descriptivas en inglés, en `snake_case`. La salida de `php artisan test` convierte esos nombres en frases legibles, así que deben leerse bien.
- **Nombres de clases y archivos de test**: renómbralos con `git mv` y actualiza el `class` (p. ej. `SistemasTest` → `BodySystemsTest`, `AuditoriaTest` → `AuditLogTest`). Los de la aplicación (`App\Livewire\Sistemas`…) **no cambian**; el test puede llamarse en inglés aunque pruebe una clase con nombre español. Conserva un mapa (`docs/TEST_RENAMES.md`: nombre antiguo → nuevo) para el informe.
- **Comentarios, docblocks, mensajes de aserción, nombres de variables y claves de `dataProvider`, datos de ejemplo** y cualquier literal de texto (nombres ficticios sí pueden quedarse).
- **Aserciones de texto de la interfaz:** actualízalas al inglés nuevo. **Preferible:** que lean el valor de la misma config (`config('sistemas.grupos.0.titulo')`) en lugar de repetir la cadena, para que no vuelvan a desfasarse.
- **JavaScript:** `resources/js/indicador/modelo.test.mjs` y cualquier otro: descripciones de `test(...)`, mensajes y datos de ejemplo.
- **Helpers y fábricas de test** (`tests/**`, `TestCase`): nombres y comentarios.
- **Salida verificable:** ejecuta `php artisan test --testdox` y `php artisan test --list-tests`, extrae los **tokens únicos** de los nombres y revísalos uno a uno; no puede quedar ninguna palabra española (ruta, solo, muestra, guarda, tarjeta, valor, estado, vacío, sin, con…). Añade el resultado al informe.
- Añade los tests nuevos de las tareas 1 y 7, ya en inglés.
- Hazlo **por lotes pequeños** (unos 8–10 archivos), corriendo la suite entre lotes, y commit en cada lote. El número de tests (hoy 503) **no debe bajar**; dime el total antes y después.
- Respeta `DatabaseTransactions` y la base `healts_nasa_test`: no cambies cómo funcionan los tests, solo su lenguaje.

### 9. Verificación en el navegador
Con `preview_start` o el navegador del panel, recorre **todas las rutas** en tema claro, oscuro y alto contraste y a 390 px. Si la ventana está oculta y no deja capturar pantalla, usa `get_page_text` o `read_page` y comprueba con JavaScript desbordes (`scrollWidth > clientWidth`). Abre el flotante, escribe los dos ejemplos de síntomas en inglés y comprueba la respuesta completa. Confirma que no queda español en pantalla (`i18n:audit --render`).

### 10. Cierre
- `docs/GLOSSARY_EN.md` completo y `docs/TRANSLATION_REVIEW.md` con lo que debe revisar una persona (términos clínicos dudosos, traducciones de requisitos que no son citas literales).
- Suite completa en verde, auditoría sin hallazgos, JS y build correctos.

## Definición de terminado
- Ninguna pantalla muestra español (auditoría `--render` limpia), con diseño intacto en móvil y en los tres temas.
- **La salida de `php artisan test --testdox` y de los tests de JavaScript está íntegramente en inglés** (nombres de clases, de tests y mensajes), con el mismo número de tests o más, y `docs/TEST_RENAMES.md` con el mapa de nombres.
- La resolución de requisitos, el conteo de duplicados, el filtro de seguridad y el analizador de síntomas funcionan en inglés (tests).
- El comando `i18n:datos` existe y se probó en la base de pruebas; la base real **no se modificó**.
- Informe final con: cantidad de archivos y cadenas cambiados, hallazgos de lógica acoplada y cómo los resolviste, términos dudosos pendientes de revisión, tests renombrados y total de tests antes y después, lo que no pudiste verificar, y los pendientes fuera de alcance (URLs, comentarios de la aplicación, README y `docs/`, PDF, app del reloj y simulador de Python).
