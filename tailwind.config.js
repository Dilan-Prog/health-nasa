/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js', './app/Livewire/**/*.php'],
    theme: {
        extend: {
            colors: {
                nasa: { blue: '#0B3D91', navy: '#0A1F44', red: '#FC3D21', light: '#EDF1FA' },
                estado: { normal: '#1E9E5A', precaucion: '#F5A623', critico: '#FC3D21', sin: '#9CA3AF' },
            },
            fontFamily: { sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'] },
        },
    },
    plugins: [],
};
