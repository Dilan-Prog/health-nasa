# Monitor de salud para astronautas

Proyecto para el **NASA Space Apps Challenge 2026**, reto *Software de monitorización de la salud*.

Herramienta visual con la que un astronauta evalúa su propia salud durante una misión y recibe orientación concreta. Corre en un **servidor a bordo de la estación**, dentro de la red local y **sin internet**. Tierra es opcional y asíncrona: recibe alertas y resúmenes cuando hay enlace.

> La herramienta **orienta, no diagnostica**. Los umbrales de `config/umbrales.php` son valores de referencia y **no están validados** contra NASA HRP ni NASA-STD-3001.

## Arquitectura

```
[Dispositivo de cada astronauta] <-- red local --> [Servidor Laravel a bordo]
        navegador o PWA                              |- Motor de reglas (PHP)
                                                     |- MySQL local (historial)
                                                     |- Reverb (tiempo real)
                                                     |- Cola de alertas a tierra
                                                              |  (cuando hay enlace)
                                                              v
                                                     [Flight surgeon en tierra]
```

| Capa | Herramienta |
|---|---|
| Backend | Laravel 10, PHP 8.2 |
| Base de datos | MySQL local |
| Interfaz | Livewire 3, Alpine.js (incluido en Livewire), Tailwind CSS 3 |
| Tiempo real | Laravel Reverb + Laravel Echo (servidor propio, sin Pusher) |
| App instalable | PWA ligera (manifest + service worker escrito a mano) |
| Pruebas | PHPUnit |

## Estado actual

Hecho: motor de reglas, modelo de datos, panel principal, tiempo real, cola a tierra con enlace simulado y PWA en `localhost`.

Pendiente: vistas adicionales (detalle, historial, alertas, panel de tripulación), HTTPS local para instalar la PWA en otros dispositivos, prueba con dispositivos reales y validación de umbrales con fuentes NASA.

El detalle completo está en [docs/PRIMER_COMMIT.md](docs/PRIMER_COMMIT.md).

## Puesta en marcha

Requisitos: PHP 8.2, Composer, Node 18+, MySQL (Laragon sirve).

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate   # luego configura DB_* en .env
php artisan migrate --seed
npm run build
```

Operación en el servidor (cuatro procesos, cada uno en su terminal):

```bash
php artisan serve --host=0.0.0.0 --port=8000          # aplicación web
php artisan reverb:start --host=0.0.0.0 --port=8080   # tiempo real (Reverb)
php artisan salud:simulador-vivo                      # datos simulados en vivo (prototipo)
php artisan schedule:work                             # tareas programadas (envío a tierra, poda)
```

> **`APP_DEBUG` debe ser `false` fuera de local.** `.env.example` ya lo trae en `false`; con `true` se exponen trazas y configuración en los errores. Actívalo solo en tu `.env` de desarrollo.

Guía completa (red local, HTTPS con mkcert, enlace con tierra): [docs/EJECUTAR.md](docs/EJECUTAR.md).

## Fuentes citadas y valores de ejemplo

Fuentes: **NASA-STD-3001 Vol. 1 Rev. C**, **NASA HRP** (Human Research Program) y **CSA** (Agencia Espacial Canadiense). Citarlas no implica que los valores estén validados.

**Aviso:** es un prototipo educativo. Todos los valores clínicos y umbrales son **valores de ejemplo, pendientes de revisión médica**, y la herramienta no sustituye a un profesional médico. Ver la página `/aviso-legal`.

## Seguridad

- **Cifrado del expediente.** Alergias, condiciones, medicamentos, antecedentes, contactos, restricciones y notas se guardan cifrados con la `APP_KEY` de Laravel (AES-256). **Si `APP_KEY` cambia o se pierde, esos datos quedan ilegibles.** Haz copia de `APP_KEY` y de la base (por separado y en lugar seguro) antes de migrar o desplegar. Para cifrar datos antiguos: `php artisan expediente:cifrar` (simulación) y `php artisan expediente:cifrar --confirmar` (escribe).
- **Auditoría de accesos.** Se registra quién abre qué pantalla sensible (sin valores médicos). Consulta en `/auditoria` (flight surgeon cuando el login está activo). Poda: `php artisan auditoria:podar` (simulación) y `--confirmar`; conserva 90 días (supuesto del proyecto).
- **API del reloj.** `php artisan reloj:token <id> [--dias=30]` entrega token y secreto de firma una sola vez. El token vence (401), el anterior sigue válido 1 hora al regenerar, límite de 120 peticiones/min por token (429). Firma opcional del lote: `X-Marca-Tiempo` y `X-Firma: sha256=<hex>` (HMAC-SHA256 de `marca.cuerpo`); se exige con `RELOJ_EXIGIR_FIRMA=true`. Campo opcional `bateria` (0 a 100) con aviso de batería baja. `RELOJ_SIN_TOKEN_ASTRONAUTA` es solo para pruebas y debe estar vacío fuera de ellas.
- **Datos dudosos.** Un salto brusco o una señal plana marcan la medición como "dato sospechoso": se guarda, no avisa a tierra por sí sola ni entra en la línea base.
- **Pendiente de despliegue (no lo hace la aplicación):** cifrado de disco de MySQL (tablespace encryption) y TLS en web y en la conexión a la base.

Todos los números de estas medidas son supuestos del proyecto, no de la norma.

## Hoja de ruta (Próximamente)

Biomarcadores y datos que aún no tienen entrada ni lógica, visibles como "Próximamente" en la Guía y en Sistemas (`config/proximamente.php`): VO₂máx, fuerza, densidad mineral ósea, grosor de retina y presión intraocular, vitamina D, calcio urinario, cortisol salival, PVT, ingesta calórica, pCO₂/pO₂/humedad de cabina, ECG y flujo yugular, telemetría de ARED/T2/CEVIS, radiación por órgano y evento solar, prescripción dinámica de ejercicio y posvuelo de 45 días.

Plataforma (también "Próximamente", sin fechas; se muestra en la Guía):

- **IA local con Ollama**: modelo de lenguaje local en lugar de las respuestas simuladas actuales.
- **Orquestación con n8n**: flujos automáticos entre servicios; hoy no hay ninguno conectado.

## Pruebas

```bash
php artisan test
```

33 tests. Los que usan la base de datos corren dentro de transacciones y no borran datos.

## Documentación

- [docs/Requisitos_Monitor_Salud_Astronautas.pdf](docs/Requisitos_Monitor_Salud_Astronautas.pdf): requisitos y decisiones de arquitectura.
- [docs/Plan_Tecnico_Monitor_Salud_Astronautas.pdf](docs/Plan_Tecnico_Monitor_Salud_Astronautas.pdf): plan técnico y de ejecución.
- [docs/PRIMER_COMMIT.md](docs/PRIMER_COMMIT.md): qué contiene el primer commit.
- [docs/EJECUTAR.md](docs/EJECUTAR.md): cómo ejecutar sin internet.

Proyecto basado en Laravel, licencia MIT.
