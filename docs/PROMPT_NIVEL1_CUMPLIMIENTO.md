# Prompt Nivel 1: cumplimiento fácil (trazabilidad visible, peligros, botiquín informativo y UX)

Úsalo en UNA sola sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, cuando ninguna otra sesión esté editando el repo ni corriendo tests.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local, Windows, XAMPP). Es un prototipo: el sistema **orienta, no diagnostica ni receta**. Quiero cerrar los puntos fáciles de cumplimiento frente a los documentos de referencia (NASA-STD-3001 Vol. 1 Rev. C, guía interna "Datos médicos" y páginas del HRP). Responde en español, con cambios mínimos y verificados.

## Reglas de trabajo
- **Una sola sesión escribe.** Antes de empezar, comprueba con `git status` y `Get-CimInstance Win32_Process -Filter "Name='php.exe'"` que no hay otro `artisan test` ni archivos a medias de otras sesiones. Si hay archivos sin commit que no son tuyos, no los toques ni los incluyas en tus commits.
- **Tests:** corren contra la base `healts_nasa_test` (ya configurada en `phpunit.xml`). Una sola suite a la vez. No tocar la base real `healts-nasa` salvo migraciones nuevas, y avisarme antes de aplicarlas.
- **Nada clínico se da por validado.** Todo valor nuevo lleva la etiqueta "valor de ejemplo" hasta revisión médica. **No inventes cifras, identificadores V1, dosis ni contraindicaciones**: si un dato no está en este prompt, deja el campo vacío o "pendiente de verificar".
- Reutiliza el sistema de diseño existente (CSS en `resources/css/`, tarjetas, chips, colores de estado). Sin librerías nuevas.
- Cambiar un umbral mueve estados: actualiza tests y seeders a propósito y dime qué cambió.

## Tipos de fuente (la UI debe distinguirlos)
| tipo | etiqueta visible |
|---|---|
| `norma` | "NASA-STD-3001 Vol. 1 Rev. C" |
| `hrp` | "HRP (observación)" |
| `guia` | "Guía interna (Datos médicos)" |
| `referencia` | "Referencia clínica" |

## Tareas, en este orden

### 1. Tabla de requisitos + seeder (base de todo)
Migración `requisitos`: `id`, `codigo` (nullable, p. ej. "V1 4027"), `texto`, `dominio`, `operador`, `valor` (decimal nullable), `valor_max` (nullable), `unidad`, `revision` (nullable), `fuente_url` (nullable), `tipo` (norma|hrp|guia|referencia), `validado` (bool, false por defecto). Modelo `Requisito` y seeder `RequisitosSeeder` (llamado desde `DatabaseSeeder`) con SOLO estos datos:

**norma** · revisión "NASA-STD-3001 Vol. 1 Rev. C" (aprobada 15-sep-2023) · url https://www.nasa.gov/wp-content/uploads/2023/11/nasa-std-3001-vol-1-rev-c-with-signature.pdf
- Radiación, dosis efectiva de carrera: < 600 mSv.
- Radiación, evento de partículas solares: < 250 mSv.
- Radiación GCR: < 1.3 mSv/día en espacio libre; < 0.9 mSv/día en superficie planetaria (sistemas habitables de más de 60 días).
- Radiación, tecnologías nucleares: < 20 mSv por año de misión.
- Radiación por órgano (30 días / 1 año / carrera): cristalino 1000/2000/4000 mGy-Eq; piel 1500/3000/6000 mGy-Eq; órganos formadores de sangre 250/500/no aplica mGy-Eq; sistema circulatorio 250/500/1000 mGy-Eq; sistema nervioso central 500/1000/1500 mGy. Partículas Z ≥ 10: 100 mGy/año y 250 mGy de carrera.
- Hueso: DMO de cadera y columna ≥ 95 % del valor pre-misión (**codigo "V1 4027"**); cuello femoral ≥ 90 %.
- Aeróbico: VO₂máx ≥ 32.9 ml/min/kg (EVA en microgravedad); ≥ 36.5 (EVA en superficie); capacidad aeróbica ≥ 80 % de la pre-misión.
- Fuerza: ≥ 80 % de los valores basales. Pre-misión, múltiplos del peso corporal: peso muerto 1.0 / 1.3 / 1.6 y press de banca 0.7 / 0.8 / 1.0 (EVA microgravedad / EVA superficie / egreso sin asistencia).
- Nutrición: 100 % de los requerimientos calculados.
- Sueño, carga de trabajo, conducta y sensoriomotor: requisito **cualitativo** de que existan límites definidos (sin número; el estándar no publica horas de sueño).
- Operativos: mínimo 2 CMO por vehículo; vitamina D y DXA cada 3 años en pre-misión.
(El `codigo` queda vacío en todo lo demás. Solo "V1 4027" está confirmado; "V1 4030" aparece en el documento como ejemplo sin dominio asignado: no lo uses.)

**hrp** (observación, sirve solo para contexto y proyección, nunca para alertar): pérdida ósea 1–1.5 %/mes; pérdida aeróbica media 10–12 % (12–38 % de la tripulación pierde > 20 %); sueño real 6–6.5 h y 8.5 h asignadas; más de 200 alimentos a bordo.

**guia** (documento "Datos médicos"): VO₂máx < 80 % de la base o < 32.9; fuerza < 80 %; calorías < 90 % del requerimiento 3 días seguidos; hidratación < 2.0 L/día; SANS: +20 µm de grosor retiniano o +0.50 D hipermetrópico; PVT con lapsos > 500 ms o sueño < 6 h por más de 48 h (severidad "preventiva"); carga ARED hasta 272 kg; ejercicio 2.5 h/día, 6–7 días/semana; T2 y CEVIS 30–45 min con intervalos HIIT; posvuelo de 45 días.

**referencia**: presión arterial (Normal < 120/80; Elevada 120–129 y < 80; Hipertensión 1: 130–139 u 80–89; Hipertensión 2: ≥ 140 o ≥ 90; Crisis > 180/120); pulso de los Apolo 66–144 (media ~96 en el descenso, 70–109 en el ascenso); temperatura central en órbita ~37.0 → ~38.0 °C tras ~2.5 meses, > 40 °C posible en ejercicio, y la alerta es un **salto sobre su propia base**; sueño recomendado 7–9 h.

Test: el seeder crea los requisitos de cada tipo; solo existe un `codigo` ("V1 4027") y todos tienen `validado = false`.

### 2. Etiquetas de fuente y "valor de ejemplo" en la interfaz
- Componente Blade reutilizable `<x-fuente-chip>` que muestra la etiqueta del tipo, la revisión si la hay y "valor de ejemplo" mientras `validado` sea false.
- Úsalo en la Guía y en las tarjetas de orientación del Médico de vuelo (campo de fuentes existente), sin cambiar su lógica. Si una tarjeta no se puede vincular todavía a un requisito (el vínculo fino es del Nivel 2), muestra "Umbral de ejemplo (sin requisito asociado)".
- Test: la Guía y el Médico de vuelo renderizan la etiqueta "valor de ejemplo".

### 3. Los 5 peligros y los 17 riesgos HRP visibles
Config `config/peligros.php` con:
- **5 peligros:** radiación espacial; aislamiento y confinamiento; distancia de la Tierra; campos gravitatorios; ambientes cerrados u hostiles. Nota visible: "No actúan de forma aislada: pueden combinarse".
- **17 riesgos HRP**, agrupados así: fisiología (11): respuestas inmunitarias alteradas; función sensoriomotora y vestibular alterada; adaptaciones cardiovasculares; enfermedad por descompresión; interacciones huésped-microorganismo; hipoxia; alimentación y nutrición inadecuadas; lesión y desempeño comprometido en EVA; menor aptitud cardiorrespiratoria y musculoesquelética; síndrome neuro-ocular (SANS); cambios óseos. Conducta (5): cambios conductuales y trastornos psiquiátricos; trabajo en equipo inadecuado; sueño inadecuado y horarios irregulares; soporte de operaciones de sistemas inadecuado; lesión por cargas dinámicas. Radiación (1): cánceres inducidos por radiación.
- Para cada riesgo, un estado de cobertura del sistema: `cubierto`, `parcial` o `sin cobertura`, con una frase. Punto de partida (corrígelo mirando el código): sin cobertura → sensoriomotor/vestibular, descompresión, microbioma, trabajo en equipo, soporte de sistemas, cargas dinámicas; parcial → inmunidad, hipoxia, nutrición, EVA, fitness, conducta, cáncer; cubierto → cardiovascular, hueso, SANS, sueño (verifícalo).
- Mostrar en **Guía** y en **Misión** una sección "Peligros y riesgos" con los 5 peligros, la tabla de cobertura y, si es fácil, la matriz peligro × indicador usando el campo `riesgos` que ya existe en la config de cada indicador.
- Test: aparecen 5 peligros y 17 riesgos.

### 4. Correcciones de valores sueltos (solo estas)
- Hidratación: el límite inferior de precaución pasa de 2.05 a **2.0 L/día**.
- SANS (`vision_sans`): quitar el −0.15 negativo de precaución; no añadas números nuevos, solo elimina el límite negativo y dime cómo quedó.
- Rutinas: la meta de ejercicio por defecto pasa de 90 min a **150 min/día (2.5 h), 6 a 7 días por semana**. Localiza dónde está definida.
- Actualiza tests y seeders afectados y lista los estados que cambiaron.

### 5. Botiquín informativo (sin dosis)
Tabla `botiquin_items` (nombre, grupo, stock, unidad, caducidad, contraindicaciones, nota) y página `/botiquin` con entrada en "Estación y tierra". Grupos y nombres (solo los de la guía): mareo por adaptación (prometazina, meclizina, ondansetrón); sueño y fatiga (melatonina, zolpidem, zaleplon, modafinilo, cafeína); analgesia e inflamación (ibuprofeno, acetaminofén, aspirina, ketorolaco); antimicrobianos y antivirales (amoxicilina/clavulanato, azitromicina, ciprofloxacino, valaciclovir); emergencias (epinefrina, oxígeno al 100 %, enoxaparina).
- **Sin dosis, sin indicaciones de uso y sin sugerencias automáticas.** `contraindicaciones` = "Pendiente de verificación médica" en todos. Stock y caducidad son campos editables con valores de ejemplo claramente marcados.
- Aviso visible: "Inventario informativo. No es una prescripción; consulta al médico de vuelo".
- Test: la página lista los grupos, y el HTML no contiene unidades de dosis (`mg`, `ml/kg`, `UI`) en el contenido del inventario.

### 6. Modo oscuro por defecto y accesibilidad táctil
- Tokens de color oscuros por defecto, `prefers-color-scheme`, alternancia persistida en `localStorage` y un modo de alto contraste. Aplica a todo el layout y a las pantallas existentes sin romper los colores de estado.
- Objetivos táctiles de al menos 44 px en los controles pequeños (por ejemplo `.al-filtro`, `.al-vista`, `.ind-chip`, `.ind-enlace`) y textos de 12–13 px subidos a un tamaño legible.
- Escala **VAS** (0–100) para el estado de ánimo: haz que `animo` sea registrable en Registrar y aparezca en la Guía; si no es viable sin rehacer la pantalla, retíralo de la config y dímelo.

### 7. Páginas pendientes y ajustes
- **Aviso legal** (texto: prototipo educativo, valores de ejemplo, no sustituye a un profesional médico) y **Cola a Tierra** (lista de `alertas_salientes` con su estado pendiente/enviada; si ya existe algo equivalente en Alertas o Consultas a tierra, enlázalo en vez de duplicarlo). Entradas en la barra lateral.
- `.env.example` con `APP_DEBUG=false` y una nota en el README de que debe ser false fuera de local. No cambies mi `.env`.
- Actualiza el README: cómo arrancar (servidor, Reverb, `salud:simulador-vivo`, `schedule:work`), las fuentes citadas (NASA-STD-3001 Vol. 1 Rev. C, NASA HRP, CSA) y el aviso de valores de ejemplo.

## Definición de terminado
- Suite completa sin fallos nuevos (dime cuáles fallaban antes y por qué).
- Cada alerta o tarjeta muestra el tipo de fuente y "valor de ejemplo".
- La Guía y la Misión muestran 5 peligros y 17 riesgos; existe `/botiquin` sin dosis.
- Commits pequeños por tema (tabla de requisitos, etiquetas, peligros, correcciones de valores, botiquín, UX, páginas y README), solo con los archivos que tocaste y con mensajes claros en español. Al final, resumen de lo hecho, lo pendiente y lo que no pudiste verificar.
