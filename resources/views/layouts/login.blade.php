<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Monitor de salud</title>
    <meta name="color-scheme" content="dark light">
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('tema'); } catch (e) {}
            if (t !== 'dark' && t !== 'light' && t !== 'hc') {
                t = window.matchMedia && matchMedia('(prefers-contrast: more)').matches ? 'hc'
                    : (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
{{ $slot }}
@livewireScripts
</body>
</html>
