# Prompt Parte 1: cerrar el prototipo (entrega hoy 11:59 pm)

Copia todo lo de abajo en una sesión de Claude Code abierta en `C:\laragon\www\Healts-Nasa`. Es la única sesión que debe tocar el repo y correr tests mientras dure.

---

Proyecto: monitor de salud para astronautas (Laravel 10 + Livewire + MySQL local `healts-nasa`, Windows, PHP de XAMPP, servidor en `artisan serve --host=0.0.0.0 --port=8000`). Hoy a las 11:59 pm hay que entregar un **prototipo funcional**. Esta es la Parte 1: dejarlo estable, con retención de datos y commiteado. La IA/n8n queda fuera de esta parte.

Reglas de trabajo:
- Responde en español. Cambios mínimos y verificados; nada de refactors no pedidos.
- Antes de borrar o sobrescribir algo, míralo. **No borres datos de la base `healts-nasa` sin que yo lo confirme en el chat**, ni ejecutes la poda real por tu cuenta.
- Corre **una sola** suite de tests a la vez. Antes de lanzarla, comprueba que no haya otro `artisan test` activo (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"`). Si hay otro que no lanzaste tú, avísame en vez de matarlo.
- Los tests actuales usan `DatabaseTransactions` sobre la base real, lo que provoca deadlocks y los vuelve lentos.

## Tareas, en este orden

### 1. Base de pruebas separada
- Crea la base MySQL `healts_nasa_test` y haz que PHPUnit la use (variables `DB_DATABASE` en `phpunit.xml` o `.env.testing`), sin tocar `.env`.
- Verifica con un test que `DB_DATABASE` en testing es la de pruebas y no `healts-nasa`.
- Cuando esté aislada, `RefreshDatabase` es seguro en los tests; cámbialo solo si los tests siguen siendo lentos o inestables.

### 2. Migración y endpoint del reloj
- Aplica `php artisan migrate` en la base real (solo añade `astronautas.token_dispositivo`).
- Ya existen: `POST /api/mediciones` (`app/Http/Controllers/Api/MedicionesController.php`), el comando `php artisan reloj:token {astronauta}` y `tests/Feature/ApiMedicionesTest.php`. Haz que pasen.
- El controlador tiene un atajo de pruebas sin token (`RELOJ_SIN_TOKEN_ASTRONAUTA`, solo en `local`). Déjalo, pero documenta en el README que debe estar vacío fuera de pruebas, porque el servidor escucha en toda la red local.

### 3. Retención: solo los últimos 15 días de mediciones
- Añade `config('mision.dias_historial')` con valor 15, tomado de `MISION_DIAS_HISTORIAL`.
- **Primero, lectura:** que el Panel, Indicador, Historial y cualquier gráfica solo usen mediciones de los últimos 15 días (un solo punto de entrada, p. ej. un scope `Medicion::recientes()`).
- **Después, poda:** comando `php artisan mediciones:podar {--dias=} {--confirmar}`. Por defecto es simulación: muestra cuántas filas de `mediciones` borraría y no borra nada. Solo borra con `--confirmar`.
- Antes de escribirlo, revisa las claves foráneas: `evaluaciones` y `alertas_salientes` dependen de `mediciones`. Decide con evidencia si hay cascada, y no dejes huérfanos ni borres alertas que aún no se enviaron a tierra.
- Cuidado: indicadores acumulados (`dosis_acumulada`, `perdida_osea`, `masa_muscular`) y el `HistorialExpedicionSeeder` dependen de historia larga. Dime cómo los trataste (excluirlos de la poda o guardar su acumulado) antes de ejecutar nada.
- No la programes en el scheduler hasta que yo lo diga.
- Tests de la poda sobre la base de pruebas.

### 4. Verificación completa
- Corre la suite completa una vez. Todo debe pasar; si algo falla, diagnostica la causa real, no repitas hasta que pase.
- Recorre en el navegador (`preview_start` si hay `.claude/launch.json`) las pantallas `/`, `/tripulacion`, `/indicador`, `/registrar`, `/rutinas`, `/alertas`, `/guia`, `/mision`, `/historial`, `/sistemas` y confirma que cargan sin errores de consola.

### 5. Commits
- Hay archivos sin commitear de varias partes (Guía, Indicador, ContextoMisión, Alertas, Historial, Sistemas, API del reloj, docs). Revisa `git status` y `git diff`. Si algún archivo parece a medias, no lo incluyas y dime cuál.
- Haz commits pequeños por tema, con mensajes claros en español. No uses `--no-verify`.

## Definición de terminado
- Suite en verde contra la base de pruebas, sin deadlocks.
- `/api/mediciones` probado con curl y un token real generado con `reloj:token`.
- La UI solo muestra 15 días; la poda existe y está probada en simulación, sin haber borrado la base real.
- Todo commiteado y un resumen final de lo hecho, lo pendiente y lo que no pudiste verificar.
