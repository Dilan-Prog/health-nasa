<div class="rounded-xl bg-nasa-navy text-white p-4 sm:p-6">
    <h2 class="text-base font-bold mb-1">Body model · example</h2>
    <p class="text-xs text-blue-200 mb-4">Fixed example data. Hover, use Tab/Enter, or tap a muscle.</p>

    <div class="flex gap-2 mb-4">
        <button type="button" wire:click="$set('ejemplo', 'a')" class="px-3 py-1.5 rounded text-sm border border-white/40 {{ $ejemplo === 'a' ? 'bg-white text-nasa-navy font-semibold' : '' }}">Example A</button>
        <button type="button" wire:click="$set('ejemplo', 'b')" class="px-3 py-1.5 rounded text-sm border border-white/40 {{ $ejemplo === 'b' ? 'bg-white text-nasa-navy font-semibold' : '' }}">Example B</button>
    </div>

    <x-silueta-corporal :activos="$activos" class="silueta-corporal--oscuro" />

    <p class="mt-4 text-sm" aria-live="polite">
        Last muscle selected:
        <strong>{{ $nombreSeleccionado ?? ($seleccionado ?? 'none') }}</strong>
    </p>
</div>
