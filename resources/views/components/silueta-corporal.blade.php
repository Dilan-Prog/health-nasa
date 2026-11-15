{{--
    Silueta corporal anatómica (vistas frontal, lateral y posterior), SVG en línea con cada músculo como pieza independiente.

    Uso:   <x-silueta-corporal :activos="['pectoral_mayor_der', 'biceps_izq']" vista="frontal" />
    Props: activos (array de ids a resaltar; un id presente en varias vistas se resalta en todas),
           vista (frontal | lateral | posterior; la vista inicial, el usuario puede cambiarla arriba a la derecha).
           Cualquier atributo extra (class, etc.) va al contenedor.
    Evento al hacer clic o pulsar Enter/Espacio sobre un músculo:
        Livewire.dispatch('musculo-seleccionado', { id })   -> #[On('musculo-seleccionado')] public function x(string $id)
        y un CustomEvent DOM del mismo nombre que burbujea desde el contenedor.
    Las formas (atributo d) son las de los SVG originales, sin tocar (resources/views/silueta/*.blade.php, generados).
    Los colores viven en resources/css/silueta-corporal.css como variables CSS.
--}}
@props(['activos' => [], 'vista' => 'frontal'])
@php
    use App\Support\MusculosCuerpo;

    $vista = array_key_exists($vista, MusculosCuerpo::VISTAS) ? $vista : 'frontal';
    $uid = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)); // ids únicos de <mask> por instancia
    $set = array_flip(array_map('strval', (array) $activos));
    $nombre = fn (string $id): string => MusculosCuerpo::nombre($id) ?? $id;
    $etiqueta = fn (string $id): string => $nombre($id).(isset($set[$id]) ? ', resaltado' : '');
    $conocidos = MusculosCuerpo::ids();
    $desconocidos = array_values(array_diff(array_keys($set), $conocidos));
    $resaltados = array_map($nombre, array_values(array_intersect($conocidos, array_keys($set))));
@endphp
<div {{ $attributes->class(['silueta-corporal']) }}
     x-data="{
        vista: '{{ $vista }}',
        tip: { visible: false, texto: '', x: 0, y: 0 },
        musculo(e) { return e.target.closest ? e.target.closest('[data-musculo]') : null },
        mostrar(e) {
            const m = this.musculo(e); if (! m) { return this.tip.visible = false }
            const caja = this.$refs.caja.getBoundingClientRect();
            if (e.type.startsWith('pointer')) { this.tip.x = e.clientX - caja.left; this.tip.y = e.clientY - caja.top }
            else { const b = m.getBoundingClientRect(); this.tip.x = b.left + b.width / 2 - caja.left; this.tip.y = b.top - caja.top }
            this.tip.texto = m.dataset.nombre; this.tip.visible = true;
            this.$nextTick(() => { const w = this.$refs.tip.offsetWidth / 2; this.tip.x = Math.min(Math.max(this.tip.x, w), caja.width - w) });
        },
        ocultar() { this.tip.visible = false },
        cambiar(v) { this.vista = v; this.tip.visible = false },
        elegir(e) {
            const m = this.musculo(e); if (! m) return;
            const detail = { id: m.dataset.musculo };
            window.Livewire?.dispatch('musculo-seleccionado', detail);
            this.$refs.caja.dispatchEvent(new CustomEvent('musculo-seleccionado', { detail, bubbles: true }));
        },
     }"
     x-ref="caja">
    <div class="silueta-vistas" role="group" aria-label="Cambiar vista">
        @foreach (MusculosCuerpo::VISTAS as $clave => $etiquetaVista)
            <button type="button" x-on:click="cambiar('{{ $clave }}')" x-bind:aria-pressed="vista === '{{ $clave }}'"
                    aria-pressed="{{ $clave === $vista ? 'true' : 'false' }}">{{ $etiquetaVista }}</button>
        @endforeach
    </div>
    <div class="silueta-lienzo"
         x-on:pointerover="mostrar($event)" x-on:pointermove="mostrar($event)" x-on:pointerleave="ocultar()"
         x-on:focusin="mostrar($event)" x-on:focusout="ocultar()"
         x-on:click="elegir($event)"
         x-on:keydown.enter.prevent="elegir($event)" x-on:keydown.space.prevent="elegir($event)">
        @foreach (array_keys(MusculosCuerpo::VISTAS) as $clave)
            @include('silueta.'.$clave)
        @endforeach
    </div>
    <div class="silueta-tooltip" role="tooltip" wire:ignore x-ref="tip" x-show="tip.visible" x-cloak
         x-bind:style="`left:${tip.x}px;top:${tip.y}px`" x-text="tip.texto"></div>
    <p class="sr-only" aria-live="polite">{{ count($resaltados) ? 'Músculos resaltados: '.implode(', ', $resaltados).'.' : 'Ningún músculo resaltado.' }}</p>
    @if ($desconocidos && config('app.debug'))
        <!-- silueta-corporal: ids no reconocidos (ignorados): {{ implode(', ', $desconocidos) }} -->
    @endif
</div>
