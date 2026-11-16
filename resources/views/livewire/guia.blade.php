<div class="guia">
    <x-encabezado titulo-a="Educational guide" :titulo-b="$astronauta ? 'Crew member '.$astronauta->nombre : 'No crew member'" />

    <main class="guia-main" id="guia-contenido"
          x-data="{ q: '', coincide(t) { const q = this.q.trim().toLowerCase(); return q === '' || t.includes(q); },
                    get vacias() { return this.q.trim() !== '' && ![...$el.querySelectorAll('[data-guia]')].some(e => this.coincide(e.dataset.guia)); } }">
        <div class="guia-cabecera">
            <span class="mono guia-kicker">EDUCATIONAL GUIDE</span>
            <h2 class="guia-titulo">How space affects your body</h2>
            <p class="guia-sub">What each indicator measures, why it changes in microgravity, and what you can do.</p>
        </div>

        <div class="guia-buscador">
            <span class="icono" aria-hidden="true">search</span>
            <input type="search" class="guia-entrada" x-model="q" placeholder="Search indicator or system…" aria-label="Search indicator or system" autocomplete="off">
        </div>

        <div class="guia-rejilla">
            @foreach (array_merge($tarjetas, $extras) as $t)
                <article class="guia-tarjeta" data-guia="{{ $t['busca'] }}" x-show="coincide($el.dataset.guia)" wire:key="guia-{{ $t['clave'] }}" aria-labelledby="guia-{{ $t['clave'] }}-t">
                    <div class="guia-tarjeta-cab">
                        <span class="icono guia-ic" aria-hidden="true">{{ $t['ic'] }}</span>
                        <h3 class="guia-tarjeta-t" id="guia-{{ $t['clave'] }}-t">{{ $t['label'] }}</h3>
                        <span class="guia-parte">{{ $t['parte'] }}</span>
                    </div>
                    <div class="guia-rango"><span class="guia-et">Range: </span><span class="mono">{{ $t['rango'] }}</span></div>
                    <p class="guia-texto">{{ $t['texto'] }}</p>
                    <p class="guia-hacer"><strong>What to do:</strong> {{ $t['rec'] }}</p>
                    @if ($t['contexto'])
                        <p class="guia-contexto"><strong>Context:</strong> {{ $t['contexto'] }}</p>
                    @endif
                    <div class="fuente-chips">
                        @forelse ($t['fuentes'] as $f)
                            <x-fuente-chip :tipo="$f['tipo']" :revision="$f['revision']" :validado="$f['validado']" :label="$f['etiqueta']" />
                            @if ($f['codigo'])<span class="mono fuente-cod">{{ $f['codigo'] }}</span>@endif
                        @empty
                            <span class="fuente-sin">Example threshold (no associated requirement)</span>
                        @endforelse
                    </div>
                    @if ($t['estado'])
                        <div class="guia-tuyo" data-estado="{{ $t['estado'] }}">
                            <span class="guia-et">Your last value</span>
                            <span class="mono guia-valor">{{ $t['valor'] }} <span class="guia-unidad">{{ $t['unidad'] }}</span></span>
                            <span class="mono guia-pildora"><span class="icono" aria-hidden="true">{{ $t['estado_txt'][1] }}</span>{{ $t['estado_txt'][0] }}</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="guia-vacio" x-show="vacias" x-cloak role="status">
            <span class="icono" aria-hidden="true">search_off</span>
            <strong>No results</strong>
            <span>Try another term, for example “bone”, “sleep”, or “radiation”.</span>
        </div>

        @if ($proximamente)
            <section class="prox" aria-labelledby="guia-prox-t">
                <h3 class="prox-titulo" id="guia-prox-t">Coming soon</h3>
                <p class="prox-sub">Planned biomarkers that are not measured yet.</p>
                <ul class="prox-lista">
                    @foreach ($proximamente as $p)
                        <li class="prox-item" data-proximamente aria-disabled="true">
                            <span class="prox-nombre">{{ $p['nombre'] }}</span>
                            <span class="mono prox-etiqueta">Coming soon</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($plataforma)
            <section class="prox prox-plataforma" aria-labelledby="guia-plat-t">
                <h3 class="prox-titulo" id="guia-plat-t">Platform</h3>
                <p class="prox-sub">Planned technical components that do not exist yet.</p>
                <ul class="prox-lista">
                    @foreach ($plataforma as $p)
                        <li class="prox-item prox-item-plat" data-proximamente aria-disabled="true">
                            <span class="prox-texto">
                                <span class="prox-nombre">{{ $p['nombre'] }}</span>
                                <span class="prox-desc">{{ $p['descripcion'] }}</span>
                            </span>
                            <span class="mono prox-etiqueta">Coming soon</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="guia-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Guidance only, not a diagnosis. Consult the Flight Surgeon if in doubt.</p>

        <x-peligros-riesgos />
    </main>
</div>
