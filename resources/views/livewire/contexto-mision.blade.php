@php
    $d = $d ?? null;
    $retardoTxt = fn ($m) => $m === null ? '—' : ($m == 0 ? 'No delay' : $m.' min');
@endphp
<div class="mision">
    <x-encabezado titulo-a="Mission" :titulo-b="$mision ? $mision->nombre : 'No mission'" />

    <main class="mision-main" id="mision-contenido">
        @if (! $d)
            <div class="mision-vacio" role="status">
                <span class="icono" aria-hidden="true">rocket_launch</span>
                <strong>There is no active mission</strong>
                <span>When a mission with a crew is assigned, its context will appear here.</span>
            </div>
        @else
            <div class="mision-cabecera">
                <span class="mision-kicker mono">MISSION CONTEXT</span>
                <h2 class="mision-titulo">{{ $d['nombre'] }} · {{ $d['entorno_txt'] }}</h2>
                <p class="mision-sub">
                    @if ($d['dia'] && $d['total'])
                        Day {{ $d['dia'] }} of {{ $d['total'] }} · {{ $d['quedan'] === 0 ? 'last day of mission' : ($d['quedan'] === 1 ? '1 day left' : $d['quedan'].' days left') }}.
                    @else
                        The dates for this mission are not defined yet.
                    @endif
                </p>
            </div>

            <div class="mision-stats" role="list" aria-label="Mission data">
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">MISSION DAY</span><span class="mision-stat-v mono">{{ $d['dia'] ?? '—' }} / {{ $d['total'] ?? '—' }}</span></div>
                @if ($d['es_estacion'])
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ORBIT</span><span class="mision-stat-v mono">{{ $d['orbita_km'] ? $d['orbita_km'].' km' : '—' }}</span></div>
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">SPEED</span><span class="mision-stat-v mono">{{ $d['velocidad'] ? $d['velocidad'].' km/s' : '—' }}</span></div>
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ORBITS/DAY</span><span class="mision-stat-v mono">{{ $d['orbitas_dia'] ?? '—' }}</span></div>
                @else
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ENVIRONMENT</span><span class="mision-stat-v mono mision-stat-v--txt">{{ $d['entorno_txt'] }}</span></div>
                @endif
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">CREW</span><span class="mision-stat-v mono">{{ $d['n_tripulantes'] }} aboard</span></div>
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">GROUND DELAY</span><span class="mision-stat-v mono">{{ $retardoTxt($d['retardo']) }}</span></div>
                <div class="mision-stat" role="listitem" data-estado="{{ $d['enlace'] ? 'ok' : 'warn' }}">
                    <span class="mision-stat-et mono">GROUND LINK</span>
                    <span class="mision-stat-v mono mision-stat-v--estado"><span class="icono" aria-hidden="true">{{ $d['enlace'] ? 'cloud_done' : 'cloud_off' }}</span>{{ $d['enlace'] ? 'Active' : 'No link' }}</span>
                </div>
                @if ($d['dosis'])
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">DOSE {{ $d['nombre_corto'] }}</span><span class="mision-stat-v mono">{{ $d['dosis']['txt'] }} mSv</span></div>
                @endif
            </div>

            @if ($d['fases'])
                <section class="mision-panel" aria-labelledby="mision-tl-t">
                    <h3 class="mision-kicker mono" id="mision-tl-t">TIMELINE</h3>
                    <div class="mision-tl-marco">
                        <div class="mision-tl" role="img" aria-label="Timeline: day {{ $d['dia'] ?? '—' }} of {{ $d['total'] }}. Milestones: {{ collect($d['fases'])->map(fn ($f) => $f['nombre'].' day '.$f['dia'])->implode(', ') }}.">
                            <div class="mision-tl-linea"></div>
                            <div class="mision-tl-avance" style="width: {{ $d['avance'] }}%"></div>
                            @foreach ($d['fases'] as $f)
                                <div class="mision-hito {{ $f['hecha'] ? 'is-hecho' : '' }}" style="left: {{ $f['pct'] }}%">
                                    <span class="mision-hito-punto" aria-hidden="true"></span>
                                    <span class="mision-hito-n">{{ $f['nombre'] }}</span>
                                    <span class="mision-hito-d mono">D{{ $f['dia'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <div class="mision-cuerpo">
                <section class="mision-panel" aria-labelledby="mision-dosis-t">
                    <h3 class="mision-kicker mono" id="mision-dosis-t">CUMULATIVE DOSE VS. MISSION LIMIT</h3>
                    @if ($d['dosis'])
                        <div class="mision-dosis-cifras mono"><span>{{ $d['dosis']['txt'] }} mSv</span><span>{{ rtrim(rtrim(number_format($d['limite'], 1, '.', ''), '0'), '.') }} mSv</span></div>
                        <div class="mision-barra" data-estado="{{ $d['dosis']['estado'] }}" role="progressbar" aria-label="Cumulative dose against mission limit" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($d['dosis']['pct']) }}">
                            <span style="width: {{ $d['dosis']['pct'] }}%"></span>
                        </div>
                        <p class="mision-nota">You have reached {{ number_format($d['dosis']['pct'], 0) }} % of the mission reference limit. This is context data: your team and mission control review the dose with you.</p>
                    @else
                        <p class="mision-nota">There is no cumulative dose measurement yet{{ $d['nombre_corto'] ? ' for '.ucfirst(mb_strtolower($d['nombre_corto'])) : '' }}.</p>
                    @endif
                </section>

                <section class="mision-panel" aria-labelledby="mision-ev-t">
                    <h3 class="mision-kicker mono" id="mision-ev-t">UPCOMING EVENTS</h3>
                    @if ($d['eventos'])
                        <ul class="mision-eventos">
                            @foreach ($d['eventos'] as $e)
                                <li><span class="icono" aria-hidden="true">{{ $e['icono'] }}</span><span class="mision-ev-d mono">D{{ $e['dia'] }}</span><span>{{ $e['titulo'] }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mision-nota">No events scheduled.</p>
                    @endif
                </section>
            </div>

            <section class="mision-panel" aria-labelledby="mision-nivel-t">
                <h3 class="mision-kicker mono" id="mision-nivel-t">CARE LEVEL AND PHASE</h3>
                <div class="mision-form">
                    <label class="mision-campo" for="mision-care">
                        <span class="mision-campo-et">Medical care level</span>
                        <select id="mision-care" wire:model.live="careLevel" aria-describedby="mision-care-ayuda">
                            @foreach (\App\Models\Mision::CARE_LEVELS as $n => $et)
                                <option value="{{ $n }}">{{ $et }}</option>
                            @endforeach
                        </select>
                        @error('careLevel')<span class="mision-error" role="alert">{{ $message }}</span>@enderror
                    </label>
                    <label class="mision-campo" for="mision-fase">
                        <span class="mision-campo-et">Mission phase</span>
                        <select id="mision-fase" wire:model.live="missionPhase">
                            @foreach (\App\Models\Mision::FASES as $k => $et)
                                <option value="{{ $k }}">{{ $et }}</option>
                            @endforeach
                        </select>
                        @error('missionPhase')<span class="mision-error" role="alert">{{ $message }}</span>@enderror
                    </label>
                </div>
                <p class="mision-nota" id="mision-care-ayuda">Levels 4 and 5 use local autonomous triage: immediate guidance on board and an asynchronous message to the ground, without video consultation. This is a mission choice and does not change when the environment changes.</p>
                @if ($guardado)<p class="mision-nota" role="status">{{ $guardado }}</p>@endif
            </section>

            <x-peligros-riesgos />
        @endif
    </main>
</div>
