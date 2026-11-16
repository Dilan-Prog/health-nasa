@props(['tipo', 'revision' => null, 'validado' => false, 'label' => null])
@php
    $etiqueta = $label ?? (\App\Models\Requisito::TIPOS[$tipo] ?? (string) $tipo);
    // La norma ya lleva su revisión en la etiqueta del tipo; no se repite.
    $rev = ($revision && $revision !== $etiqueta) ? $revision : null;
@endphp
<span {{ $attributes->class(['fuente-chip']) }} data-tipo="{{ $tipo }}" data-validado="{{ $validado ? '1' : '0' }}">
    <span class="icono fuente-ic" aria-hidden="true">menu_book</span>
    <span class="fuente-et">Fuente: {{ $etiqueta }}</span>
    @if ($rev)<span class="fuente-rev mono">{{ $rev }}</span>@endif
    @unless ($validado)<span class="fuente-ejemplo">valor de ejemplo</span>@endunless
</span>
