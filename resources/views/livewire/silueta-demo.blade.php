<div class="rounded-xl bg-nasa-navy text-white p-4 sm:p-6">
    <h2 class="text-base font-bold mb-1">Modelo corporal · ejemplo</h2>
    <p class="text-xs text-blue-200 mb-4">Datos fijos de ejemplo. Pasa el cursor, usa Tab/Enter o toca un músculo.</p>

    <div class="flex gap-2 mb-4">
        <button type="button" wire:click="$set('ejemplo', 'a')" class="px-3 py-1.5 rounded text-sm border border-white/40 {{ $ejemplo === 'a' ? 'bg-white text-nasa-navy font-semibold' : '' }}">Ejemplo A</button>
        <button type="button" wire:click="$set('ejemplo', 'b')" class="px-3 py-1.5 rounded text-sm border border-white/40 {{ $ejemplo === 'b' ? 'bg-white text-nasa-navy font-semibold' : '' }}">Ejemplo B</button>
    </div>

    <x-silueta-corporal :activos="$activos" />

    <p class="mt-4 text-sm" aria-live="polite">
        Último músculo seleccionado:
        <strong>{{ $nombreSeleccionado ?? ($seleccionado ?? 'ninguno') }}</strong>
    </p>
</div>
