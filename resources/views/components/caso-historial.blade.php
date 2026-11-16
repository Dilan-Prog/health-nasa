@props(['o'])
{{-- Historial de 7 días de una Orientacion: minigráfica SVG simple por indicador + valores (compartido por el flotante y el Panel). --}}
<ul class="mf-hist">
    @foreach ($o->historial7d as $h)
        @php($pts = \App\Livewire\MedicoFlotante::puntosSerie($h['serie'] ?? []))
        <li class="mf-hist-i">
            <span class="mf-hist-et">{{ $h['etiqueta'] }}</span>
            @if ($pts)
                <svg class="mf-hist-svg" viewBox="0 0 100 28" preserveAspectRatio="none" role="img"
                     aria-label="Últimos 7 días de {{ $h['etiqueta'] }}: valor actual {{ $h['actual'] }} {{ $h['unidad'] }}">
                    <polyline points="{{ $pts }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
                </svg>
            @endif
            <span class="mf-hist-v mono">{{ $h['actual'] }} {{ $h['unidad'] }}@if (! empty($h['base'])) <span class="mf-hist-b">· base {{ $h['base'] }}</span>@endif</span>
        </li>
    @endforeach
</ul>
