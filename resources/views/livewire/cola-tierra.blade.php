<div class="pag">
    <x-encabezado titulo-a="Cola a" titulo-b="Tierra" />

    <main class="pag-main" id="cola-tierra-contenido">
        <section class="pag-card pag-resumen" aria-label="Resumen de la cola">
            <span class="pag-chip mono" data-estado="{{ $pendientes ? 'warn' : 'ok' }}">
                <span class="icono" aria-hidden="true">schedule</span>{{ $pendientes }} pendientes
            </span>
            <span class="pag-enlace mono" data-estado="{{ $enlace ? 'ok' : 'warn' }}">
                <span class="icono" aria-hidden="true">satellite_alt</span>{{ $enlace ? 'ENLACE ACTIVO · RESPUESTA ~1 MIN' : 'SIN ENLACE · LOS AVISOS ESPERAN' }}
            </span>
        </section>

        <section class="pag-card" aria-label="Avisos a tierra">
            <h2 class="pag-h2">Avisos a tierra</h2>
            @if (! $avisos)
                <p class="pag-vacio">No hay avisos en la cola.</p>
            @else
                <ul class="pag-avisos">
                    @foreach ($avisos as $a)
                        <li class="pag-aviso" wire:key="aviso-{{ $a['id'] }}">
                            <span class="pag-chip" data-estado="{{ $a['enviada'] ? 'ok' : 'warn' }}"><span class="icono" aria-hidden="true">{{ $a['icono'] }}</span>{{ $a['estado'] }}</span>
                            <div class="pag-aviso-cuerpo">
                                <strong>{{ $a['tripulante'] }}</strong>
                                <span class="pag-nota">{{ $a['resumen'] }}</span>
                            </div>
                            <span class="pag-hora mono">
                                {{ $a['creada'] }} UTC
                                @if ($a['enviada_en'])<br>enviada {{ $a['enviada_en'] }}@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="pag-nota">Se muestran los {{ $limite }} avisos más recientes.</p>
            @endif
        </section>
    </main>
</div>
