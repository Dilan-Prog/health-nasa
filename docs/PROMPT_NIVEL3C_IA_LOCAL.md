> **Aplazado: Próximamente.** La IA local (Ollama) y n8n no se construyen por ahora; este documento queda como hoja de ruta. La experiencia con IA **simulada** está en `docs/PROMPT_MEDICO_FLOTANTE.md`.

# Prompt Nivel 3C: Médico de vuelo con IA local (Ollama) orquestada por n8n

**Requisito previo:** Niveles 1, 2 y 3A terminados. La IA debe quedar **apagada por defecto** (`IA_ACTIVA=false`): el sistema tiene que funcionar completo sin ella.
Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests. Texto de las fuentes en `docs/fuentes/`.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP), **100 % local, sin internet en operación**. Quiero que el Médico de vuelo **detecte patrones** (fiebre, hipoxia, deshidratación, estrés agudo, irregularidad del pulso, falta de sueño, fallo de sensor) y que una **IA local** (Qwen con Ollama, orquestada por n8n) redacte una orientación con fuentes y confianza. Principios no negociables:
1. **La IA sugiere; nunca diagnostica ni decide.** Hipótesis "a descartar", nunca "tiene X". Siempre recomienda confirmar con el médico de vuelo.
2. **La detección y las emergencias son deterministas y viven en Laravel**, no en la IA ni en n8n. Un patrón crítico se muestra y se escala a tierra sin esperar a nadie.
3. **El conocimiento médico va en documentos versionados (fichas), no escondido en prompts ni en nodos de n8n.**
4. **Ni dosis, ni medicamentos, ni tratamientos.** Solo qué chequear, qué señales vigilar y cuándo escalar.
5. **Todo se valida en Laravel** (esquema, fuentes, seguridad). Lo que devuelva n8n o el modelo es dato no confiable.
6. Si algo falla (IA apagada, n8n caído, tiempo agotado, JSON inválido, filtro de seguridad), se muestra la orientación por reglas y se marca claramente.

Responde en español, con cambios mínimos y verificados.

## Reglas de trabajo
- **Una sola sesión escribe.** Revisa `git status` y los procesos `php.exe` antes de empezar. No detengas `salud:simulador-vivo` si está corriendo.
- **Tests** contra `healts_nasa_test`; una sola suite a la vez. La base real `healts-nasa`: **avísame antes de aplicar migraciones**.
- **Nada clínico está validado.** No inventes contenido médico: las fichas usan SOLO lo que dicen los documentos de `docs/fuentes/` (y las páginas HRP citadas), con la fuente anotada, y quedan marcadas `revision_medica: pendiente`. Los umbrales nuevos son "supuesto del proyecto, no de la norma"; lístamelos al final.
- **No puedes probar Ollama ni n8n reales:** no están instalados. Todo se prueba con simulaciones (`Http::fake` y un servidor de prueba). La prueba real la hará el usuario siguiendo `n8n/README.md`.
- Reutiliza lo que existe (no dupliques): `ProcesadorMediciones`, `Evaluacion`, `OrientacionAlertas`, `Livewire\MedicoVuelo` (ya tiene tarjetas, voto "me ayudó", borrador y envío de consultas a tierra), `OrientacionFeedback`, `ConsultaTierra`, `SimulacionEvento`, `care_level`, `Requisito`.
- Commits pequeños por tema con solo tus archivos.

## Tareas, en este orden

### C1. Casos y detector de patrones (funciona sin IA)
- Migración `casos_clinicos`: `astronauta_id`, `patron`, `severidad` (precaucion|critico), `confianza_regla` (0–1), `evidencia` JSON (ids de mediciones, valores, ventana y estados usados), `estado` (detectado|analizando|orientado|fallback|escalado|cerrado), `orientacion` JSON nullable, `origen_orientacion` (ia|reglas), `requisito_ids` JSON, `simulacion_evento_id` nullable, `detectado_en`, `cerrado_en`.
- `config/patrones.php`, **como datos**. Un patrón es una combinación de **estados de indicadores existentes** (`precaucion`/`critico` según los umbrales que ya hay) dentro de una ventana de minutos, para no inventar umbrales numéricos nuevos. Punto de partida, ajústalo mirando `config/simulacion.php` (escenarios `fiebre`, `hipoxia`, `deshidratacion`, `estres`, `arritmia`, `falta_sueno`, `fallo_sensor`):
  - fiebre: temperatura alterada + pulso alterado;
  - hipoxia: saturación de O₂ baja + pulso alto (+ estrés cognitivo si está);
  - deshidratación: hidratación baja + pulso o temperatura alterados;
  - estrés agudo: estrés cognitivo + pulso (+ presión);
  - irregularidad del pulso: pulso con saltos repetidos. **Con una lectura cada pocos minutos no se puede hablar de arritmia:** el caso se llama "irregularidad del pulso (requiere ECG)" y siempre recomienda ECG o contacto;
  - falta de sueño: la regla de ventana del 3A;
  - fallo de sensor: lecturas marcadas "sospechosas" del 3A (en este caso se recomienda revisar el dispositivo, no la salud).
  Cada patrón: señales requeridas y opcionales, ventana, severidad, si es emergencia, `ficha` asociada y requisitos que cita.
- `DetectorPatrones`: evita duplicados (un caso abierto por tripulante y patrón en un periodo configurable), sube la severidad si empeora y cierra el caso cuando las señales vuelven a normal. Debe ser barato: se llama solo cuando entra una medición en precaución o crítico, con un pequeño enfriamiento por tripulante (la ruta recibe ~56 mediciones por minuto).
- **Emergencia:** un caso crítico crea de inmediato la tarjeta por reglas y la alerta a tierra existente, con su requisito; no espera a la IA.
- Mostrar los casos en el Médico de vuelo con la lógica por reglas (texto del catálogo) y la etiqueta "Orientación por reglas". Con `IA_ACTIVA=false` es todo lo que hay.
- Tests por patrón con tabla de casos (señales → caso esperado), sin duplicados, cierre, emergencia sin IA y que no dispare con datos "sospechosos".

### C2. Fichas de conocimiento, contrato y filtro de seguridad
- Carpeta `conocimiento/` con una ficha `.md` por patrón y frontmatter YAML: `slug`, `patron`, `titulo`, `fuentes` (lista con tipo, título y archivo o url), `revision_medica: pendiente`. Cuerpo con secciones fijas: señales a vigilar, qué chequear, cuándo escalar, límites de lo que el sistema puede afirmar. **Solo contenido sustentado por los documentos** (p. ej. hipoxia: "disminución del oxígeno disponible en los tejidos; fatiga, dolor de cabeza y menor concentración", sección 1.6 de `salud_astronautas_y_monitoreo.txt`; fiebre: el criterio de la referencia de temperatura es un salto sobre la propia media, y > 40 °C es esperable durante el ejercicio). Si un documento no cubre algo, escribe "Pendiente de fuente verificable" en lugar de rellenar. Sin dosis ni medicamentos.
- `LectorFichas`: carga y valida las fichas (frontmatter obligatorio); test que falla si falta una ficha de un patrón.
- **Contrato JSON** (versionado, `version: 1`), documentado en `docs/contrato_ia.md` y validado con reglas de Laravel:
  - Entrada a n8n: `caso_id`, `version`, `instrucciones` (texto de `resources/prompts/orientacion.md`, que solo contiene reglas de formato y seguridad, nunca conocimiento médico), `patron`, `severidad`, `care_level`, `evidencia` (valores y ventana), `base_personal` (si existe), `fichas` (contenido completo de las relevantes), `tripulante` mínimo (id, edad). No envíes nombre, expediente ni datos de contacto.
  - Salida: `hallazgo` (texto), `severidad` (precaucion|critico), `sugerencias` (lista de pasos), `chequeos`, `senales_alarma`, `confianza` (0–1), `fuentes` (lista de `slug` de las fichas enviadas), `requiere_medico` (bool), `modelo` (texto).
- `FiltroSeguridadOrientacion`, **determinista, en Laravel**: rechaza la respuesta si (a) no cumple el esquema; (b) cita una fuente que no se envió; (c) contiene dosis o unidades de dosis (`mg`, `ml`, `mcg`, `UI`, `g/día`, etc.) o nombres de medicamentos del botiquín; (d) usa lenguaje de diagnóstico definitivo ("tiene", "padece", "diagnóstico de"); (e) `requiere_medico` es falso en severidad crítica; (f) `confianza` fuera de rango. Si falla: el caso pasa a `fallback` (orientación por reglas) y se marca "revisión humana". Tests de cada causa de rechazo.

### C3. Cliente de IA, ejecución y respaldo
- `config/ia.php` y variables en `.env.example`: `IA_ACTIVA=false`, `N8N_WEBHOOK_URL`, `IA_TIMEOUT_S` (alto por defecto: el primer uso carga el modelo en una GPU de 4 GB; supuesto del proyecto), `IA_MODELO_ETIQUETA`.
- Servicio `OrientadorIA` con `Http` de Laravel: envía el contrato, valida con C2 y guarda `orientacion` y `origen_orientacion=ia`. Cualquier fallo (apagada, conexión, tiempo, JSON inválido, filtro) → `fallback` sin lanzar excepciones al usuario, con el motivo registrado.
- Ejecución sin bloquear la web: el proyecto usa `QUEUE_CONNECTION=sync`, así que no encoles un job que se ejecutaría en la petición. Sigue el patrón de `salud:simulador-vivo`: comando de larga vida `casos:procesar` que toma casos en `detectado`, los pasa a `analizando`, llama a `OrientadorIA` y actualiza. Debe apagarse limpio y tolerar n8n caído.
- Actualización en vivo del Médico de vuelo (evento Reverb nuevo, o `wire:poll` si Reverb no está): "Analizando…" → orientación o respaldo.
- Tests con `Http::fake`: respuesta buena, tiempo agotado, 500, JSON roto, fuente ajena, dosis, IA apagada.

### C4. Interfaz del Médico de vuelo
Amplía lo que ya existe, sin rehacer la pantalla. Cada caso es una tarjeta con: patrón, severidad, hallazgo, sugerencias, chequeos, señales de alarma, **confianza** con barra, **fuentes** como chips (reusa `<x-fuente-chip>` y los requisitos citados), y la etiqueta de origen: **"IA local · pendiente de revisión médica"**, **"Orientación por reglas"** o **"Revisión humana requerida"**. Banner fijo "Orientación, no diagnóstico".
- Estados: analizando, orientación lista, IA no disponible (solo reglas), emergencia (primero y en rojo, sin IA), caso cerrado.
- Retroalimentación: reutiliza `OrientacionFeedback` para "me ayudó / no me ayudó" **por caso**, y añade escala de dolor 0–10 a los 30 y 60 minutos y campo de efectos adversos. Es **registro**, nunca reentrenamiento automático.
- Con `care_level` 3 se ofrece videoconsulta; con 4 y 5 solo mensaje asíncrono, como ya hace la pantalla.
- Tests de renderizado de cada estado y etiqueta.

### C5. Artefactos de n8n y prueba de integración simulada
- Carpeta `n8n/`:
  - `workflows/orientacion-caso.json`: flujo exportable e importable. Nodos: **Webhook** (POST) → **Code** (comprueba que el cuerpo tenga el contrato) → **HTTP Request** a Ollama (`http://127.0.0.1:11434/api/chat`, `stream: false`, temperatura baja, `format` con el esquema JSON de salida, y el modelo tomado de una variable de entorno o parámetro, **sin fijar un nombre de modelo que no hayas podido verificar**) → **Code** (extrae y devuelve el JSON) → **Respond to Webhook**; rama de error que responde `{ "error": ... }`. Los mensajes del modelo se construyen con `instrucciones` y `fichas` que llegan en el cuerpo: el flujo no contiene conocimiento médico.
  - `README.md` con los pasos exactos de instalación y arranque (ver "Lo que prepara el usuario") y variables para apagar la telemetría y las comprobaciones de versión de n8n.
  - `mock/servidor-mock.js` (Node, sin dependencias): imita el webhook devolviendo respuestas de prueba (buena, mala, lenta) para probar Laravel de punta a punta sin Ollama.
- Prueba de integración que levante el mock y recorra caso → `casos:procesar` → tarjeta.
- No afirmes que el flujo de n8n "funciona": solo que su JSON es válido y que Laravel se comporta bien con un servidor equivalente. Dime qué no pudiste verificar.

### C6. Evaluación contra los escenarios etiquetados
- Comando `casos:evaluar` que cruza `simulacion_eventos` (verdad conocida: escenario, inicio, fin) con `casos_clinicos`: por escenario, **¿lo detectó?, ¿en cuánto tiempo?, ¿qué patrón?**, y **falsos positivos** (casos sin evento). Tabla en consola y exportación a CSV. Mapa escenario → patrón en `config/patrones.php`.
- Métricas de la IA que sí se pueden medir sin juicio médico: % de respuestas que pasan el esquema, % que pasa el filtro de seguridad, % de fallbacks y latencia. La **calidad médica** de la orientación la juzga una persona: deja la plantilla CSV de revisión (caso, orientación, útil sí/no, comentario) sin inventar resultados.
- Endpoint `POST /api/simulacion/eventos` (token del dispositivo, como el del reloj) para que un **simulador externo en Python** registre inicio y fin de sus escenarios y así pueda evaluarse; con tests.
- Tests: aciertos, retraso, falso positivo y escenario sin detección.

### C7. Cierre
- README: cómo encender la IA (variables, comandos `casos:procesar` y `casos:evaluar`), qué hace el sistema con la IA apagada y los límites explícitos ("orienta, no diagnostica").
- Suite completa en verde.

## Lo que prepara el usuario (no lo hagas tú; deja instrucciones claras en `n8n/README.md`)
1. **Ollama:** instalarlo en Windows (con internet una sola vez), descargar un modelo pequeño que quepa en 4 GB de VRAM (familia Qwen de ~3–4B cuantizada; comprobar el nombre exacto en la biblioteca de Ollama), probarlo con `ollama run` y comprobar si usa la GPU con `ollama ps`.
2. **n8n:** versión de Node compatible (hoy hay Node 24; verificar la compatibilidad de n8n y, si hace falta, instalar Node 22 LTS), arrancar con `npx n8n` la primera vez con internet, desactivar telemetría y avisos de versión para trabajar sin conexión, crear la cuenta propietaria, importar `orientacion-caso.json`, activarlo y copiar la URL de producción del webhook a `N8N_WEBHOOK_URL`.
3. **Red:** n8n (puerto 5678) y Ollama (11434) solo en `127.0.0.1`, sin abrir esos puertos en el firewall.
4. **Simulador:** decidir si los escenarios etiquetados se lanzan desde el panel `/simulacion` (PHP, ya registra los eventos) o desde Python (usará el endpoint de C6).
5. **Revisión médica:** alguien con criterio médico debe revisar las fichas antes de quitar "pendiente de revisión médica".
6. **Copia de seguridad** de la base antes de la primera prueba real.

## Definición de terminado
- Con `IA_ACTIVA=false`: se detectan los siete patrones, se muestran con "Orientación por reglas", las emergencias escalan solas y todo funciona.
- Con la IA simulada (mock): el caso pasa por analizando y orientación lista; una respuesta inválida o con dosis cae a respaldo y se marca "Revisión humana requerida".
- `casos:evaluar` entrega la tabla de aciertos, retraso y falsos positivos por escenario.
- Suite en verde. Resumen final con supuestos introducidos, lo que no pudiste verificar (n8n y Ollama reales) y lo que queda a cargo del usuario.
