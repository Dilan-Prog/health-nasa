# Prompt Nivel 3A: seguridad, datos confiables y hoja de ruta visible (3B "Próximamente")

**Requisito previo:** Niveles 1 y 2 terminados y commiteados (ya lo están: requisitos con `requisito_id`, login y roles tras `MISION_EXIGIR_LOGIN`, línea base pre-vuelo, `care_level`, sistemas unificados en `config/sistemas.php`).
Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests. Texto de las fuentes en `docs/fuentes/` (`LEEME.md` explica cada archivo).

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP). Orienta, no diagnostica ni receta. Responde en español, con cambios mínimos y verificados.

## Reglas de trabajo
- **Una sola sesión escribe.** Antes de empezar: `git status` y procesos `php.exe` (la simulación en vivo `salud:simulador-vivo` puede estar corriendo: no la detengas; si quieres probar algo que escriba mediciones, hazlo en tests).
- **Tests** contra `healts_nasa_test`. Una sola suite a la vez. La base real `healts-nasa` recibe la simulación y los datos del reloj: **avísame antes de aplicar migraciones o transformaciones de datos en ella**; no borres datos.
- **Nada clínico está validado.** Todo número nuevo es un "supuesto del proyecto, no de la norma": anótalo así en la config y lístamelos al final. No inventes cifras ni requisitos V1.
- Reutiliza el sistema de diseño existente. Sin librerías nuevas.
- Cada tarea termina con tests y un commit pequeño con solo tus archivos. No toques archivos que no sean tuyos (`git status` limpio al empezar salvo lo que ya sepas).

## Tareas, en este orden

### 1. Evaluador de ventanas (sueño primero)
Hoy el motor evalúa una medición a la vez. Crea `App\Services\Salud\EvaluadorVentanas` dirigido por `config/ventanas.php`, que mira las últimas N lecturas o un periodo de un indicador y puede elevar el estado.
- Regla de partida, de la guía "Datos médicos": **sueño efectivo < 6 h por más de 48 h** (el sueño es un dato diario, así que se traduce a un número mínimo de lecturas diarias consecutivas bajo el umbral; haz ese número configurable y documéntalo como supuesto). Cita la fila de `requisitos` (tipo guía) correspondiente y no la presentes como requisito de la norma: el estándar no publica horas de sueño.
- Resultado: eleva el estado del último dato a **precaución** como mínimo, con la acción etiquetada "preventiva" (el documento la llama así: bloquear tareas críticas, higiene del sueño). **No recomiendes hipnóticos ni fármacos.** No añadas un valor nuevo al enum `Estado` si evitarlo es limpio; si no lo es, dímelo antes.
- Deja en la config, **desactivadas y marcadas "próximamente"**, las entradas de calorías (< 90 % del requerimiento 3 días seguidos) y PVT (lapsos > 500 ms): necesitan indicadores que aún no existen (bloque 3B).
- Llamada desde `ProcesadorMediciones` solo para indicadores con regla de ventana, sin penalizar el rendimiento de la ruta de 56 mediciones por minuto.
- Tests con tabla de casos: 1 noche mala, 2 noches malas, intercalada, datos faltantes, y que la tarjeta del Médico de vuelo muestre la regla y su requisito.

### 2. Datos dudosos: marcar "sospechoso", no rechazar
Fuente: `docs/fuentes/salud_astronautas_y_monitoreo.txt`, sección 3 (radiación, interferencias y baterías pueden producir lecturas erróneas, datos corruptos o falsas alarmas).
- Servicio `CalidadSenal` y columnas en `mediciones` (`calidad` = ok|sospechosa, `calidad_motivo` nullable). Detecta: **salto brusco** entre lecturas consecutivas (umbral por indicador en `config/calidad.php`) y **señal plana** (N lecturas idénticas seguidas en indicadores continuos: pulso, SpO₂, temperatura). Todos los números son supuestos del proyecto.
- La medición **se guarda siempre**; una sospechosa no genera alerta a tierra por sí sola, no entra en la línea base móvil y se ve con la etiqueta "dato sospechoso" (con el motivo) en Indicador, Historial y tarjetas. Si la medición sospechosa además cruza un umbral crítico, el estado crítico se mantiene (ante la duda, no ocultar un riesgo) y se muestra el aviso.
- Debe reconocer el escenario `fallo_sensor` del simulador (`config/simulacion.php`). Compruébalo con un test que genere esos valores.
- Tests: salto, plana, normal, sospechosa que además es crítica, y que no contamina la línea base.

### 3. Cifrado de campos sensibles del expediente
- Modelos: `ExpedienteMedico`, `Alergia`, `MedicamentoTripulante`, `CondicionMedica`, `AntecedenteFamiliar`, `ContactoEmergencia` (revisa sus columnas y cifra las de texto libre y datos personales, no las claves ni las fechas que se usan para consultar).
- Usa un cast propio `EncryptedFlexible` basado en `Crypt` (AES-256 de Laravel) que **lee datos antiguos en texto plano sin fallar** (intenta descifrar y, si no es un valor cifrado, devuelve el texto) y siempre escribe cifrado. Cambia a `text` las columnas que se queden cortas, con migración.
- Comando `expediente:cifrar` **idempotente**, con modo simulación por defecto y `--confirmar` para escribir, que cifra las filas existentes. No lo ejecutes sobre la base real sin avisarme.
- Aviso en el README: si cambia `APP_KEY`, esos datos quedan ilegibles; hacer copia de `APP_KEY` y de la base. Documenta también (sin implementarlo) el cifrado de disco de MySQL y TLS: no es algo que la aplicación pueda hacer sola.
- Tests: el valor guardado en la base no es el texto plano, el modelo lo devuelve en claro, un dato antiguo en claro sigue siendo legible, y el comando es idempotente.

### 4. Auditoría de accesos
- Tabla `auditoria_accesos`: `user_id` nullable, `rol`, `accion` (ver|exportar|enviar|…), `recurso` (p. ej. `ficha`, `expediente`, `medico_vuelo`, `botiquin`, `consultas_tierra`), `recurso_id`, `astronauta_id`, `ip`, `user_agent`, `ocurrido_en`. **No guardes valores médicos**, solo quién accedió a qué.
- Servicio `Auditoria::registrar(...)` con deduplicación (misma persona y recurso en menos de 5 minutos = un registro). Engánchalo al montar las pantallas sensibles (ficha del tripulante, expediente, Médico de vuelo, Botiquín, consultas a tierra) y en el envío de consultas a tierra. Sin sesión (demo con `MISION_EXIGIR_LOGIN` falso), registra el rol como `anonimo`.
- Pantalla `/auditoria` de solo lectura, filtrable y paginada, visible solo para `flight_surgeon` cuando el login está activo. Entrada en el menú de "Estación y tierra".
- Comando `auditoria:podar` con simulación por defecto y `--confirmar`, conservando 90 días (supuesto, configurable). No lo programes.
- Tests: se registra, deduplica, el tripulante no puede abrir `/auditoria`, el flight surgeon sí, y no aparece ningún valor médico en los registros.

### 5. API del reloj más segura
Archivos: `MedicionesController`, comando `reloj:token`, `routes/api.php`, migración sobre `astronautas`.
- **Expiración del token:** columna de vencimiento; `reloj:token {astronauta} {--dias=30}`; un token vencido responde 401 con mensaje claro. El token anterior sigue válido una hora de gracia al regenerar (supuesto, configurable) para no cortar al dispositivo mientras se actualiza.
- **Limitador propio** (`throttle:reloj`, por token, no por IP) con un máximo razonable por minuto (supuesto).
- **Firma del lote:** cabecera `X-Firma: sha256=<hex>` con HMAC-SHA256 del cuerpo y una marca de tiempo firmada (rechazar > 5 min de antigüedad), con un secreto por dispositivo que se muestra **una sola vez** al generar el token y se guarda cifrado. La verificación es **opcional por configuración** (`mision.reloj_exigir_firma`, falsa por defecto) para no romper el APK actual; con ella activa, un lote sin firma o con firma mala da 401.
- Acepta el campo opcional `bateria` (0–100) y guárdalo (`bateria_pct`, `bateria_en`); si es menor que un umbral configurable (supuesto), muéstralo como aviso "batería baja" en la ficha del tripulante. El documento lo recomienda; el APK tendrá que enviarlo después.
- Añade en `docs/PROMPT_APK_RELOJ.md` una nota al final con la firma y el campo `bateria`, sin reescribir el resto.
- El atajo sin token (`RELOJ_SIN_TOKEN_ASTRONAUTA`) se queda, pero cada uso se registra en el log con la IP y se documenta que debe estar vacío fuera de pruebas.
- Tests: token vencido, gracia, límite excedido, firma buena, firma mala, marca de tiempo antigua, firma no exigida, y `bateria`.

### 6. Los biomarcadores del bloque 3B, visibles como "Próximamente"
**No los implementes.** Solo hazlos visibles como hoja de ruta, sin campos ni lógica.
- `config/proximamente.php` con estas entradas (nombre, sistema destino por su `clave` en `config/sistemas.php`, y fuente): VO₂máx y capacidad aeróbica; fuerza muscular; densidad mineral ósea (cadera, columna, cuello femoral); grosor de retina y presión intraocular; vitamina D; calcio urinario y electrolitos; cortisol salival; PVT (lapsos de reacción); ingesta calórica; pCO₂, pO₂ y humedad de cabina; ECG y flujo yugular; telemetría de ARED, T2 y CEVIS; radiación por órgano y proyección de evento solar; prescripción dinámica de ejercicio; posvuelo de 45 días.
- Mostrarlas con la etiqueta **"Próximamente"** (atenuadas, sin enlaces ni campos de entrada) en dos sitios: una sección "Próximamente" en la **Guía**, y una línea "Próximamente: …" dentro de la tarjeta del sistema correspondiente en **Sistemas**, junto a lo que ya dice "sin cobertura todavía".
- Test: cada entrada se muestra con "Próximamente", ninguna es clicable y no existe ningún `input` asociado; todas apuntan a una `clave` de sistema que existe.

### 7. Cierre
- README: sección de seguridad (cifrado, `APP_KEY`, auditoría, firma del reloj, TLS y disco como pendientes del despliegue) y hoja de ruta con el 3B como "Próximamente".
- Suite completa en verde. Si algo falla, diagnostica la causa real.

## Definición de terminado
- Dos noches de sueño malas elevan el estado con su requisito citado; un dato plano o con salto brusco queda "sospechoso" y no ensucia la base.
- En la base de pruebas, los datos del expediente se guardan cifrados y los antiguos en claro siguen leyéndose; el comando de cifrado existe y no se ejecutó en la real.
- `/auditoria` funciona y respeta los roles; la API acepta token con expiración, límite, firma opcional y batería.
- El 3B se ve como "Próximamente" en Guía y Sistemas.
- Resumen final con: supuestos del proyecto que introdujiste, lo que no ejecutaste en la base real, y lo que no pudiste verificar. Commits por tema (ventanas, calidad de señal, cifrado, auditoría, API del reloj, próximamente, README).
