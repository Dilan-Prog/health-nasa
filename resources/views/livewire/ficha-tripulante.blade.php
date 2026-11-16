<div class="ficha">
    <x-encabezado titulo-a="Crew" :titulo-b="'Record of '.$a->nombre" />

    <main class="ficha-main" id="ficha-contenido">
        <div class="ficha-acciones">
            <a class="ficha-btn" href="{{ route('tripulacion') }}"><span class="icono" aria-hidden="true">arrow_back</span>Crew</a>
            {{-- Record editing is not built yet: inactive button, no link. --}}
            <button type="button" class="ficha-btn ficha-btn--primario" disabled aria-disabled="true">
                <span class="icono" aria-hidden="true">edit</span>Edit record
                <span class="mono ficha-pronto">COMING SOON</span>
            </button>
        </div>

        <section class="ficha-cabecera" aria-label="Key details">
            <div class="ficha-quien">
                <span class="ficha-avatar" aria-hidden="true">{{ $iniciales }}</span>
                <div class="ficha-quien-txt">
                    <h2 class="ficha-nombre">{{ $a->nombre }}</h2>
                    @if ($subtitulo !== '')
                        <p class="ficha-sub">{{ $subtitulo }}</p>
                    @endif
                    @if (count($pildoras))
                        <ul class="ficha-pildoras" aria-label="Fitness and medical alerts">
                            @foreach ($pildoras as $p)
                                <li class="mono ficha-pildora" data-tono="{{ $p['tono'] }}"><span class="icono" aria-hidden="true">{{ $p['ic'] }}</span>{{ $p['l'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            <div class="ficha-compl">
                <div class="ficha-compl-fila">
                    <span class="mono ficha-compl-et" id="ficha-pct">RECORD COMPLETE</span>
                    <span class="mono ficha-compl-val">{{ $comp['porcentaje'] }} %</span>
                </div>
                <div class="ficha-barra" role="progressbar" aria-labelledby="ficha-pct" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $comp['porcentaje'] }}">
                    <span style="width: {{ $comp['porcentaje'] }}%"></span>
                </div>
                <p class="ficha-compl-falta">
                    @if (count($comp['faltantes']))
                        Missing: {{ implode(', ', $comp['faltantes']) }}.
                    @else
                        Record complete.
                    @endif
                </p>
            </div>
        </section>

        @if ($bateriaBaja !== null)
            <p class="ficha-aviso" role="note"><span class="icono" aria-hidden="true">battery_alert</span><span><strong>Low battery:</strong> the watch reported {{ $bateriaBaja }} %@if ($a->bateria_en) ({{ $a->bateria_en->diffForHumans() }})@endif.</span></p>
        @endif

        @if ($restriccion)
            <p class="ficha-aviso" role="note"><span class="icono" aria-hidden="true">warning</span><span><strong>Flight restriction:</strong> {{ $restriccion }}</span></p>
        @endif

        <section class="ficha-tarjeta" aria-labelledby="ficha-lb">
            <div class="ficha-tarjeta-cab">
                <h2 class="ficha-h2" id="ficha-lb">Personal baseline</h2>
                <p class="ficha-nota">Starting value compared with the latest value measured on board.</p>
            </div>
            <div class="ficha-tabla-wrap" tabindex="0" role="region" aria-labelledby="ficha-lb">
                <table class="ficha-tabla">
                    <caption class="ficha-solo-lectores">Personal baseline compared with the latest measured value, by indicator</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="mono">INDICATOR</th>
                            <th scope="col" class="mono">BASELINE</th>
                            <th scope="col" class="mono">LATEST</th>
                            <th scope="col" class="mono">DIFFERENCE</th>
                            <th scope="col" class="mono">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linea as $r)
                            <tr wire:key="lb-{{ $r['clave'] }}">
                                <th scope="row" class="ficha-ind"><span class="icono" aria-hidden="true">{{ $r['ic'] }}</span>{{ $r['label'] }}</th>
                                <td class="mono {{ $r['base'] === null ? 'ficha-vacio' : '' }}">
                                    @if ($r['base'] !== null) {{ $r['base'] }} <span class="ficha-unidad">{{ $r['unit'] }}</span> @else No data @endif
                                </td>
                                <td class="mono ficha-ultimo {{ $r['ultimo'] === null ? 'ficha-vacio' : '' }}">
                                    @if ($r['ultimo'] !== null) {{ $r['ultimo'] }} @else Not measured @endif
                                </td>
                                <td class="mono ficha-delta">{{ $r['delta'] ?? '—' }}</td>
                                <td><span class="mono ficha-pildora ficha-pildora--s" data-tono="{{ $r['estado'] }}"><span class="icono" aria-hidden="true">{{ $r['estado_ic'] }}</span>{{ $r['estado_txt'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ficha-tarjeta" aria-labelledby="ficha-lbp">
            <div class="ficha-tarjeta-cab">
                <h2 class="ficha-h2" id="ficha-lbp">Pre-flight baseline</h2>
                <p class="ficha-nota">Starting value measured before the mission (default window: L-180 to L-30 days relative to the start). If a metric is left empty, the record value is used when available. The numbers are examples, not validated.</p>
            </div>
            <form class="ficha-form" wire:submit="guardarLineaBase" novalidate>
                @foreach (config('linea_base.metricas') as $k => $cfg)
                    <fieldset class="ficha-campos" wire:key="lbp-{{ $k }}">
                        <legend class="ficha-legend">{{ $cfg['label'] }}</legend>
                        <label class="ficha-campo"><span class="mono">VALUE</span>
                            <input type="text" inputmode="decimal" wire:model="lb.{{ $k }}.valor" aria-invalid="{{ $errors->has("lb.$k.valor") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">UNIT</span>
                            <input type="text" maxlength="20" wire:model="lb.{{ $k }}.unidad" aria-invalid="{{ $errors->has("lb.$k.unidad") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">WINDOW FROM</span>
                            <input type="date" wire:model="lb.{{ $k }}.desde" aria-invalid="{{ $errors->has("lb.$k.desde") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">WINDOW TO</span>
                            <input type="date" wire:model="lb.{{ $k }}.hasta" aria-invalid="{{ $errors->has("lb.$k.hasta") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo ficha-campo--ancho"><span class="mono">SOURCE</span>
                            <input type="text" maxlength="120" wire:model="lb.{{ $k }}.fuente" aria-invalid="{{ $errors->has("lb.$k.fuente") ? 'true' : 'false' }}">
                        </label>
                        @foreach (['valor', 'unidad', 'desde', 'hasta', 'fuente'] as $campo)
                            @error("lb.$k.$campo") <span class="ficha-error" role="alert">{{ $message }}</span> @enderror
                        @endforeach
                        @if ($lbMetricas[$k]['actual'] !== null)
                            <p class="ficha-desv mono">Current: {{ $lbMetricas[$k]['actual'] }}
                                @if ($lbMetricas[$k]['desv'] !== null) · Deviation: <strong>{{ $lbMetricas[$k]['desv'] }}</strong> from baseline @else · No baseline to compare @endif
                            </p>
                        @endif
                    </fieldset>
                @endforeach
                <div class="ficha-form-pie">
                    <button type="submit" class="ficha-btn ficha-btn--primario"><span class="icono" aria-hidden="true">save</span>Save baseline</button>
                    @if ($okLb)<span class="ficha-ok" role="status">{{ $okLb }}</span>@endif
                </div>
            </form>
        </section>

        <section class="ficha-tarjeta" aria-labelledby="ficha-asc">
            <div class="ficha-tarjeta-cab">
                <h2 class="ficha-h2" id="ficha-asc">ASCAN record · first annual exam</h2>
                <p class="ficha-nota">Recording only, no interpretation. Enter the result, date, and status of each test; the app does not calculate or assess them.</p>
            </div>
            <form class="ficha-form" wire:submit="guardarAscan" novalidate>
                @foreach ($ascGrupos as $gi => $g)
                    <fieldset class="ficha-campos ficha-campos--asc" wire:key="asc-g-{{ $gi }}">
                        <legend class="ficha-legend">{{ $g['titulo'] }}</legend>
                        @foreach ($g['pruebas'] as $k => $nombre)
                            <div class="ficha-asc-fila" wire:key="asc-{{ $k }}">
                                <span class="ficha-asc-nombre">{{ $nombre }}</span>
                                <label class="ficha-campo"><span class="mono">RESULT</span>
                                    <input type="text" maxlength="255" wire:model="asc.{{ $k }}.resultado" aria-label="Result: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.resultado") ? 'true' : 'false' }}">
                                </label>
                                <label class="ficha-campo"><span class="mono">DATE</span>
                                    <input type="date" wire:model="asc.{{ $k }}.fecha" aria-label="Date: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.fecha") ? 'true' : 'false' }}">
                                </label>
                                <label class="ficha-campo"><span class="mono">STATUS</span>
                                    <select wire:model="asc.{{ $k }}.estado" aria-label="Status: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.estado") ? 'true' : 'false' }}">
                                        @foreach ($ascEstados as $v => $et)<option value="{{ $v }}">{{ $et }}</option>@endforeach
                                    </select>
                                </label>
                                @foreach (['resultado', 'fecha', 'estado'] as $campo)
                                    @error("asc.$k.$campo") <span class="ficha-error" role="alert">{{ $message }}</span> @enderror
                                @endforeach
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
                <div class="ficha-form-pie">
                    <button type="submit" class="ficha-btn ficha-btn--primario"><span class="icono" aria-hidden="true">save</span>Save ASCAN record</button>
                    @if ($okAsc)<span class="ficha-ok" role="status">{{ $okAsc }}</span>@endif
                </div>
            </form>
        </section>

        <div class="ficha-rejilla">
            <section class="ficha-tarjeta" aria-labelledby="ficha-bio">
                <h2 class="ficha-h2 ficha-h2--ic" id="ficha-bio"><span class="icono ficha-ic" aria-hidden="true">monitor_heart</span>Biometrics</h2>
                <dl class="ficha-bio">
                    @foreach ($bio as $i)
                        <div>
                            <dt class="mono">{{ $i['l'] }}</dt>
                            <dd class="mono {{ $i['vacio'] ? 'ficha-vacio' : '' }}" @if ($i['tono']) data-tono="{{ $i['tono'] }}" @endif>{{ $i['v'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @foreach ($listas as $l)
                <section class="ficha-tarjeta" aria-labelledby="ficha-{{ $l['id'] }}" wire:key="lista-{{ $l['id'] }}">
                    <h2 class="ficha-h2 ficha-h2--ic" id="ficha-{{ $l['id'] }}">
                        <span class="icono ficha-ic" aria-hidden="true">{{ $l['ic'] }}</span>{{ $l['titulo'] }}
                        <span class="mono ficha-cuenta">{{ count($l['filas']) }}</span>
                    </h2>
                    @if ($l['aviso'])
                        <p class="ficha-info" role="note"><span class="icono" aria-hidden="true">info</span>{{ $l['aviso'] }}</p>
                    @endif
                    @if (count($l['filas']) === 0)
                        <p class="ficha-vacio-txt">{{ $l['vacio'] }}</p>
                    @else
                        <ul class="ficha-lista">
                            @foreach ($l['filas'] as $f)
                                <li class="ficha-item {{ $f['resalta'] ? 'ficha-item--crit' : '' }}">
                                    <span class="ficha-item-txt">
                                        <span class="ficha-item-main">{{ $f['main'] }}</span>
                                        @if (filled($f['sub']))<span class="ficha-item-sub">{{ $f['sub'] }}</span>@endif
                                    </span>
                                    @if ($f['pill'])
                                        <span class="mono ficha-pildora ficha-pildora--s" data-tono="{{ $f['pill']['tono'] }}"><span class="icono" aria-hidden="true">{{ $f['pill']['ic'] }}</span>{{ $f['pill']['l'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach

            <section class="ficha-tarjeta ficha-dispositivo" aria-labelledby="ficha-disp">
                {{-- PENDING: accurate measurements require real measurement devices (watch, blood pressure monitor, glucometer, etc.). For now measurements are simulated (SimuladorMediciones) or entered by hand. This block will be enabled when real devices are integrated. --}}
                <h2 class="ficha-h2 ficha-h2--ic" id="ficha-disp"><span class="icono ficha-ic" aria-hidden="true">watch</span>Link device <span class="mono ficha-pronto">COMING SOON</span></h2>
                <p class="ficha-nota">You will be able to link the crew member's watch when this feature is available.</p>
                <button type="button" class="ficha-btn" disabled aria-disabled="true"><span class="icono" aria-hidden="true">watch</span>Link device</button>
            </section>
        </div>

        <p class="ficha-aviso-pie" role="note"><span class="icono" aria-hidden="true">info</span>Guidance only, not a diagnosis. Consult the Flight Surgeon if in doubt.</p>
    </main>
</div>
