# Primer commit: qué contiene y por qué

Este documento describe todo lo que entra en el commit inicial del proyecto, las decisiones detrás de cada pieza y lo que queda pendiente.

## 1. Contexto

Reto: crear una herramienta visual con la que el astronauta evalúe su propia salud en misiones espaciales y reciba orientación. Los requisitos y las decisiones de arquitectura están en `docs/Requisitos_Monitor_Salud_Astronautas.pdf`.

Decisión principal (opción A del PDF): **Laravel 10 corre en un servidor a bordo** y los astronautas entran por la red local. Todo funciona sin internet. Tierra es un destino opcional con cola de salida.

## 2. Base del proyecto

- Esqueleto de **Laravel 10** (PHP 8.2) con Vite.
- Paquetes añadidos: `livewire/livewire` 3, `laravel/reverb`; en npm, `tailwindcss` 3, `postcss`, `autoprefixer`, `laravel-echo`, `pusher-js` y `chart.js` (este último aún sin usar).
- `.env` no se versiona. `.env.example` incluye variables de Reverb con claves locales de ejemplo, no secretas.

## 3. Motor de reglas (`app/Services/Salud/`)

Lógica pura, sin depender de Eloquent, cubierta por tests unitarios.

| Clase | Responsabilidad |
|---|---|
| `Estado` | Enum: normal, precaución, crítico; permite quedarse con el más grave |
| `ValidadorMedicion` | Detecta valores imposibles, faltantes, no numéricos, diastólica ≥ sistólica y duplicados (misma medición en menos de 60 s) |
| `EvaluadorEstado` | Medición a estado; en valores compuestos (presión) manda el peor componente. También calcula tendencia (subiendo, bajando, estable) |
| `LineaBasePersonal` | Compara contra el historial del propio astronauta (z-score, mínimo 5 mediciones). Solo puede agravar un normal, nunca degradar |
| `ReglasEscalamiento` | Decide: ninguno, recomendar, alertar a la tripulación o contactar tierra. Con retardo de comunicación alto, un crítico se atiende primero a bordo |
| `CatalogoRecomendaciones` | Acción concreta por indicador y estado (textos de ejemplo, pendientes de revisión médica) |
| `IndiceSistemaCorporal` | Agrega por sistema (cardiovascular, óseo, inmune, mental, metabólico) con puntaje 0-100; un crítico nunca se oculta por el promedio |
| `SimuladorMediciones` | Genera mediciones plausibles y deterministas con semilla; una deriva empuja hacia precaución o crítico |
| `ProcesadorMediciones` | Orquesta todo: valida, evalúa, guarda, encola la alerta a tierra y emite el evento |
| `EnvioATierra` | Vacía la cola a tierra solo si hay enlace; si el envío falla, la alerta sigue pendiente |

**Umbrales** en `config/umbrales.php`, para seis indicadores: presión arterial, frecuencia cardíaca, glucosa, temperatura, horas de sueño y estado de ánimo. Cada uno tiene rango válido, normal y de precaución, sistema corporal y riesgos asociados. También define el retardo de comunicación por entorno (estación, nave, base lunar, base marciana).

> Los umbrales son **valores de referencia clínica general, sin validar** contra NASA HRP ni NASA-STD-3001.

## 4. Datos (`database/`)

Migración `2026_11_14_000001_create_salud_tables.php`:

| Tabla | Contenido |
|---|---|
| `astronautas` | Nombre, misión, entorno; relación opcional con `users` |
| `mediciones` | Astronauta, indicador, valores (JSON), fecha, origen (manual o simulado) |
| `evaluaciones` | Estado, tendencia, acción y recomendación de cada medición |
| `alertas_salientes` | Cola hacia tierra con estado pendiente o enviada |

Modelos: `Astronauta`, `Medicion`, `Evaluacion`, `AlertaSaliente`. Seeder `AstronautasSeeder` con tres astronautas de ejemplo (estación, nave y base marciana).

## 5. Interfaz y tiempo real

- **Panel principal** (`app/Livewire/Panel.php` y `resources/views/livewire/panel.blade.php`): selector de astronauta, formulario de medición, semáforo por indicador con tendencia y recomendación, gráfica de tendencia en SVG, figura humana coloreada por sistema, simulador y controles del enlace con tierra.
- **Layout** con colores NASA (azul `#0B3D91`, azul oscuro `#0A1F44`, rojo `#FC3D21`) y aviso de que la herramienta orienta, no diagnostica.
- **Reverb**: el evento `MedicionRegistrada` se emite al canal público `tripulacion`. Los demás dispositivos refrescan el panel solos. `ShouldBroadcastNow` evita necesitar un worker de colas. Echo usa el host desde el que se abrió la página, para que funcione con la IP de la red local.
- **Sin servicios externos**: CSS, JS e íconos van empaquetados con Vite. Sin CDNs.

## 6. Enlace con tierra

- `config/tierra.php`: `TIERRA_ENLACE` (estado inicial) y `TIERRA_URL` (vacío = modo simulado).
- Comando `php artisan tierra:enviar`, programado cada minuto en `app/Console/Kernel.php` (requiere `schedule:work`).
- El panel puede simular la pérdida y el regreso del enlace; al regresar, la cola se vacía.

## 7. PWA

- `public/manifest.webmanifest`, tres íconos en `public/icons/` (generados con un script propio), `public/sw.js` y `public/offline.html`.
- El service worker cachea la interfaz compilada y los íconos, muestra la pantalla de aviso si el servidor no responde y **nunca** cachea Livewire ni Reverb.
- Se descartó `vite-plugin-pwa`: un service worker a mano en la raíz evita problemas de alcance con Laravel.

## 7.1 Prototipo de figura anatómica (`public/body-preview.html`)

Archivo HTML autónomo de ~24 KB, "Figura anatómica interactiva · Monitor de salud", con estilos y script incrustados, sin enlaces externos ni secretos. **No se creó en la sesión de desarrollo del motor y el panel**: ya estaba en el proyecto (fecha 14 nov, 14:52) y se incluye tal como se encontró. Está sin integrar al panel Livewire, que hoy usa su propia figura en SVG. Se accede en `/body-preview.html`. Pendiente decidir si reemplaza a la figura del panel o se elimina.

## 8. Pruebas

33 tests, 441 aserciones, todos en verde.

| Archivo | Cubre |
|---|---|
| `tests/Unit/Salud/MotorSaludTest.php` | Evaluador, límites inclusivos, compuestos, tendencias, validador, línea base, escalamiento, recomendaciones e índice |
| `tests/Unit/Salud/SimuladorTest.php` | Mediciones válidas y normales sin deriva, determinismo con semilla |
| `tests/Feature/ProcesadorMedicionesTest.php` | Guardado, alerta a tierra en críticos, rechazo de imposibles y duplicados |
| `tests/Feature/EnvioATierraTest.php` | Sin enlace las alertas esperan, modo simulado, POST con fallo y reintento |

Los tests de base de datos usan `DatabaseTransactions`, nunca `RefreshDatabase`, para no borrar los datos locales.

## 9. Documentación incluida

- `README.md`: visión general y puesta en marcha.
- `docs/Requisitos_Monitor_Salud_Astronautas.pdf` y `docs/Plan_Tecnico_Monitor_Salud_Astronautas.pdf`.
- `docs/EJECUTAR.md`: operación sin internet, enlace con tierra, prueba con otro dispositivo y HTTPS.

## 10. Verificado y no verificado

**Verificado:** los 33 tests, las migraciones en MySQL, el panel en el navegador, el tiempo real entre dos pestañas en el mismo equipo, el registro del service worker en `localhost`, y que un cliente WebSocket de Node conecta a Reverb por la IP de la red.

**No verificado:**
- Tiempo real entre dos dispositivos distintos. Desde el navegador integrado, el WebSocket por la IP de la red falló; se sospecha una limitación de ese navegador, pero no se confirmó.
- Instalación de la PWA, que necesita HTTPS en la red local (mkcert).
- La pantalla de aviso sin conexión con el servidor caído.

## 11. Pendiente

1. Probar con un dispositivo real en la red local.
2. HTTPS local con mkcert para instalar la PWA.
3. Vistas que faltan: registrar medición, detalle de indicador, detalle por sistema, historial, alerta y escalamiento, guía educativa, rutinas, contexto de misión, panel de la tripulación, cola a tierra, panel de tierra, aviso legal y autenticación.
4. Validar los umbrales con fuentes NASA o marcarlos como ejemplo en pantalla.
5. Corregir el simulador: el estado de ánimo sube con la deriva y nunca llega a crítico.
6. La cuenta de alertas pendientes del panel es global, no por astronauta.
