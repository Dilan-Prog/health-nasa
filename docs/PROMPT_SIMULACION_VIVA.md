# Prompt: simulación viva y realista de mediciones + panel para activarla

Úsalo en UNA sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`, cuando ninguna otra sesión esté tocando el repo ni corriendo tests.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local `healts-nasa`, Windows, XAMPP, servidor en `artisan serve --host=0.0.0.0 --port=8000`, Reverb para tiempo real). Es un prototipo; el motor de reglas ya existe y funciona. Quiero **simular mediciones en vivo de forma realista** y un **panel para activar y controlar esa simulación**. Responde en español, con cambios mínimos y verificados.

## Lo que ya existe (reutilízalo, no lo rehagas)
- `App\Services\Salud\ProcesadorMediciones::registrar($astronauta, $clave, ['valor'=>x], 'simulado', $instante, $emitir)`: valida, evalúa, guarda, encola alerta a tierra y emite `MedicionRegistrada`. Lanza `ValidationException` en duplicados o valores imposibles.
- `config/umbrales.php` (indicadores, rangos válidos/normales/precaución) y `config/indicadores.php`.
- `SimuladorMediciones` (ruido simple, sin tiempo) y `php artisan salud:simular-mision` (genera historial; **no lo modifiques**, lo usan los seeders y tests).
- `POST /api/mediciones` con payload `{indicador, valor, medido_en}` para el reloj real.
- Modelos `Astronauta`, `Mision`, `Medicion`; columna `mediciones.origen`.

## Parte A: motor de simulación realista
Crea `App\Services\Salud\SimuladorVivo` (separado del `SimuladorMediciones` actual). Cada tripulante tiene **línea base propia** (reproducible con semilla) y el motor modela, con un reloj virtual:
1. **Ritmo circadiano:** pulso más bajo de noche, temperatura con ciclo diario (~±0.4 °C), sueño que se genera una vez al día.
2. **Ruido de sensor** correlacionado en el tiempo (no ruido blanco puro: usa un proceso autorregresivo simple) y una pequeña pérdida de cuantización.
3. **Datos faltantes:** probabilidad configurable de lectura perdida, y huecos más largos ocasionales.
4. **Ejercicio:** sesiones programadas que suben pulso y bajan algo la SpO₂ con recuperación gradual.
5. **Escenarios con etiqueta**, definidos como **datos** en `config/simulacion.php` (no dentro del código): deshidratación, fiebre, falta de sueño, arritmia, estrés, hipoxia/CO₂ alto (se expresa con SpO₂ baja, pulso alto y estrés cognitivo, porque CO₂ no es un indicador), y fallo de sensor (valores congelados o imposibles). Cada escenario define, por indicador, un desplazamiento y una curva de entrada, meseta y salida, más duración.
6. El motor produce mediciones con la **misma forma que el reloj** (`indicador`, `valor`, `medido_en`) mediante una pequeña clase de datos compartida, para poder cambiar de simulado a real solo cambiando la fuente.
7. **Reloj virtual:** factor de velocidad (1x, 60x, 360x) usado solo para el modelo fisiológico. El `medido_en` que se guarda es siempre la hora real (nunca futura), para no romper la validación ni la ventana de 15 días.

Frecuencias sugeridas (por hora virtual): pulso y SpO₂ cada pocos minutos; temperatura y presión cada hora; peso, glucosa, hidratación y sueño una vez al día a horas fijas. Respeta `frecuencia` de cada indicador en la config.

**Etiqueta de verdad:** cuando un escenario empieza y termina, regístralo en una tabla `simulacion_eventos` (astronauta, escenario, inicio, fin). Servirá después para evaluar el médico de vuelo con casos de respuesta conocida.

## Parte B: ejecución
- Comando de larga vida `php artisan salud:simulador-vivo` que cada N segundos lee el estado desde la base, genera las mediciones de los tripulantes activos y las registra con origen `simulado` vía `ProcesadorMediciones`. Debe tolerar `ValidationException` (omitir) y apagarse limpio con Ctrl+C.
- Estado en base de datos, no en memoria: tabla de estado por tripulante (activa, fuente, escenario actual, semilla, estado del ruido, último tick) y una fila de motor (velocidad, intervalo, latido). Migración nueva con nombre de fecha posterior a las existentes.
- Un tripulante con **fuente = reloj** (tiene token de dispositivo y se marcó así) nunca se simula.
- Evita inundar de alertas a tierra: no encolar más de una alerta idéntica por tripulante e indicador en una ventana corta (revisa `EnvioATierra` antes de decidir cómo).

## Parte C: panel "Simulación"
Componente Livewire `App\Livewire\Simulacion`, ruta `/simulacion`, entrada en la barra lateral (sección "Estación y tierra"). Solo disponible fuera de producción, como la demo de la silueta. Mantén el sistema de diseño existente (CSS en `resources/css/`, mismas tarjetas, chips y colores de estado).
- **Interruptor general** "Simulación activa" y selector de velocidad (1x / 60x / 360x) e intervalo.
- **Estado del motor:** latido ("último tick hace X s"). Si el comando no está corriendo, aviso claro con el comando a ejecutar y botón de copiar. No intentes lanzar procesos desde la web.
- **Lista de tripulantes** con: interruptor individual, fuente (simulado/reloj), escenario activo con tiempo restante, última medición y estado. Selector para **lanzar un escenario** ahora y botón para **cancelarlo**.
- **Vista en vivo:** últimas mediciones recibidas y alertas generadas (actualización con Reverb si el Panel ya lo usa; si no, `wire:poll`). Comprueba cómo se suscribe hoy el Panel a `MedicionRegistrada` y haz que se refresque con la simulación.
- Estados: motor parado, sin tripulantes, escenario en curso, error.
- **No incluyas** botón de "borrar datos simulados": casi todo el historial del proyecto tiene origen `simulado` y se perdería.

## Reglas de trabajo
- Prioridad si falta tiempo (entrega hoy 11:59 pm): primero B y el panel con interruptores, escenarios y latido (MVP); después circadiano y ruido correlacionado; al final ejercicio, datos faltantes y reloj virtual.
- No modifiques `SimularMision`, `SimuladorMediciones`, `ProcesadorMediciones` ni los umbrales, salvo lo mínimo para la limitación de alertas, y dime si lo haces.
- Tests: determinismo con semilla, curva de un escenario (entra, meseta, sale), datos faltantes, que un tripulante con fuente reloj no se simula, un tick del comando, y el panel (interruptores y lanzar/cancelar escenario). Corre **una sola** suite a la vez y comprueba antes que no haya otro `artisan test`. Si existe una base de pruebas separada, úsala; si no, no cambies la configuración de tests por tu cuenta.
- No borres datos de la base `healts-nasa` ni apliques migraciones sin avisarme. No hagas commit hasta que yo lo pida; al terminar, resume lo hecho, lo pendiente y lo que no pudiste verificar.

## Definición de terminado
- Con el comando corriendo y el interruptor activo, los valores del Panel e Indicador cambian en vivo con aspecto fisiológico plausible.
- Lanzar el escenario "fiebre" o "hipoxia" lleva el estado del tripulante a precaución o crítico y genera la alerta a tierra una sola vez.
- Al apagar el interruptor o parar el comando, dejan de llegar mediciones.
- Tests nuevos en verde y sin tocar los existentes.
