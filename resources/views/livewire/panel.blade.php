@php
    $etq = ['ok' => 'Normal', 'warn' => 'Caution', 'crit' => 'Critical'];
    $vistas = [
        ['frontal', 'Front view', 'accessibility_new', ''],
        ['lateral', 'Right side view', 'directions_walk', ''],
        ['lateral_izq', 'Left side view', 'directions_walk', 'panel-vista--espejo'],
        ['posterior', 'Back view', 'u_turn_left', ''],
    ];
    $hoy = $resumen && $resumen['medido_hoy'];
    $hora = $resumen && $resumen['ultima_medicion'] ? $resumen['ultima_medicion']->copy()->utc()->format('H:i') : null;
    $nCrit = $resumen['conteo']['crit'] ?? 0;
    $nWarn = $resumen['conteo']['warn'] ?? 0;
    $nTotal = count($indicadores);
    $resumenTxt = $cargando ? 'Calculating…' : ($nCrit + $nWarn > 0
        ? $nCrit.' critical · '.$nWarn.' caution'
        : 'All in range');
    $iconoEnvio = fn (bool $enviado) => $enviado ? ($conEnlace ? 'check' : 'schedule') : ($conEnlace ? 'campaign' : 'schedule_send');
@endphp
<div class="panel-raiz">
    <x-encabezado titulo-a="Dashboard" :titulo-b="'Crew member '.($astronauta?->nombre ?? '—')" />

    @if (! $astronauta)
        <main class="panel-principal">
            <p class="panel-vacio-total">There are no crew members in the current mission.</p>
        </main>
    @else
    <main class="panel-principal" x-data="{ vista: 'frontal' }">

        {{-- Izquierda: persona y figura --}}
        <section class="panel-sil" aria-label="Crew member">
            <div class="panel-persona">
                <div class="panel-nombre">{{ $astronauta->nombre }}</div>
                <div class="panel-rol">{{ $astronauta->rol }}@if ($astronauta->edad) · {{ $astronauta->edad }} years @endif</div>
                <span role="status" class="panel-medicion" data-estado="{{ $hoy ? 'ok' : 'warn' }}">
                    <span class="icono">{{ $hoy ? 'task_alt' : 'schedule' }}</span>
                    {{ $hoy ? 'Measurement done'.($hora ? ' · '.$hora.' UTC' : '') : 'Measurement pending today' }}
                </span>
                @unless ($hoy)
                    <a href="{{ route('registrar') }}" class="panel-btn-registrar">
                        <span class="icono">edit_note</span>Log measurement
                    </a>
                @endunless
            </div>

            <div class="panel-sil-caja">
                <div role="tablist" aria-label="Silhouette view" class="panel-vistas">
                    @foreach ($vistas as [$id, $label, $ic, $extra])
                        <button type="button" role="tab" class="panel-vista {{ $extra }}" aria-label="{{ $label }}" title="{{ $label }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                x-bind:aria-selected="vista === '{{ $id }}' ? 'true' : 'false'"
                                x-on:click="vista = '{{ $id }}'; $dispatch('silueta-vista', '{{ $id }}')">
                            <span class="icono">{{ $ic }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="panel-sil-alto">
                    <x-silueta-corporal :activos="$musculos" :selector="false" class="silueta-corporal--ajustar" />
                </div>
            </div>

            <div class="panel-modo" role="group" aria-label="Body display mode">
                <button type="button" class="panel-modo-b is-activo" aria-pressed="true">
                    <span class="mono">2D</span>
                </button>
                <button type="button" class="panel-modo-b" disabled aria-disabled="true" title="Coming soon">
                    <span class="mono">3D</span>
                    <span class="panel-modo-pronto">Coming soon</span>
                </button>
            </div>

            <div class="panel-datos">
                @foreach ([
                    ['height', $astronauta->altura_m ? number_format($astronauta->altura_m, 2, '.', '').' m' : '—', 'HEIGHT'],
                    ['scale', $astronauta->peso_kg ? number_format($astronauta->peso_kg, 1, '.', '').' kg' : '—', 'WEIGHT'],
                    ['bloodtype', $astronauta->grupo_sanguineo ?: '—', 'BLOOD TYPE'],
                    ['rocket_launch', $astronauta->evas ?? '—', 'EVAs'],
                ] as [$ic, $v, $l])
                    <div class="panel-dato">
                        <span class="icono panel-dato-ic">{{ $ic }}</span>
                        <span class="panel-dato-v">{{ $v }}</span>
                        <span class="panel-dato-l">{{ $l }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Centro: vistas e indicadores --}}
        <section class="panel-ind" aria-label="Indicators">

            <div class="panel-lista">
                @foreach ($indicadores as $m)
                    @php($e = $m['estado'])
                    <button type="button" class="panel-fila {{ $e === 'crit' ? 'is-crit' : '' }} {{ $sel === $m['clave'] ? 'is-sel' : '' }}"
                            @if ($e) data-estado="{{ $e }}" @endif
                            wire:click="seleccionar('{{ $m['clave'] }}')" aria-pressed="{{ $sel === $m['clave'] ? 'true' : 'false' }}">
                        <span class="icono panel-fila-ic">{{ $m['icono'] }}</span>
                        <span class="panel-fila-cuerpo">
                            <span class="panel-fila-valor"><span class="panel-valor mono">{{ $m['valor'] ?? '—' }}</span><span class="panel-unidad">{{ $m['unidad'] }}</span></span>
                            <span class="panel-fila-etq">{{ $m['etiqueta'] }}</span>@if (! empty($m['sospechoso']))<span class="panel-fila-etq" title="{{ $m['sospechoso'] }}"> · suspect data</span>@endif
                        </span>
                        <span class="panel-fila-der">
                            <span class="panel-badge mono"><span class="panel-punto"></span>{{ $e ? $etq[$e] : 'No data' }}</span>
                            <span class="panel-rango mono">{{ $m['rango'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Derecha: qué hacer ahora --}}
        <aside class="panel-q" aria-label="What to do now">
            <div class="panel-q-cab">
                <h2 class="panel-q-titulo">What to do now</h2>
                <div role="status" class="panel-q-estado mono">
                    <span class="panel-q-resumen">{{ $resumenTxt }}</span>
                    <span class="panel-enlace" data-estado="{{ $conEnlace ? 'ok' : 'crit' }}">
                        <span class="icono">{{ $conEnlace ? 'satellite_alt' : 'link_off' }}</span>{{ $conEnlace ? 'LINK ACTIVE' : 'NO LINK' }}
                    </span>
                    <span class="panel-q-retardo">{{ $conEnlace ? 'Ground response in ~'.$retardo.' min' : 'Alerts are queued until the link is restored' }}</span>
                    <button type="button" class="panel-enlace-demo" wire:click="alternarEnlace" title="Toggle ground link (demo)" aria-label="Toggle ground link (demo)">
                        <span class="icono">swap_horiz</span>
                    </button>
                </div>
            </div>

            @if ($caso && $orientacionCaso)
                @php($oc = $orientacionCaso)
                <article class="panel-caso" data-sev="{{ $caso->severidad }}" aria-label="Flight Surgeon case: {{ $oc->titulo }}" wire:key="panel-caso-{{ $caso->id }}-{{ $caso->severidad }}">
                    <div class="panel-caso-cab">
                        <span class="icono panel-caso-ic" aria-hidden="true">stethoscope</span>
                        <span class="panel-caso-t">{{ $oc->titulo }}</span>
                        <span class="panel-badge mono"><span class="icono" aria-hidden="true">{{ $caso->severidad === 'critico' ? 'error' : 'warning' }}</span>{{ $caso->severidad === 'critico' ? 'Critical' : 'Caution' }}</span>
                    </div>
                    <p class="panel-caso-txt">{{ $oc->hallazgo }}</p>
                    @if ($oc->acciones)
                        <ol class="panel-caso-pasos">
                            @foreach ($oc->acciones as $ac)<li>{{ $ac['texto'] }}</li>@endforeach
                        </ol>
                    @endif
                    @if ($oc->historial7d)
                        <x-caso-historial :o="$oc" />
                    @endif
                    <p class="panel-caso-sim mono">Simulated guidance · {{ $oc->confianza }} % simulated confidence</p>
                    <div class="panel-botones">
                        <button type="button" class="panel-btn" onclick="window.Livewire && Livewire.dispatch('abrir-medico-flotante', { casoId: {{ $caso->id }} })"><span class="icono" aria-hidden="true">open_in_full</span>Open in Flight Surgeon</button>
                    </div>
                </article>
            @endif

            @if ($cargando)
                <div role="status" aria-live="polite" class="panel-cargando">
                    <span class="panel-cargando-t mono"><span class="icono">hourglass_top</span>LOADING SUGGESTIONS…</span>
                    @foreach ([1, 2, 3] as $k)
                        <div class="panel-esqueleto">
                            <div class="panel-esq-fila"><span class="panel-esq panel-esq--ic"></span><span class="panel-esq panel-esq--t"></span><span class="panel-esq panel-esq--b"></span></div>
                            <span class="panel-esq panel-esq--l1"></span>
                            <span class="panel-esq panel-esq--g"></span>
                            <span class="panel-esq panel-esq--l2"></span>
                        </div>
                    @endforeach
                </div>
            @elseif (! $tarjetas)
                <div class="panel-vacio">
                    <span class="icono panel-vacio-ic">check_circle</span>
                    <div class="panel-vacio-txt">
                        <span class="panel-vacio-t">All in range, no pending actions</span>
                        <span class="panel-vacio-s">All {{ $nTotal }} indicators are within your personal range.</span>
                    </div>
                </div>
            @else
                <div class="panel-pista {{ $seleccionado ? 'is-sel' : '' }}">
                    <span class="icono">{{ $seleccionado ? 'accessibility_new' : 'touch_app' }}</span>
                    <span class="panel-pista-t">{{ $seleccionado ? 'On the figure: '.$seleccionado['etiqueta'].' · '.$seleccionado['zona'] : 'Select a card or an indicator to see the related area on the figure.' }}</span>
                    @if ($seleccionado)
                        <button type="button" class="panel-btn-quitar" wire:click="quitarSeleccion">Clear</button>
                    @endif
                </div>

                @foreach ($tarjetas as $c)
                    @php($crit = $c['estado'] === 'crit')
                    <article class="panel-tarjeta {{ $sel === $c['clave'] ? 'is-sel' : '' }}" data-estado="{{ $c['estado'] }}" aria-label="{{ $c['etiqueta'].', '.$etq[$c['estado']] }}" wire:key="tarjeta-{{ $astronauta->id }}-{{ $c['clave'] }}">
                        <button type="button" class="panel-tarjeta-cab" wire:click="seleccionar('{{ $c['clave'] }}')" aria-pressed="{{ $sel === $c['clave'] ? 'true' : 'false' }}">
                            <span class="icono panel-tarjeta-ic">{{ $c['icono'] }}</span>
                            <span class="panel-tarjeta-t">{{ $c['etiqueta'] }}</span>
                            <span class="panel-badge mono"><span class="icono">{{ $crit ? 'error' : 'warning' }}</span>{{ $etq[$c['estado']] }}</span>
                            <span class="panel-tarjeta-razon">{{ $c['razon'] }}</span>
                            @if (! empty($c['sospechoso']))<span class="panel-tarjeta-razon">Suspect data: {{ $c['sospechoso'] }}</span>@endif
                        </button>

                        <div class="panel-tend">
                            <div class="panel-tend-cab mono"><span>7-DAY TREND</span><span class="panel-tend-base"><span class="panel-tend-linea"></span>YOUR BASELINE {{ $c['base'] }}</span></div>
                            <svg role="img" class="panel-tend-svg" viewBox="0 0 280 56" preserveAspectRatio="none"
                                 aria-label="7-day trend of {{ $c['etiqueta'] }}, from baseline to current {{ $c['valor'] }} {{ $c['unidad'] }}">
                                <line x1="0" x2="280" y1="{{ $c['svg']['baseY'] }}" y2="{{ $c['svg']['baseY'] }}" class="panel-tend-base-l" stroke-width="1.5" stroke-dasharray="5 4" vector-effect="non-scaling-stroke"></line>
                                <polyline points="{{ $c['svg']['pts'] }}" fill="none" class="panel-tend-pol" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>
                            </svg>
                            <div class="panel-tend-pie mono"><span>6 D AGO</span><span>TODAY</span></div>
                        </div>

                        <ul class="panel-acciones">
                            @foreach ($c['acciones'] as $j => $txt)
                                @php($hecha = $hechas[$astronauta->id.'.'.$c['clave'].'.'.$j] ?? false)
                                <li><label class="panel-accion {{ $hecha ? 'is-hecha' : '' }}">
                                    <input type="checkbox" @checked($hecha) wire:click="alternarAccion('{{ $c['clave'] }}', {{ $j }})">
                                    <span>{{ $txt }}</span>
                                </label></li>
                            @endforeach
                        </ul>

                        <div class="panel-botones">
                            <button type="button" class="panel-btn" wire:click="repetirMedicion('{{ $c['clave'] }}')"><span class="icono">replay</span>Repeat measurement</button>
                            <a class="panel-btn" href="{{ route('guia') }}"><span class="icono">menu_book</span>View guide</a>
                            <button type="button" class="panel-btn panel-btn--aviso {{ $crit ? 'is-crit' : '' }}" wire:click="avisarATierra('{{ $c['clave'] }}')" @disabled($c['avisado'])>
                                <span class="icono">{{ $iconoEnvio($c['avisado']) }}</span>{{ $c['avisado'] ? ($conEnlace ? 'Alert sent to ground' : 'Queued · will send when link is restored') : ($conEnlace ? 'Alert ground' : 'Queue alert to ground') }}
                            </button>
                        </div>
                    </article>
                @endforeach
            @endif

            @unless ($cargando)
                <section class="panel-chequeo" aria-label="Guided self-check">
                    <div class="panel-chequeo-cab">
                        <span class="icono panel-chequeo-ic">fact_check</span>
                        <span class="panel-chequeo-t">Guided self-check</span>
                        <span class="panel-chequeo-paso mono">{{ $chequeo['hecho'] ? 'COMPLETED' : 'QUESTION '.($chequeo['n'] + 1).' OF '.count($preguntas) }}</span>
                    </div>
                    <div role="progressbar" class="panel-progreso" aria-valuemin="0" aria-valuemax="{{ count($preguntas) }}" aria-valuenow="{{ $chequeo['n'] }}" aria-label="Self-check progress">
                        <div class="panel-progreso-barra" style="width: {{ round($chequeo['n'] / count($preguntas) * 100) }}%"></div>
                    </div>

                    @unless ($chequeo['hecho'])
                        <div class="panel-pregunta">
                            <span class="panel-pregunta-t">{{ $preguntas[min($chequeo['n'], count($preguntas) - 1)] }}</span>
                            <div class="panel-si-no">
                                <button type="button" class="panel-btn panel-btn--sn" wire:click="responder('y')">Yes</button>
                                <button type="button" class="panel-btn panel-btn--sn" wire:click="responder('n')">No</button>
                            </div>
                            @if ($chequeo['n'] > 0)
                                <button type="button" class="panel-btn-atras" wire:click="preguntaAnterior"><span class="icono">arrow_back</span>Previous question</button>
                            @endif
                        </div>
                    @else
                        @php($r = $chequeo['res'])
                        <div role="status" class="panel-resultado" data-estado="{{ $r['k'] }}">
                            <div class="panel-resultado-cab">
                                <span class="icono panel-resultado-ic">{{ $r['ic'] }}</span>
                                <div class="panel-resultado-tt"><span class="panel-resultado-et mono">RESULT</span><span class="panel-resultado-t">{{ $r['titulo'] }}</span></div>
                            </div>
                            <span class="panel-resultado-txt">{{ $r['texto'] }}</span>
                            <div class="panel-botones">
                                @if ($chequeo['rk'] === 'red')
                                    <button type="button" class="panel-btn panel-btn--aviso is-crit" wire:click="avisarATierra('chk')" @disabled($chequeo['avisado'])>
                                        <span class="icono">{{ $iconoEnvio($chequeo['avisado']) }}</span>{{ $chequeo['avisado'] ? ($conEnlace ? 'Alert sent to ground' : 'Queued · will send when link is restored') : ($conEnlace ? 'Alert ground now' : 'Queue alert to ground') }}
                                    </button>
                                @endif
                                <button type="button" class="panel-btn" wire:click="reiniciarChequeo"><span class="icono">restart_alt</span>Repeat self-check</button>
                            </div>
                        </div>
                    @endunless
                </section>
            @endunless

            <p role="note" class="panel-nota"><span class="icono">info</span>This tool provides guidance only; it does not diagnose. If in doubt, contact the ground.</p>
        </aside>
    </main>
    @endif
</div>
