import './bootstrap';
import './indicador/app.js';
import './tema.js';

// Cuando otro dispositivo registra una medición, el panel se refresca solo.
document.addEventListener('livewire:init', () => {
    window.Echo.channel('tripulacion').listen('.MedicionRegistrada', (e) => {
        window.Livewire.dispatch('medicion-registrada', { astronautaId: e.astronautaId });
    });
});
