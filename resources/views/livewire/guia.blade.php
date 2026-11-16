<div class="guia">
    <x-encabezado titulo-a="Guía educativa" :titulo-b="$astronauta ? 'Tripulante '.$astronauta->nombre : 'Sin tripulante'" />

    <main class="guia-main" id="guia-contenido"
          x-data="{ q: '', coincide(t) { const q = this.q.trim().toLowerCase(); return q === '' || t.includes(q); },
                    get vacias() { return this.q.trim() !== '' && ![...$el.querySelectorAll('[data-guia]')].some(e => this.coincide(e.dataset.guia)); } }">
        <div class="guia-cabecera">
            <span class="mono guia-kicker">GUÍA EDUCATIVA</span>
            <h2 class="guia-titulo">Cómo afecta el espacio a tu cuerpo</h2>
            <p class="guia-sub">Qué mide cada indicador, por qué cambia en microgravedad y qué puedes hacer.</p>
        </div>

        <div class="guia-buscador">
            <span class="icono" aria-hidden="true">search</span>
            <input type="search" class="guia-entrada" x-model="q" placeholder="Buscar indicador o sistema…" aria-label="Buscar indicador o sistema" autocomplete="off">
        </div>

        <div class="guia-rejilla">
            @foreach (array_merge($tarjetas, $extras) as $t)
                <article class="guia-tarjeta" data-guia="{{ $t['busca'] }}" x-show="coincide($el.dataset.guia)" wire:key="guia-{{ $t['clave'] }}" aria-labelledby="guia-{{ $t['clave'] }}-t">
                    <div class="guia-tarjeta-cab">
                        <span class="icono guia-ic" aria-hidden="true">{{ $t['ic'] }}</span>
                        <h3 class="guia-tarjeta-t" id="guia-{{ $t['clave'] }}-t">{{ $t['label'] }}</h3>
                        <span class="guia-parte">{{ $t['parte'] }}</span>
                    </div>
                    <div class="guia-rango"><span class="guia-et">Rango: </span><span class="mono">{{ $t['rango'] }}</span></div>
                    <p class="guia-texto">{{ $t['texto'] }}</p>
                    <p class="guia-hacer"><strong>Qué hacer:</strong> {{ $t['rec'] }}</p>
                    @if ($t['contexto'])
                        <p class="guia-contexto"><strong>Contexto:</strong> {{ $t['contexto'] }}</p>
                    @endif
                    <div class="fuente-chips">
                        @forelse ($t['fuentes'] as $f)
                            <x-fuente-chip :tipo="$f['tipo']" :revision="$f['revision']" :validado="$f['validado']" :label="$f['etiqueta']" />
                            @if ($f['codigo'])<span class="mono fuente-cod">{{ $f['codigo'] }}</span>@endif
                        @empty
                            <span class="fuente-sin">Umbral de ejemplo (sin requisito asociado)</span>
                        @endforelse
                    </div>
                    @if ($t['estado'])
                        <div class="guia-tuyo" data-estado="{{ $t['estado'] }}">
                            <span class="guia-et">Tu último valor</span>
                            <span class="mono guia-valor">{{ $t['valor'] }} <span class="guia-unidad">{{ $t['unidad'] }}</span></span>
                            <span class="mono guia-pildora"><span class="icono" aria-hidden="true">{{ $t['estado_txt'][1] }}</span>{{ $t['estado_txt'][0] }}</span>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="guia-vacio" x-show="vacias" x-cloak role="status">
            <span class="icono" aria-hidden="true">search_off</span>
            <strong>Sin resultados</strong>
            <span>Prueba con otro término, por ejemplo «hueso», «sueño» o «radiación».</span>
        </div>

        <p class="guia-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>

        <x-peligros-riesgos />
    </main>
</div>
