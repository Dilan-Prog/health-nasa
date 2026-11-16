@php
    $sev = ['critico' => ['Crítico', 'error'], 'precaucion' => ['Precaución', 'warning']];
@endphp
<div class="mf" data-critico="{{ $critico ? '1' : '0' }}" wire:poll.10s="comprobar"
     x-data="{
        open: false,
        abrir(auto = false, d = null) {
            this.open = true;
            this.$nextTick(() => {
                if (d && d.casoId) { const m = document.getElementById('mf-caso-' + d.casoId); if (m) m.scrollIntoView({ block: 'nearest' }); }
                const a = document.activeElement;
                const escribiendo = a && a !== document.body && a.matches && a.matches('input, textarea, select, [contenteditable]');
                if (! auto || ! escribiendo) this.$refs.panel.focus({ preventScroll: true });
            });
            $wire.marcarLeidos();
        },
        cerrar() { this.open = false; this.$nextTick(() => this.$refs.circulo.focus()); },
     }"
     x-init="if (@js($abrirAlCargar)) abrir(true)"
     x-on:medico-flotante-abrir.window="abrir(true, Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     x-on:abrir-medico-flotante.window="abrir(false, Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
     x-on:keydown.escape.window="if (open) cerrar()"
     x-bind:class="{ 'is-abierto': open }">

    <button type="button" class="mf-circulo" x-ref="circulo" aria-label="Médico de vuelo" aria-haspopup="dialog"
            x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="mf-panel"
            x-on:click="open ? cerrar() : abrir(false)">
        <span class="icono" aria-hidden="true">stethoscope</span>
        @if ($nSinLeer > 0)
            <span class="mf-insignia mono" aria-label="{{ $nSinLeer }} sin leer">{{ $nSinLeer }}</span>
        @endif
    </button>

    {{-- Anuncio para lector de pantalla: cortés; asertivo solo si hay un caso crítico. --}}
    <div class="mf-sr" role="status" aria-live="{{ $critico ? 'assertive' : 'polite' }}" aria-atomic="true">{{ $anuncio }}</div>

    <section id="mf-panel" class="mf-panel" role="dialog" aria-modal="false" aria-labelledby="mf-titulo" tabindex="-1"
             x-ref="panel" x-show="open" x-cloak>
        <header class="mf-cab">
            <div class="mf-cab-t">
                <h2 id="mf-titulo" class="mf-titulo"><span class="icono" aria-hidden="true">stethoscope</span>Médico de vuelo · IA local (simulada)</h2>
                <div class="mf-etqs">
                    <span class="mf-etq mono">Simulado</span>
                    <span class="mf-etq mf-etq--pronto mono">{{ config('ia.etiquetas.proximamente', 'IA local y n8n: Próximamente') }}</span>
                </div>
            </div>
            <button type="button" class="mf-cerrar" aria-label="Cerrar Médico de vuelo" x-on:click="cerrar()"><span class="icono" aria-hidden="true">close</span></button>
        </header>

        <div class="mf-lista">
            @if (! $astronauta)
                <p class="mf-vacio">Elige un tripulante.</p>
            @elseif (! $mensajes)
                <p class="mf-vacio">Sin casos abiertos para {{ $astronauta->nombre }}. Todo en rango según el detector simulado.</p>
            @endif

            @foreach ($mensajes as $m)
                @php($c = $m['caso'])
                @php($o = $m['o'])
                <article id="mf-caso-{{ $c->id }}" class="mf-msg" data-sev="{{ $c->severidad }}" data-estado="{{ $c->estado }}" wire:key="mf-{{ $astronauta->id }}-{{ $c->id }}-{{ $c->severidad }}">
                    <div class="mf-msg-cab">
                        <span class="mf-sev mono"><span class="icono" aria-hidden="true">{{ $sev[$c->severidad][1] ?? 'warning' }}</span>{{ $sev[$c->severidad][0] ?? ucfirst($c->severidad) }}</span>
                        @unless ($m['abierto'])<span class="mf-sev mf-sev--cerrado mono">Resuelto</span>@endunless
                        <span class="mf-hora mono">{{ $c->detectado_en?->copy()->utc()->format('H:i') }} UTC</span>
                    </div>
                    <h3 class="mf-msg-t">{{ $o->titulo }}</h3>
                    <p class="mf-hallazgo">{{ $o->hallazgo }}</p>

                    @if ($o->acciones)
                        <h4 class="mf-sub">Qué hacer</h4>
                        <ol class="mf-pasos">
                            @foreach ($o->acciones as $ac)
                                <li>
                                    <span>{{ $ac['texto'] }}</span>
                                    @if (! empty($ac['fuente']))
                                        <x-fuente-chip :tipo="$ac['fuente']['tipo'] ?? 'ficha'" :revision="$ac['fuente']['revision'] ?? null" :validado="(bool) ($ac['fuente']['validado'] ?? true)" :label="$ac['fuente']['etiqueta'] ?? null" :title="$ac['fuente']['texto'] ?? null" />
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ($o->historial7d)
                        <h4 class="mf-sub">Historial de 7 días</h4>
                        <x-caso-historial :o="$o" />
                    @endif

                    <h4 class="mf-sub">Expediente relevante</h4>
                    <ul class="mf-lis">@foreach ($o->expedienteRelevante as $x)<li>{{ $x }}</li>@endforeach</ul>

                    <h4 class="mf-sub">Historial de tratamientos</h4>
                    <ul class="mf-lis">@foreach ($o->tratamientos as $x)<li>{{ $x }}</li>@endforeach</ul>

                    @if ($o->fuentes)
                        <h4 class="mf-sub">Fuentes</h4>
                        <div class="fuente-chips">
                            @foreach ($o->fuentes as $f)
                                <x-fuente-chip tipo="ficha" :validado="true" :label="$f['etiqueta']" :title="$f['texto'] ?? null" />
                            @endforeach
                        </div>
                    @endif

                    <p class="mf-conf mono">Confianza simulada: {{ $o->confianza }} % · origen {{ $o->origen }}</p>

                    <div class="mf-botones">
                        <button type="button" class="mf-btn" wire:click="entender({{ $c->id }})"><span class="icono" aria-hidden="true">done</span>Entendido</button>
                        <button type="button" class="mf-btn mf-btn--aviso" wire:click="avisarATierra({{ $c->id }})" @disabled($m['avisado'])>
                            <span class="icono" aria-hidden="true">{{ $m['avisado'] ? 'check' : 'campaign' }}</span>{{ $m['avisado'] ? 'Aviso en cola a tierra' : 'Avisar a tierra' }}
                        </button>
                        <a class="mf-btn" href="{{ route('medico') }}"><span class="icono" aria-hidden="true">open_in_new</span>Abrir Médico de vuelo</a>
                    </div>
                </article>
            @endforeach
        </div>

        <footer class="mf-aviso" role="note"><span class="icono" aria-hidden="true">info</span><span>{{ $aviso }}</span></footer>
    </section>
</div>
