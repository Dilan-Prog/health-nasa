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

Operación en el servidor (tres procesos):

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan schedule:work
```

Guía completa (red local, HTTPS con mkcert, enlace con tierra): [docs/EJECUTAR.md](docs/EJECUTAR.md).

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
