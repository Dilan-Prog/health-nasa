<div class="ficha">
    <x-encabezado titulo-a="Tripulación" :titulo-b="'Expediente de '.$a->nombre" />

    <main class="ficha-main" id="ficha-contenido">
        <div class="ficha-acciones">
            <a class="ficha-btn" href="{{ route('tripulacion') }}"><span class="icono" aria-hidden="true">arrow_back</span>Tripulación</a>
            {{-- La edición del expediente aún no está construida: botón inactivo, sin enlace. --}}
            <button type="button" class="ficha-btn ficha-btn--primario" disabled aria-disabled="true">
                <span class="icono" aria-hidden="true">edit</span>Editar expediente
                <span class="mono ficha-pronto">PRÓXIMAMENTE</span>
            </button>
        </div>

        <section class="ficha-cabecera" aria-label="Datos principales">
            <div class="ficha-quien">
                <span class="ficha-avatar" aria-hidden="true">{{ $iniciales }}</span>
                <div class="ficha-quien-txt">
                    <h2 class="ficha-nombre">{{ $a->nombre }}</h2>
                    @if ($subtitulo !== '')
                        <p class="ficha-sub">{{ $subtitulo }}</p>
                    @endif
                    @if (count($pildoras))
                        <ul class="ficha-pildoras" aria-label="Aptitud y alertas médicas">
                            @foreach ($pildoras as $p)
                                <li class="mono ficha-pildora" data-tono="{{ $p['tono'] }}"><span class="icono" aria-hidden="true">{{ $p['ic'] }}</span>{{ $p['l'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            <div class="ficha-compl">
                <div class="ficha-compl-fila">
                    <span class="mono ficha-compl-et" id="ficha-pct">EXPEDIENTE COMPLETO</span>
                    <span class="mono ficha-compl-val">{{ $comp['porcentaje'] }} %</span>
                </div>
                <div class="ficha-barra" role="progressbar" aria-labelledby="ficha-pct" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $comp['porcentaje'] }}">
                    <span style="width: {{ $comp['porcentaje'] }}%"></span>
                </div>
                <p class="ficha-compl-falta">
                    @if (count($comp['faltantes']))
                        Falta: {{ implode(', ', $comp['faltantes']) }}.
                    @else
                        Expediente completo.
                    @endif
                </p>
            </div>
        </section>

        @if ($restriccion)
            <p class="ficha-aviso" role="note"><span class="icono" aria-hidden="true">warning</span><span><strong>Restricción de vuelo:</strong> {{ $restriccion }}</span></p>
        @endif

        <section class="ficha-tarjeta" aria-labelledby="ficha-lb">
            <div class="ficha-tarjeta-cab">
                <h2 class="ficha-h2" id="ficha-lb">Línea base personal</h2>
                <p class="ficha-nota">Valor de partida frente al último valor medido a bordo.</p>
            </div>
            <div class="ficha-tabla-wrap" tabindex="0" role="region" aria-labelledby="ficha-lb">
                <table class="ficha-tabla">
                    <caption class="ficha-solo-lectores">Línea base personal frente al último valor medido, por indicador</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="mono">INDICADOR</th>
                            <th scope="col" class="mono">LÍNEA BASE</th>
                            <th scope="col" class="mono">ÚLTIMO</th>
                            <th scope="col" class="mono">DIFERENCIA</th>
                            <th scope="col" class="mono">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linea as $r)
                            <tr wire:key="lb-{{ $r['clave'] }}">
                                <th scope="row" class="ficha-ind"><span class="icono" aria-hidden="true">{{ $r['ic'] }}</span>{{ $r['label'] }}</th>
                                <td class="mono {{ $r['base'] === null ? 'ficha-vacio' : '' }}">
                                    @if ($r['base'] !== null) {{ $r['base'] }} <span class="ficha-unidad">{{ $r['unit'] }}</span> @else Sin dato @endif
                                </td>
                                <td class="mono ficha-ultimo {{ $r['ultimo'] === null ? 'ficha-vacio' : '' }}">
                                    @if ($r['ultimo'] !== null) {{ $r['ultimo'] }} @else Sin medir @endif
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
                <h2 class="ficha-h2" id="ficha-lbp">Línea base pre-vuelo</h2>
                <p class="ficha-nota">Valor de partida medido antes de la misión (ventana por defecto: de L-180 a L-30 días respecto al inicio). Si una métrica queda vacía, se usa el valor del expediente cuando exista. Los números son de ejemplo, no validados.</p>
            </div>
            <form class="ficha-form" wire:submit="guardarLineaBase" novalidate>
                @foreach (config('linea_base.metricas') as $k => $cfg)
                    <fieldset class="ficha-campos" wire:key="lbp-{{ $k }}">
                        <legend class="ficha-legend">{{ $cfg['label'] }}</legend>
                        <label class="ficha-campo"><span class="mono">VALOR</span>
                            <input type="text" inputmode="decimal" wire:model="lb.{{ $k }}.valor" aria-invalid="{{ $errors->has("lb.$k.valor") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">UNIDAD</span>
                            <input type="text" maxlength="20" wire:model="lb.{{ $k }}.unidad" aria-invalid="{{ $errors->has("lb.$k.unidad") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">VENTANA DESDE</span>
                            <input type="date" wire:model="lb.{{ $k }}.desde" aria-invalid="{{ $errors->has("lb.$k.desde") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo"><span class="mono">VENTANA HASTA</span>
                            <input type="date" wire:model="lb.{{ $k }}.hasta" aria-invalid="{{ $errors->has("lb.$k.hasta") ? 'true' : 'false' }}">
                        </label>
                        <label class="ficha-campo ficha-campo--ancho"><span class="mono">FUENTE</span>
                            <input type="text" maxlength="120" wire:model="lb.{{ $k }}.fuente" aria-invalid="{{ $errors->has("lb.$k.fuente") ? 'true' : 'false' }}">
                        </label>
                        @foreach (['valor', 'unidad', 'desde', 'hasta', 'fuente'] as $campo)
                            @error("lb.$k.$campo") <span class="ficha-error" role="alert">{{ $message }}</span> @enderror
                        @endforeach
                        @if ($lbMetricas[$k]['actual'] !== null)
                            <p class="ficha-desv mono">Actual: {{ $lbMetricas[$k]['actual'] }}
                                @if ($lbMetricas[$k]['desv'] !== null) · Desviación: <strong>{{ $lbMetricas[$k]['desv'] }}</strong> frente a la base @else · Sin base para comparar @endif
                            </p>
                        @endif
                    </fieldset>
                @endforeach
                <div class="ficha-form-pie">
                    <button type="submit" class="ficha-btn ficha-btn--primario"><span class="icono" aria-hidden="true">save</span>Guardar línea base</button>
                    @if ($okLb)<span class="ficha-ok" role="status">{{ $okLb }}</span>@endif
                </div>
            </form>
        </section>

        <section class="ficha-tarjeta" aria-labelledby="ficha-asc">
            <div class="ficha-tarjeta-cab">
                <h2 class="ficha-h2" id="ficha-asc">Ficha ASCAN · primer examen anual</h2>
                <p class="ficha-nota">Registro, no interpretación. Anota resultado, fecha y estado de cada prueba; la app no los calcula ni los valora.</p>
            </div>
            <form class="ficha-form" wire:submit="guardarAscan" novalidate>
                @foreach ($ascGrupos as $gi => $g)
                    <fieldset class="ficha-campos ficha-campos--asc" wire:key="asc-g-{{ $gi }}">
                        <legend class="ficha-legend">{{ $g['titulo'] }}</legend>
                        @foreach ($g['pruebas'] as $k => $nombre)
                            <div class="ficha-asc-fila" wire:key="asc-{{ $k }}">
                                <span class="ficha-asc-nombre">{{ $nombre }}</span>
                                <label class="ficha-campo"><span class="mono">RESULTADO</span>
                                    <input type="text" maxlength="255" wire:model="asc.{{ $k }}.resultado" aria-label="Resultado: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.resultado") ? 'true' : 'false' }}">
                                </label>
                                <label class="ficha-campo"><span class="mono">FECHA</span>
                                    <input type="date" wire:model="asc.{{ $k }}.fecha" aria-label="Fecha: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.fecha") ? 'true' : 'false' }}">
                                </label>
                                <label class="ficha-campo"><span class="mono">ESTADO</span>
                                    <select wire:model="asc.{{ $k }}.estado" aria-label="Estado: {{ $nombre }}" aria-invalid="{{ $errors->has("asc.$k.estado") ? 'true' : 'false' }}">
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
                    <button type="submit" class="ficha-btn ficha-btn--primario"><span class="icono" aria-hidden="true">save</span>Guardar ficha ASCAN</button>
                    @if ($okAsc)<span class="ficha-ok" role="status">{{ $okAsc }}</span>@endif
                </div>
            </form>
        </section>

        <div class="ficha-rejilla">
            <section class="ficha-tarjeta" aria-labelledby="ficha-bio">
                <h2 class="ficha-h2 ficha-h2--ic" id="ficha-bio"><span class="icono ficha-ic" aria-hidden="true">monitor_heart</span>Biometría</h2>
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
                {{-- PENDIENTE: para tomar mediciones precisas hacen falta dispositivos de medición reales (reloj, tensiómetro, glucómetro, etc.). Por ahora las mediciones se simulan (SimuladorMediciones) o se capturan a mano. Este bloque se activará cuando se integren dispositivos reales. --}}
                <h2 class="ficha-h2 ficha-h2--ic" id="ficha-disp"><span class="icono ficha-ic" aria-hidden="true">watch</span>Vincular dispositivo <span class="mono ficha-pronto">PRÓXIMAMENTE</span></h2>
                <p class="ficha-nota">Podrás vincular el reloj del tripulante cuando esta función esté disponible.</p>
                <button type="button" class="ficha-btn" disabled aria-disabled="true"><span class="icono" aria-hidden="true">watch</span>Vincular dispositivo</button>
            </section>
        </div>

        <p class="ficha-aviso-pie" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>
    </main>
</div>
