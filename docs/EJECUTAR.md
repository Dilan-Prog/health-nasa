# Cómo ejecutar el Monitor de salud (sin internet)

## Preparación (una sola vez, CON internet)
```bash
composer install
npm install
npm run build          # empaqueta CSS/JS: no hay CDNs
php artisan migrate --seed
```
Base de datos: MySQL local, `DB_DATABASE=healts-nasa` en `.env`.

## Operación (SIN internet)
Tres procesos en el servidor a bordo:
```bash
php artisan serve --host=0.0.0.0 --port=8000     # la app
php artisan reverb:start --host=0.0.0.0 --port=8080   # tiempo real entre dispositivos
php artisan schedule:work                         # vacía la cola hacia tierra (tierra:enviar)
```
Los demás dispositivos abren `http://IP-DEL-SERVIDOR:8000` en la red local.

## Probar con otro dispositivo (mismo Wi-Fi o router)
1. En el servidor: `ipconfig` y anotar la IPv4 de la red (ej. `192.168.0.197`).
2. Arrancar los tres procesos de arriba con `--host=0.0.0.0`.
3. En el teléfono o laptop: abrir `http://IP-DEL-SERVIDOR:8000`.
4. Registrar una medición en un dispositivo; el otro debe actualizarse solo.
5. Si no carga: revisar el firewall de Windows (puertos 8000 y 8080) y que el router no tenga "aislamiento de clientes".

## Enlace con tierra
- `TIERRA_ENLACE` en `.env` define el estado inicial; en el panel se puede simular la pérdida y el regreso del enlace.
- `TIERRA_URL` vacío = modo simulado (las alertas se marcan como enviadas). Con una URL, se hace POST JSON y, si falla, la alerta sigue pendiente.
- Manual: `php artisan tierra:enviar`.

## Instalar como app (PWA) en la red local
Los navegadores solo registran el service worker en contexto seguro: `localhost` o **HTTPS**.
Para otros dispositivos de la red hace falta HTTPS con un certificado local:
1. Instalar mkcert (descarga única, con internet) y ejecutar `mkcert -install`.
2. `mkcert IP-DEL-SERVIDOR localhost` genera `.pem` y `-key.pem`.
3. Servir la app con HTTPS (Laragon: Menú > Apache/Nginx > SSL; o un proxy como Caddy/nginx con esos archivos).
4. Instalar la CA raíz de mkcert (`mkcert -CAROOT`) en cada dispositivo.
5. Cambiar en `.env`: `REVERB_SCHEME=https` y servir Reverb con TLS (`REVERB_SERVER_*` y certificados en `config/reverb.php`), y `npm run build` de nuevo.

Sin HTTPS la app funciona igual en el navegador; solo no se podrá instalar.

## Pruebas
```bash
php artisan test
```
Las pruebas usan transacciones: no borran los datos de la BD local.

## Pendiente de validar
Los umbrales de `config/umbrales.php` son valores de referencia, **no** están validados contra NASA HRP ni NASA-STD-3001.
