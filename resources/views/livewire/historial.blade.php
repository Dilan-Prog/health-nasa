@php
    $nombreTripulante = $astronauta?->nombre;
    $presets = \App\Services\Salud\DatosHistorial::PRESETS;
    $tipos = \App\Services\Salud\DatosHistorial::TIPOS;
@endphp
<div class="hist">
    <x-encabezado titulo-a="History" :titulo-b="$nombreTripulante ? 'Crew member '.$nombreTripulante : 'No crew member'" />

    <main class="hist-main" id="historial-contenido">
        @if (! $astronauta)
            <div class="hist-aviso" role="status">
                <span class="icono" aria-hidden="true">groups</span>
                <strong>No crew member selected</strong>
                <span>Choose a crew member in the selector to see their history.</span>
            </div>
        @elseif ($error || ! $d)
            <div class="hist-aviso hist-aviso--error" role="alert">
                <span class="icono" aria-hidden="true">error</span>
                <strong>Could not load the history</strong>
                <span>Reading the measurements failed. Your data has not been lost.</span>
                <button type="button" class="hist-btn" wire:click="reintentar"><span class="icono" aria-hidden="true">refresh</span>Retry</button>
            </div>
        @else
            @php $r = $d['rango']; @endphp
            <section class="hist-filtro" aria-label="Day filter">
                <div class="hist-filtro-grupo">
                    <span class="hist-et mono" id="hist-periodo-et">PERIOD</span>
                    <div class="hist-presets" role="radiogroup" aria-labelledby="hist-periodo-et">
                        @foreach ($presets as [$pid, $plabel])
                            <button type="button" role="radio" class="hist-preset" aria-checked="{{ $preset === $pid ? 'true' : 'false' }}"
                                    wire:key="preset-{{ $pid }}" wire:click="elegirPreset('{{ $pid }}')">{{ $plabel }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($preset === 'custom')
                    <div class="hist-rango">
                        <label class="hist-campo" for="h-desde">From day
                            <input id="h-desde" class="hist-input mono" type="number" min="1" max="{{ $d['total'] }}" inputmode="numeric"
                                   wire:model.live.debounce.500ms="desde" aria-invalid="{{ $r['invalido'] ? 'true' : 'false' }}" aria-describedby="h-rango-msg">
                        </label>
                        <label class="hist-campo" for="h-hasta">To day
                            <input id="h-hasta" class="hist-input mono" type="number" min="1" max="{{ $d['total'] }}" inputmode="numeric"
                                   wire:model.live.debounce.500ms="hasta" aria-invalid="{{ $r['invalido'] ? 'true' : 'false' }}" aria-describedby="h-rango-msg">
                        </label>
                    </div>
                @endif

                <span id="h-rango-msg" class="hist-msg {{ $r['invalido'] ? 'is-invalido' : '' }}" role="status">
                    <span class="icono" aria-hidden="true">{{ $r['icono'] }}</span>{{ $r['mensaje'] }}
                </span>
            </section>

            <div class="hist-rejilla" wire:loading.class="is-ocupado" wire:target="preset,desde,hasta,cambiarIndicador,cambiarTipo,elegirPreset">
                @foreach ($d['graficas'] as $c)
                    @php
                        $n = $c['i'] + 1;
                        $est = $c['estado'] ?? 'ok';
                    @endphp
                    <section class="hist-grafica" data-estado="{{ $est }}" aria-label="{{ $c['aria'] }}" wire:key="grafica-{{ $c['i'] }}">
                        <div class="hist-cab">
                            <span class="icono hist-cab-ic" aria-hidden="true">{{ $c['ic'] }}</span>
                            <label class="hist-sel-wrap">
                                <span class="hist-solo-lector">Indicator for chart {{ $n }}</span>
                                <select class="hist-select" wire:change="cambiarIndicador({{ $c['i'] }}, $event.target.value)">
                                    @foreach ($d['opciones'] as $o)
                                        <option value="{{ $o['id'] }}" @selected($o['id'] === $c['id'])>{{ $o['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="hist-tipos" role="radiogroup" aria-label="Chart type {{ $n }}">
                                @foreach ($tipos as [$tid, $tic, $tlabel])
                                    <button type="button" role="radio" class="hist-tipo" aria-checked="{{ $c['tipo'] === $tid ? 'true' : 'false' }}"
                                            aria-label="{{ $tlabel }}" title="{{ $tlabel }}" wire:click="cambiarTipo({{ $c['i'] }}, '{{ $tid }}')">
                                        <span class="icono" aria-hidden="true">{{ $tic }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if (! empty($c['vacio']))
                            <div class="hist-vacio" role="status">
                                <span class="icono" aria-hidden="true">query_stats</span>
                                No {{ mb_strtolower($c['label']) }} measurements in this period.
                            </div>
                        @else
                            @if (! empty($c['sospechosos']))
                                <span class="hist-msg" role="status" data-estado="warn">
                                    <span class="icono" aria-hidden="true">sensors_off</span><strong>Suspect data</strong>&nbsp;({{ $c['sospechosos'] }}): {{ $c['sospechosoMotivo'] }}
                                </span>
                            @endif
                            <div class="hist-stats">
                                @foreach ($c['stats'] as $s)
                                    <div class="hist-stat">
                                        <span class="hist-stat-k mono">{{ $s['k'] }}</span>
                                        <span class="hist-stat-v mono" @if (! empty($s['estado'])) data-estado="{{ $s['estado'] }}" @endif>{{ $s['v'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="hist-plot">
                                <div class="hist-ejey mono"><span>{{ $c['yMax'] }}</span><span>{{ $c['yMin'] }}</span></div>
                                <svg class="hist-svg" role="img" aria-label="{{ $c['aria'] }}" viewBox="0 0 600 172" preserveAspectRatio="none">
                                    <rect class="hist-banda" x="0" width="600" y="{{ $c['bandY'] }}" height="{{ $c['bandH'] }}"></rect>
                                    @if (($c['baseY'] ?? -10) >= 0)
                                        <line class="hist-base" x1="0" x2="600" y1="{{ $c['baseY'] }}" y2="{{ $c['baseY'] }}" vector-effect="non-scaling-stroke"></line>
                                    @endif
                                    @if (! empty($c['areaD']))<path class="hist-area" d="{{ $c['areaD'] }}"></path>@endif
                                    @foreach ($c['bars'] ?? [] as $b)
                                        <rect class="hist-barra" data-estado="{{ $b['estado'] ?? $est }}" x="{{ $b['x'] }}" y="{{ $b['y'] }}" width="{{ $b['w'] }}" height="{{ $b['h'] }}"></rect>
                                    @endforeach
                                    @if (! empty($c['linePts']))<polyline class="hist-linea" points="{{ $c['linePts'] }}" vector-effect="non-scaling-stroke"></polyline>@endif
                                    @if (! empty($c['dotsD']))<path class="hist-puntos" d="{{ $c['dotsD'] }}" vector-effect="non-scaling-stroke"></path>@endif
                                </svg>
                                <span></span>
                                <div class="hist-ejex mono"><span>DAY {{ $r['desde'] }}</span><span>DAY {{ $r['hasta'] }}</span></div>
                            </div>
                        @endif

                        <div class="hist-leyenda">
                            <span class="hist-ley"><span class="hist-ley-banda"></span>Normal range {{ $c['rangeTxt'] }}</span>
                            <span class="hist-ley"><span class="hist-ley-base"></span>Your baseline</span>
                            <span class="hist-tendencia mono" data-estado="{{ $c['trendEstado'] ?? $est }}">
                                <span class="icono" aria-hidden="true">{{ $c['trendIc'] }}</span>{{ $c['trendTxt'] }}
                            </span>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <p class="hist-nota" role="note"><span class="icono" aria-hidden="true">info</span>Guidance only, not a diagnosis. If in doubt, consult the Flight Surgeon.</p>
    </main>
</div>
