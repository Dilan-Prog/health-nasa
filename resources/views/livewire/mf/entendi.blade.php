{{-- "Entendí": chips editables (quitar / añadir). El texto de síntomas viene del análisis, siempre escapado. Variables: $consulta, $e, $sintomas, $parcial --}}
<x-mf-seccion id="mf-s-entendi" titulo="1 · ENTENDÍ" icono="hearing" :abierta="true">
    <p class="mf-cita"><span class="mf-it-e mono">TU MENSAJE</span><span class="mf-cita-t">{{ $consulta->texto }}</span></p>
    @if ($sintomas)
        <ul class="mf-chips mf-chips--ed" aria-label="Síntomas que entendí">
            @foreach ($sintomas as $i => $s)
                <li class="mf-chip-ed">
                    <span class="mf-chip-et">{{ $s['etiqueta'] ?? $s['clave'] ?? '' }}@if (! empty($s['intensidad'])) · {{ $s['intensidad'] }}/10 @endif @if (! empty($s['duracion_min'])) · {{ $s['duracion_min'] }} min @endif</span>
                    <button type="button" class="mf-chip-x" wire:click="quitarSintoma({{ $i }})" aria-label="Quitar {{ $s['etiqueta'] ?? $s['clave'] ?? 'síntoma' }}"><span class="icono" aria-hidden="true">close</span></button>
                </li>
            @endforeach
        </ul>
        @if ($parcial)<p class="mf-nota">Entendí solo en parte: revisa los síntomas y corrígelos si hace falta.</p>@endif
        @if (isset($e['confianza']))<span class="mf-evid mono">CONFIANZA DE LA INTERPRETACIÓN · {{ (int) $e['confianza'] }} %</span>@endif
    @else
        <p class="mf-nota">No entendí ningún síntoma en lo que escribiste.</p>
    @endif
    @if (! empty($e['negados']))
        <p class="mf-evid mono">NEGADOS · {{ implode(', ', array_map(fn ($k) => $sintomasCfg[$k] ?? $k, $e['negados'])) }}</p>
    @endif
    <div class="mf-anadir" role="group" aria-label="Añadir un síntoma">
        <select class="mf-sel" wire:model="nuevoSintoma" aria-label="Síntoma a añadir">
            <option value="">Añadir síntoma…</option>
            @foreach ($sintomasCfg as $k => $et)<option value="{{ $k }}">{{ $et }}</option>@endforeach
        </select>
        <select class="mf-sel" wire:model="nuevaZona" aria-label="Zona del cuerpo (opcional)">
            <option value="">Zona (opcional)</option>
            @foreach ($zonasCfg as $k => $et)<option value="{{ $k }}">{{ $et }}</option>@endforeach
        </select>
        <select class="mf-sel" wire:model="nuevoLado" aria-label="Lado (opcional)">
            <option value="">Lado</option>
            <option value="izquierdo">Izquierdo</option>
            <option value="derecho">Derecho</option>
        </select>
        <button type="button" class="mf-btn" wire:click="anadirSintoma"><span class="icono" aria-hidden="true">add</span>Añadir</button>
    </div>
</x-mf-seccion>
