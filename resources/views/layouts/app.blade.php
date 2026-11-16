@php
    $mision = \App\Support\TripulanteActual::mision();
    $nombreMision = $mision?->nombre ?? 'Mission';
    $etiquetaMision = mb_strtoupper($nombreMision);
    $nombreEstacion = match ($mision?->entorno) {
        'estacion' => 'Space station (LEO)',
        'nave' => 'Spacecraft (transit)',
        'base_lunar' => 'Lunar base',
        'base_marciana' => 'Mars base',
        default => 'Space station (LEO)',
    };
    $grupos = [
        ['t' => 'Astronaut', 'links' => [
            ['t' => 'Dashboard', 'ic' => 'dashboard', 'ruta' => 'panel', 'href' => '/'],
            ['t' => 'Log', 'ic' => 'add_circle', 'ruta' => 'registrar', 'href' => '/registrar'],
            ['t' => 'Indicators', 'ic' => 'monitoring', 'ruta' => 'indicador', 'href' => '/indicador'],
            ['t' => 'Body systems', 'ic' => 'accessibility_new', 'ruta' => 'sistemas', 'href' => '/sistemas'],
            ['t' => 'History', 'ic' => 'history', 'ruta' => 'historial', 'href' => '/historial'],
            ['t' => 'Alerts', 'ic' => 'notifications_active', 'ruta' => 'alertas', 'href' => '/alertas'],
            ['t' => 'Guide', 'ic' => 'menu_book', 'ruta' => 'guia', 'href' => '/guia'],
            ['t' => 'Routines', 'ic' => 'event_note', 'ruta' => 'rutinas', 'href' => '/rutinas'],
            ['t' => 'Mission', 'ic' => 'rocket_launch', 'ruta' => 'mision', 'href' => '/mision'],
        ]],
        ['t' => 'Station & ground', 'links' => [
            ['t' => 'Crew', 'ic' => 'groups', 'ruta' => 'tripulacion', 'href' => '/tripulacion'],
            ...(app()->environment('production') ? [] : [['t' => 'Simulation', 'ic' => 'model_training', 'ruta' => 'simulacion', 'href' => '/simulacion']]),
            ['t' => 'Ground queue', 'ic' => 'satellite_alt', 'ruta' => 'cola-tierra', 'href' => '/cola-a-tierra'],
            ['t' => 'Medical kit', 'ic' => 'medication', 'ruta' => 'botiquin', 'href' => '/botiquin'],
            ['t' => 'Flight Surgeon', 'ic' => 'stethoscope', 'ruta' => 'medico', 'href' => '/medico-de-vuelo'],
            ['t' => 'Audit log', 'ic' => 'fact_check', 'ruta' => 'auditoria', 'href' => '/auditoria'],
        ]],
        ['t' => 'Support', 'links' => [
            ['t' => 'Legal notice', 'ic' => 'gavel', 'ruta' => 'aviso-legal', 'href' => '/aviso-legal'],
            ['t' => 'Sign in', 'ic' => 'login', 'ruta' => 'login', 'href' => '/acceso'],
            ['t' => 'Roles', 'ic' => 'admin_panel_settings'], // Coming soon: sin href, se muestra deshabilitado
        ]],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Health monitor · Astronauts</title>
    <meta name="theme-color" content="#0B3D91">
    <meta name="color-scheme" content="dark light">
    {{-- Tema antes del primer pintado: elección guardada (oscuro | claro | hc); sin ella, prefers-contrast / prefers-color-scheme; por defecto oscuro. --}}
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('tema'); } catch (e) {}
            if (t !== 'dark' && t !== 'light' && t !== 'hc') {
                t = 'dark';
                try {
                    if (window.matchMedia('(prefers-contrast: more)').matches) t = 'hc';
                    else if (window.matchMedia('(prefers-color-scheme: light)').matches) t = 'light';
                } catch (e) {}
            }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="shell">
    <aside class="shell-lateral" aria-label="Main navigation">
        <div class="shell-marca">
            <span class="mono shell-marca-et">{{ $etiquetaMision }}</span>
            <span class="shell-marca-nombre">{{ $nombreEstacion }}</span>
        </div>
        <livewire:selector-tripulante variante="lateral" />
        <nav class="shell-nav">
            @foreach ($grupos as $g)
                <div class="nav-grupo">
                    <span class="nav-grupo-t">{{ $g['t'] }}</span>
                    @foreach ($g['links'] as $l)
                        @if (isset($l['href']))
                            @php($activo = request()->routeIs($l['ruta']))
                            <a href="{{ $l['href'] }}" class="nav-link {{ $activo ? 'is-activo' : '' }}" @if ($activo) aria-current="page" @endif>
                                <span class="icono">{{ $l['ic'] }}</span>{{ $l['t'] }}
                            </a>
                        @elseif (isset($l['accion']))
                            <button type="button" class="nav-link" onclick="window.Livewire && Livewire.dispatch('{{ $l['accion'] }}')">
                                <span class="icono">{{ $l['ic'] }}</span>{{ $l['t'] }}
                            </button>
                        @else
                            <a role="link" aria-disabled="true" title="Coming soon" class="nav-link is-off">
                                <span class="icono">{{ $l['ic'] }}</span>{{ $l['t'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>
        @auth
            <div class="shell-sesion">
                <span class="shell-sesion-nombre">{{ auth()->user()->name }}</span>
                <span class="mono shell-sesion-rol">{{ mb_strtoupper(auth()->user()->etiquetaRol()) }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="shell-sesion-btn"><span class="icono" aria-hidden="true">logout</span>Sign out</button>
                </form>
            </div>
        @endauth
        <div class="tema-grupo">
            <span class="tema-et">Theme</span>
            <div class="tema" role="group" aria-label="Color theme">
                <button type="button" class="tema-op" data-tema="dark" aria-pressed="false" title="Dark"><span class="icono" aria-hidden="true">dark_mode</span><span class="tema-txt">Dark</span></button>
                <button type="button" class="tema-op" data-tema="light" aria-pressed="false" title="Light"><span class="icono" aria-hidden="true">light_mode</span><span class="tema-txt">Light</span></button>
                <button type="button" class="tema-op" data-tema="hc" aria-pressed="false" title="High contrast"><span class="icono" aria-hidden="true">contrast</span><span class="tema-txt">High contrast</span></button>
            </div>
        </div>
    </aside>

    <div class="shell-columna">
        <div class="shell-barra">
            <div class="shell-marca">
                <span class="mono shell-marca-et">{{ $etiquetaMision }}</span>
                <span class="shell-marca-nombre shell-marca-nombre--sm">{{ $nombreEstacion }}</span>
            </div>
            <livewire:selector-tripulante variante="compacta" />
            <button type="button" class="tema-ciclo" data-tema-ciclo title="Change theme (dark, light, high contrast)" aria-label="Change color theme">
                <span class="icono" aria-hidden="true">contrast</span>
            </button>
        </div>
        {{ $slot }}
    </div>
</div>

<livewire:medico-flotante />

{{-- El registro de mediciones es ahora la pantalla /registrar: el evento `abrir-registro` (con `indicador` opcional) navega a ella.
     El componente RegistrarMedicion (modal) sigue existiendo, sin montar. --}}
@livewireScripts
<script>
    document.addEventListener('livewire:init', () => {
        window.Livewire.on('abrir-registro', (p) => {
            const d = Array.isArray(p) ? p[0] : p;
            window.location.href = @js(route('registrar', [], false)) + (d && d.indicador ? '?indicador=' + encodeURIComponent(d.indicador) : '');
        });
    });
</script>
<script>
    // El service worker solo se registra en contextos seguros (HTTPS o localhost).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>
</body>
</html>
