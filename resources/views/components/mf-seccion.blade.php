@props(['id', 'titulo', 'abierta' => true, 'icono' => null])
{{-- Sección plegable de la tarjeta de respuesta del Médico de vuelo flotante. El contenido va siempre en el DOM (x-show). --}}
<section {{ $attributes->class(['mf-sec', 'mf-plegable']) }} x-data="{ o: {{ $abierta ? 'true' : 'false' }} }">
    <h3 class="mf-h3 mf-h3--btn">
        <button type="button" class="mf-pleg" x-bind:aria-expanded="o ? 'true' : 'false'" aria-controls="{{ $id }}" x-on:click="o = ! o">
            @if ($icono)<span class="icono mf-pleg-ic" aria-hidden="true">{{ $icono }}</span>@endif
            <span class="mf-pleg-t">{{ $titulo }}</span>
            <span class="icono mf-pleg-flecha" aria-hidden="true" x-text="o ? 'expand_less' : 'expand_more'">{{ $abierta ? 'expand_less' : 'expand_more' }}</span>
        </button>
    </h3>
    <div id="{{ $id }}" class="mf-sec-cuerpo" x-show="o">{{ $slot }}</div>
</section>
