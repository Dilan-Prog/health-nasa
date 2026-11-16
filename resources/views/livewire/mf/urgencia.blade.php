{{-- Banner de urgencia de la respuesta (en emergencia va PRIMERO, con el botón principal). Variables: $r, $urg, $emerg, $U, $avisoConsultaEnviado --}}
<section class="mf-urg" data-urg="{{ $urg }}" aria-labelledby="mf-urg-t">
    <div class="mf-urg-cab">
        <span class="icono mf-urg-ic" aria-hidden="true">{{ $U[$urg][1] ?? 'info' }}</span>
        <h3 id="mf-urg-t" class="mf-urg-t">Urgencia: {{ $U[$urg][0] ?? mb_strtoupper($urg) }}</h3>
        @if (! empty($r['evolucion']))
            <span class="mf-urg-evol mono">REEVALUACIÓN · {{ $r['evolucion'] === 'empeoro' ? 'EMPEORÓ' : 'SIGUE IGUAL' }}</span>
        @endif
    </div>
    @if (! empty($r['conclusion']))
        <p class="mf-urg-txt">{{ $r['conclusion'] }}</p>
    @endif
    @if ($emerg)
        @if (! empty($r['medidas_inmediatas']))
            <ul class="mf-lista-pl" aria-label="Medidas inmediatas">
                @foreach ($r['medidas_inmediatas'] as $m)<li>{{ $m }}</li>@endforeach
            </ul>
        @endif
        <button type="button" class="mf-btn mf-btn--emerg" id="mf-avisar-ahora" wire:click="avisarConsulta" @disabled($avisoConsultaEnviado) data-enviado="{{ $avisoConsultaEnviado ? '1' : '0' }}">
            <span class="icono" aria-hidden="true">{{ $avisoConsultaEnviado ? 'check' : 'campaign' }}</span>{{ $avisoConsultaEnviado ? 'Aviso enviado a tierra' : 'Avisar a tierra ahora' }}
        </button>
    @endif
</section>
