<div class="pag">
    <x-encabezado titulo-a="Legal" titulo-b="notice" />

    <main class="pag-main" id="aviso-legal-contenido">
        <section class="pag-card" aria-labelledby="pag-legal-h">
            <h2 class="pag-h2" id="pag-legal-h"><span class="icono" aria-hidden="true">gavel</span>Educational prototype</h2>
            <p>This tool is an <strong>educational prototype</strong> that uses simulated data. It provides guidance only; it does not diagnose or prescribe, it is <strong>not medical advice</strong>, and it <strong>does not replace a medical professional</strong> or the Flight Surgeon.</p>
            <p>This project is not affiliated with or endorsed by NASA.</p>
            <p>For any symptom or concern about your health, consult a medical professional.</p>
        </section>

        <section class="pag-card" aria-labelledby="pag-valores-h">
            <h2 class="pag-h2" id="pag-valores-h"><span class="icono" aria-hidden="true">science</span>Example values</h2>
            <p>Crew member and measurement data are simulated. Clinical thresholds and values are <strong>example values, pending medical review</strong>; they are not validated for real use.</p>
            <span class="pag-chip" data-estado="warn"><span class="icono" aria-hidden="true">warning</span>Example value (unvalidated)</span>
        </section>

        <section class="pag-card" aria-labelledby="pag-fuentes-h">
            <h2 class="pag-h2" id="pag-fuentes-h"><span class="icono" aria-hidden="true">menu_book</span>Sources cited</h2>
            <ul class="pag-lista">
                @foreach ($fuentes as [$nombre, $nota])
                    <li><strong>{{ $nombre }}</strong> <span class="pag-nota">{{ $nota }}</span></li>
                @endforeach
            </ul>
            <p class="pag-nota">Citing a source does not mean the displayed value is validated or that the source endorses this tool.</p>
        </section>
    </main>
</div>
