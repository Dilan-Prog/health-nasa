<div class="sis">
    <x-encabezado titulo-a="Body systems" :titulo-b="$astronauta ? 'Crew member '.$astronauta->nombre : 'No crew member'" />

    <main class="sis-main" id="sistemas-contenido">
        <div class="sis-cabecera">
            <span class="mono sis-kicker">BODY SYSTEMS</span>
            <h2 class="sis-titulo">Body systems</h2>
            <p class="sis-sub">Status by system · {{ $astronauta ? $astronauta->nombre : 'No crew member' }}</p>
            <p class="sis-intro">The color of each system reflects its most affected indicator. Tap an indicator to see the detail.</p>
        </div>

        @if (! $astronauta)
            <div class="sis-vacio" role="status">
                <span class="icono" aria-hidden="true">person_off</span>
                <strong>No crew member selected</strong>
                <span>Select a crew member to see the status of their body systems.</span>
            </div>
        @else
            <div class="sis-rejilla">
                @foreach ($tarjetas as $t)
                    <section class="sis-tarjeta" data-estado="{{ $t['estado'] }}" wire:key="sis-{{ $t['indice'] }}" aria-labelledby="sis-{{ $t['indice'] }}-t">
                        <div class="sis-tarjeta-cab">
                            <span class="icono sis-ic" aria-hidden="true">{{ $t['ic'] }}</span>
                            <h3 class="sis-tarjeta-t" id="sis-{{ $t['indice'] }}-t">{{ $t['titulo'] }}</h3>
                            <span class="mono sis-pildora"><span class="icono" aria-hidden="true">{{ $t['estado_ic'] }}</span>{{ $t['estado_txt'] }}</span>
                        </div>
                        <ul class="sis-filas">
                            @foreach ($t['filas'] as $f)
                                <li>
                                    <a class="sis-fila" href="{{ $f['url'] }}" data-estado="{{ $f['estado'] }}">
                                        <span class="sis-punto icono" aria-hidden="true">{{ $f['estado_ic'] }}</span>
                                        <span class="sis-fila-t">{{ $f['label'] }}</span>
                                        <span class="mono sis-valor">{{ $f['valor'] }}</span>
                                        <span class="sis-unidad">{{ $f['unidad'] }}</span>
                                        <span class="sis-solo-lectores">{{ $f['estado_txt'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if ($t['sin_cobertura'])
                            <p class="sis-pendiente">No coverage yet.@if ($t['pendiente']) Not yet measured: {{ $t['pendiente'] }}@endif</p>
                        @endif
                        @if ($t['proximamente'])
                            <p class="sis-pendiente prox-linea" data-proximamente><span class="mono prox-etiqueta">Coming soon</span>: {{ implode(' · ', $t['proximamente']) }}</p>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif

        <p class="sis-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Guidance only, not a diagnosis. Consult the Flight Surgeon if in doubt.</p>
    </main>
</div>
