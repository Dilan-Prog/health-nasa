@php
    $mision = \App\Support\TripulanteActual::mision();
    $nombreMision = $mision?->nombre ?? 'Misión';
    $etiquetaMision = mb_strtoupper($nombreMision);
    $nombreEstacion = match ($mision?->entorno) {
        'estacion' => 'Estación Orbital',
        'nave' => 'Nave de tránsito',
        'base_lunar' => 'Base lunar',
        'base_marciana' => 'Base marciana',
        default => 'Estación Orbital',
    };
    $grupos = [
        ['t' => 'Astronauta', 'links' => [
            ['t' => 'Panel', 'ic' => 'dashboard', 'ruta' => 'panel', 'href' => '/'],
            ['t' => 'Registrar', 'ic' => 'add_circle', 'ruta' => 'registrar', 'href' => '/registrar'],
            ['t' => 'Indicador', 'ic' => 'monitoring', 'ruta' => 'indicador', 'href' => '/indicador'],
            ['t' => 'Sistemas', 'ic' => 'accessibility_new'],
            ['t' => 'Historial', 'ic' => 'history'],
            ['t' => 'Alertas', 'ic' => 'notifications_active'],
            ['t' => 'Guía', 'ic' => 'menu_book'],
            ['t' => 'Rutinas', 'ic' => 'event_note', 'ruta' => 'rutinas', 'href' => '/rutinas'],
            ['t' => 'Misión', 'ic' => 'rocket_launch'],
        ]],
        ['t' => 'Estación y Tierra', 'links' => [
            ['t' => 'Tripulación', 'ic' => 'groups', 'ruta' => 'tripulacion', 'href' => '/tripulacion'],
            ['t' => 'Cola a Tierra', 'ic' => 'satellite_alt'],
            ['t' => 'Flight surgeon', 'ic' => 'stethoscope'],
        ]],
        ['t' => 'Apoyo', 'links' => [
            ['t' => 'Aviso legal', 'ic' => 'gavel'],
            ['t' => 'Acceso', 'ic' => 'login'],
        ]],
    ];
@endphp
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
<body>
<div class="shell">
    <aside class="shell-lateral" aria-label="Navegación principal">
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
                            <a role="link" aria-disabled="true" title="Próximamente" class="nav-link is-off">
                                <span class="icono">{{ $l['ic'] }}</span>{{ $l['t'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="shell-columna">
        <div class="shell-barra">
            <div class="shell-marca">
                <span class="mono shell-marca-et">{{ $etiquetaMision }}</span>
                <span class="shell-marca-nombre shell-marca-nombre--sm">{{ $nombreEstacion }}</span>
            </div>
            <livewire:selector-tripulante variante="compacta" />
        </div>
        {{ $slot }}
    </div>
</div>

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
