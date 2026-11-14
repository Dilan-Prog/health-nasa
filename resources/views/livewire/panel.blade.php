@php
    $color = fn ($e) => match ($e) {
        'normal' => '#1E9E5A', 'precaucion' => '#F5A623', 'critico' => '#FC3D21', default => '#CBD5E1',
    };
    $etiqueta = ['normal' => 'Normal', 'precaucion' => 'Precaución', 'critico' => 'Crítico'];
    $nombresSistema = ['cardiovascular' => 'Cardiovascular', 'oseo' => 'Óseo', 'inmune' => 'Inmune', 'mental' => 'Mental', 'metabolico' => 'Metabólico'];
    $est = fn ($s) => $indice[$s]['estado']?->value;
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Columna izquierda: astronauta, figura humana, índices --}}
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 p-4">
            <label class="text-xs font-semibold text-nasa-blue uppercase">Astronauta</label>
            <select wire:model.live="astronautaId" class="mt-1 w-full rounded border-gray-300 border p-2">
                @foreach ($astronautas as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre }} · {{ $a->mision }} ({{ str_replace('_', ' ', $a->entorno) }})</option>
                @endforeach
            </select>
            <div class="mt-3 border-t pt-3 text-xs text-gray-600">
                <p>Enlace con tierra:
                    <b class="{{ $enlace ? 'text-estado-normal' : 'text-nasa-red' }}">{{ $enlace ? 'ACTIVO' : 'SIN ENLACE' }}</b>
                    · Alertas pendientes: <b>{{ $alertasPendientes }}</b>
                </p>
                <div class="mt-2 flex gap-2">
                    <button wire:click="alternarEnlace" class="rounded border border-nasa-blue text-nasa-blue px-2 py-1">
                        {{ $enlace ? 'Simular pérdida de enlace' : 'Restablecer enlace' }}
                    </button>
                    @if ($enlace && $alertasPendientes)
                        <button wire:click="enviarAhora" class="rounded bg-nasa-blue text-white px-2 py-1">Enviar ahora</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4">
            <h2 class="font-bold text-nasa-blue mb-2">Figura humana</h2>
            <svg viewBox="0 0 200 330" class="mx-auto w-48" role="img" aria-label="Estado por sistema corporal">
                {{-- Inmune: contorno del torso --}}
                <rect x="62" y="68" width="76" height="120" rx="30" fill="none" stroke="{{ $color($est('inmune')) }}" stroke-width="6" stroke-dasharray="8 4"/>
                {{-- Mental: cabeza --}}
                <circle cx="100" cy="36" r="26" fill="{{ $color($est('mental')) }}"/>
                {{-- Óseo: brazos y piernas --}}
                <rect x="28" y="76" width="22" height="100" rx="11" fill="{{ $color($est('oseo')) }}"/>
                <rect x="150" y="76" width="22" height="100" rx="11" fill="{{ $color($est('oseo')) }}"/>
                <rect x="70" y="196" width="26" height="110" rx="13" fill="{{ $color($est('oseo')) }}"/>
                <rect x="104" y="196" width="26" height="110" rx="13" fill="{{ $color($est('oseo')) }}"/>
                {{-- Cardiovascular: corazón --}}
                <path d="M100 128 C84 112 80 98 92 92 C98 89 100 94 100 96 C100 94 102 89 108 92 C120 98 116 112 100 128 Z" fill="{{ $color($est('cardiovascular')) }}"/>
                {{-- Metabólico: abdomen --}}
                <ellipse cx="100" cy="160" rx="22" ry="20" fill="{{ $color($est('metabolico')) }}"/>
            </svg>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($nombresSistema as $k => $n)
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><i class="inline-block w-3 h-3 rounded-full" style="background: {{ $color($est($k)) }}"></i>{{ $n }}</span>
                        <span class="text-gray-600">{{ $indice[$k]['puntaje'] !== null ? $indice[$k]['puntaje'].'/100' : 'sin datos' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Columna central y derecha --}}
    <section class="lg:col-span-2 space-y-6">
        <div class="rounded-lg border border-gray-200 p-4">
            <h2 class="font-bold text-nasa-blue mb-3">Registrar medición</h2>
            <form wire:submit="registrar" class="grid gap-3 sm:grid-cols-4 items-end">
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold text-gray-600">Indicador</label>
                    <select wire:model.live="indicador" class="w-full rounded border border-gray-300 p-2">
                        @foreach ($config['indicadores'] as $k => $i)
                            <option value="{{ $k }}">{{ $i['nombre'] }} ({{ $i['unidad'] }})</option>
                        @endforeach
                    </select>
                </div>
                @if ($indicador === 'presion_arterial')
                    <input wire:model="sistolica" type="number" step="any" placeholder="Sistólica" class="rounded border border-gray-300 p-2" required>
                    <input wire:model="diastolica" type="number" step="any" placeholder="Diastólica" class="rounded border border-gray-300 p-2" required>
                @else
                    <input wire:model="valor" type="number" step="any" placeholder="Valor" class="rounded border border-gray-300 p-2 sm:col-span-2" required>
                @endif
                <button class="bg-nasa-blue text-white rounded px-4 py-2 font-semibold hover:bg-nasa-navy sm:col-span-4">Registrar</button>
            </form>

            @foreach ($errores as $e)
                <p class="mt-2 text-sm text-nasa-red">{{ $e }}</p>
            @endforeach
            @if ($aviso)
                <p class="mt-3 rounded bg-nasa-light p-3 text-sm"><b>Recomendación:</b> {{ $aviso }}</p>
            @endif

            <div class="mt-4 flex flex-wrap gap-2 border-t pt-3 text-sm">
                <span class="text-gray-500 self-center">Simulador:</span>
                <button wire:click="simular(0)" class="rounded border border-estado-normal text-estado-normal px-3 py-1">Valores normales</button>
                <button wire:click="simular(0.5)" class="rounded border border-estado-precaucion text-estado-precaucion px-3 py-1">Deriva a precaución</button>
                <button wire:click="simular(1)" class="rounded border border-estado-critico text-estado-critico px-3 py-1">Deriva a crítico</button>
                <button wire:click="limpiar" wire:confirm="¿Borrar todas las mediciones de este astronauta?" class="ml-auto text-xs text-gray-500 underline">Limpiar historial</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($config['indicadores'] as $clave => $def)
                @php
                    $ev = $ultimas[$clave] ?? null;
                    $serie = $series[$clave] ?? [];
                    $puntos = '';
                    if (count($serie) > 1) {
                        $min = min($serie); $max = max($serie); $rango = max($max - $min, 1e-9);
                        foreach ($serie as $i => $v) {
                            $puntos .= round($i * 160 / (count($serie) - 1), 1).','.round(34 - ($v - $min) / $rango * 30, 1).' ';
                        }
                    }
                @endphp
                <article class="rounded-lg border border-gray-200 p-4" style="border-left: 6px solid {{ $color($ev?->estado) }}">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold">{{ $def['nombre'] }}</h3>
                            <p class="text-xs text-gray-500">{{ $def['unidad'] }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold text-white" style="background: {{ $color($ev?->estado) }}">
                            {{ $ev ? $etiqueta[$ev->estado] : 'Sin datos' }}
                        </span>
                    </div>
                    @if ($ev)
                        <p class="mt-2 text-2xl font-bold">
                            {{ collect($ev->medicion->valores)->implode(' / ') }}
                            <span class="text-sm font-normal text-gray-500">
                                {{ ['subiendo' => '↑ subiendo', 'bajando' => '↓ bajando', 'estable' => '→ estable'][$ev->tendencia] }}
                            </span>
                        </p>
                        <p class="mt-1 text-sm text-gray-700">{{ $ev->recomendacion }}</p>
                        @if ($puntos)
                            <svg viewBox="0 0 160 38" class="mt-2 w-full h-10"><polyline points="{{ $puntos }}" fill="none" stroke="{{ $color($ev->estado) }}" stroke-width="2"/></svg>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-400">Aún no hay mediciones.</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
</div>
