@php
    $insignias = ['ok' => ['NORMAL', 'check_circle'], 'warn' => ['CAUTION', 'warning'], 'crit' => ['CRITICAL', 'error']];
    $vistasTab = ['frontal' => 'Front', 'lateral' => 'Right side', 'lateral_izq' => 'Left side', 'posterior' => 'Back'];
    $plural = fn (int $n, string $uno, string $varios) => $n.' '.($n === 1 ? $uno : $varios);
    $nZ = $r['nZonas'];
    $resumenTxt = $plural($r['nHechas'], 'measurement', 'measurements').' · '.$plural($nZ, 'pain zone', 'pain zones').' · '.$plural($r['nCrit'], 'critical', 'critical');
    $sinEnlace = ! $conEnlace;
    $bloqueado = $r['nErr'] > 0 || $r['vacio'];
    $barraNota = $r['nErr'] > 0
        ? ['report', 'Fix '.$plural($r['nErr'], 'value', 'values').' before saving', true]
        : ($r['vacio'] ? ['info', 'Enter at least one measurement or mark a pain zone', false]
        : ($sinEnlace ? ['cloud_off', 'No link to the ground: it will be saved on the device and the alert will be queued', false]
        : ($r['alerta'] ? ['schedule', 'There is a critical value or severe pain · ground response in ~'.$retardo.' min', false] : null)));
    $textoSeguridad = 'This tool provides guidance only; it does not diagnose. If in doubt, contact the ground.';
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
    <x-encabezado titulo-a="Log" :titulo-b="'Crew member '.($astronauta?->nombre ?? '—')" />

    @if (! $astronauta)
        <main class="rg-principal">
            <p class="rg-vacio-total">There are no crew members in the current mission.</p>
        </main>
    @else
    <main class="rg-principal">

        {{-- ===== Izquierda: dolor ===== --}}
        <section class="rg-izq {{ $guardado !== null ? 'is-inerte' : '' }}" aria-label="Where do you feel pain?" @if ($guardado !== null) inert @endif>
            <div class="rg-tarjeta rg-tarjeta--sil">
                <div class="rg-fila-cab">
                    <h2 class="rg-h2">Where do you feel pain?</h2>
                    <div role="tablist" aria-label="Silhouette view" class="rg-vistas">
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
                <span class="rg-ayuda">Tap or press Enter on a muscle to mark it. Tap it again to remove it.</span>
            </div>

            <div class="rg-tarjeta rg-tarjeta--zonas">
                <div class="rg-fila-zonas">
                    <h3 class="rg-h3">Marked zones</h3>
                    <span class="rg-contador mono" role="status">{{ $nZ === 1 ? '1 zone marked' : $nZ.' zones marked' }}</span>
                    @if ($nZ > 0)
                        <button type="button" class="rg-btn" wire:click="limpiarZonas">Clear all</button>
                    @endif
                </div>

                @if ($nZ === 0)
                    <div class="rg-zonas-vacio"><span class="icono" aria-hidden="true">touch_app</span>Tap a muscle to mark a pain zone</div>
                @endif

                @foreach ($zonas as $id => $z)
                    @php $nombre = $this->nombreZona($id); @endphp
                    <div class="rg-zona" wire:key="zona-{{ $id }}" data-estado="{{ \App\Models\ZonaDolor::estado($z['int']) }}"
                         style="--ic: {{ \App\Models\ZonaDolor::color($z['int']) }}">
                        <div class="rg-zona-cab">
                            <span class="icono rg-zona-pin" aria-hidden="true">location_on</span>
                            <span class="rg-zona-nombre">{{ $nombre }}</span>
                            <button type="button" class="rg-quitar" aria-label="Remove {{ $nombre }}" wire:click="alternarZona('{{ $id }}')">
                                <span class="icono" aria-hidden="true">close</span>
                            </button>
                        </div>
                        <div role="radiogroup" aria-label="Pain intensity in {{ $nombre }}" class="rg-intensidad">
                            <span class="rg-int-cab mono"><span>INTENSITY</span><span class="rg-int-val">{{ $z['int'] }}/10 · {{ $this->palabraDolor($z['int']) }}</span></span>
                            <div class="rg-escala">
                                @for ($n = 1; $n <= 10; $n++)
                                    <button type="button" role="radio" class="rg-nivel mono {{ $n === $z['int'] ? 'is-on' : '' }}"
                                            style="--ic: {{ \App\Models\ZonaDolor::color($n) }}"
                                            aria-checked="{{ $n === $z['int'] ? 'true' : 'false' }}"
                                            aria-label="{{ $n }} of 10, {{ $this->palabraDolor($n) }}"
                                            wire:click="fijarIntensidad('{{ $id }}', {{ $n }})">{{ $n }}</button>
                                @endfor
                            </div>
                        </div>
                        <div class="rg-zona-opc">
                            <label class="rg-etq" for="tipo-{{ $id }}">Pain type
                                <select id="tipo-{{ $id }}" class="rg-select" wire:change="fijarTipo('{{ $id }}', $event.target.value)">
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t }}" @selected($z['tipo'] === $t)>{{ \App\Models\ZonaDolor::etiquetaTipo($t) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div role="radiogroup" aria-label="Since when" class="rg-desde">
                                <span class="rg-etq">Since when</span>
                                <div class="rg-desde-fila">
                                    @foreach ($desde as $d)
                                        <button type="button" role="radio" class="rg-opcion {{ $z['desde'] === $d ? 'is-on' : '' }}"
                                                aria-checked="{{ $z['desde'] === $d ? 'true' : 'false' }}"
                                                wire:click="fijarDesde('{{ $id }}', '{{ $d }}')">{{ \App\Models\ZonaDolor::etiquetaDesde($d) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ===== Derecha: medición ===== --}}
        <section class="rg-der" aria-label="New measurement">
            @if ($guardado === null)
                <div class="rg-tarjeta rg-tarjeta--form">
                    <div class="rg-fila-cab">
                        <div class="rg-titulos">
                            <h2 class="rg-h2">New measurement</h2>
                            <span class="rg-sello mono">READING · {{ mb_strtoupper($ahora->format('d M Y')) }} · {{ $ahora->format('H:i') }} UTC</span>
                        </div>
                        <div role="radiogroup" aria-label="Measurement source" class="rg-origenes">
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
                                    ? [['in-presion_arterial-s', 'Systolic', 'campos.pa_s'], ['in-presion_arterial-d', 'Diastolic', 'campos.pa_d']]
                                    : [['in-'.$clave, 'Value', 'campos.'.$clave]];
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
                                <span class="rg-campo-nombre">Mood</span>
                                <span class="rg-campo-unidad">VAS 0–100</span>
                            </div>
                            <span class="rg-rango mono">0 = VERY BAD · 100 = VERY GOOD · EXAMPLE VALUE</span>
                            @if ($aOn)
                                <div class="rg-vas-fila">
                                    <label class="rg-etq" for="in-animo">How you feel now
                                        <input id="in-animo" class="rg-vas" type="range" min="0" max="100" step="1"
                                               wire:model.live.debounce.250ms="campos.animo"
                                               aria-valuetext="{{ $campos['animo'] }} of 100" aria-describedby="msg-animo">
                                    </label>
                                    <output class="rg-vas-valor mono" for="in-animo">{{ $campos['animo'] }}<span class="rg-vas-de">/100</span></output>
                                </div>
                                <div class="rg-vas-extremos mono" aria-hidden="true"><span>0 · very bad</span><span>100 · very good</span></div>
                                <button type="button" class="rg-btn rg-btn--animo" wire:click="alternarAnimo">
                                    <span class="icono" aria-hidden="true">close</span>Remove from log
                                </button>
                            @else
                                <button type="button" class="rg-btn rg-btn--animo" wire:click="alternarAnimo">
                                    <span class="icono" aria-hidden="true">add_reaction</span>Log my mood
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
                        <summary><span class="icono" aria-hidden="true">symptoms</span>Other symptoms <span class="rg-opcional">(optional)</span></summary>
                        <div class="rg-sintomas-cuerpo">
                            <div class="rg-pildoras">
                                @foreach (\App\Models\RegistroSalud::SINTOMAS as $s)
                                    @php $on = in_array($s, $sintomas, true); @endphp
                                    <label class="rg-pildora {{ $on ? 'is-on' : '' }}" wire:key="sintoma-{{ $loop->index }}">
                                        <input type="checkbox" @checked($on) wire:change="alternarSintoma('{{ $s }}')">{{ \App\Models\RegistroSalud::etiquetaSintoma($s) }}
                                    </label>
                                @endforeach
                            </div>
                            <label class="rg-etq" for="reg-nota">Free note
                                <textarea id="reg-nota" class="rg-nota" rows="3" wire:model="nota" placeholder="Describe any other discomfort or context"></textarea>
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
                            <span class="icono" aria-hidden="true">{{ $conEnlace ? 'satellite_alt' : 'link_off' }}</span>{{ $conEnlace ? 'LINK ACTIVE' : 'NO LINK' }}
                        </span>
                    </div>
                    @if ($barraNota)
                        <span class="rg-barra-nota" @if ($barraNota[2]) data-estado="crit" @endif>
                            <span class="icono" aria-hidden="true">{{ $barraNota[0] }}</span>{{ $barraNota[1] }}
                        </span>
                    @endif
                    <div class="rg-barra-acciones">
                        <button type="button" class="rg-btn rg-btn--cancelar" wire:click="cancelar">Cancel</button>
                        <button type="button" class="rg-guardar {{ $r['alerta'] ? 'es-alerta' : '' }}"
                                wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                                @disabled($bloqueado) aria-disabled="{{ $bloqueado ? 'true' : 'false' }}">
                            <span wire:loading.remove wire:target="guardar" class="rg-guardar-txt">
                                <span class="icono" aria-hidden="true">{{ $r['alerta'] ? ($sinEnlace ? 'schedule_send' : 'campaign') : 'save' }}</span>
                                {{ $r['alerta'] ? ($sinEnlace ? 'Save and queue alert' : 'Save and alert ground') : 'Save log entry' }}
                            </span>
                            <span wire:loading wire:target="guardar" class="rg-guardar-txt">
                                <span class="icono rg-girar" aria-hidden="true">progress_activity</span>Saving…
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
                        ? 'No link to the ground. It will be sent automatically when the connection is restored.'
                        : ($g['aviso'] ? 'The ground has received the alert. ' : '').$plural($g['nMediciones'], 'measurement saved', 'measurements saved').' and '.$plural($g['nZonas'], 'pain zone saved', 'pain zones saved').'.';
                @endphp
                <div role="status" class="rg-tarjeta rg-hecho">
                    <div class="rg-hecho-cab">
                        <span class="icono rg-hecho-ic" data-estado="{{ $off ? 'warn' : 'ok' }}" aria-hidden="true">{{ $off ? 'cloud_off' : 'task_alt' }}</span>
                        <div class="rg-hecho-textos">
                            <span class="rg-hecho-titulo">{{ $off ? 'Saved on the device' : 'Log entry saved · '.$g['hora'].' UTC' }}</span>
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
                        <a href="{{ route('panel') }}" class="rg-btn-primario">View suggestions on the Dashboard<span class="icono" aria-hidden="true">arrow_forward</span></a>
                        <button type="button" class="rg-btn" wire:click="nuevoRegistro">New log entry</button>
                    </div>
                    <p role="note" class="rg-seguridad"><span class="icono" aria-hidden="true">info</span>{{ $textoSeguridad }}</p>
                </div>
            @endif
        </section>
    </main>
    @endif
</div>
