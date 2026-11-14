<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monitor de salud · Astronautas</title>
    <meta name="theme-color" content="#0B3D91">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <header class="bg-nasa-navy text-white border-b-4 border-nasa-red">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <h1 class="text-lg font-bold tracking-wide">Monitor de salud <span class="text-nasa-red">·</span> Astronautas</h1>
            <span class="text-xs text-blue-200">Servidor a bordo · red local</span>
        </div>
    </header>
    <main class="max-w-6xl mx-auto px-4 py-6">{{ $slot }}</main>
    <footer class="max-w-6xl mx-auto px-4 pb-8 text-xs text-gray-500">
        Esta herramienta orienta, no diagnostica. Los umbrales son valores de referencia pendientes de validación con fuentes NASA.
    </footer>
    @livewireScripts
    <script>
        // El service worker solo se registra en contextos seguros (HTTPS o localhost).
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
