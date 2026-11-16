// Tema de color: oscuro | claro | hc (alto contraste). El <head> del layout ya fijó data-theme antes del primer pintado;
// aquí solo se gestionan los botones y la persistencia (localStorage con try/catch: puede estar bloqueado).
const TEMAS = ['dark', 'light', 'hc'];
const NOMBRES = { dark: 'dark', light: 'light', hc: 'high contrast' };

function guardado() {
    try { const t = localStorage.getItem('tema'); return TEMAS.includes(t) ? t : null; } catch (e) { return null; }
}

function actual() {
    const t = document.documentElement.getAttribute('data-theme');
    return TEMAS.includes(t) ? t : 'dark';
}

function marcarBotones() {
    const t = actual();
    document.querySelectorAll('[data-tema]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.tema === t ? 'true' : 'false'));
    document.querySelectorAll('[data-tema-ciclo]').forEach((b) => b.setAttribute('title', 'Current theme: ' + NOMBRES[t] + '. Change'));
}

export function aplicarTema(t) {
    if (!TEMAS.includes(t)) return;
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('tema', t); } catch (e) { /* sin almacenamiento: el cambio vale solo para esta página */ }
    marcarBotones();
}

document.addEventListener('click', (e) => {
    const op = e.target.closest('[data-tema]');
    if (op) { aplicarTema(op.dataset.tema); return; }
    if (e.target.closest('[data-tema-ciclo]')) aplicarTema(TEMAS[(TEMAS.indexOf(actual()) + 1) % TEMAS.length]);
});

// Sin elección guardada, seguir los cambios del sistema (claro/oscuro, contraste).
try {
    ['(prefers-color-scheme: light)', '(prefers-contrast: more)'].forEach((q) => window.matchMedia(q).addEventListener('change', () => {
        if (guardado()) return;
        const m = (s) => window.matchMedia(s).matches;
        document.documentElement.setAttribute('data-theme', m('(prefers-contrast: more)') ? 'hc' : m('(prefers-color-scheme: light)') ? 'light' : 'dark');
        marcarBotones();
    }));
} catch (e) { /* matchMedia no disponible */ }

// Entre pestañas: si cambia la elección, aplicarla.
window.addEventListener('storage', (e) => { if (e.key === 'tema' && TEMAS.includes(e.newValue)) { document.documentElement.setAttribute('data-theme', e.newValue); marcarBotones(); } });

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', marcarBotones); else marcarBotones();
document.addEventListener('livewire:navigated', marcarBotones);
