{{-- "Entendí": chips editables (quitar / añadir). El texto de síntomas viene del análisis, siempre escapado. Variables: $consulta, $e, $sintomas, $parcial --}}
<x-mf-seccion id="mf-s-entendi" titulo="1 · WHAT I UNDERSTOOD" icono="hearing" :abierta="true">
    <p class="mf-cita"><span class="mf-it-e mono">YOUR MESSAGE</span><span class="mf-cita-t">{{ $consulta->texto }}</span></p>
    @if ($sintomas)
        <ul class="mf-chips mf-chips--ed" aria-label="Symptoms I understood">
            @foreach ($sintomas as $i => $s)
                <li class="mf-chip-ed">
                    <span class="mf-chip-et">{{ $s['etiqueta'] ?? $s['clave'] ?? '' }}@if (! empty($s['intensidad'])) · {{ $s['intensidad'] }}/10 @endif @if (! empty($s['duracion_min'])) · {{ $s['duracion_min'] }} min @endif</span>
                    <button type="button" class="mf-chip-x" wire:click="quitarSintoma({{ $i }})" aria-label="Remove {{ $s['etiqueta'] ?? $s['clave'] ?? 'symptom' }}"><span class="icono" aria-hidden="true">close</span></button>
                </li>
            @endforeach
        </ul>
        @if ($parcial)<p class="mf-nota">I only partly understood: check the symptoms and correct them if needed.</p>@endif
        @if (isset($e['confianza']))<span class="mf-evid mono">INTERPRETATION CONFIDENCE · {{ (int) $e['confianza'] }} %</span>@endif
    @else
        <p class="mf-nota">I did not recognize any symptom in what you wrote.</p>
    @endif
    @if (! empty($e['negados']))
        <p class="mf-evid mono">DENIED · {{ implode(', ', array_map(fn ($k) => $sintomasCfg[$k] ?? $k, $e['negados'])) }}</p>
    @endif
    <div class="mf-anadir" role="group" aria-label="Add a symptom">
        <select class="mf-sel" wire:model="nuevoSintoma" aria-label="Symptom to add">
            <option value="">Add symptom…</option>
            @foreach ($sintomasCfg as $k => $et)<option value="{{ $k }}">{{ $et }}</option>@endforeach
        </select>
        <select class="mf-sel" wire:model="nuevaZona" aria-label="Body area (optional)">
            <option value="">Area (optional)</option>
            @foreach ($zonasCfg as $k => $et)<option value="{{ $k }}">{{ $et }}</option>@endforeach
        </select>
        <select class="mf-sel" wire:model="nuevoLado" aria-label="Side (optional)">
            <option value="">Side</option>
            <option value="izquierdo">Left</option>
            <option value="derecho">Right</option>
        </select>
        <button type="button" class="mf-btn" wire:click="anadirSintoma"><span class="icono" aria-hidden="true">add</span>Add</button>
    </div>
</x-mf-seccion>
