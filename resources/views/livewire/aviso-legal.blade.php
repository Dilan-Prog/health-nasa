<div class="pag">
    <x-encabezado titulo-a="Aviso" titulo-b="legal" />

    <main class="pag-main" id="aviso-legal-contenido">
        <section class="pag-card" aria-labelledby="pag-legal-h">
            <h2 class="pag-h2" id="pag-legal-h"><span class="icono" aria-hidden="true">gavel</span>Prototipo educativo</h2>
            <p>Esta herramienta es un <strong>prototipo educativo</strong>. Orienta, no diagnostica ni receta, y <strong>no sustituye a un profesional médico</strong> ni al flight surgeon.</p>
            <p>Ante cualquier síntoma o duda sobre tu salud, consulta a un profesional médico.</p>
        </section>

        <section class="pag-card" aria-labelledby="pag-valores-h">
            <h2 class="pag-h2" id="pag-valores-h"><span class="icono" aria-hidden="true">science</span>Valores de ejemplo</h2>
            <p>Los datos de tripulantes y mediciones son simulados. Los umbrales y valores clínicos son <strong>valores de ejemplo, pendientes de revisión médica</strong>; no están validados para uso real.</p>
            <span class="pag-chip" data-estado="warn"><span class="icono" aria-hidden="true">warning</span>Valor de ejemplo</span>
        </section>

        <section class="pag-card" aria-labelledby="pag-fuentes-h">
            <h2 class="pag-h2" id="pag-fuentes-h"><span class="icono" aria-hidden="true">menu_book</span>Fuentes citadas</h2>
            <ul class="pag-lista">
                @foreach ($fuentes as [$nombre, $nota])
                    <li><strong>{{ $nombre }}</strong> <span class="pag-nota">{{ $nota }}</span></li>
                @endforeach
            </ul>
            <p class="pag-nota">Citar una fuente no implica que el valor mostrado esté validado ni que la fuente avale esta herramienta.</p>
        </section>
    </main>
</div>
