@props(['o'])
{{-- Historial de 7 días de una Orientacion: minigráfica SVG 112×32 por indicador (base discontinua + línea del color de severidad). Compartido por el flotante y el Panel. --}}
<ul class="mf-hist" data-sev="{{ $o->severidad }}">
    @foreach ($o->historial7d as $h)
        @php($g = \App\Livewire\MedicoFlotante::minigrafica($h['serie'] ?? [], $h['base'] ?? null))
        <li class="mf-hist-i">
            <span class="mf-hist-et">{{ $h['etiqueta'] }}</span>
            <span class="mf-hist-v mono">NOW {{ $h['actual'] }} {{ $h['unidad'] }}@if (! empty($h['base'])) · BASELINE {{ $h['base'] }} {{ $h['unidad'] }}@endif</span>
            @if ($g['puntos'])
                <svg class="mf-hist-svg" viewBox="0 0 120 32" preserveAspectRatio="none" role="img"
                     aria-label="{{ $h['etiqueta'] }}: 7 days, now {{ $h['actual'] }} {{ $h['unidad'] }}@if (! empty($h['base'])), your baseline {{ $h['base'] }}@endif">
                    @if ($g['yBase'] !== null)
                        <line class="mf-hist-base" x1="0" x2="120" y1="{{ $g['yBase'] }}" y2="{{ $g['yBase'] }}" stroke-width="1" stroke-dasharray="4 3" vector-effect="non-scaling-stroke"></line>
                    @endif
                    <polyline class="mf-hist-linea" points="{{ $g['puntos'] }}" fill="none" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>
                </svg>
            @endif
        </li>
    @endforeach
</ul>
