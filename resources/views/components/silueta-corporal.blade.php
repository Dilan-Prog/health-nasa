{{--
    Silueta corporal anatómica (vistas frontal, lateral der., lateral izq. y posterior), SVG en línea con cada músculo como pieza independiente.

    Props: activos   array de ids a resaltar (un id presente en varias vistas se resalta en todas).
           vista     frontal | lateral | lateral_izq | posterior (vista inicial; en miniatura, la única que se pinta).
           selector  bool (true): muestra los botones de vista internos; false los oculta (control externo).
           modo      interactivo (defecto) | miniatura (solo la vista indicada, sin Alpine/handlers/roles/tabindex/tooltip,
                     aria-hidden="true"; para tarjetas).
    Cualquier atributo extra (class, etc.) va al contenedor. Clases útiles: silueta-corporal--ajustar (alto 100% del padre,
    proporción 420/1100, centrada) y silueta-corporal--oscuro (paleta para fondo oscuro; el defecto es para fondo claro).

    Ejemplos:
      <x-silueta-corporal :activos="$ids" vista="frontal" />                                   (interactivo, con botones)
      <div style="height:560px"><x-silueta-corporal :activos="$ids" :selector="false" class="silueta-corporal--ajustar" /></div>
      <x-silueta-corporal modo="miniatura" vista="posterior" :activos="$ids" class="silueta-corporal--ajustar" />

    Control externo de la vista (solo modo interactivo): window.dispatchEvent(new CustomEvent('silueta-vista', {detail: 'posterior'}))
    o, desde Alpine, $dispatch('silueta-vista', 'posterior'). Al cambiar con sus propios botones emite 'silueta-vista-cambiada'
    (detail = vista, burbujea). Ojo: el evento externo afecta a TODAS las siluetas interactivas de la página.

    Eventos al hacer clic o pulsar Enter/Espacio sobre un músculo:
        Livewire.dispatch('musculo-seleccionado', { id })   -> #[On('musculo-seleccionado')] public function x(string $id)
        y un CustomEvent DOM del mismo nombre que burbujea desde el contenedor.

    Ids que pinta cada vista (MusculosCuerpo::POR_VISTA; las formas d son las de los SVG originales y no se tocan):
        frontal     50 ids, ambos lados (_izq/_der).
        lateral     26 ids, solo lado derecho (_der) y los centrales sin lado.
        lateral_izq 26 ids, espejo de la lateral con ids _izq.
        posterior   32 ids, ambos lados.
    Los partidos resources/views/silueta/*.blade.php son generados; solo se parametrizan atributos con $interactivo.
    Colores: resources/css/silueta-corporal.css (variables CSS).
--}}
@props(['activos' => [], 'vista' => 'frontal', 'selector' => true, 'modo' => 'interactivo'])
@php
    use App\Support\MusculosCuerpo;

    $interactivo = $modo !== 'miniatura';
    $vista = array_key_exists($vista, MusculosCuerpo::VISTAS) ? $vista : 'frontal';
    $uid = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)); // ids únicos de <mask> por instancia
    $set = array_flip(array_map('strval', (array) $activos));
    $nombre = fn (string $id): string => MusculosCuerpo::nombreEn($id) ?? $id;
    $etiqueta = fn (string $id): string => $nombre($id).(isset($set[$id]) ? ', highlighted' : '');
    $conocidos = MusculosCuerpo::ids();
    $desconocidos = array_values(array_diff(array_keys($set), $conocidos));
    $resaltados = array_map($nombre, array_values(array_intersect($conocidos, array_keys($set))));
@endphp
@if (! $interactivo)
<div {{ $attributes->class(['silueta-corporal', 'silueta-corporal--miniatura']) }}>
    <div class="silueta-lienzo">
        @include('silueta.'.$vista)
    </div>
</div>
@else
<div {{ $attributes->class(['silueta-corporal']) }}
     x-data="{
        vista: '{{ $vista }}',
        vistas: @js(array_keys(MusculosCuerpo::VISTAS)),
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
        cambiar(v) {
            this.vista = v; this.tip.visible = false;
            this.$refs.caja.dispatchEvent(new CustomEvent('silueta-vista-cambiada', { detail: v, bubbles: true }));
        },
        externa(v) { if (this.vistas.includes(v)) { this.vista = v; this.tip.visible = false } },
        elegir(e) {
            const m = this.musculo(e); if (! m) return;
            const detail = { id: m.dataset.musculo };
            window.Livewire?.dispatch('musculo-seleccionado', detail);
            this.$refs.caja.dispatchEvent(new CustomEvent('musculo-seleccionado', { detail, bubbles: true }));
        },
     }"
     x-on:silueta-vista.window="externa($event.detail)"
     x-ref="caja">
    @if ($selector)
    <div class="silueta-vistas" role="group" aria-label="Change view">
        @foreach (MusculosCuerpo::VISTAS as $clave => $etiquetaVista)
            <button type="button" x-on:click="cambiar('{{ $clave }}')" x-bind:aria-pressed="vista === '{{ $clave }}'"
                    aria-pressed="{{ $clave === $vista ? 'true' : 'false' }}">{{ $etiquetaVista }}</button>
        @endforeach
    </div>
    @endif
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
    <p class="sr-only" aria-live="polite">{{ count($resaltados) ? 'Highlighted muscles: '.implode(', ', $resaltados).'.' : 'No muscle highlighted.' }}</p>
    @if ($desconocidos && config('app.debug'))
        <!-- silueta-corporal: ids no reconocidos (ignorados): {{ implode(', ', $desconocidos) }} -->
    @endif
</div>
@endif
