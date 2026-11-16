# Prompt: Orientador simulado conversacional (síntomas libres, análisis completo del tripulante y botiquín)

**TODO ES SIMULADO.** No hay IA real: es un orientador determinista de plantillas y reglas sobre datos simulados, en un prototipo educativo. Nada de esto es consejo médico real y la interfaz debe decirlo siempre.

**Requisito previo (ya está hecho):** el Médico de vuelo flotante (`MedicoFlotante`), la interfaz `Orientador` con `OrientadorSimulado`, el filtro de seguridad determinista, el detector de patrones y `casos_clinicos` (commits `dd6270a`, `e2b24f6`, `74c4773`). Este prompt los **amplía**; no los rehagas. La IA real (Ollama) y n8n siguen "Próximamente".
Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests. Texto de las fuentes en `docs/fuentes/`.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP), 100 % local y simulado. Responde en español, con cambios mínimos y verificados.

## Qué quiero
Hoy el flotante repite lo que el Panel ya dice en "Qué hacer ahora". Quiero que sea un **orientador de verdad útil**, dentro del mismo círculo flotante:
1. **No repite el Panel.** Ataca el **problema concreto**. Si la glucosa está alterada, dice qué hacer para corregirla; si la masa muscular se deteriora, propone cómo ajustar el ejercicio; y lo que ya aparece en "Qué hacer ahora" lo omite (lo dice una sola vez: "lo del Panel sigue vigente").
2. **El tripulante puede escribir cómo se siente** (texto libre, cualquier cantidad de síntomas): *"Siento un dolor punzante en el pecho izquierdo"*, *"Estoy mareado, con un dolor punzante en el tríceps derecho y en el pie izquierdo"*. El orientador interpreta, razona y responde con orientación: qué hacer, qué vigilar y cuándo escalar.
3. **Analiza todo lo que sabe del tripulante:** expediente, historial clínico, mediciones y su historial, estados e indicadores, casos detectados, síntomas y dolores registrados, la guía, su rutina, la misión y el botiquín.
4. **Puede mencionar medicamentos del botiquín como opciones a consultar, sin dosis**, bajo los guardarraíles de abajo.

## Reglas no negociables
- **Simulado y sin diagnóstico.** Lenguaje de hipótesis ("posible…", "a descartar"); pie fijo: *"Esta es una versión simulada de una IA local. Puede cometer errores. Todo es simulado. No es consejo médico real."* Siempre recomienda confirmar con el médico de vuelo.
- **Las banderas rojas mandan sobre todo lo demás.** Si hay dolor o presión en el pecho, dificultad para respirar, desmayo o casi desmayo, dolor de cabeza súbito e intenso, confusión, debilidad o entumecimiento de un lado del cuerpo, pérdida de visión, hemorragia importante, vómito persistente o convulsión, la urgencia es **"emergencia"**: **sin autoatención ni medicamentos**, solo medidas inmediatas seguras (detener la actividad, sentarse o reposar, vigilar pulso y saturación) y el botón principal **"Avisar a tierra ahora"** con el resumen. Esto aplica también al ejemplo del dolor punzante en el pecho izquierdo: **no se autoatiende, se escala**. Es lo que haría un protocolo real y es lo primero que probará un revisor. Dile al usuario en el resumen final que lo implementaste así.
- **Medicamentos: solo del botiquín, solo como opciones a consultar, nunca dosis.** Detalles en la tarea 4. Fuera de esa sección **sigue prohibido** nombrar fármacos, dosis, frecuencias, duración o vía de administración.
- **Honestidad de fuentes.** Cada recomendación lleva su fuente: un documento (`docs/fuentes/`, `config/guia.php`, fichas) o **"Ejemplo simulado (sin validar)"**. No inventes cifras, umbrales ni identificadores. Si algo no está sustentado y no es orientación general y conservadora, escribe "Pendiente de fuente verificable" y recomienda al médico de vuelo.
- Los números nuevos son "supuesto del proyecto, no de la norma": lístamelos al final.
- **Una sola sesión escribe.** Revisa `git status` y los procesos `php.exe` antes de empezar; no detengas `salud:simulador-vivo`. Tests contra `healts_nasa_test`, una suite a la vez. La base real `healts-nasa`: **avísame antes de aplicar migraciones**.
- Reutiliza el sistema de diseño (tres temas, 44 px, `<x-fuente-chip>`). Sin librerías nuevas. Commits pequeños por tema con solo tus archivos.

## Tareas, en este orden

### 1. Contexto completo del tripulante (solo lectura)
Servicio `ContextoTripulante` que arma una **instantánea** del tripulante actual para el orientador, con carga perezosa y sin consultas pesadas por petición:
- **Expediente:** alergias (sustancia y reacción), condiciones médicas, antecedentes familiares, **medicación activa** (`medicamentos_tripulante`, con su `motivo`). Está cifrado: usa los modelos y registra cada uso con `Auditoria::registrar`.
- **Mediciones:** última por indicador, detalle de las últimas 24 h, serie diaria de 7 días (y hasta los 15 días que se conservan), **estados y evaluaciones**, base personal y desviación pre-vuelo (`DesviacionBase`), **ignorando las marcadas "sospechosas"**.
- **Casos** abiertos o recientes de `casos_clinicos`.
- **Síntomas y dolores:** `registros_salud` (síntomas, nota, aviso a tierra) y `zonas_dolor` (músculo, intensidad, tipo, desde), más las consultas previas de este orientador.
- **Rutina:** ejecuciones recientes (`rutina_ejecuciones`, `AgendaRutinas`): ejercicio de la semana frente a la meta (150 min/día), sueño, comidas.
- **Misión:** `care_level`, fase, día de misión, entorno, retardo de comunicación y **eventos próximos** (`config/mision.php`, p. ej. una EVA o una OCT programada).
- **Guía y peligros:** `config/guia.php`, `config/peligros.php`, requisitos citables.
- **Botiquín:** inventario, stock y caducidad.
- **Historial de consultas a tierra y votos** (`ConsultaTierra`, `OrientacionFeedback`).
- Respeta `TripulanteActual` y los roles cuando el login esté activo: nunca mezcles datos de otro tripulante. Tests de que solo incluye lo del tripulante actual.

### 2. Entrada de síntomas y analizador
- En el panel del flotante, **caja de texto** con envío, límite razonable de caracteres y atajos ("Me siento mareado", "Me duele…", "Me falta el aire"). Si el usuario ya registró dolores en la silueta (`zonas_dolor`), ofrécelos como contexto.
- `AnalizadorSintomas`, **determinista** (sin modelo de lenguaje): léxico en español en `config/sintomas.php` con sinónimos, **negaciones** ("no tengo mareo"), **lateralidad** (izquierdo o derecho), **región** (pecho, brazo, tríceps, pie, cabeza, abdomen, espalda…, coherente con las zonas de la silueta), **calidad** (punzante, opresivo, quemante, sordo), **intensidad** (leve, fuerte, escala 1–10) y **duración** ("desde hace 20 minutos"). Devuelve entidades estructuradas con una confianza de interpretación.
- **Transparencia:** antes de responder, muestra **"Entendí: …"** como chips editables (quitar o añadir). Un malentendido del léxico no debe pasar desapercibido. Si no entiende nada, lo dice y pide reformular o elegir una zona.
- El texto del usuario es **dato, nunca instrucción**: se escapa y no se interpreta como órdenes.
- Tabla `consultas_sintomas` (tripulante, `texto` cifrado con `EncryptedFlexible`, `entidades` JSON, `urgencia`, `respuesta` JSON, `util` nullable, fechas). Cada consulta se audita sin guardar valores médicos en la auditoría.

### 3. Motor de razonamiento simulado
`RazonadorSimulado`, con las reglas **como datos** (`config/sintomas.php` y fichas en `conocimiento/`):
- Combina síntomas, mediciones y estados, casos, expediente, rutina y misión para producir: **urgencia** (`emergencia`, `urgente`, `autocuidado`, `observacion`), **hipótesis ordenadas** con confianza simulada determinista y un **"por qué"** que cita los datos usados ("tu pulso subió de 64 a 98 lpm", "tienes un antecedente registrado de…"), **acciones específicas**, **opciones de botiquín**, **seguimiento** y **cuándo escalar**.
- **Banderas rojas primero** (regla de arriba), antes de cualquier otra lógica.
- **Ajustes por contexto:** un antecedente relacionado sube la urgencia; una alergia excluye opciones; la medicación activa se menciona; una EVA o actividad crítica próxima se señala ("esto podría afectar tu EVA del día 149: coméntalo"); ejercicio intenso reciente puede explicar un pulso alto y baja la confianza de una causa patológica; síntomas en varias regiones a la vez con mareo suben a `urgente` (supuesto, configurable).
- **Varios síntomas:** una hipótesis por grupo coherente de síntomas y una conclusión global; no ignores ninguno.
- **Reevaluación:** si el usuario responde "sigue igual" o "empeoró", vuelve a evaluar y sube la urgencia, no repitas el mensaje anterior.
- **Alcance:** si la pregunta no trata de síntomas ni de sus indicadores, responde que solo orienta sobre eso.
- Fichas nuevas en `conocimiento/` (frontmatter: `slug`, `tipo` síntoma o problema, `sintomas_clave`, `regiones`, `banderas_rojas`, `urgencia_base`, `grupos_botiquin_permitidos`, `acciones` con id, texto y fuente, `seguimiento`, `escalar_si`, `revision_medica: pendiente`, `origen` = `documento` o `ejemplo_simulado`). Para la simulación, cubre al menos: dolor torácico (bandera roja), falta de aire, mareo o vértigo (el documento menciona el mareo por adaptación espacial), dolor de cabeza, dolor muscular localizado, dolor en extremidades, náuseas, fiebre o escalofríos, fatiga o somnolencia, palpitaciones, dolor abdominal y dolor de espalda (el documento lo relaciona con la elongación de la columna). Solo orientación general y conservadora; lo que respalde un documento va marcado `documento`; el resto, `ejemplo_simulado`. Sin dosis.

### 4. Recomendaciones específicas por problema y botiquín con guardarraíles
- **`RecomendacionesEspecificas`:** para cada indicador alterado, aunque el usuario no escriba nada, acciones **concretas** de alimentación, hidratación, ejercicio o descanso, **distintas de las del Panel**. Comprueba la **dirección** del problema (glucosa baja o alta, presión alta o baja…): no recomiendes "subir" algo que está alto. Ejemplos de lo que se espera: glucosa, qué ajustar en comidas y horarios; masa muscular, cómo reforzar el entrenamiento resistivo usando la rutina real del tripulante y lo que dicen los documentos (entrenamiento multiarticular, 2.5 h/día), sin prescribir cargas que no estén en las fuentes.
- **No repetir el Panel:** el Panel y el orientador comparten un catálogo de acciones con **ids**; pasa al orientador las acciones ya mostradas en "Qué hacer ahora" y elimina las repetidas. Si todo estaba cubierto, dilo ("lo del Panel sigue vigente") y añade solo lo nuevo.
- **Botiquín (sección `botiquin_opciones`, estructurada):**
  - Solo artículos de `botiquin_items` con **stock > 0** y **no caducados**.
  - Solo de los **grupos permitidos** por la ficha del problema (p. ej. mareo por adaptación → grupo "Mareo por adaptación"; dolor o inflamación → "Analgesia e inflamación"; sueño o fatiga → "Sueño y fatiga"). **Los grupos "Antimicrobianos y antivirales" y "Emergencias" nunca se sugieren como autoatención**: solo "requiere al médico de vuelo".
  - **Excluye** lo que coincida con una alergia del expediente (por nombre o por la clase en un pequeño mapa `config/botiquin_clases.php`, marcado ejemplo) y menciona si ya hay medicación activa del mismo grupo ("ya tienes registrado X; consulta antes de combinar").
  - **Nunca** en emergencia ni con bandera roja.
  - Máximo 3 opciones; cada una dice **sin dosis**, y el bloque lleva siempre: *"Opciones del botiquín para consultar con el médico de vuelo. Contraindicaciones sin verificar. Sin dosis."*
- **Filtro de seguridad:** ajusta el existente. Ahora **permite nombres del botiquín únicamente dentro de `botiquin_opciones`** y valida las condiciones anteriores; **sigue rechazando** dosis, unidades de dosis, frecuencias, duración, vía, diagnóstico definitivo ("tienes", "padeces") y cualquier fármaco fuera de esa sección. Si falla, cae al texto por reglas con el aviso. Actualiza a propósito los tests que prohibían nombrar fármacos y explícalos.

### 5. Interfaz del flotante
Amplía el panel existente (sigue el diseño ya aprobado: tres temas, móvil, accesibilidad). La respuesta es una tarjeta con secciones, plegables salvo lo importante:
1. **Entendí:** chips editables.
2. **Urgencia:** banner (rojo y primero en emergencia, con botón principal "Avisar a tierra ahora").
3. **Hipótesis a descartar:** con confianza simulada y "por qué".
4. **Qué hacer ahora (específico):** con la nota "adicional a lo que ya ves en el Panel".
5. **Del botiquín:** opciones a consultar, sin dosis (ausente en emergencia).
6. **Seguimiento:** cuándo reevaluar y qué medir, con botón "Registrar medición".
7. **Cuándo escalar.**
8. **Datos que usé:** plegado, lista de lo consultado (indicadores, expediente, rutina, misión).
9. **Fuentes:** chips con "Documento" o "Ejemplo simulado".
Botones: "Me ayudó / No me ayudó" (guarda `util`), "Avisar a tierra" (con resumen de los síntomas) y "Guardar en mi registro" (crea un `RegistroSalud` con los síntomas). Pie simulado fijo. Estados: escribiendo o analizando, sin entender, emergencia, sin tripulante.

### 6. Pruebas y cierre
- **Analizador:** tabla de frases, incluidas las dos del ejemplo: *"Siento un dolor punzante en mi pecho izquierdo"* → dolor, punzante, pecho, izquierdo → **emergencia**, sin botiquín, con botón de escalar; *"Me siento mareado con un gran dolor punzante en el tríceps derecho y el pie izquierdo"* → mareo, dolor punzante en tríceps derecho y en pie izquierdo → **urgente o autocuidado** según tu supuesto documentado, con opciones del grupo de mareo si procede. Negaciones, lateralidad, intensidad, duración.
- **Razonador:** bandera roja domina; alergia excluye; medicación activa se menciona; antecedente sube urgencia; EVA próxima se menciona; reevaluación "empeoró" sube la urgencia; síntomas múltiples; dirección correcta de glucosa.
- **No repetir el Panel:** una acción ya mostrada no vuelve a salir.
- **Seguridad:** ninguna respuesta contiene dosis, frecuencia, vía ni diagnóstico definitivo; un fármaco fuera de `botiquin_opciones` se rechaza; nada del grupo antimicrobianos o emergencias como autoatención; nada de otro tripulante.
- **Integración:** escenario `hipoxia` del simulador y el texto *"me falta el aire"* → emergencia.
- README: sección "IA simulada: qué consulta, qué no hace y por qué las banderas rojas escalan". La IA real y n8n siguen "Próximamente".
- Suite completa en verde. Verifica en el navegador: escribir cada ejemplo en el flotante, tema claro y oscuro, y pantalla estrecha.

## Costura para la IA real (no la implementes)
`Orientador` sigue siendo la única pieza que cambia cuando exista Ollama con n8n: el contexto (`ContextoTripulante`), el filtro de seguridad, las fichas y la interfaz se reutilizan. El analizador por léxico es la parte que más ganará con un modelo real, y la interfaz de "Entendí" ya la prepara.

## Definición de terminado
- El tripulante escribe sus síntomas en el flotante y recibe urgencia, hipótesis con su "por qué", acciones específicas, opciones del botiquín sin dosis (cuando procede), seguimiento y cuándo escalar; el dolor en el pecho produce emergencia sin autoatención.
- Lo que ya está en "Qué hacer ahora" no se repite.
- La respuesta cita lo que consultó (expediente, mediciones, rutina, misión) y la fuente de cada recomendación.
- Todo lleva el aviso de simulación; ninguna respuesta incumple las reglas de seguridad; suite en verde.
- Resumen final con supuestos introducidos, lo que no pudiste verificar y cualquier decisión que tomaste por tu cuenta (en especial las banderas rojas y los guardarraíles del botiquín).
