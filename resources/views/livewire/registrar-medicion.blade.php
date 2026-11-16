<div class="reg-raiz">
    <div class="reg-vivo" role="status" aria-live="polite">{{ $exito }}</div>

    @if ($abierto)
        @php
            $insignias = [
                'ok' => ['NORMAL', 'check_circle'],
                'warn' => ['CAUTION', 'warning'],
                'crit' => ['CRITICAL', 'error'],
            ];
        @endphp
        <div class="reg-fondo"
             x-data="{
                 previo: null,
                 init() {
                     this.previo = document.activeElement;
                     this.$nextTick(() => {
                         const c = this.$el.querySelector('[data-clave=&quot;{{ $enfocar }}&quot;]') || this.$el.querySelector('input');
                         c && c.focus();
                     });
                 },
                 destroy() { this.previo && this.previo.focus && this.previo.focus(); },
                 trampa(e) {
                     const f = [...this.$refs.dialogo.querySelectorAll('button, input')].filter(el => !el.disabled && el.offsetParent !== null);
                     if (!f.length) return;
                     const a = f[0], z = f[f.length - 1];
                     if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
                     else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
                 },
             }"
             @keydown.escape.window="$wire.cerrar()"
             @keydown.tab="trampa($event)"
             @registro-foco-error.window="$nextTick(() => { const c = $refs.dialogo.querySelector('[aria-invalid=true]'); c && c.focus(); })"
             @click.self="$wire.cerrar()">
            <form class="reg-dialogo" x-ref="dialogo" role="dialog" aria-modal="true" aria-labelledby="reg-titulo"
                  wire:submit="guardar" novalidate>
                <div class="reg-cabecera">
                    <div class="reg-titulos">
                        <span class="reg-sobretitulo mono">TODAY'S MEASUREMENT · {{ $hora }} UTC</span>
                        <span class="reg-titulo" id="reg-titulo">Log measurement · {{ $nombre }}</span>
                    </div>
                    <button type="button" class="reg-cerrar" aria-label="Close" wire:click="cerrar">
                        <span class="icono" aria-hidden="true">close</span>
                    </button>
                </div>

                <div class="reg-rejilla">
                    @foreach ($indicadores as $clave => $ind)
                        @php($a = $analisis[$clave] ?? ['error' => null, 'estado' => null])
                        <div class="reg-campo">
                            <label class="reg-etiqueta" for="reg-{{ $clave }}">
                                <span class="icono" aria-hidden="true">{{ $ind['icono'] }}</span>{{ $ind['etiqueta'] }}
                            </label>
                            <div class="reg-entrada {{ $a['error'] ? 'es-invalido' : '' }}">
                                <input id="reg-{{ $clave }}" data-clave="{{ $clave }}" class="mono" type="text"
                                       inputmode="{{ $clave === 'presion_arterial' ? 'text' : 'decimal' }}" autocomplete="off"
                                       wire:model.live.debounce.300ms="campos.{{ $clave }}"
                                       @if ($a['error']) aria-invalid="true" @endif
                                       aria-describedby="reg-rango-{{ $clave }}{{ $a['error'] ? ' reg-err-'.$clave : '' }}">
                                <span class="reg-unidad">{{ $ind['unidad'] }}</span>
                                @if ($a['estado'])
                                    <span class="reg-insignia" data-estado="{{ $a['estado'] }}">
                                        <span class="icono" aria-hidden="true">{{ $insignias[$a['estado']][1] }}</span>{{ $insignias[$a['estado']][0] }}
                                    </span>
                                @endif
                            </div>
                            @if ($a['error'])
                                <span class="reg-error" id="reg-err-{{ $clave }}">{{ $a['error'] }}</span>
                            @endif
                            <span class="reg-rango mono" id="reg-rango-{{ $clave }}">{{ $ind['rango'] }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($errorGeneral)
                    <p class="reg-error reg-error-general" role="alert">{{ $errorGeneral }}</p>
                @endif

                <div class="reg-acciones">
                    <button type="button" class="reg-btn" wire:click="cerrar">Cancel</button>
                    <button type="submit" class="reg-btn reg-btn-primario" wire:loading.attr="disabled" wire:target="guardar">
                        <span class="icono" aria-hidden="true">save</span>Save measurement
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
