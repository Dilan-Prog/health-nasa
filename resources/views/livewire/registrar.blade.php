@php
    $insignias = ['ok' => ['BIEN', 'check_circle'], 'warn' => ['REVISAR', 'warning'], 'crit' => ['CRÍTICO', 'error']];
    $vistasTab = ['frontal' => 'Frontal', 'lateral' => 'Costado der.', 'lateral_izq' => 'Costado izq.', 'posterior' => 'De atrás'];
    $plural = fn (int $n, string $uno, string $varios) => $n.' '.($n === 1 ? $uno : $varios);
    $nZ = $r['nZonas'];
    $resumenTxt = $plural($r['nHechas'], 'medición', 'mediciones').' · '.$plural($nZ, 'zona', 'zonas').' de dolor · '.$plural($r['nCrit'], 'crítica', 'críticas');
    $sinEnlace = ! $conEnlace;
    $bloqueado = $r['nErr'] > 0 || $r['vacio'];
    $barraNota = $r['nErr'] > 0
        ? ['report', 'Corrige '.$plural($r['nErr'], 'valor', 'valores').' antes de guardar', true]
        : ($r['vacio'] ? ['info', 'Escribe al menos una medición o marca una zona con dolor', false]
        : ($sinEnlace ? ['cloud_off', 'Sin enlace con tierra: se guardará en el dispositivo y el aviso quedará en cola', false]
        : ($r['alerta'] ? ['schedule', 'Hay un valor crítico o dolor intenso · respuesta de tierra en ~'.$retardo.' min', false] : null)));
    $textoSeguridad = 'Esta herramienta orienta, no diagnostica. Ante cualquier duda, contacta a tierra.';
@endphp
<div class="rg-raiz" data-enfocar="{{ $indicador }}"
     x-data="{
        vista: 'frontal',
        init() {
            const c = this.$root.dataset.enfocar;
            if (!c) return;
            this.$nextTick(() => {
                const el = document.getElementById('in-' + c) || document.getElementById('in-' + c + '-s');
                if (el) { el.scrollIntoView({ block: 'center' }); el.focus(); }
            });
        },
     }"
     @registro-foco-error.window="$nextTick(() => { const c = $root.querySelector('[aria-invalid=true]'); c && c.focus(); })">
    <x-encabezado titulo-a="Registrar" :titulo-b="'Tripulante '.($astronauta?->nombre ?? '—')" />

    @if (! $astronauta)
        <main class="rg-principal">
            <p class="rg-vacio-total">No hay tripulantes en la misión actual.</p>
        </main>
    @else
    <main class="rg-principal">

        {{-- ===== Izquierda: dolor ===== --}}
        <section class="rg-izq {{ $guardado !== null ? 'is-inerte' : '' }}" aria-label="¿Dónde sientes dolor?" @if ($guardado !== null) inert @endif>
            <div class="rg-tarjeta rg-tarjeta--sil">
                <div class="rg-fila-cab">
                    <h2 class="rg-h2">¿Dónde sientes dolor?</h2>
                    <div role="tablist" aria-label="Vista de la silueta" class="rg-vistas">
                        @foreach ($vistasTab as $id => $label)
                            <button type="button" role="tab" class="rg-vista" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                    x-bind:aria-selected="vista === '{{ $id }}' ? 'true' : 'false'"
                                    x-on:click="vista = '{{ $id }}'; $dispatch('silueta-vista', '{{ $id }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="rg-sil-caja" x-on:musculo-seleccionado="$wire.alternarZona($event.detail.id)">
                    <div class="rg-sil-alto">
                        <x-silueta-corporal :activos="array_keys($zonas)" :selector="false" class="silueta-corporal--ajustar" />
                    </div>
                </div>
                <span class="rg-ayuda">Toca o pulsa Enter sobre un músculo para marcarlo. Vuelve a tocarlo para quitarlo.</span>
            </div>

            <div class="rg-tarjeta rg-tarjeta--zonas">
                <div class="rg-fila-zonas">
                    <h3 class="rg-h3">Zonas marcadas</h3>
                    <span class="rg-contador mono" role="status">{{ $nZ === 1 ? '1 zona marcada' : $nZ.' zonas marcadas' }}</span>
                    @if ($nZ > 0)
                        <button type="button" class="rg-btn" wire:click="limpiarZonas">Limpiar todo</button>
                    @endif
                </div>

                @if ($nZ === 0)
                    <div class="rg-zonas-vacio"><span class="icono" aria-hidden="true">touch_app</span>Toca un músculo para marcar una zona con dolor</div>
                @endif

                @foreach ($zonas as $id => $z)
                    @php $nombre = \App\Models\ZonaDolor::nombre($id); @endphp
                    <div class="rg-zona" wire:key="zona-{{ $id }}" data-estado="{{ \App\Models\ZonaDolor::estado($z['int']) }}"
                         style="--ic: {{ \App\Models\ZonaDolor::color($z['int']) }}">
                        <div class="rg-zona-cab">
                            <span class="icono rg-zona-pin" aria-hidden="true">location_on</span>
                            <span class="rg-zona-nombre">{{ $nombre }}</span>
                            <button type="button" class="rg-quitar" aria-label="Quitar {{ $nombre }}" wire:click="alternarZona('{{ $id }}')">
                                <span class="icono" aria-hidden="true">close</span>
                            </button>
                        </div>
                        <div role="radiogroup" aria-label="Intensidad del dolor en {{ $nombre }}" class="rg-intensidad">
                            <span class="rg-int-cab mono"><span>INTENSIDAD</span><span class="rg-int-val">{{ $z['int'] }}/10 · {{ \App\Models\ZonaDolor::palabra($z['int']) }}</span></span>
                            <div class="rg-escala">
                                @for ($n = 1; $n <= 10; $n++)
                                    <button type="button" role="radio" class="rg-nivel mono {{ $n === $z['int'] ? 'is-on' : '' }}"
                                            style="--ic: {{ \App\Models\ZonaDolor::color($n) }}"
                                            aria-checked="{{ $n === $z['int'] ? 'true' : 'false' }}"
                                            aria-label="{{ $n }} de 10, {{ \App\Models\ZonaDolor::palabra($n) }}"
                                            wire:click="fijarIntensidad('{{ $id }}', {{ $n }})">{{ $n }}</button>
                                @endfor
                            </div>
                        </div>
                        <div class="rg-zona-opc">
                            <label class="rg-etq" for="tipo-{{ $id }}">Tipo de dolor
                                <select id="tipo-{{ $id }}" class="rg-select" wire:change="fijarTipo('{{ $id }}', $event.target.value)">
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t }}" @selected($z['tipo'] === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div role="radiogroup" aria-label="Desde cuándo" class="rg-desde">
                                <span class="rg-etq">Desde cuándo</span>
                                <div class="rg-desde-fila">
                                    @foreach ($desde as $d)
                                        <button type="button" role="radio" class="rg-opcion {{ $z['desde'] === $d ? 'is-on' : '' }}"
                                                aria-checked="{{ $z['desde'] === $d ? 'true' : 'false' }}"
                                                wire:click="fijarDesde('{{ $id }}', '{{ $d }}')">{{ $d }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ===== Derecha: medición ===== --}}
        <section class="rg-der" aria-label="Nueva medición">
            @if ($guardado === null)
                <div class="rg-tarjeta rg-tarjeta--form">
                    <div class="rg-fila-cab">
                        <div class="rg-titulos">
                            <h2 class="rg-h2">Nueva medición</h2>
                            <span class="rg-sello mono">LECTURA · {{ mb_strtoupper(str_replace('.', '', $ahora->locale('es')->isoFormat('DD MMM YYYY'))) }} · {{ $ahora->format('H:i') }} UTC</span>
                        </div>
                        <div role="radiogroup" aria-label="Origen de la medición" class="rg-origenes">
                            @foreach (\App\Models\RegistroSalud::ORIGENES as $clave => [$label, $ic])
                                <button type="button" role="radio" class="rg-opcion rg-opcion--origen {{ $origen === $clave ? 'is-on' : '' }}"
                                        aria-checked="{{ $origen === $clave ? 'true' : 'false' }}" wire:click="fijarOrigen('{{ $clave }}')">
                                    <span class="icono" aria-hidden="true">{{ $ic }}</span>{{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rg-campos">
                        @foreach ($indicadores as $clave => $ind)
                            @php
                                $a = $analisis[$clave];
                                $err = $a['error'] !== null;
                                $est = $err ? 'crit' : $a['estado'];
                                $msg = $err ? $a['error'] : ($a['mensaje'] ?? $a['pendiente']);
                                $ids = $clave === 'presion_arterial'
                                    ? [['in-presion_arterial-s', 'Sistólica', 'campos.pa_s'], ['in-presion_arterial-d', 'Diastólica', 'campos.pa_d']]
                                    : [['in-'.$clave, 'Valor', 'campos.'.$clave]];
                            @endphp
                            <div class="rg-campo {{ $err ? 'es-invalido' : '' }}" wire:key="campo-{{ $clave }}" @if ($est) data-estado="{{ $est }}" @endif>
                                <div class="rg-campo-cab">
                                    <span class="icono rg-campo-ic" aria-hidden="true">{{ $ind['icono'] }}</span>
                                    <span class="rg-campo-nombre">{{ $ind['etiqueta'] }}</span>
                                    <span class="rg-campo-unidad">{{ $ind['unidad'] }}</span>
                                </div>
                                <span class="rg-rango mono">{{ $ind['rango'] }}</span>
                                <div class="rg-entradas">
                                    @foreach ($ids as [$inId, $etq, $modelo])
                                        <label class="rg-etq" for="{{ $inId }}">{{ $etq }}
                                            <input id="{{ $inId }}" class="rg-entrada mono" type="text" inputmode="decimal" autocomplete="off" placeholder="—"
                                                   wire:model.live.debounce.400ms="{{ $modelo }}"
                                                   @if ($err) aria-invalid="true" @endif
                                                   aria-describedby="msg-{{ $clave }}">
                                        </label>
                                    @endforeach
                                </div>
                                <div id="msg-{{ $clave }}" class="rg-msg" aria-live="polite" @if (! $msg && ! $est) hidden @endif>
                                    @if ($est)
                                        <span class="rg-insignia">
                                            <span class="icono" aria-hidden="true">{{ $err ? 'report' : $insignias[$est][1] }}</span>{{ $err ? 'ERROR' : $insignias[$est][0] }}
                                        </span>
                                    @endif
                                    @if ($msg)
                                        <span class="rg-msg-txt {{ $err ? 'es-error' : '' }}">{{ $msg }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- Estado de ánimo: escala analógica visual (VAS) 0-100, opcional. Se activa con el botón. --}}
                        @php
                            $aa = $analisis['animo'];
                            $aErr = $aa['error'] !== null;
                            $aEst = $aErr ? 'crit' : $aa['estado'];
                            $aMsg = $aErr ? $aa['error'] : $aa['mensaje'];
                            $aOn = trim((string) ($campos['animo'] ?? '')) !== '';
                        @endphp
                        <div class="rg-campo rg-campo--animo {{ $aErr ? 'es-invalido' : '' }}" wire:key="campo-animo" @if ($aEst) data-estado="{{ $aEst }}" @endif>
                            <div class="rg-campo-cab">
                                <span class="icono rg-campo-ic" aria-hidden="true">mood</span>
                                <span class="rg-campo-nombre">Estado de ánimo</span>
                                <span class="rg-campo-unidad">VAS 0–100</span>
                            </div>
                            <span class="rg-rango mono">0 = MUY MAL · 100 = MUY BIEN · VALOR DE EJEMPLO</span>
                            @if ($aOn)
                                <div class="rg-vas-fila">
                                    <label class="rg-etq" for="in-animo">Cómo te sientes ahora
                                        <input id="in-animo" class="rg-vas" type="range" min="0" max="100" step="1"
                                               wire:model.live.debounce.250ms="campos.animo"
                                               aria-valuetext="{{ $campos['animo'] }} de 100" aria-describedby="msg-animo">
                                    </label>
                                    <output class="rg-vas-valor mono" for="in-animo">{{ $campos['animo'] }}<span class="rg-vas-de">/100</span></output>
                                </div>
                                <div class="rg-vas-extremos mono" aria-hidden="true"><span>0 · muy mal</span><span>100 · muy bien</span></div>
                                <button type="button" class="rg-btn rg-btn--animo" wire:click="alternarAnimo">
                                    <span class="icono" aria-hidden="true">close</span>Quitar del registro
                                </button>
                            @else
                                <button type="button" class="rg-btn rg-btn--animo" wire:click="alternarAnimo">
                                    <span class="icono" aria-hidden="true">add_reaction</span>Registrar mi ánimo
                                </button>
                            @endif
                            <div id="msg-animo" class="rg-msg" aria-live="polite" @if (! $aMsg && ! $aEst) hidden @endif>
                                @if ($aEst)
                                    <span class="rg-insignia">
                                        <span class="icono" aria-hidden="true">{{ $aErr ? 'report' : $insignias[$aEst][1] }}</span>{{ $aErr ? 'ERROR' : $insignias[$aEst][0] }}
                                    </span>
                                @endif
                                @if ($aMsg)
                                    <span class="rg-msg-txt {{ $aErr ? 'es-error' : '' }}">{{ $aMsg }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <details class="rg-sintomas" wire:ignore.self @if ($sintomas || $nota !== '') open @endif>
                        <summary><span class="icono" aria-hidden="true">symptoms</span>Otros síntomas <span class="rg-opcional">(opcional)</span></summary>
                        <div class="rg-sintomas-cuerpo">
                            <div class="rg-pildoras">
                                @foreach (\App\Models\RegistroSalud::SINTOMAS as $s)
                                    @php $on = in_array($s, $sintomas, true); @endphp
                                    <label class="rg-pildora {{ $on ? 'is-on' : '' }}" wire:key="sintoma-{{ $loop->index }}">
                                        <input type="checkbox" @checked($on) wire:change="alternarSintoma('{{ $s }}')">{{ $s }}
                                    </label>
                                @endforeach
                            </div>
                            <label class="rg-etq" for="reg-nota">Nota libre
                                <textarea id="reg-nota" class="rg-nota" rows="3" wire:model="nota" placeholder="Describe cualquier otra molestia o contexto"></textarea>
                            </label>
                        </div>
                    </details>
                    <p role="note" class="rg-seguridad rg-seguridad--movil"><span class="icono" aria-hidden="true">info</span>{{ $textoSeguridad }}</p>
                </div>

                {{-- Barra de resumen y guardado (fija abajo en móvil) --}}
                <div class="rg-barra">
                    <div class="rg-barra-fila">
                        <span role="status" class="rg-resumen mono">{{ $resumenTxt }}</span>
                        <span class="rg-enlace mono" data-estado="{{ $conEnlace ? 'ok' : 'crit' }}">
                            <span class="icono" aria-hidden="true">{{ $conEnlace ? 'satellite_alt' : 'link_off' }}</span>{{ $conEnlace ? 'ENLACE ACTIVO' : 'SIN ENLACE' }}
                        </span>
                    </div>
                    @if ($barraNota)
                        <span class="rg-barra-nota" @if ($barraNota[2]) data-estado="crit" @endif>
                            <span class="icono" aria-hidden="true">{{ $barraNota[0] }}</span>{{ $barraNota[1] }}
                        </span>
                    @endif
                    <div class="rg-barra-acciones">
                        <button type="button" class="rg-btn rg-btn--cancelar" wire:click="cancelar">Cancelar</button>
                        <button type="button" class="rg-guardar {{ $r['alerta'] ? 'es-alerta' : '' }}"
                                wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                                @disabled($bloqueado) aria-disabled="{{ $bloqueado ? 'true' : 'false' }}">
                            <span wire:loading.remove wire:target="guardar" class="rg-guardar-txt">
                                <span class="icono" aria-hidden="true">{{ $r['alerta'] ? ($sinEnlace ? 'schedule_send' : 'campaign') : 'save' }}</span>
                                {{ $r['alerta'] ? ($sinEnlace ? 'Guardar y encolar aviso' : 'Guardar y avisar a tierra') : 'Guardar registro' }}
                            </span>
                            <span wire:loading wire:target="guardar" class="rg-guardar-txt">
                                <span class="icono rg-girar" aria-hidden="true">progress_activity</span>Guardando…
                            </span>
                        </button>
                    </div>
                    <p role="note" class="rg-seguridad rg-seguridad--barra"><span class="icono" aria-hidden="true">info</span>{{ $textoSeguridad }}</p>
                </div>
            @else
                @php
                    $g = $guardado;
                    $off = ! $g['enlace'];
                    $cuerpo = $off
                        ? 'Sin enlace con tierra. Se enviará automáticamente al recuperar la conexión.'
                        : ($g['aviso'] ? 'Tierra ha recibido el aviso. ' : '').$plural($g['nMediciones'], 'medición guardada', 'mediciones guardadas').' y '.$plural($g['nZonas'], 'zona de dolor guardada', 'zonas de dolor guardadas').'.';
                @endphp
                <div role="status" class="rg-tarjeta rg-hecho">
                    <div class="rg-hecho-cab">
                        <span class="icono rg-hecho-ic" data-estado="{{ $off ? 'warn' : 'ok' }}" aria-hidden="true">{{ $off ? 'cloud_off' : 'task_alt' }}</span>
                        <div class="rg-hecho-textos">
                            <span class="rg-hecho-titulo">{{ $off ? 'Guardado en el dispositivo' : 'Registro guardado · '.$g['hora'].' UTC' }}</span>
                            <span class="rg-hecho-cuerpo">{{ $cuerpo }}</span>
                        </div>
                    </div>
                    <div class="rg-hecho-filas">
                        @foreach ($g['filas'] as $f)
                            <div class="rg-hecho-fila">
                                <span class="icono rg-hecho-fila-ic" aria-hidden="true">{{ $f['ic'] }}</span>
                                <span class="rg-hecho-fila-et">{{ $f['label'] }}</span>
                                <span class="rg-hecho-fila-v mono">{{ $f['v'] }}</span>
                                <span class="rg-insignia mono" data-estado="{{ $f['estado'] }}">
                                    <span class="icono" aria-hidden="true">{{ $insignias[$f['estado']][1] }}</span>{{ $insignias[$f['estado']][0] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="rg-hecho-acciones">
                        <a href="{{ route('panel') }}" class="rg-btn-primario">Ver sugerencias en el Panel<span class="icono" aria-hidden="true">arrow_forward</span></a>
                        <button type="button" class="rg-btn" wire:click="nuevoRegistro">Nuevo registro</button>
                    </div>
                    <p role="note" class="rg-seguridad"><span class="icono" aria-hidden="true">info</span>{{ $textoSeguridad }}</p>
                </div>
            @endif
        </section>
    </main>
    @endif
</div>
