<div class="al">
    <x-encabezado titulo-a="Alertas" :titulo-b="$astronauta ? 'Tripulante '.$astronauta->nombre : 'Sin tripulante'" />

    <main class="al-main" id="alertas-contenido">
        {{-- Resumen --}}
        <section class="al-card al-resumen" aria-label="Resumen de alertas">
            <div class="al-conteos">
                @foreach ($conteos as $c)
                    <span class="al-conteo mono" @if ($c['k']) data-estado="{{ $c['k'] }}" @endif>
                        <span class="icono" aria-hidden="true">{{ $c['ic'] }}</span>{{ $c['n'] }} {{ $c['l'] }}
                    </span>
                @endforeach
            </div>
            <div class="al-vistas" role="tablist" aria-label="Vista de alertas">
                @foreach (['a' => ['Bandeja', 'inbox'], 'b' => ['Tablero', 'view_kanban'], 'c' => ['Escalera', 'stairs']] as $k => [$l, $ic])
                    <button type="button" role="tab" class="al-vista" aria-selected="{{ $vista === $k ? 'true' : 'false' }}" wire:click="$set('vista','{{ $k }}')">
                        <span class="icono" aria-hidden="true">{{ $ic }}</span>{{ $l }}
                    </button>
                @endforeach
            </div>
            <span class="al-enlace mono" data-estado="{{ $enlace ? 'ok' : 'warn' }}">
                <span class="icono" aria-hidden="true">satellite_alt</span>{{ $enlace ? 'ENLACE ACTIVO · RESPUESTA ~1 MIN' : 'SIN ENLACE · LOS AVISOS ESPERAN' }}
            </span>
        </section>

        @if ($vista === 'a')
            <div class="al-dos">
                <section class="al-card al-bandeja" aria-label="Alertas">
                    <div class="al-bandeja-cab">
                        <h2 class="al-h2">Bandeja de alertas</h2>
                        <div class="al-filtros" role="radiogroup" aria-label="Filtro">
                            @foreach (['activas' => 'Activas', 'graves' => 'Solo graves', 'resueltas' => 'Resueltas'] as $k => $l)
                                <button type="button" role="radio" class="al-filtro" aria-checked="{{ $filtro === $k ? 'true' : 'false' }}" wire:click="$set('filtro','{{ $k }}')">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>
                    @forelse ($lista as $x)
                        <button type="button" class="al-fila" data-estado="{{ $x['tono'] }}" aria-pressed="{{ $det && $det['clave'] === $x['clave'] ? 'true' : 'false' }}"
                                wire:click="seleccionar('{{ $x['clave'] }}')" wire:key="fila-{{ $x['clave'] }}">
                            <span class="icono al-fila-ic" aria-hidden="true">{{ $x['ic'] }}</span>
                            <span class="al-fila-t">
                                <span class="al-fila-ind">{{ $x['ind'] }} <span class="mono al-val">{{ $x['v'] }}</span></span>
                                <span class="mono al-meta">
                                    <span class="al-meta-i"><span class="icono" aria-hidden="true">{{ $x['estIc'] }}</span>{{ $x['estL'] }}</span>
                                    <span class="al-meta-i"><span class="icono" aria-hidden="true">{{ $x['lvlIc'] }}</span>{{ $x['lvlL'] }}</span>
                                    <span>{{ $x['ago'] }}</span>
                                </span>
                            </span>
                            <span class="al-sev mono"><span class="icono" aria-hidden="true">{{ $x['sevIc'] }}</span>{{ $x['sevL'] }}</span>
                        </button>
                    @empty
                        <div class="al-vacio">Sin alertas en esta vista</div>
                    @endforelse
                </section>

                @if ($det)
                    <section class="al-card al-detalle" data-estado="{{ $det['tono'] }}" aria-label="Detalle de la alerta">
                        <div class="al-det-cab">
                            <span class="icono al-det-ic" aria-hidden="true">{{ $det['ic'] }}</span>
                            <div class="al-det-t">
                                <span class="al-det-ind">{{ $det['ind'] }}</span>
                                <span class="mono al-det-v">{{ $det['v'] }}</span>
                                <span class="mono al-meta">DETECTADA {{ $det['t'] }} UTC</span>
                            </div>
                            <span class="al-sev al-sev--lg mono"><span class="icono" aria-hidden="true">{{ $det['sevIc'] }}</span>{{ $det['sevL'] }}</span>
                        </div>
                        <p class="al-why">{{ $det['why'] }}</p>
                        <div class="al-pasos" role="group" aria-label="Nivel de escalamiento">
                            @foreach ($niveles as $i => [$l, $ic])
                                @php($on = $det['abierta'] && $i <= $det['lvl'])
                                <div class="al-paso {{ $on ? 'is-on' : '' }}" @if ($i === $det['lvl']) aria-current="true" @endif>
                                    @if ($i < 2)<span class="al-paso-linea {{ $det['abierta'] && $i < $det['lvl'] ? 'is-on' : '' }}"></span>@endif
                                    <span class="icono al-paso-c" aria-hidden="true">{{ $ic }}</span>
                                    <span class="al-paso-l">{{ $l }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="al-hacer"><strong>Qué hacer:</strong> {{ $det['act'] }}</div>
                        <div class="al-acciones">
                            @if ($det['puedeAck'])
                                <button type="button" class="al-btn al-btn--pri" wire:click="reconocer('{{ $det['clave'] }}')"><span class="icono" aria-hidden="true">visibility</span>Reconocer</button>
                            @endif
                            @if ($det['puedeEsc'])
                                <button type="button" class="al-btn al-btn--crit" wire:click="escalar('{{ $det['clave'] }}')"><span class="icono" aria-hidden="true">trending_up</span>{{ $det['escL'] }}</button>
                            @endif
                            @if ($det['abierta'])
                                <button type="button" class="al-btn" wire:click="resolver('{{ $det['clave'] }}')"><span class="icono" aria-hidden="true">check_circle</span>Marcar resuelta</button>
                            @else
                                <button type="button" class="al-btn" wire:click="reabrir('{{ $det['clave'] }}')"><span class="icono" aria-hidden="true">replay</span>Reabrir</button>
                            @endif
                        </div>
                        <div class="al-log">
                            <span class="mono al-log-et">ACTIVIDAD</span>
                            @forelse ($bitacora as $l)
                                <div class="al-log-f" data-estado="{{ $l['k'] }}"><span class="mono al-meta">{{ $l['t'] }}</span><span class="icono" aria-hidden="true">{{ $l['ic'] }}</span><span>{{ $l['msg'] }}</span></div>
                            @empty
                                <div class="al-log-f"><span></span><span></span><span>Sin actividad</span></div>
                            @endforelse
                        </div>
                    </section>
                @else
                    <section class="al-card al-detalle"><div class="al-vacio">No hay alertas para este tripulante</div></section>
                @endif
            </div>
        @elseif ($vista === 'b')
            <div class="al-tablero">
                @foreach ($columnas as $col)
                    <section class="al-col" aria-label="{{ $col['l'] }}" wire:key="col-{{ $loop->index }}">
                        <h2 class="al-col-t"><span class="icono" aria-hidden="true">{{ $col['ic'] }}</span><span class="al-col-n1">{{ $col['l'] }}</span><span class="mono al-col-n">{{ count($col['items']) }}</span></h2>
                        @forelse ($col['items'] as $x)
                            <article class="al-tarjeta" data-estado="{{ $x['tono'] }}" aria-label="{{ $x['ind'] }}" wire:key="t-{{ $x['clave'] }}">
                                <div class="al-tarjeta-cab">
                                    <span class="icono al-tarjeta-ic" aria-hidden="true">{{ $x['ic'] }}</span>
                                    <span class="al-tarjeta-ind">{{ $x['ind'] }}</span>
                                    <span class="al-sev al-sev--sm mono"><span class="icono" aria-hidden="true">{{ $x['sevIc'] }}</span>{{ $x['sevL'] }}</span>
                                </div>
                                <span class="mono al-tarjeta-v">{{ $x['v'] }}</span>
                                <span class="al-tarjeta-why">{{ $x['why'] }}</span>
                                <span class="mono al-meta"><span class="al-meta-i"><span class="icono" aria-hidden="true">{{ $x['lvlIc'] }}</span>{{ $x['lvlL'] }}</span><span>{{ $x['ago'] }}</span></span>
                                <div class="al-acciones al-acciones--sm">
                                    @if ($x['puedeAck'])<button type="button" class="al-btn al-btn--pri al-btn--sm" wire:click="reconocer('{{ $x['clave'] }}')">Reconocer</button>@endif
                                    @if ($x['puedeEsc'])<button type="button" class="al-btn al-btn--crit-o al-btn--sm" title="{{ $x['escL'] }}" aria-label="{{ $x['escL'] }}" wire:click="escalar('{{ $x['clave'] }}')">Escalar</button>@endif
                                    @if ($x['abierta'])<button type="button" class="al-btn al-btn--sm" wire:click="resolver('{{ $x['clave'] }}')">Resolver</button>
                                    @else<button type="button" class="al-btn al-btn--sm" wire:click="reabrir('{{ $x['clave'] }}')">Reabrir</button>@endif
                                </div>
                            </article>
                        @empty
                            <span class="al-nada">Nada aquí</span>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @else
            <div class="al-dos">
                <section class="al-escalera" aria-label="Escalamiento">
                    @foreach ($escalera as $n)
                        <div class="al-nivel" @if ($n['peor']) data-estado="{{ $n['peor'] }}" @endif wire:key="niv-{{ $loop->index }}">
                            <div class="al-nivel-cab">
                                <span class="icono al-nivel-ic" aria-hidden="true">{{ $n['ic'] }}</span>
                                <div class="al-nivel-t"><span class="al-nivel-l">{{ $n['l'] }}</span><span class="al-nivel-who">{{ $n['who'] }}</span></div>
                                <span class="mono al-nivel-n">{{ count($n['items']) }}</span>
                            </div>
                            @forelse ($n['items'] as $x)
                                <div class="al-nivel-fila" data-estado="{{ $x['tono'] }}" wire:key="nf-{{ $x['clave'] }}">
                                    <span class="icono al-fila-ic" aria-hidden="true">{{ $x['ic'] }}</span>
                                    <span class="al-nivel-fila-t"><span class="al-fila-ind">{{ $x['ind'] }} <span class="mono al-val">{{ $x['v'] }}</span></span><span class="mono al-meta">{{ $x['sevL'] }} · {{ $x['estL'] }} · {{ $x['ago'] }}</span></span>
                                    <div class="al-acciones al-acciones--sm">
                                        @if ($x['puedeAck'])<button type="button" class="al-btn al-btn--pri al-btn--sm" wire:click="reconocer('{{ $x['clave'] }}')">Reconocer</button>@endif
                                        @if ($x['puedeEsc'])<button type="button" class="al-btn al-btn--crit-o al-btn--sm" aria-label="{{ $x['escL'] }}" wire:click="escalar('{{ $x['clave'] }}')"><span class="icono" aria-hidden="true">arrow_upward</span>Subir nivel</button>@endif
                                        <button type="button" class="al-btn al-btn--sm" wire:click="resolver('{{ $x['clave'] }}')">Resolver</button>
                                    </div>
                                </div>
                            @empty
                                <span class="al-nivel-vacio">Sin alertas en este nivel</span>
                            @endforelse
                        </div>
                    @endforeach
                </section>
                <section class="al-card al-tiempo" aria-label="Línea de tiempo">
                    <h2 class="al-h2">Línea de tiempo</h2>
                    <ol class="al-tl">
                        @forelse ($bitacora as $l)
                            <li class="al-tl-i" data-estado="{{ $l['k'] }}">
                                <span class="al-tl-eje"><span class="al-tl-linea"></span><span class="icono al-tl-pto" aria-hidden="true">{{ $l['ic'] }}</span></span>
                                <span class="al-tl-t"><span class="mono al-meta">{{ $l['t'] }}</span><span>{{ $l['msg'] }}</span></span>
                            </li>
                        @empty
                            <li class="al-tl-i"><span></span><span class="al-tl-t"><span>Sin actividad</span></span></li>
                        @endforelse
                    </ol>
                </section>
            </div>
        @endif

        <p class="al-nota" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>
    </main>
</div>
