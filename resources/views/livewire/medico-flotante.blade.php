@php
    $sev = ['critico' => ['Critical', 'error'], 'precaucion' => ['Caution', 'warning']];
    $item = fn ($x) => \App\Livewire\MedicoFlotante::item($x);
@endphp
<div class="mf" id="medico-flotante" data-critico="{{ $critico ? '1' : '0' }}" wire:poll.10s="comprobar"
     x-data="{
        open: false,
        abrir(auto = false, d = null) {
            this.open = true;
            this.$nextTick(() => {
                if (d && d.casoId) { const m = document.getElementById('mf-caso-' + d.casoId); if (m) m.scrollIntoView({ block: 'nearest' }); }
                const a = document.activeElement;
                const escribiendo = a && a !== document.body && a.matches && a.matches('input, textarea, select, [contenteditable]');
                if (! auto || ! escribiendo) this.$refs.titulo.focus({ preventScroll: true });
            });
            $wire.marcarLeidos();
        },
        cerrar() { this.open = false; this.$nextTick(() => this.$refs.circulo.focus()); },
     }"
     x-init="if (@js($abrirAlCargar)) abrir(true)"
     x-on:medico-flotante-abrir.window="abrir(true, Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     x-on:abrir-medico-flotante.window="abrir(false, Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     x-on:mf-respuesta.window="open = true; $nextTick(() => { const t = document.getElementById('mf-resp-titulo'); if (t) { t.scrollIntoView({ block: 'nearest' }); t.focus({ preventScroll: true }); } })"
     x-on:keydown.escape.window="if (open) cerrar()"
     x-bind:class="{ 'is-abierto': open }">

    <button type="button" class="mf-circulo" id="mf-circulo" x-ref="circulo" title="Flight Surgeon" aria-label="{{ $etiquetaCirculo }}" aria-haspopup="dialog"
            x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="mf-dialogo"
            x-on:click="open ? cerrar() : abrir(false)">
        @if ($critico)<span class="mf-pulso" data-pulso aria-hidden="true"></span>@endif
        <span class="icono mf-circulo-ic" aria-hidden="true">stethoscope</span>
        @if ($nSinLeer > 0)<span class="mf-insignia mono" aria-hidden="true">{{ $nSinLeer }}</span>@endif
    </button>

    {{-- Anuncio para lector de pantalla con el rectángulo cerrado; abierto lo cubre el aria-live de la lista. --}}
    <div class="mf-sr" role="status" aria-live="{{ $critico ? 'assertive' : 'polite' }}" aria-atomic="true" x-show="! open">{{ $anuncio }}</div>

    <section id="mf-dialogo" class="mf-dialogo" role="dialog" aria-modal="false" aria-labelledby="mf-titulo" x-show="open" x-cloak>
        <header class="mf-cab">
            <div class="mf-cab-f">
                <span class="mf-avatar icono" aria-hidden="true">stethoscope</span>
                <div class="mf-cab-t">
                    <h2 id="mf-titulo" class="mf-titulo" tabindex="-1" x-ref="titulo">Flight Surgeon · Local AI (simulated)</h2>
                    <span class="mf-para mono">{{ $astronauta ? 'FOR '.mb_strtoupper($astronauta->nombre).' · ' : '' }}GUIDES, DOES NOT DIAGNOSE</span>
                </div>
                <button type="button" class="mf-cerrar" id="mf-cerrar" aria-label="Close Flight Surgeon" x-on:click="cerrar()"><span class="icono" aria-hidden="true">close</span></button>
            </div>
            <div class="mf-etqs">
                <span class="mf-etq mono"><span class="icono" aria-hidden="true">science</span>SIMULATED</span>
                <span class="mf-etq mf-etq--pronto mono"><span class="icono" aria-hidden="true">schedule</span>{{ mb_strtoupper(config('ia.etiquetas.proximamente', 'Local AI and n8n: Coming soon')) }}</span>
            </div>
        </header>

        <div class="mf-lista" aria-live="{{ $critico ? 'assertive' : 'polite' }}">
            @if (! $astronauta)
                <p class="mf-vacio">Select a crew member</p>
            @elseif (! $mensajes)
                <p class="mf-vacio"><span class="icono" aria-hidden="true">check_circle</span>No open cases for {{ $astronauta->nombre }}</p>
            @endif

            @if ($consulta)
                @include('livewire.mf.consulta')
            @endif

            @foreach ($mensajes as $m)
                @php($c = $m['caso'])
                @php($o = $m['o'])
                @php($esCritico = $c->severidad === 'critico')
                @php($hora = $o->detectadoEn ?: $c->detectado_en?->copy()->utc()->format('H:i'))
                <article id="mf-caso-{{ $c->id }}" class="mf-msg" data-sev="{{ $c->severidad }}" data-estado="{{ $c->estado }}"
                         aria-label="{{ $o->titulo }}, {{ $esCritico ? 'CRITICAL' : 'CAUTION' }}"
                         wire:key="mf-{{ $astronauta->id }}-{{ $c->id }}-{{ $c->severidad }}"
                         x-data="{ det: {{ $loop->first ? 'true' : 'false' }} }">
                    <div class="mf-msg-cab">
                        <span class="icono mf-msg-ic" aria-hidden="true">{{ $o->icono ?: 'stethoscope' }}</span>
                        <span class="mf-msg-t">{{ $o->titulo }}</span>
                        <span class="mf-sev mono"><span class="icono" aria-hidden="true">{{ $sev[$c->severidad][1] ?? 'warning' }}</span>{{ mb_strtoupper($sev[$c->severidad][0] ?? $c->severidad) }}</span>
                        @if ($hora || $o->empeoro || ! $m['abierto'])
                            <span class="mf-hora mono">
                                @if ($hora)DETECTED {{ $hora }} UTC @endif
                                @if ($o->empeoro)@if ($hora) · @endif<span class="mf-empeoro">WORSENED</span>@endif
                                @unless ($m['abierto']) · RESOLVED @endunless
                            </span>
                        @endif
                    </div>

                    @if ($esCritico && $o->escaladoEn)
                        <div class="mf-banda"><span class="icono" aria-hidden="true">satellite_alt</span>Automatically escalated to ground via the alert route · {{ $o->escaladoEn }} UTC</div>
                    @endif

                    <p class="mf-hallazgo">{{ $o->hallazgo }}</p>
                    @if ($o->evidencia)<span class="mf-evid mono">EVIDENCE · {{ $o->evidencia }}</span>@endif

                    <div id="mf-detalle-{{ $c->id }}" class="mf-detalle" x-show="det">
                        @if ($o->acciones)
                            <section class="mf-sec">
                                <h3 class="mf-h3">WHAT TO DO</h3>
                                <ol class="mf-pasos">
                                    @foreach ($o->acciones as $ac)
                                        <li>
                                            <span class="mf-n mono" aria-hidden="true">{{ $loop->iteration }}</span>
                                            <span class="mf-paso">
                                                <span>{{ $ac['texto'] }}</span>
                                                @if (! empty($ac['fuente']['etiqueta']))
                                                    <span class="mf-fuente-t mono" title="{{ $ac['fuente']['texto'] ?? '' }}">SOURCE · {{ $ac['fuente']['etiqueta'] }}@if (isset($ac['fuente']['validado']) && ! $ac['fuente']['validado']) · EXAMPLE VALUE @endif</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>
                        @endif

                        @if ($o->historial7d)
                            <section class="mf-sec">
                                <h3 class="mf-h3">7-DAY HISTORY</h3>
                                <x-caso-historial :o="$o" />
                            </section>
                        @endif

                        <section class="mf-sec">
                            <h3 class="mf-h3">RELEVANT MEDICAL RECORD</h3>
                            @forelse ($o->expedienteRelevante as $x)
                                @php($i = $item($x))
                                <div class="mf-it">@if ($i['etiqueta'])<span class="mf-it-e mono">{{ $i['etiqueta'] }}</span>@endif<span class="mf-it-t">{{ $i['texto'] }}</span>@if ($i['nota'])<span class="mf-it-n">{{ $i['nota'] }}</span>@endif</div>
                            @empty
                                <span class="mf-it-t">No related history</span>
                            @endforelse
                            @if ($o->auditadoEn)
                                <span class="mf-audit mono"><span class="icono" aria-hidden="true">lock</span>Record access logged in audit trail · {{ $o->auditadoEn }} UTC</span>
                            @endif
                        </section>

                        <section class="mf-sec">
                            <h3 class="mf-h3">TREATMENT HISTORY</h3>
                            @forelse ($o->tratamientos as $x)
                                @php($i = $item($x))
                                <div class="mf-it">@if ($i['etiqueta'])<span class="mf-it-e mono">{{ $i['etiqueta'] }}</span>@endif<span class="mf-it-t">{{ $i['texto'] }}</span>@if ($i['nota'])<span class="mf-it-n">{{ $i['nota'] }}</span>@endif</div>
                            @empty
                                <span class="mf-it-t">No treatments recorded</span>
                            @endforelse
                            @if ($o->matiz)<p class="mf-matiz">{{ $o->matiz }}</p>@endif
                        </section>

                        @if ($o->fuentes)
                            @php($pendiente = $o->revisionMedica === 'pendiente')
                            <section class="mf-sec">
                                <h3 class="mf-h3">SOURCES · MEDICAL REVIEW: {{ mb_strtoupper($o->revisionMedica) }}</h3>
                                <div class="mf-chips">
                                    @foreach ($o->fuentes as $f)
                                        <span class="mf-fuente mono" data-pendiente="{{ $pendiente ? '1' : '0' }}" title="{{ $f['texto'] ?? '' }}"><span class="icono" aria-hidden="true">{{ $pendiente ? 'help' : 'description' }}</span>{{ $f['etiqueta'] }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <div class="mf-conf">
                            <div class="mf-conf-f mono"><span>RULE CONFIDENCE · SIMULATED</span><strong>{{ number_format($o->confianza / 100, 2, '.', '') }}</strong></div>
                            <div class="mf-barra" aria-hidden="true"><div class="mf-barra-v" style="width: {{ max(0, min(100, $o->confianza)) }}%"></div></div>
                        </div>
                    </div>

                    <div class="mf-botones">
                        <button type="button" class="mf-btn mf-btn--txt" x-bind:aria-expanded="det ? 'true' : 'false'" aria-controls="mf-detalle-{{ $c->id }}" x-on:click="det = ! det">
                            <span class="icono" aria-hidden="true" x-text="det ? 'expand_less' : 'expand_more'">{{ $loop->first ? 'expand_less' : 'expand_more' }}</span><span x-text="det ? 'Hide details' : 'Show details'">{{ $loop->first ? 'Hide details' : 'Show details' }}</span>
                        </button>
                        <button type="button" class="mf-btn" wire:click="entender({{ $c->id }})"><span class="icono" aria-hidden="true">check</span>Got it</button>
                        <button type="button" class="mf-btn mf-btn--aviso" wire:click="avisarATierra({{ $c->id }})" @disabled($m['avisado']) data-enviado="{{ $m['avisado'] ? '1' : '0' }}">
                            <span class="icono" aria-hidden="true">{{ $m['avisado'] ? 'check' : 'campaign' }}</span>{{ $m['avisado'] ? 'Alert sent to ground' : 'Alert ground' }}
                        </button>
                        <a class="mf-btn mf-btn--abrir" href="{{ route('medico') }}"><span class="icono" aria-hidden="true">open_in_new</span>Open Flight Surgeon</a>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($astronauta)
            <form class="mf-comp" id="mf-comp" wire:submit="enviar" x-data="{ n: 0 }" x-init="n = $refs.texto.value.length"
                  x-on:mf-respuesta.window="$nextTick(() => { n = $refs.texto.value.length })" aria-labelledby="mf-comp-t">
                <label id="mf-comp-t" for="mf-texto" class="mf-h3">TELL ME HOW YOU FEEL</label>
                <div class="mf-atajos" role="group" aria-label="Shortcuts">
                    @foreach (config('orientador.atajos', []) as [$etq, $fill])
                        <button type="button" class="mf-atajo" x-on:click="$refs.texto.value = @js($fill); $refs.texto.dispatchEvent(new Event('input', { bubbles: true })); $refs.texto.focus()">{{ $etq }}</button>
                    @endforeach
                </div>
                @if ($zonasDolor->isNotEmpty())
                    <div class="mf-atajos" role="group" aria-label="Pain areas you already logged">
                        <span class="mf-it-e mono">YOUR LOGGED AREAS</span>
                        @foreach ($zonasDolor as $z)
                            <button type="button" class="mf-atajo mf-atajo--zona" wire:click="usarZona({{ $z->id }})">{{ \App\Models\ZonaDolor::nombre($z->musculo) }} · {{ $z->intensidad }}/10</button>
                        @endforeach
                    </div>
                @endif
                <textarea id="mf-texto" class="mf-texto" x-ref="texto" wire:model="texto" rows="2" maxlength="{{ $maxCaracteres }}" placeholder="For example: I have felt dizzy for an hour (Spanish input understood)"
                          aria-describedby="mf-cuenta @error('texto') mf-texto-err @enderror" @error('texto') aria-invalid="true" @enderror
                          x-on:input="n = $el.value.length" x-on:keydown.ctrl.enter.prevent="$el.form.requestSubmit()"></textarea>
                @error('texto')<p id="mf-texto-err" class="mf-err" role="alert">{{ $message }}</p>@enderror
                <div class="mf-comp-f">
                    <span id="mf-cuenta" class="mf-cuenta mono" x-bind:data-limite="n >= {{ $maxCaracteres }} ? '1' : '0'"><span x-text="n">{{ mb_strlen($texto) }}</span> / {{ $maxCaracteres }}</span>
                    <span class="mf-estado" role="status"><span wire:loading wire:target="enviar,quitarSintoma,anadirSintoma,reevaluar"><span class="icono" aria-hidden="true">hourglass_top</span>Analyzing…</span></span>
                    <button type="submit" class="mf-btn mf-btn--enviar" wire:loading.attr="disabled" wire:target="enviar"><span class="icono" aria-hidden="true">send</span>Send</button>
                </div>
            </form>
        @endif

        <footer class="mf-aviso" role="note"><span class="icono" aria-hidden="true">info</span><span>{{ $aviso }}</span></footer>
    </section>
</div>
