@php
    $iniciales = fn ($n) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $nombreEstado = ['ok' => 'Normal', 'warn' => 'Caution', 'crit' => 'Critical'];
    $vistasDef = [
        'frontal' => ['Front view', 'accessibility_new', false],
        'lateral' => ['Right side view', 'directions_walk', false],
        'lateral_izq' => ['Left side view', 'directions_walk', true],
        'posterior' => ['Back view', 'u_turn_left', false],
    ];
    $n = $tripulacion->count();
    $max = \App\Services\Tripulacion\AltaTripulante::maximo();
    $lleno = $n >= $max;
@endphp
<div class="trip">
    <x-encabezado titulo-a="Crew" :titulo-b="($mision?->nombre ?? 'No mission').' · '.$n.' '.($n === 1 ? 'crew member' : 'crew members')" />

    @php
        $hayNuevo = \Illuminate\Support\Facades\Route::has('tripulacion.nuevo');
        $hayFicha = \Illuminate\Support\Facades\Route::has('tripulacion.ficha');
        $nuevoTrip = $nuevoId ? $tripulacion->firstWhere('id', $nuevoId) : null;
    @endphp
    <div class="trip-barra">
        <span class="trip-cupo mono" title="Maximum {{ $max }} crew members per mission">{{ $n }} / {{ $max }} crew</span>
        @if ($hayNuevo)
            @if ($lleno)
                <span class="trip-nuevo is-off" role="link" aria-disabled="true" title="The mission already has the maximum of {{ $max }} crew members"><span class="icono">person_add</span> Crew full</span>
            @else
                <a class="trip-nuevo" href="{{ route('tripulacion.nuevo') }}"><span class="icono">person_add</span> New crew member</a>
            @endif
        @endif
    </div>

    @if ($aviso)
        <div class="trip-aviso-ok" role="status">
            <span class="icono trip-aviso-ic">check_circle</span>
            <span class="trip-aviso-txt"><strong>Crew member created</strong><span>Crew member “{{ $aviso }}” registered successfully.</span></span>
            @if ($hayFicha && $nuevoTrip)
                <a class="trip-aviso-btn" href="{{ route('tripulacion.ficha', $nuevoTrip) }}">View record</a>
            @endif
            <button type="button" class="trip-aviso-x" aria-label="Dismiss message" wire:click="cerrarAviso"><span class="icono">close</span></button>
        </div>
    @endif

    @if ($n === 0)
        <section class="trip-vacio" role="status" aria-labelledby="trip-vacio-t">
            <span class="icono trip-vacio-ic">groups</span>
            <h2 id="trip-vacio-t">This mission has no crew members yet</h2>
            <p>Add each crew member with a medical record so the app can compare their indicators against their own baseline.</p>
            @if ($hayNuevo)
                <a class="trip-nuevo" href="{{ route('tripulacion.nuevo') }}"><span class="icono">person_add</span> New crew member</a>
            @endif
        </section>
    @else
        <main class="trip-rejilla">
            @foreach ($tripulacion as $a)
                @php
                    $r = $resumenes[$a->id];
                    $hora = $r['medido_hoy'] && $r['ultima_medicion'] ? $r['ultima_medicion']->copy()->utc()->format('H:i') : null;
                    $medido = $r['medido_hoy'];
                    $medLabel = $medido ? 'Measurement taken'.($hora ? ' · '.$hora.' UTC' : '') : 'Measurement pending today';
                    $vista = $vistas[$a->id] ?? 'frontal';
                    $rol = trim(($a->rol ?? '').($a->edad ? ' · '.$a->edad.' yr' : ''), ' ·');
                    $datos = [
                        ['height', $a->altura_m !== null ? number_format($a->altura_m, 2, '.', '').' m' : '—', 'HEIGHT'],
                        ['scale', $a->peso_kg !== null ? rtrim(rtrim(number_format($a->peso_kg, 1, '.', ''), '0'), '.').' kg' : '—', 'WEIGHT'],
                        ['bloodtype', $a->grupo_sanguineo ?: '—', 'BLOOD'],
                        ['rocket_launch', $a->evas ?? '—', 'EVAs'],
                    ];
                @endphp
                <div class="trip-tarjeta @if ($nuevoId === $a->id) is-nueva @endif" role="button" tabindex="0" wire:key="trip-{{ $a->id }}"
                     aria-label="Open dashboard for {{ $a->nombre }} · {{ $medLabel }}"
                     wire:click="abrir({{ $a->id }})"
                     wire:keydown.enter.self.prevent="abrir({{ $a->id }})"
                     wire:keydown.space.self.prevent="abrir({{ $a->id }})">
                    <span class="trip-cab">
                        <span class="avatar avatar--36">{{ $iniciales($a->nombre) }}</span>
                        <span class="trip-quien">
                            <span class="trip-nombre">{{ $a->nombre }}</span>
                            <span class="trip-rol">{{ $rol }}</span>
                        </span>
                        @if ($nuevoId === $a->id)<span class="trip-nuevo-tag mono">NEW</span>@endif
                        @if ($hayFicha)
                            <a class="trip-expediente" href="{{ route('tripulacion.ficha', $a) }}" x-on:click.stop
                               aria-label="Record of {{ $a->nombre }}" title="Record"><span class="icono">folder_shared</span></a>
                        @endif
                        <span class="icono trip-flecha">arrow_forward</span>
                    </span>
                    <span class="trip-chips">
                        <span class="trip-chip trip-chip--med" data-estado="{{ $medido ? 'ok' : 'warn' }}" title="{{ $medLabel }}">
                            <span class="icono">{{ $medido ? 'task_alt' : 'schedule' }}</span>{{ $medido ? 'MEASURED'.($hora ? ' '.$hora : '') : 'PENDING' }}
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
                            <span class="trip-vistas" role="tablist" aria-label="Silhouette view for {{ $a->nombre }}">
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
                                      title="{{ $i['etiqueta'] }} · {{ $i['estado'] ? $nombreEstado[$i['estado']] : 'No data' }}">
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
