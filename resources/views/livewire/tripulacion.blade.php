@php
    $iniciales = fn ($n) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $nombreEstado = ['ok' => 'Bien', 'warn' => 'Revisar', 'crit' => 'Crítico'];
    $vistasDef = [
        'frontal' => ['Vista frontal', 'accessibility_new', false],
        'lateral' => ['Vista lateral derecha', 'directions_walk', false],
        'lateral_izq' => ['Vista lateral izquierda', 'directions_walk', true],
        'posterior' => ['Vista posterior', 'u_turn_left', false],
    ];
    $n = $tripulacion->count();
    $max = \App\Services\Tripulacion\AltaTripulante::maximo();
    $lleno = $n >= $max;
@endphp
<div class="trip">
    <x-encabezado titulo-a="Tripulación" :titulo-b="($mision?->nombre ?? 'Sin misión').' · '.$n.' '.($n === 1 ? 'tripulante' : 'tripulantes')" />

    @if (session('tripulante_creado') || \Illuminate\Support\Facades\Route::has('tripulacion.nuevo'))
        <div class="trip-barra">
            @if (session('tripulante_creado'))
                <p class="trip-aviso-ok" role="status"><span class="icono">task_alt</span> Tripulante «{{ session('tripulante_creado') }}» registrado correctamente.</p>
            @endif
            <span class="trip-cupo mono" title="Máximo {{ $max }} tripulantes por misión">{{ $n }} / {{ $max }} tripulantes</span>
            @if (\Illuminate\Support\Facades\Route::has('tripulacion.nuevo'))
                @if ($lleno)
                    <span class="trip-nuevo is-off" role="link" aria-disabled="true" title="La misión ya tiene el máximo de {{ $max }} tripulantes"><span class="icono">person_add</span> Tripulación completa</span>
                @else
                    <a class="trip-nuevo" href="{{ route('tripulacion.nuevo') }}"><span class="icono">person_add</span> Nuevo tripulante</a>
                @endif
            @endif
        </div>
    @endif

    @if ($n === 0)
        <div class="trip-vacio" role="status">
            <span class="icono">groups</span>
            <strong>Esta misión aún no tiene tripulantes</strong>
            <span>Cuando se asignen astronautas a la misión aparecerán aquí con su estado de salud.</span>
        </div>
    @else
        <main class="trip-rejilla">
            @foreach ($tripulacion as $a)
                @php
                    $r = $resumenes[$a->id];
                    $hora = $r['medido_hoy'] && $r['ultima_medicion'] ? $r['ultima_medicion']->copy()->utc()->format('H:i') : null;
                    $medido = $r['medido_hoy'];
                    $medLabel = $medido ? 'Medición realizada'.($hora ? ' · '.$hora.' UTC' : '') : 'Medición pendiente de hoy';
                    $vista = $vistas[$a->id] ?? 'frontal';
                    $rol = trim(($a->rol ?? '').($a->edad ? ' · '.$a->edad.' años' : ''), ' ·');
                    $datos = [
                        ['height', $a->altura_m !== null ? number_format($a->altura_m, 2, '.', '').' m' : '—', 'ALTURA'],
                        ['scale', $a->peso_kg !== null ? rtrim(rtrim(number_format($a->peso_kg, 1, '.', ''), '0'), '.').' kg' : '—', 'PESO'],
                        ['bloodtype', $a->grupo_sanguineo ?: '—', 'SANGRE'],
                        ['rocket_launch', $a->evas ?? '—', 'EVAs'],
                    ];
                @endphp
                <div class="trip-tarjeta" role="button" tabindex="0" wire:key="trip-{{ $a->id }}"
                     aria-label="Abrir panel de {{ $a->nombre }} · {{ $medLabel }}"
                     wire:click="abrir({{ $a->id }})"
                     wire:keydown.enter.self.prevent="abrir({{ $a->id }})"
                     wire:keydown.space.self.prevent="abrir({{ $a->id }})">
                    <span class="trip-cab">
                        <span class="avatar avatar--36">{{ $iniciales($a->nombre) }}</span>
                        <span class="trip-quien">
                            <span class="trip-nombre">{{ $a->nombre }}</span>
                            <span class="trip-rol">{{ $rol }}</span>
                        </span>
                        <span class="icono trip-flecha">arrow_forward</span>
                    </span>
                    <span class="trip-chips">
                        <span class="trip-chip trip-chip--med" data-estado="{{ $medido ? 'ok' : 'warn' }}" title="{{ $medLabel }}">
                            <span class="icono">{{ $medido ? 'task_alt' : 'schedule' }}</span>{{ $medido ? 'MEDIDO'.($hora ? ' '.$hora : '') : 'PENDIENTE' }}
                        </span>
                        @foreach (['crit', 'warn', 'ok'] as $k)
                            @if ($r['conteo'][$k] > 0)
                                <span class="trip-chip" data-estado="{{ $k }}"><span class="trip-punto"></span>{{ $r['conteo'][$k] }} {{ $nombreEstado[$k] }}</span>
                            @endif
                        @endforeach
                    </span>
                    <span class="trip-cuerpo">
                        <span class="trip-sil"><span class="trip-sil-in">
                            <x-silueta-corporal modo="miniatura" :vista="$vista" :activos="$r['musculos_activos']" class="silueta-corporal--ajustar" />
                        </span></span>
                        <span class="trip-lateral">
                            <span class="trip-vistas" role="tablist" aria-label="Vista de la silueta de {{ $a->nombre }}">
                                @foreach ($vistasDef as $id => [$etq, $ic, $espejo])
                                    <button type="button" role="tab" class="trip-vista" aria-selected="{{ $vista === $id ? 'true' : 'false' }}"
                                            aria-label="{{ $etq }}" title="{{ $etq }}"
                                            wire:click.stop="cambiarVista({{ $a->id }}, '{{ $id }}')"
                                            wire:key="v-{{ $a->id }}-{{ $id }}">
                                        <span class="icono" @if ($espejo) style="transform:scaleX(-1)" @endif>{{ $ic }}</span>
                                    </button>
                                @endforeach
                            </span>
                            @foreach ($r['indicadores'] as $i)
                                <span class="trip-ind" @if ($i['estado']) data-estado="{{ $i['estado'] }}" @endif
                                      title="{{ $i['etiqueta'] }} · {{ $i['estado'] ? $nombreEstado[$i['estado']] : 'Sin datos' }}">
                                    <span class="icono trip-ind-ic">{{ $i['icono'] }}</span>
                                    <span class="trip-ind-v">
                                        <span class="trip-ind-num">{{ $i['valor'] ?? '—' }}</span>
                                        <span class="trip-ind-u">{{ $i['unidad'] }}</span>
                                    </span>
                                </span>
                            @endforeach
                        </span>
                    </span>
                    <span class="trip-datos mono">
                        @foreach ($datos as [$ic, $v, $l])
                            <span class="trip-dato" title="{{ $l }}">
                                <span class="icono">{{ $ic }}</span>
                                <span class="trip-dato-t"><span class="trip-dato-v">{{ $v }}</span><span class="trip-dato-l">{{ $l }}</span></span>
                            </span>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </main>
    @endif
</div>
