<div class="pag">
    <x-encabezado titulo-a="Ground" titulo-b="queue" />

    <main class="pag-main" id="cola-tierra-contenido">
        <section class="pag-card pag-resumen" aria-label="Queue summary">
            <span class="pag-chip mono" data-estado="{{ $pendientes ? 'warn' : 'ok' }}">
                <span class="icono" aria-hidden="true">schedule</span>{{ $pendientes }} pending
            </span>
            <span class="pag-enlace mono" data-estado="{{ $enlace ? 'ok' : 'warn' }}">
                <span class="icono" aria-hidden="true">satellite_alt</span>{{ $enlace ? 'LINK ACTIVE · RESPONSE ~1 MIN' : 'NO LINK · ALERTS WAIT' }}
            </span>
        </section>

        <section class="pag-card" aria-label="Alerts to ground">
            <h2 class="pag-h2">Alerts to ground</h2>
            @if (! $avisos)
                <p class="pag-vacio">No alerts in the queue.</p>
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
                                @if ($a['enviada_en'])<br>sent {{ $a['enviada_en'] }}@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="pag-nota">Showing the {{ $limite }} most recent alerts.</p>
            @endif
        </section>
    </main>
</div>
