# Prompt Nivel 2: reglas con trazabilidad, línea base pre-vuelo, perfil de misión y login con roles

**Requisito previo:** el Nivel 1 ya está terminado y commiteado (tabla `requisitos`, `RequisitosSeeder`, componente `<x-fuente-chip>`, `config/peligros.php`). La sesión debe comprobarlo al empezar y, si falta algo, detenerse y avisarme.
Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, sin otra sesión escribiendo ni corriendo tests.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP). El sistema **orienta, no diagnostica ni receta**. Quiero llevar las reglas y la seguridad a un nivel defendible frente a NASA-STD-3001 Vol. 1 Rev. C y a la guía interna "Datos médicos". Responde en español, con cambios mínimos y verificados.

## Reglas de trabajo
- **Una sola sesión escribe.** Antes de empezar revisa `git status` y los procesos `php.exe`. No toques ni commitees archivos de otras sesiones.
- **Tests** contra `healts_nasa_test` (ya en `phpunit.xml`). Una sola suite a la vez. La base real `healts-nasa` recibe la simulación en vivo: **avísame antes de aplicar migraciones en ella** y no borres datos.
- **Nada clínico está validado.** Todo número nuevo es "valor de ejemplo". **No inventes cifras ni identificadores V1.** Cuando un número sea un supuesto del proyecto y no del documento (márgenes, deltas), ponlo en la config con el comentario `supuesto del proyecto, no de la norma` y dime cuáles son.
- Cambiar umbrales mueve estados: actualiza tests y seeders a propósito y lista qué estados cambiaron.
- Reutiliza el sistema de diseño existente. Sin librerías nuevas.

## Tareas, en este orden

### 1. Trazabilidad: cada evaluación cita su requisito
- Migración: `evaluaciones.requisito_id` (nullable, FK a `requisitos`). En la carga de `alertas_salientes` guarda también `requisito` (código o texto), `revision` y `tipo_fuente`.
- Config `config/trazabilidad.php`: mapa de indicador y condición → requisito (por `dominio`/`texto` del seeder del Nivel 1). Asignación de partida, verifícala:
  - `radiacion_diaria` → GCR (< 1.3 espacio libre / < 0.9 superficie), tipo norma.
  - `dosis_acumulada` → carrera < 600 mSv, tipo norma.
  - `perdida_osea` → es una **tasa del HRP (observación)**: debe citarse como "Proyección (HRP)", nunca como requisito. Añade en la tarjeta la referencia a V1 4027 (DMO ≥ 95 % / 90 %) como el requisito real.
  - `presion_arterial`, `temperatura`, `frecuencia_cardiaca` → tipo referencia.
  - `hidratacion`, `vision_sans`, `sueno` → tipo guía.
- `ProcesadorMediciones` fija `requisito_id` al crear la evaluación. Las tarjetas y la Guía usan `<x-fuente-chip>` mostrando código (si existe), revisión, tipo y "valor de ejemplo".
- Test: toda evaluación nueva de un indicador mapeado tiene `requisito_id`, y si genera alerta a tierra la carga incluye requisito y revisión.

### 2. Umbrales corregidos
Archivos: `config/umbrales.php`, `EvaluadorEstado`, `ReglasEscalamiento`, `CatalogoRecomendaciones`. Para cada regla, tabla de casos en tests (ver tarea 7).

- **Radiación diaria por entorno.** Límite GCR: **1.3 mSv/día en espacio libre** (`estacion`, `nave`) y **0.9 mSv/día en superficie** (`base_lunar`, `base_marciana`). Crítico cuando el valor llega al límite; precaución a partir de un margen configurable (`margen_precaucion`, supuesto del proyecto, por defecto 0.8 del límite). La asignación entorno → límite también es un supuesto: documéntala.
- **Dosis acumulada.** Evaluar contra **600 mSv de carrera** con el mismo margen. Una **proyección de evento solar > 250 mSv** es crítica, con acción "alarma y refugio blindado" (si no existe entrada de evento solar, prepara solo la regla y el texto, y dime que falta la entrada: es del Nivel 3). Los límites por órgano y por año requieren dosis por órgano que el sistema no tiene: no los inventes, déjalos como pendiente.
- **Presión arterial por bandas.** Normal < 120/80; Elevada 120–129 y < 80; Hipertensión 1: 130–139 u 80–89; Hipertensión 2: ≥ 140 o ≥ 90; Crisis > 180/120. Mapeo a estados del sistema (supuesto, documéntalo): Normal → normal; Elevada y HTA 1 → precaución; HTA 2 → crítico; Crisis → crítico con acción de contactar a tierra. Muestra el nombre de la banda en la tarjeta.
- **Temperatura por salto sobre la base personal.** Referencia: tras ~2.5 meses en órbita la temperatura central se estabiliza cerca de 38 °C, y el criterio médico es un **aumento brusco sobre la propia media**, no el 38.0 fijo. Implementa alerta por delta sobre la línea base personal (`salto_precaucion_c` y `salto_critico_c` en config, supuestos del proyecto) y conserva los límites absolutos solo como rango de validez. Si hay una sesión de ejercicio ejecutada en la última hora (`RutinaEjecucion`), no alertar por temperatura alta (> 40 °C es esperable en ejercicio); si no puedes enlazarlo limpiamente, dímelo en vez de forzarlo.
- **Pulso.** No cambies sus umbrales. Añade solo el rango de referencia de los Apolo (66–144 lpm, media ~96 en el descenso, 70–109 en el ascenso) como contexto en la Guía, con tipo referencia.
- Cada regla nueva se cita con su requisito (tarea 1).

### 3. Línea base pre-vuelo
- Tabla `linea_base`: `astronauta_id`, `metrica`, `valor`, `unidad`, `ventana_desde`, `ventana_hasta` (por defecto L-180 a L-30 días respecto al inicio de la misión), `fuente`. Métricas: `vo2max`, `dmo_cadera`, `dmo_columna`, `dmo_cuello_femoral`, `grosor_retina_um`, `kcal_objetivo`, `temperatura_basal`, `fuerza`.
- Captura desde la **ficha del tripulante** (sección nueva, mínima, sin rehacer la pantalla) y lectura desde el expediente si ya trae VO₂máx o temperatura basal.
- Servicio `DesviacionBase`: devuelve la desviación **en %** de un valor actual frente a su base pre-vuelo. Se mantiene `LineaBasePersonal` (base móvil) como complemento de tendencia, no se sustituye.
- Mostrar la desviación % en la ficha y en el Indicador cuando existan base y valor actual. Las reglas de "80 % de la base", "95 %" y "+20 µm" quedan **preparadas pero sin entrada de datos** (los biomarcadores son del Nivel 3): dímelo explícitamente.
- Test: cálculo de la desviación, y la temperatura usa la base pre-vuelo si existe.

### 4. Perfil de misión (`care_level` y `mission_phase`)
- Migración en `misiones`: `care_level` (3, 4 o 5) y `mission_phase` (`prevuelo`, `vuelo`, `posvuelo`, por defecto `vuelo`). Valor inicial derivado del `entorno`: `estacion` → 3, `nave` y `base_lunar` → 4, `base_marciana` → 5 (supuesto, documéntalo). Selector en la pantalla Misión.
- Comportamiento: nivel 3 = telemedicina síncrona (se ofrece videoconsulta); niveles 4 y 5 = **triaje autónomo local**, sin videoconsulta, con orientación local inmediata y mensaje asíncrono. El Médico de vuelo debe leer `care_level` en vez de inferirlo solo del retardo. No rehagas `ReglasEscalamiento`: ajústalo mínimamente y cubre ambos niveles con tests.
- Opcional si queda tiempo: endpoint de solo lectura `GET /api/tripulantes/{id}/estado` (protegido por la tarea 5) que devuelva el esquema del documento: `astronaut_id`, `mission_phase`, `care_level`, `baseline_metrics`, `current_telemetry`, `automated_alerts` (con severidad AMBER o RED).

### 5. Login y roles
- Comprueba si Breeze o una alternativa ya está instalada. Si no, implementa login simple con Auth de Laravel y una pantalla Livewire, sin paquetes nuevos.
- Rol en `users.rol`: `tripulante` (ve solo lo suyo, enlazado por `astronautas.user_id`), `cmo` (ve a toda la tripulación de su misión) y `flight_surgeon` (ve todo, y es quien atiende consultas y cola a tierra). Un único punto central, por ejemplo `Astronauta::visiblesPara($user)`, y haz que `TripulanteActual::obtener()` y el selector de tripulante lo respeten, en vez de editar cada componente.
- Middleware `auth` en todas las rutas web salvo login y Aviso legal. La API del reloj sigue con su token de dispositivo.
- Bandera de entorno `MISION_EXIGIR_LOGIN` (verdadera en `.env.example`) y **falsa en `phpunit.xml`** para que los tests existentes no se rompan; los tests nuevos de seguridad la activan explícitamente.
- Seeder con tres usuarios de demostración (uno por rol). **No escribas las contraseñas en el chat ni en los commits**: léelas de variables de entorno con valores de ejemplo en `.env.example`.
- Tests: sin login redirige; el tripulante no ve a otro; el CMO ve su tripulación; el flight surgeon ve todo; la API del reloj sigue funcionando.

### 6. Ficha ASCAN
Tabla `examenes_ascan` (`astronauta_id`, `prueba`, `resultado`, `fecha`, `estado` = pendiente|normal|anormal) y una sección en la ficha del tripulante. **Sin interpretar ni calcular nada.** Pruebas del primer examen anual: grupo ABO y Rh; IgG de citomegalovirus, Epstein-Barr (antígeno nuclear y de cápside viral), herpes simple 1/2, toxoplasma y varicela-zóster; anticuerpos de sarampión, paperas y rubéola; hepatitis A; Immunocap de epitelio y orina de ratón (IgE); lipoproteína (a); test de aliento de H. pylori; G6PD; electroforesis de proteínas séricas; inmunoglobulinas cuantitativas (IgG, IgA, IgM); riesgo de cálculos urinarios; y panel de tromboembolismo venoso (cardiolipina IgG, β2-glicoproteína 1 IgM/IgG, resistencia a proteína C activada, mutación de protrombina 20210 G/A, proteína C, proteína S, antitrombina, anticuerpos antifosfolípidos, factor V Leiden).

### 7. Una prueba por regla
- Tests con *data providers* por regla: valor, entorno o fase, estado esperado y requisito citado. Cubre radiación por entorno, dosis acumulada, las cinco bandas de presión, temperatura por salto con y sin base pre-vuelo, hidratación 2.0 y `care_level` 3 frente a 4–5.
- Un test por "requisito citado": toda regla con umbral debe tener `requisito_id`.

## Definición de terminado
- Suite completa en verde (o fallos explicados), y lista de estados que cambiaron al mover umbrales.
- Toda alerta nueva muestra requisito, revisión y tipo de fuente; las de HRP se rotulan como proyección.
- Login con tres roles funcionando y tests de seguridad pasando.
- Resumen final con: supuestos del proyecto que introdujiste, lo que quedó solo preparado (a la espera de biomarcadores del Nivel 3) y lo que no pudiste verificar.
- Commits pequeños por tema (trazabilidad, umbrales, línea base, perfil de misión, login y roles, ASCAN, pruebas), solo con tus archivos.
