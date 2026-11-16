@php
    $peligros = config('peligros.peligros');
    $estados = config('peligros.estados');
    $indicadores = config('umbrales.indicadores');
    $total = collect(config('peligros.grupos'))->sum(fn ($g) => count($g['riesgos']));
@endphp
<section class="peli" aria-labelledby="peli-t">
    <h3 class="peli-titulo" id="peli-t">Peligros y riesgos</h3>
    <p class="peli-sub">Los 5 peligros del vuelo espacial y los {{ $total }} riesgos del HRP, con la cobertura que les da este sistema ({{ config('peligros.etiqueta_ejemplo') }}).</p>

    <h4 class="peli-sec mono">5 PELIGROS</h4>
    <ul class="peli-peligros">
        @foreach ($peligros as $p)
            <li class="peli-peligro"><span class="icono" aria-hidden="true">{{ $p['ic'] }}</span>{{ $p['nombre'] }}</li>
        @endforeach
    </ul>
    <p class="peli-nota" role="note"><span class="icono" aria-hidden="true">info</span>{{ config('peligros.nota') }}</p>

    <h4 class="peli-sec mono">{{ $total }} RIESGOS Y COBERTURA</h4>
    <div class="peli-tabla-envol">
        <table class="peli-tabla">
            <caption class="peli-sr">Cobertura del sistema para cada riesgo HRP</caption>
            <thead><tr><th scope="col">Riesgo</th><th scope="col">Cobertura</th><th scope="col">Detalle</th></tr></thead>
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

    <h4 class="peli-sec mono">PELIGRO × INDICADOR</h4>
    <div class="peli-tabla-envol">
        <table class="peli-tabla peli-matriz">
            <caption class="peli-sr">Qué indicadores se ven afectados por cada peligro</caption>
            <thead>
                <tr>
                    <th scope="col">Indicador</th>
                    @foreach ($peligros as $p)<th scope="col">{{ $p['nombre'] }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($indicadores as $ind)
                    <tr>
                        <th scope="row">{{ $ind['nombre'] }}</th>
                        @foreach ($peligros as $k => $p)
                            <td>@if (in_array($k, $ind['riesgos'] ?? [], true))<span class="icono" aria-hidden="true">check</span><span class="peli-sr">Sí</span>@else<span class="peli-sr">No</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="peli-pie">«Distancia de la Tierra» no tiene indicador propio: se refleja en el retardo de comunicación de la misión.</p>
</section>
