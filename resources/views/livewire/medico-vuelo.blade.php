<div class="mv" wire:poll.5s
     x-data="{ llamada: 0, aviso: '', llamar() { if (this.llamada) { return; } this.llamada = 1; this.aviso = 'Conectando con tierra por el canal de emergencia.'; setTimeout(() => { this.llamada = 2; this.aviso = 'Conectado con control de misión.'; }, 1800); } }">
    <x-encabezado titulo-a="Flight surgeon" :titulo-b="$astronauta ? 'Tripulante '.$astronauta->nombre : 'Sin tripulante'" />

    <main class="mv-main" id="medico-contenido">
        @if ($emergencia)
            <div class="mv-banner" role="alert">
                <span class="icono mv-banner-ic" aria-hidden="true">emergency</span>
                <span class="mv-banner-t">
                    <span class="mono mv-banner-et">EMERGENCIA · REGLA DE EMERGENCIA{{ ! empty($emergencia['rule']) ? ' '.$emergencia['rule'] : '' }} · SIN IA</span>
                    <strong class="mv-banner-h">{{ $emergencia['finding'] }}</strong>
                    <span class="mv-banner-s">Sigue el protocolo fijo y contacta a tierra. No esperes a la IA.</span>
                </span>
                <button type="button" class="mv-btn mv-btn--blanco" @click="llamar()" :disabled="llamada === 2">
                    <span class="icono" aria-hidden="true" x-text="llamada === 2 ? 'call' : 'phone_in_talk'">phone_in_talk</span>
                    <span x-text="llamada === 0 ? 'Contactar a tierra ahora' : (llamada === 1 ? 'Conectando…' : 'Conectado con control')">Contactar a tierra ahora</span>
                </button>
            </div>
        @endif

        <section class="mv-card mv-medico" aria-label="Médico de vuelo asignado">
            <span class="mv-avatar" aria-hidden="true">MV</span>
            <span class="mv-medico-t">
                <span class="mv-medico-n">El médico de vuelo</span>
                <span class="mv-medico-d">Centro de control · canal médico privado</span>
            </span>
            <ul class="mv-chips" aria-label="Estado del sistema">
                @if ($nivelAtencion)
                    <li class="mv-chip"><span class="icono" aria-hidden="true">medical_services</span>{{ $nivelAtencion }}</li>
                @endif
                <li class="mv-chip" data-estado="{{ $retardo > 0 ? 'warn' : 'ok' }}"><span class="icono" aria-hidden="true">{{ $retardo > 0 ? 'schedule' : 'cell_tower' }}</span>{{ $retardo > 0 ? 'Retardo '.$retardo.' min' : 'Retardo < 1 s' }}</li>
                <li class="mv-chip" data-estado="{{ $hayEmerg ? '' : 'ok' }}"><span class="icono" aria-hidden="true">{{ $hayEmerg ? 'pause_circle' : 'memory' }}</span>{{ $hayEmerg ? 'IA en segundo plano' : 'IA local activa' }}</li>
                <li class="mv-chip"><span class="icono" aria-hidden="true">date_range</span>Datos: últimos 15 días</li>
                <li class="mv-chip" data-estado="{{ $enlace ? '' : 'crit' }}"><span class="icono" aria-hidden="true">{{ $enlace ? 'satellite_alt' : 'link_off' }}</span>{{ $enlace ? 'Enlace con tierra' : 'Sin enlace con tierra' }}</li>
            </ul>
        </section>

        <div class="mv-dos">
            {{-- Orientación --}}
            <section class="mv-orient" aria-labelledby="mv-zona-b">
                <div class="mv-orient-cab">
                    <h2 class="mv-h2" id="mv-zona-b">{{ $hayEmerg ? 'Emergencia y orientación' : 'Orientación del asistente' }}</h2>
                    <span class="mv-legal"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta siempre al médico de vuelo.</span>
                </div>

                @if (! $astronauta)
                    <div class="mv-vacio mv-card"><h3>Sin tripulante</h3><p>Elige un tripulante para ver su orientación.</p></div>
                @elseif (count($tarjetas) === 0)
                    <div class="mv-vacio mv-card">
                        <span class="icono mv-vacio-ic" aria-hidden="true">verified</span>
                        <h3>Todo en rango hoy</h3>
                        <p>Tus indicadores están dentro de tu rango y de tu línea base. No hay orientación pendiente. Sigue con tus rutinas y registra la medición de mañana.</p>
                    </div>
                @endif

                @php $pausaMostrada = false; @endphp
                @foreach ($tarjetas as $c)
                    @php
                        $em = $c['kind'] === 'emerg';
                        $ai = $c['kind'] === 'ai';
                        $conf = (int) ($c['conf'] ?? 0);
                        $nivel = $conf >= 75 ? 'Alta' : ($conf >= 55 ? 'Media' : 'Baja');
                        [$sevL, $sevIc] = \App\Livewire\MedicoVuelo::SEV[$c['sev']] ?? ['Revisar', 'warning'];
                        [$selL, $selIc, $selTono] = \App\Livewire\MedicoVuelo::SELLO[$c['safe'] ?? ($em ? 'rule' : 'none')] ?? \App\Livewire\MedicoVuelo::SELLO['none'];
                        $abierta = $fuente[$c['id']] ?? null;
                        $voto = $votos[$c['id']] ?? null;
                        $enviada = ! empty($anadidas[$c['id']]);
                        $hid = 'tarjeta-'.$c['id'];
                    @endphp
                    @if ($hayEmerg && $ai && ! $pausaMostrada)
                        @php $pausaMostrada = true; @endphp
                        <div class="mv-pausa" role="note">
                            <span class="icono" aria-hidden="true">pause_circle</span>
                            <span><strong>Orientación del asistente en pausa ({{ $nPausa }})</strong><span class="mv-pausa-s">Las reglas de emergencia tienen prioridad. Se reanuda al cerrar la emergencia.</span></span>
                        </div>
                    @endif
                    <article class="mv-tarjeta {{ $em ? 'is-emerg' : '' }} {{ $hayEmerg && $ai ? 'is-pausa' : '' }}" data-estado="{{ $c['sev'] }}" aria-labelledby="{{ $hid }}" wire:key="tarjeta-{{ $c['id'] }}">
                        <div class="mv-tarjeta-cab">
                            <span class="mv-pill mono {{ $em ? 'mv-pill--emerg' : ($ai ? 'mv-pill--ia' : 'mv-pill--regla') }}">
                                <span class="icono" aria-hidden="true">{{ $em ? 'emergency' : ($ai ? 'smart_toy' : 'rule') }}</span>{{ $em ? 'Regla de emergencia' : ($ai ? 'Asistente IA local' : 'Regla fija'.(! empty($c['rule']) ? ' '.$c['rule'] : '')) }}
                            </span>
                            <span class="mv-pill mv-pill--sev mono"><span class="icono" aria-hidden="true">{{ $sevIc }}</span>{{ $sevL }}</span>
                            <span class="mv-grow"></span>
                            <span class="mono mv-hora">{{ $c['time'] ?? '' }}</span>
                        </div>
                        <h3 class="mv-tarjeta-h" id="{{ $hid }}">{{ $c['finding'] }}</h3>
                        @if (! empty($c['banda']) || ! empty($c['fuente']) || ! empty($c['referencia']))
                            <div class="mv-norma">
                                @if (! empty($c['banda']))<span class="mv-banda mono">{{ $c['banda'] }}</span>@endif
                                @foreach (['fuente', 'referencia'] as $k)
                                    @if (! empty($c[$k]))
                                        @php $f = $c[$k]; @endphp
                                        @if (! empty($f['es_proyeccion']))<span class="mv-proy mono">Proyección (HRP)</span>@endif
                                        <x-fuente-chip :tipo="$f['tipo'] ?? ''" :revision="$f['revision'] ?? null" :validado="(bool) ($f['validado'] ?? false)" :label="$f['etiqueta'] ?? null" />
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($c['ventana']))
                            @php $w = $c['ventana']; @endphp
                            <p class="mv-pend" data-regla-ventana="{{ $w['nombre'] }}"><span class="icono" aria-hidden="true">date_range</span><span><strong>Regla de ventana · {{ $w['etiqueta'] }}:</strong> {{ $w['texto'] }}
                                @if (! empty($w['requisito']))<br>Requisito (guía «Datos médicos»): {{ $w['requisito']['texto'] }}.@endif
                                <br>Supuesto del proyecto, no de la norma: el estándar no publica horas de sueño. Orienta, no diagnostica.</span></p>
                        @endif

                        <div class="mv-pasos">
                            <span class="mono mv-et">{{ $em ? 'QUÉ HACER AHORA · PROTOCOLO FIJO' : ($ai ? 'SUGERENCIA' : 'QUÉ HACER') }}</span>
                            <ol class="mv-ol">
                                @foreach ($c['steps'] as $n => $paso)
                                    <li><span class="mv-num" aria-hidden="true">{{ $n + 1 }}</span><span>{{ $paso }}</span></li>
                                @endforeach
                            </ol>
                        </div>

                        @if (! empty($c['pend']))
                            <p class="mv-pend"><span class="icono" aria-hidden="true">pending_actions</span>{{ $c['pend'] }}</p>
                        @endif

                        @if ($ai)
                            <div class="mv-conf">
                                <div class="mv-conf-cab"><span class="mono mv-et" id="conf-{{ $c['id'] }}">CONFIANZA</span><span class="mono mv-conf-t">{{ $conf }} % · {{ $nivel }}</span></div>
                                <div class="mv-seg" role="meter" aria-labelledby="conf-{{ $c['id'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $conf }}" aria-valuetext="{{ $conf }} % · {{ $nivel }}">
                                    @for ($i = 0; $i < 10; $i++)<span class="{{ $i < round($conf / 10) ? 'is-on' : '' }}"></span>@endfor
                                </div>
                                @if ($conf < 55)
                                    <span class="mv-baja">Confianza baja: confirma con el médico de vuelo antes de actuar.</span>
                                @endif
                            </div>
                        @endif

                        @if (! empty($c['src']))
                            <div class="mv-fuentes">
                                <span class="mono mv-et">FUENTES</span>
                                <div class="mv-fuentes-l">
                                    @foreach ($c['src'] as $i => $s)
                                        <button type="button" class="mv-fuente" aria-expanded="{{ $abierta === $i ? 'true' : 'false' }}" aria-controls="fuente-{{ $c['id'] }}" wire:click="abrirFuente('{{ $c['id'] }}', {{ $i }})">
                                            <span class="icono" aria-hidden="true">{{ $s['ic'] ?? 'description' }}</span>{{ $s['l'] }}
                                        </button>
                                    @endforeach
                                </div>
                                @if ($abierta !== null && isset($c['src'][$abierta]))
                                    <p class="mv-extracto" id="fuente-{{ $c['id'] }}">{{ $c['src'][$abierta]['t'] }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="mv-sello-f">
                            <span class="mv-sello" data-tono="{{ $selTono }}"><span class="icono" aria-hidden="true">{{ $selIc }}</span>{{ $selL }}</span>
                            @if ($ai)<span class="mv-legal-s">Orienta, no diagnostica</span>@endif
                        </div>

                        @if ($ai)
                            <div class="mv-fb">
                                <button type="button" class="mv-btn mv-btn--chip" aria-pressed="{{ $voto === 'up' ? 'true' : 'false' }}" wire:click="votar('{{ $c['id'] }}', 'up')"><span class="icono" aria-hidden="true">thumb_up</span>Me ayudó</button>
                                <button type="button" class="mv-btn mv-btn--chip" aria-pressed="{{ $voto === 'down' ? 'true' : 'false' }}" wire:click="votar('{{ $c['id'] }}', 'down')"><span class="icono" aria-hidden="true">thumb_down</span>No me ayudó</button>
                                <span class="mv-grow"></span>
                                <button type="button" class="mv-btn mv-btn--linea" @disabled($enviada) wire:click="anadir('{{ $c['id'] }}')"><span class="icono" aria-hidden="true">{{ $enviada ? 'check' : 'forward_to_inbox' }}</span>{{ $enviada ? 'Añadido al mensaje' : 'Enviar al médico de vuelo' }}</button>
                            </div>
                            @if ($voto)
                                <span class="mv-gracias" role="status">{{ $voto === 'up' ? 'Gracias. Lo tendremos en cuenta en este equipo.' : 'Gracias. Esta sugerencia bajará de prioridad y se avisará al médico de vuelo.' }}</span>
                            @endif
                        @endif

                        @if ($em)
                            <button type="button" class="mv-btn mv-btn--emerg" @click="llamar()" :disabled="llamada === 2">
                                <span class="icono" aria-hidden="true" x-text="llamada === 2 ? 'call' : 'phone_in_talk'">phone_in_talk</span>
                                <span x-text="llamada === 0 ? 'Contactar a tierra ahora' : (llamada === 1 ? 'Conectando con tierra…' : 'Conectado con control de misión')">Contactar a tierra ahora</span>
                            </button>
                        @endif
                    </article>
                @endforeach
            </section>

            {{-- Contactar --}}
            <section class="mv-card mv-contactar" aria-labelledby="mv-zona-c">
                <h2 class="mv-h2 mono-et" id="mv-zona-c">Contactar</h2>

                <fieldset class="mv-share">
                    <legend>Alertas que se compartirán <span class="mono mv-share-n">{{ count($compartibles) ? count($elegidos).' de '.count($compartibles) : '' }}</span></legend>
                    @forelse ($compartibles as $s)
                        @php $sevT = $s['sev'] === 'crit' ? 'crit' : 'warn'; @endphp
                        <label class="mv-share-f" data-estado="{{ $sevT }}" wire:key="comp-{{ $s['id'] }}">
                            @if ($s['locked'])
                                <input type="checkbox" checked disabled>
                            @else
                                <input type="checkbox" wire:model.live="marcados.{{ $s['id'] }}">
                            @endif
                            <span class="icono mv-share-ic" aria-hidden="true">{{ $sevT === 'crit' ? 'error' : 'warning' }}</span>
                            <span class="mv-share-t">
                                <span class="mv-share-l">{{ $s['label'] }}</span>
                                <span class="mono mv-share-m">{{ $sevT === 'crit' ? 'CRÍTICO' : 'REVISAR' }} · {{ $s['locked'] ? 'SIEMPRE SE INCLUYE' : (! empty($anadidas[$s['id']]) ? 'CON ORIENTACIÓN' : 'ÚLTIMA LECTURA') }}</span>
                            </span>
                            <span class="mono mv-share-v">{{ $s['val'] }}</span>
                        </label>
                    @empty
                        <p class="mv-suave">Nada que compartir hoy.</p>
                    @endforelse
                </fieldset>

                <div class="mv-nota">
                    <div class="mv-nota-cab">
                        <label for="mv-borrador" class="mv-lbl">Nota para el médico de vuelo</label>
                        <button type="button" class="mv-link" wire:click="borrador">Generar borrador</button>
                    </div>
                    <textarea id="mv-borrador" name="nota" rows="5" wire:model="nota" aria-describedby="mv-borrador-ayuda"></textarea>
                    <span id="mv-borrador-ayuda" class="mv-ayuda">Revísalo antes de enviar. No incluye dosis ni tratamientos.</span>
                </div>

                @if (count($adjuntos))
                    <fieldset class="mv-adj">
                        <legend class="mv-lbl">Adjuntos</legend>
                        @foreach ($adjuntos as $i => $f)
                            <label class="mv-adj-f" wire:key="adj-{{ $i }}">
                                <input type="checkbox" value="{{ $i }}" wire:model.live="adjuntosSel">
                                <span class="icono" aria-hidden="true">{{ $f['ic'] }}</span><span>{{ $f['l'] }}</span><span class="mono mv-share-m">{{ $f['d'] }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @endif

                @if ($autonomo)
                    <p class="mv-aviso" data-estado="warn"><span class="icono" aria-hidden="true">medical_services</span><span>Triaje autónomo local: orientación inmediata a bordo y mensaje asíncrono a tierra</span></p>
                @elseif ($retardo >= 5)
                    <p class="mv-aviso" data-estado="warn"><span class="icono" aria-hidden="true">schedule</span><span>Retardo de {{ $retardo }} min: la videoconsulta no es posible. Envía un mensaje; el médico de vuelo lo verá en su próxima revisión.</span></p>
                @elseif (! $enlace)
                    <p class="mv-aviso" data-estado="crit"><span class="icono" aria-hidden="true">link_off</span><span>Sin enlace con tierra. Puedes preparar el mensaje: quedará en cola y se enviará solo al recuperar el enlace.</span></p>
                @endif

                @if ($error)
                    <p class="mv-aviso" data-estado="crit" role="alert"><span class="icono" aria-hidden="true">error</span><span>{{ $error }}</span></p>
                @endif

                <div class="mv-acciones">
                    @if ($puedeVideo)
                        <button type="button" class="mv-btn mv-btn--pri" wire:click="enviar('video')"><span class="icono" aria-hidden="true">videocam</span>Solicitar videoconsulta</button>
                    @endif
                    <button type="button" class="mv-btn {{ $puedeVideo ? 'mv-btn--linea-pri' : 'mv-btn--pri' }}" wire:click="enviar('msg')"><span class="icono" aria-hidden="true">send</span>{{ $enlace ? 'Enviar mensaje' : 'Guardar en cola' }}</button>
                </div>
                @if ($retardo > 0)
                    <span class="mono mv-eta"><span class="icono" aria-hidden="true">schedule</span>Respuesta estimada con {{ $retardo }} min de ida y otros tantos de vuelta, más la revisión.</span>
                @endif

                <div class="mv-envios">
                    <h3 class="mv-h3">Envíos</h3>
                    @forelse ($envios as $m)
                        <div class="mv-envio {{ $m['fallida'] ? 'is-fallida' : '' }}" wire:key="envio-{{ $m['id'] }}">
                            <div class="mv-envio-cab">
                                <span class="icono mv-envio-ic" aria-hidden="true">{{ $m['tipo'] === 'video' ? 'videocam' : 'mail' }}</span>
                                <span class="mv-envio-t"><span class="mv-envio-n">{{ $m['titulo'] }}</span><span class="mono mv-envio-s">{{ $m['cuando'] }} · {{ $m['fallida'] ? 'en cola' : mb_strtolower($m['etapas'][$m['etapa']]['l'] ?? '') }}</span></span>
                            </div>
                            <ol class="mv-tl" aria-label="{{ $m['titulo'] }}: {{ $m['fallida'] ? 'en cola, sin enlace' : ($m['etapas'][$m['etapa']]['l'] ?? '') }}">
                                @foreach ($m['etapas'] as $i => $e)
                                    @php
                                        $fallo = $m['fallida'] && $i === 0;
                                        $cur = $i === $m['etapa'] && $m['etapa'] < 3;
                                        $hecha = $e['hecha'] && ! $m['fallida'] && ! $cur;
                                        $hecha = $hecha || ($m['etapa'] === 3 && $i === 3);
                                    @endphp
                                    <li @if ($cur || $fallo) aria-current="step" @endif class="mv-et-i {{ $hecha ? 'is-hecha' : '' }} {{ $cur ? 'is-cur' : '' }} {{ $fallo ? 'is-fallo' : '' }}">
                                        <span class="mv-tl-l mv-tl-l--i {{ $i > 0 && $i <= $m['etapa'] && ! $m['fallida'] ? 'is-on' : '' }}" aria-hidden="true"></span>
                                        <span class="mv-tl-l mv-tl-l--d {{ $i < 3 && $i < $m['etapa'] && ! $m['fallida'] ? 'is-on' : '' }}" aria-hidden="true"></span>
                                        <span class="icono mv-tl-c" aria-hidden="true">{{ $fallo ? 'priority_high' : ($hecha ? 'check' : ($cur ? 'more_horiz' : '')) }}</span>
                                        <span class="mv-tl-n">{{ $e['l'] }}</span>
                                        <span class="mono mv-tl-h">{{ $e['hora'] }}</span>
                                    </li>
                                @endforeach
                            </ol>
                            @if ($m['fallida'])
                                <div class="mv-sinenlace" role="alert">
                                    <span class="icono" aria-hidden="true">link_off</span>
                                    <span class="mv-sinenlace-t">Sin enlace con tierra. Se enviará automáticamente al recuperar el enlace.</span>
                                    <button type="button" class="mv-btn mv-btn--crit-o" wire:click="reintentar({{ $m['id'] }})"><span class="icono" aria-hidden="true">refresh</span>Reintentar</button>
                                </div>
                            @elseif (! empty($m['eta']))
                                <span class="mono mv-envio-eta">{{ $m['eta'] }}</span>
                            @endif
                            @if (! empty($m['respuesta']))
                                <div class="mv-respuesta"><span class="mono mv-et">RESPUESTA DEL MÉDICO DE VUELO</span><p>{{ $m['respuesta'] }}</p></div>
                            @endif
                        </div>
                    @empty
                        <p class="mv-suave">Aún no has enviado consultas al médico de vuelo.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <p class="mv-nota-legal" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>
    </main>

    <div class="mv-sr" role="status" aria-live="polite" aria-atomic="true"><span>{{ $anuncio }}</span><span x-text="aviso"></span></div>
</div>
