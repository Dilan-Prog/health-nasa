// Alpine.data('indicador'): estado de la pantalla Indicador en el cliente (como el componente React de la maqueta).
// El modelo (resources/js/indicador/modelo.js) es puro: recibe `state` y devuelve `vals`, el objeto que pinta la plantilla.
// `state`, el modelo y las acciones viven en el cierre (sin proxies reactivos); solo `vals` es reactivo y se recalcula
// tras cada cambio, agrupando los cambios rápidos (hover) en un cálculo por fotograma.
import { crearModelo } from './modelo.js';

const LS = 'indicador.vista';
const VISTAS = [['bars', '1'], ['lines', '2'], ['forecast', '3'], ['heat', '4']];

function registrar(Alpine) {
    Alpine.data('indicador', (payload, extra = {}) => {
        const modelo = crearModelo(payload);
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let w = window.innerWidth;
        let raf = 0;
        let ft = 0;
        let drawer = false;
        let off = [];

        const params = new URLSearchParams(extra.consulta || {});
        // Vista recordada: solo si la URL no trae una explícita.
        if (!params.get('view')) {
            try {
                const v = localStorage.getItem(LS);
                if (v) params.set('view', v);
            } catch (e) { /* almacenamiento no disponible */ }
        }
        const state = modelo.estadoInicial(params, w);

        return {
            vals: null,
            urls: extra.urls || {},

            init() {
                const self = this;
                const calcular = () => modelo.build(state, acciones, { w, reduce });
                const programar = () => {
                    if (raf) return;
                    // rAF agrupa los cambios rápidos (hover); el temporizador cubre las pestañas ocultas, donde rAF se pausa.
                    const ejecutar = () => {
                        if (!raf) return;
                        cancelAnimationFrame(raf.f); clearTimeout(raf.t); raf = 0;
                        self.refrescar();
                    };
                    raf = { f: requestAnimationFrame(ejecutar), t: setTimeout(ejecutar, 50) };
                };
                const aplicar = (patch, fade = false) => {
                    if (fade) {
                        Object.assign(state, patch, { op: reduce ? 1 : 0.15, hover: null });
                        clearTimeout(ft);
                        ft = setTimeout(() => { state.op = 1; programar(); }, 40);
                    } else {
                        Object.assign(state, patch);
                    }
                    programar();
                };
                const acciones = {
                    set: (patch) => aplicar(patch),
                    fade: (patch) => aplicar(patch, true),
                    setView: (v) => {
                        try { localStorage.setItem(LS, v); } catch (e) { /* sin almacenamiento */ }
                        aplicar({ view: v }, true);
                    },
                };
                this.refrescar = () => {
                    this.vals = calcular();
                    const abierto = !!this.vals.drawerOpen;
                    if (abierto !== drawer) {
                        drawer = abierto;
                        this.$nextTick(() => {
                            const el = this.$root.querySelector(abierto ? '[data-ind-cerrar]' : '[data-ind-abrir]');
                            if (el) el.focus();
                        });
                    }
                };
                this.acciones = () => acciones;

                this.vals = calcular();
                drawer = !!this.vals.drawerOpen;

                const on = (t, ev, fn) => { t.addEventListener(ev, fn); off.push(() => t.removeEventListener(ev, fn)); };
                on(window, 'resize', () => { w = window.innerWidth; state.w = w; programar(); });
                on(window, 'keydown', (e) => this.teclado(e, acciones));
            },

            // Enlace "Registrar nueva lectura" del marcador abierto (depende de `vals`, que se recalcula en cada cambio).
            registrarHref() {
                void this.vals;
                return (this.urls.registrar || '/registrar') + '?indicador=' + encodeURIComponent(state.id || '');
            },

            destroy() {
                off.forEach((f) => f());
                off = [];
                if (raf) { cancelAnimationFrame(raf.f); clearTimeout(raf.t); raf = 0; }
                clearTimeout(ft);
            },

            teclado(e, acciones) {
                if (e.metaKey || e.ctrlKey || e.altKey) return;
                const t = e.target && e.target.tagName;
                if (/INPUT|SELECT|TEXTAREA/.test(t || '') || (e.target && e.target.isContentEditable)) return;
                if (this.vals.drawerOpen) {
                    if (e.key === 'Escape') { this.vals.closeDrawer(); return; }
                    if (e.key === 'Tab') {
                        // El cajón móvil es un diálogo modal: el foco no sale de él.
                        const panel = this.$root.querySelector('[data-component="PanelMarcadores"]');
                        const f = panel ? [...panel.querySelectorAll('button:not([disabled]), input:not([disabled])')].filter((x) => x.offsetParent !== null) : [];
                        if (f.length) {
                            const a = f[0], z = f[f.length - 1];
                            if (!panel.contains(document.activeElement)) { e.preventDefault(); a.focus(); }
                            else if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
                            else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
                        }
                        return;
                    }
                }
                const v = VISTAS.find((x) => x[1] === e.key);
                if (v && state.mode !== 'mini') acciones.setView(v[0]);
            },
        };
    });
}

if (window.Alpine) registrar(window.Alpine);
else document.addEventListener('alpine:init', () => registrar(window.Alpine));
