@php
    $etq = ['ok' => 'Bien', 'warn' => 'Revisar', 'crit' => 'Crítico'];
    $vistas = [
        ['frontal', 'Vista frontal', 'accessibility_new', ''],
        ['lateral', 'Vista lateral derecha', 'directions_walk', ''],
        ['lateral_izq', 'Vista lateral izquierda', 'directions_walk', 'panel-vista--espejo'],
        ['posterior', 'Vista posterior', 'u_turn_left', ''],
    ];
    $hoy = $resumen && $resumen['medido_hoy'];
    $hora = $resumen && $resumen['ultima_medicion'] ? $resumen['ultima_medicion']->copy()->utc()->format('H:i') : null;
    $nCrit = $resumen['conteo']['crit'] ?? 0;
    $nWarn = $resumen['conteo']['warn'] ?? 0;
    $nTotal = count($indicadores);
    $resumenTxt = $cargando ? 'Calculando…' : ($nCrit + $nWarn > 0
        ? $nCrit.' '.($nCrit === 1 ? 'crítico' : 'críticos').' · '.$nWarn.' a revisar'
        : 'Todo en rango');
    $iconoEnvio = fn (bool $enviado) => $enviado ? ($conEnlace ? 'check' : 'schedule') : ($conEnlace ? 'campaign' : 'schedule_send');
@endphp
<div class="panel-raiz">
    <x-encabezado titulo-a="Panel" :titulo-b="'Tripulante '.($astronauta?->nombre ?? '—')" />

    @if (! $astronauta)
        <main class="panel-principal">
            <p class="panel-vacio-total">No hay tripulantes en la misión actual.</p>
        </main>
    @else
    <main class="panel-principal" x-data="{ vista: 'frontal' }">

        {{-- Izquierda: persona y figura --}}
        <section class="panel-sil" aria-label="Tripulante">
            <div class="panel-persona">
                <div class="panel-nombre">{{ $astronauta->nombre }}</div>
                <div class="panel-rol">{{ $astronauta->rol }}@if ($astronauta->edad) · {{ $astronauta->edad }} años @endif</div>
                <span role="status" class="panel-medicion" data-estado="{{ $hoy ? 'ok' : 'warn' }}">
                    <span class="icono">{{ $hoy ? 'task_alt' : 'schedule' }}</span>
                    {{ $hoy ? 'Medición realizada'.($hora ? ' · '.$hora.' UTC' : '') : 'Medición pendiente de hoy' }}
                </span>
                @unless ($hoy)
                    <a href="{{ route('registrar') }}" class="panel-btn-registrar">
                        <span class="icono">edit_note</span>Registrar medición
                    </a>
                @endunless
            </div>

            <div class="panel-sil-caja">
                <div role="tablist" aria-label="Vista de la silueta" class="panel-vistas">
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

            <div class="panel-modo" role="group" aria-label="Modo de visualización del cuerpo">
                <button type="button" class="panel-modo-b is-activo" aria-pressed="true">
                    <span class="mono">2D</span>
                </button>
                <button type="button" class="panel-modo-b" disabled aria-disabled="true" title="Próximamente">
                    <span class="mono">3D</span>
                    <span class="panel-modo-pronto">Próximamente</span>
                </button>
            </div>

            <div class="panel-datos">
                @foreach ([
                    ['height', $astronauta->altura_m ? number_format($astronauta->altura_m, 2, '.', '').' m' : '—', 'ALTURA'],
                    ['scale', $astronauta->peso_kg ? number_format($astronauta->peso_kg, 1, '.', '').' kg' : '—', 'PESO'],
                    ['bloodtype', $astronauta->grupo_sanguineo ?: '—', 'SANGRE'],
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
        <section class="panel-ind" aria-label="Indicadores">

            <div class="panel-lista">
                @foreach ($indicadores as $m)
                    @php($e = $m['estado'])
                    <button type="button" class="panel-fila {{ $e === 'crit' ? 'is-crit' : '' }} {{ $sel === $m['clave'] ? 'is-sel' : '' }}"
                            @if ($e) data-estado="{{ $e }}" @endif
                            wire:click="seleccionar('{{ $m['clave'] }}')" aria-pressed="{{ $sel === $m['clave'] ? 'true' : 'false' }}">
                        <span class="icono panel-fila-ic">{{ $m['icono'] }}</span>
                        <span class="panel-fila-cuerpo">
                            <span class="panel-fila-valor"><span class="panel-valor mono">{{ $m['valor'] ?? '—' }}</span><span class="panel-unidad">{{ $m['unidad'] }}</span></span>
                            <span class="panel-fila-etq">{{ $m['etiqueta'] }}</span>
                        </span>
                        <span class="panel-fila-der">
                            <span class="panel-badge mono"><span class="panel-punto"></span>{{ $e ? $etq[$e] : 'Sin dato' }}</span>
                            <span class="panel-rango mono">{{ $m['rango'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Derecha: qué hacer ahora --}}
        <aside class="panel-q" aria-label="Qué hacer ahora">
            <div class="panel-q-cab">
                <h2 class="panel-q-titulo">Qué hacer ahora</h2>
                <div role="status" class="panel-q-estado mono">
                    <span class="panel-q-resumen">{{ $resumenTxt }}</span>
                    <span class="panel-enlace" data-estado="{{ $conEnlace ? 'ok' : 'crit' }}">
                        <span class="icono">{{ $conEnlace ? 'satellite_alt' : 'link_off' }}</span>{{ $conEnlace ? 'ENLACE ACTIVO' : 'SIN ENLACE' }}
                    </span>
                    <span class="panel-q-retardo">{{ $conEnlace ? 'Respuesta de tierra en ~'.$retardo.' min' : 'Los avisos quedan en cola hasta recuperar el enlace' }}</span>
                    <button type="button" class="panel-enlace-demo" wire:click="alternarEnlace" title="Alternar enlace con tierra (demo)" aria-label="Alternar enlace con tierra (demo)">
                        <span class="icono">swap_horiz</span>
                    </button>
                </div>
            </div>

            @if ($cargando)
                <div role="status" aria-live="polite" class="panel-cargando">
                    <span class="panel-cargando-t mono"><span class="icono">hourglass_top</span>CARGANDO SUGERENCIAS…</span>
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
                        <span class="panel-vacio-t">Todo en rango, sin acciones pendientes</span>
                        <span class="panel-vacio-s">Los {{ $nTotal }} indicadores están dentro de tu rango personal.</span>
                    </div>
                </div>
            @else
                <div class="panel-pista {{ $seleccionado ? 'is-sel' : '' }}">
                    <span class="icono">{{ $seleccionado ? 'accessibility_new' : 'touch_app' }}</span>
                    <span class="panel-pista-t">{{ $seleccionado ? 'En la figura: '.$seleccionado['etiqueta'].' · '.$seleccionado['zona'] : 'Selecciona una tarjeta o un indicador para ver en la figura la zona relacionada.' }}</span>
                    @if ($seleccionado)
                        <button type="button" class="panel-btn-quitar" wire:click="quitarSeleccion">Quitar</button>
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
                        </button>

                        <div class="panel-tend">
                            <div class="panel-tend-cab mono"><span>TENDENCIA 7 DÍAS</span><span class="panel-tend-base"><span class="panel-tend-linea"></span>TU BASE {{ $c['base'] }}</span></div>
                            <svg role="img" class="panel-tend-svg" viewBox="0 0 280 56" preserveAspectRatio="none"
                                 aria-label="Tendencia de 7 días de {{ $c['etiqueta'] }}, del valor base al actual {{ $c['valor'] }} {{ $c['unidad'] }}">
                                <line x1="0" x2="280" y1="{{ $c['svg']['baseY'] }}" y2="{{ $c['svg']['baseY'] }}" class="panel-tend-base-l" stroke-width="1.5" stroke-dasharray="5 4" vector-effect="non-scaling-stroke"></line>
                                <polyline points="{{ $c['svg']['pts'] }}" fill="none" class="panel-tend-pol" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>
                            </svg>
                            <div class="panel-tend-pie mono"><span>HACE 6 D</span><span>HOY</span></div>
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
                            <button type="button" class="panel-btn" wire:click="repetirMedicion('{{ $c['clave'] }}')"><span class="icono">replay</span>Repetir medición</button>
                            <button type="button" class="panel-btn" disabled title="Próximamente"><span class="icono">menu_book</span>Ver guía</button>
                            <button type="button" class="panel-btn panel-btn--aviso {{ $crit ? 'is-crit' : '' }}" wire:click="avisarATierra('{{ $c['clave'] }}')" @disabled($c['avisado'])>
                                <span class="icono">{{ $iconoEnvio($c['avisado']) }}</span>{{ $c['avisado'] ? ($conEnlace ? 'Aviso enviado a tierra' : 'En cola · se enviará al recuperar enlace') : ($conEnlace ? 'Avisar a tierra' : 'Encolar aviso a tierra') }}
                            </button>
                        </div>
                    </article>
                @endforeach
            @endif

            @unless ($cargando)
                <section class="panel-chequeo" aria-label="Autochequeo guiado">
                    <div class="panel-chequeo-cab">
                        <span class="icono panel-chequeo-ic">fact_check</span>
                        <span class="panel-chequeo-t">Autochequeo guiado</span>
                        <span class="panel-chequeo-paso mono">{{ $chequeo['hecho'] ? 'COMPLETADO' : 'PREGUNTA '.($chequeo['n'] + 1).' DE '.count($preguntas) }}</span>
                    </div>
                    <div role="progressbar" class="panel-progreso" aria-valuemin="0" aria-valuemax="{{ count($preguntas) }}" aria-valuenow="{{ $chequeo['n'] }}" aria-label="Progreso del autochequeo">
                        <div class="panel-progreso-barra" style="width: {{ round($chequeo['n'] / count($preguntas) * 100) }}%"></div>
                    </div>

                    @unless ($chequeo['hecho'])
                        <div class="panel-pregunta">
                            <span class="panel-pregunta-t">{{ $preguntas[min($chequeo['n'], count($preguntas) - 1)] }}</span>
                            <div class="panel-si-no">
                                <button type="button" class="panel-btn panel-btn--sn" wire:click="responder('y')">Sí</button>
                                <button type="button" class="panel-btn panel-btn--sn" wire:click="responder('n')">No</button>
                            </div>
                            @if ($chequeo['n'] > 0)
                                <button type="button" class="panel-btn-atras" wire:click="preguntaAnterior"><span class="icono">arrow_back</span>Pregunta anterior</button>
                            @endif
                        </div>
                    @else
                        @php($r = $chequeo['res'])
                        <div role="status" class="panel-resultado" data-estado="{{ $r['k'] }}">
                            <div class="panel-resultado-cab">
                                <span class="icono panel-resultado-ic">{{ $r['ic'] }}</span>
                                <div class="panel-resultado-tt"><span class="panel-resultado-et mono">RESULTADO</span><span class="panel-resultado-t">{{ $r['titulo'] }}</span></div>
                            </div>
                            <span class="panel-resultado-txt">{{ $r['texto'] }}</span>
                            <div class="panel-botones">
                                @if ($chequeo['rk'] === 'red')
                                    <button type="button" class="panel-btn panel-btn--aviso is-crit" wire:click="avisarATierra('chk')" @disabled($chequeo['avisado'])>
                                        <span class="icono">{{ $iconoEnvio($chequeo['avisado']) }}</span>{{ $chequeo['avisado'] ? ($conEnlace ? 'Aviso enviado a tierra' : 'En cola · se enviará al recuperar enlace') : ($conEnlace ? 'Avisar a tierra ahora' : 'Encolar aviso a tierra') }}
                                    </button>
                                @endif
                                <button type="button" class="panel-btn" wire:click="reiniciarChequeo"><span class="icono">restart_alt</span>Repetir autochequeo</button>
                            </div>
                        </div>
                    @endunless
                </section>
            @endunless

            <p role="note" class="panel-nota"><span class="icono">info</span>Esta herramienta orienta, no diagnostica. Ante cualquier duda, contacta a tierra.</p>
        </aside>
    </main>
    @endif
</div>
