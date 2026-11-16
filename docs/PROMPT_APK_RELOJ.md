# Prompt para crear el mini APK "Reloj → Monitor de Salud"

Copia todo lo de abajo en una sesión nueva de Claude Code, abierta en una carpeta vacía (p. ej. `C:\laragon\www\reloj-apk`).

---

Crea una app Android mínima en Kotlin (Jetpack Compose, Gradle Kotlin DSL) llamada "Reloj Monitor" que lea datos de **Health Connect** (originados en un Samsung Galaxy Watch 7 vía Samsung Health) y los envíe por HTTP a un servidor Laravel en una red local. Debe compilar un APK instalable.

## Entorno
- Windows 11. Android SDK en `%LOCALAPPDATA%\Android\Sdk` (tiene build-tools, platforms, platform-tools). Android Studio instalado en `C:\Program Files\Android\Android Studio`: usa su JDK embebido (`jbr`) para Gradle, porque el Java del sistema es la versión 22 y puede no ser compatible con el plugin de Android.
- Teléfono destino: Realme 10 Pro con Android 13 (Health Connect es una app aparte instalada desde Play Store; en Android 14+ está integrada). `minSdk 28`, `targetSdk` el más reciente que el SDK instalado permita.
- Compila con `gradlew assembleDebug` y deja el APK en una ruta que me indiques. Si falta algo del SDK, dime exactamente qué instalar en vez de inventar.

## Funcionalidad
1. **Pantalla única** con: campo "URL del servidor" (ej. `http://192.168.1.50:8000`), campo "Token del tripulante", botón "Guardar", botón "Conceder permisos de Health Connect", botón "Sincronizar ahora", y un texto con el resultado de la última sincronización (hora, guardadas, duplicadas, rechazadas). Guarda URL y token con DataStore o SharedPreferences.
2. **Permisos de Health Connect** (solo lectura): `HeartRate`, `OxygenSaturation`, `BodyTemperature`, `SleepSession`. Si Health Connect no está instalado o no está disponible, mostrar un mensaje claro con la acción para instalarlo.
3. **Lectura**: al sincronizar, leer los registros nuevos desde la última sincronización correcta (guardar el instante en preferencias; la primera vez, últimas 24 h). Para pulso y SpO₂ usar los muestreos individuales con su instante. Para sueño, enviar una medición `sueno` por sesión, con las horas dormidas, medida al final de la sesión.
4. **Envío**: `POST {URL}/api/mediciones` con cabecera `Authorization: Bearer <token>` y `Content-Type: application/json`. Lotes de hasta 200 mediciones. Usa OkHttp o HttpURLConnection.
5. **Segundo plano**: un `PeriodicWorkRequest` de WorkManager cada 15 minutos (mínimo permitido) que lee y envía. Con restricción de red conectada.
6. **Cola offline**: si el envío falla por red, no avanzar el instante de última sincronización; en la siguiente ejecución se reenvía. El servidor tolera duplicados, así que reenviar es seguro.
7. **Red local sin HTTPS**: el servidor es HTTP plano en LAN. Declara `usesCleartextTraffic` mediante un `network_security_config` limitado a dominios/IPs locales (no cleartext global si se puede evitar).
8. Al final de la pantalla, un recordatorio visible: "En Realme, desactiva la optimización de batería para esta app y para Samsung Health".

## Contrato del servidor (ya implementado, no lo cambies)
`POST /api/mediciones`

Cabecera: `Authorization: Bearer <token>`

Cuerpo:
```json
{
  "mediciones": [
    { "indicador": "frecuencia_cardiaca", "valor": 72,   "medido_en": "2026-11-15T19:44:28+00:00" },
    { "indicador": "saturacion_o2",       "valor": 97,   "medido_en": "2026-11-15T19:44:28+00:00" },
    { "indicador": "temperatura",         "valor": 36.6, "medido_en": "2026-11-15T19:44:28+00:00" },
    { "indicador": "sueno",               "valor": 7.5,  "medido_en": "2026-11-15T06:30:00+00:00" }
  ]
}
```
- `indicador` solo puede ser: `frecuencia_cardiaca` (lpm), `saturacion_o2` (%), `temperatura` (°C), `sueno` (horas). Otro valor da 422.
- `valor` numérico. `medido_en` en ISO 8601 con zona horaria. Máximo 200 mediciones por petición. No se aceptan fechas futuras.
- Respuestas: `200 {"guardadas":N,"duplicadas":N,"rechazadas":[{"indice":i,"motivo":"..."}]}` (los duplicados y rechazos individuales NO son error: no reintentar los rechazados). `401` token inválido (mostrar "Token inválido" y no reintentar). `422` cuerpo inválido.

## Entrega
- Código fuente completo en la carpeta, README corto con cómo compilar e instalar (`adb install`), y la ruta del APK generado.
- Sin dependencias innecesarias. Comenta solo lo no obvio.
- No puedes probarlo en el teléfono: al terminar, lista los pasos de prueba manual (conceder permisos, pegar URL y token, sincronizar, ver el resultado en el panel web).
