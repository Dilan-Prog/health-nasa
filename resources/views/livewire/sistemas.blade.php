<div class="sis">
    <x-encabezado titulo-a="Sistemas corporales" :titulo-b="$astronauta ? 'Tripulante '.$astronauta->nombre : 'Sin tripulante'" />

    <main class="sis-main" id="sistemas-contenido">
        <div class="sis-cabecera">
            <span class="mono sis-kicker">SISTEMAS CORPORALES</span>
            <h2 class="sis-titulo">Sistemas corporales</h2>
            <p class="sis-sub">Estado por sistema · {{ $astronauta ? $astronauta->nombre : 'Sin tripulante' }}</p>
            <p class="sis-intro">El color de cada sistema refleja su indicador más comprometido. Toca un indicador para ver el detalle.</p>
        </div>

        @if (! $astronauta)
            <div class="sis-vacio" role="status">
                <span class="icono" aria-hidden="true">person_off</span>
                <strong>Sin tripulante seleccionado</strong>
                <span>Elige un tripulante para ver el estado de sus sistemas corporales.</span>
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
                            <p class="sis-pendiente">Sin cobertura todavía.@if ($t['pendiente']) Falta medir: {{ $t['pendiente'] }}@endif</p>
                        @endif
                        @if ($t['proximamente'])
                            <p class="sis-pendiente prox-linea" data-proximamente><span class="mono prox-etiqueta">Próximamente</span>: {{ implode(' · ', $t['proximamente']) }}</p>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif

        <p class="sis-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>
    </main>
</div>
