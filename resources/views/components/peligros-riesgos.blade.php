@php
    $peligros = config('peligros.peligros');
    $estados = config('peligros.estados');
    $indicadores = config('umbrales.indicadores');
    $total = collect(config('peligros.grupos'))->sum(fn ($g) => count($g['riesgos']));
@endphp
<section class="peli" aria-labelledby="peli-t">
    <h3 class="peli-titulo" id="peli-t">Hazards and risks</h3>
    <p class="peli-sub">The 5 spaceflight hazards and the {{ $total }} HRP risks, with the coverage this system gives them ({{ config('peligros.etiqueta_ejemplo') }}).</p>

    <h4 class="peli-sec mono">5 HAZARDS</h4>
    <ul class="peli-peligros">
        @foreach ($peligros as $p)
            <li class="peli-peligro"><span class="icono" aria-hidden="true">{{ $p['ic'] }}</span>{{ $p['nombre'] }}</li>
        @endforeach
    </ul>
    <p class="peli-nota" role="note"><span class="icono" aria-hidden="true">info</span>{{ config('peligros.nota') }}</p>

    <h4 class="peli-sec mono">{{ $total }} RISKS AND COVERAGE</h4>
    <div class="peli-tabla-envol">
        <table class="peli-tabla">
            <caption class="peli-sr">System coverage for each HRP risk</caption>
            <thead><tr><th scope="col">Risk</th><th scope="col">Coverage</th><th scope="col">Detail</th></tr></thead>
            @foreach (config('peligros.grupos') as $g)
                <tbody>
                    <tr class="peli-grupo"><th scope="rowgroup" colspan="3">{{ $g['nombre'] }} ({{ count($g['riesgos']) }})</th></tr>
                    @foreach ($g['riesgos'] as [$nombre, $est, $frase])
                        <tr class="peli-riesgo">
                            <th scope="row">{{ $nombre }}</th>
                            <td><span class="peli-chip" data-cob="{{ $est }}"><span class="icono" aria-hidden="true">{{ $estados[$est]['ic'] }}</span>{{ $estados[$est]['txt'] }}</span></td>
                            <td>{{ $frase }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>

    <h4 class="peli-sec mono">HAZARD × INDICATOR</h4>
    <div class="peli-tabla-envol">
        <table class="peli-tabla peli-matriz">
            <caption class="peli-sr">Which indicators are affected by each hazard</caption>
            <thead>
                <tr>
                    <th scope="col">Indicator</th>
                    @foreach ($peligros as $p)<th scope="col">{{ $p['nombre'] }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($indicadores as $ind)
                    <tr>
                        <th scope="row">{{ $ind['nombre'] }}</th>
                        @foreach ($peligros as $k => $p)
                            <td>@if (in_array($k, $ind['riesgos'] ?? [], true))<span class="icono" aria-hidden="true">check</span><span class="peli-sr">Yes</span>@else<span class="peli-sr">No</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="peli-pie">“Distance from Earth” has no indicator of its own: it is reflected in the mission’s communication delay.</p>
</section>
