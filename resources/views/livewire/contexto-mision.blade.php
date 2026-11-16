@php
    $d = $d ?? null;
    $retardoTxt = fn ($m) => $m === null ? '—' : ($m == 0 ? 'Sin retardo' : $m.' min');
@endphp
<div class="mision">
    <x-encabezado titulo-a="Misión" :titulo-b="$mision ? $mision->nombre : 'Sin misión'" />

    <main class="mision-main" id="mision-contenido">
        @if (! $d)
            <div class="mision-vacio" role="status">
                <span class="icono" aria-hidden="true">rocket_launch</span>
                <strong>No hay una misión activa</strong>
                <span>Cuando se asigne una misión con tripulación, aquí verás su contexto.</span>
            </div>
        @else
            <div class="mision-cabecera">
                <span class="mision-kicker mono">CONTEXTO DE MISIÓN</span>
                <h2 class="mision-titulo">{{ $d['nombre'] }} · {{ $d['entorno_txt'] }}</h2>
                <p class="mision-sub">
                    @if ($d['dia'] && $d['total'])
                        Día {{ $d['dia'] }} de {{ $d['total'] }} · {{ $d['quedan'] === 0 ? 'último día de misión' : ($d['quedan'] === 1 ? 'queda 1 día' : 'quedan '.$d['quedan'].' días') }}.
                    @else
                        Las fechas de esta misión aún no están definidas.
                    @endif
                </p>
            </div>

            <div class="mision-stats" role="list" aria-label="Datos de la misión">
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">DÍA DE MISIÓN</span><span class="mision-stat-v mono">{{ $d['dia'] ?? '—' }} / {{ $d['total'] ?? '—' }}</span></div>
                @if ($d['es_estacion'])
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ÓRBITA</span><span class="mision-stat-v mono">{{ $d['orbita_km'] ? $d['orbita_km'].' km' : '—' }}</span></div>
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">VELOCIDAD</span><span class="mision-stat-v mono">{{ $d['velocidad'] ? $d['velocidad'].' km/s' : '—' }}</span></div>
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ÓRBITAS/DÍA</span><span class="mision-stat-v mono">{{ $d['orbitas_dia'] ?? '—' }}</span></div>
                @else
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">ENTORNO</span><span class="mision-stat-v mono mision-stat-v--txt">{{ $d['entorno_txt'] }}</span></div>
                @endif
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">TRIPULACIÓN</span><span class="mision-stat-v mono">{{ $d['n_tripulantes'] }} a bordo</span></div>
                <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">RETARDO A TIERRA</span><span class="mision-stat-v mono">{{ $retardoTxt($d['retardo']) }}</span></div>
                <div class="mision-stat" role="listitem" data-estado="{{ $d['enlace'] ? 'ok' : 'warn' }}">
                    <span class="mision-stat-et mono">ENLACE CON TIERRA</span>
                    <span class="mision-stat-v mono mision-stat-v--estado"><span class="icono" aria-hidden="true">{{ $d['enlace'] ? 'cloud_done' : 'cloud_off' }}</span>{{ $d['enlace'] ? 'Activo' : 'Sin enlace' }}</span>
                </div>
                @if ($d['dosis'])
                    <div class="mision-stat" role="listitem"><span class="mision-stat-et mono">DOSIS {{ $d['nombre_corto'] }}</span><span class="mision-stat-v mono">{{ $d['dosis']['txt'] }} mSv</span></div>
                @endif
            </div>

            @if ($d['fases'])
                <section class="mision-panel" aria-labelledby="mision-tl-t">
                    <h3 class="mision-kicker mono" id="mision-tl-t">LÍNEA DE TIEMPO</h3>
                    <div class="mision-tl-marco">
                        <div class="mision-tl" role="img" aria-label="Línea de tiempo: día {{ $d['dia'] ?? '—' }} de {{ $d['total'] }}. Hitos: {{ collect($d['fases'])->map(fn ($f) => $f['nombre'].' día '.$f['dia'])->implode(', ') }}.">
                            <div class="mision-tl-linea"></div>
                            <div class="mision-tl-avance" style="width: {{ $d['avance'] }}%"></div>
                            @foreach ($d['fases'] as $f)
                                <div class="mision-hito {{ $f['hecha'] ? 'is-hecho' : '' }}" style="left: {{ $f['pct'] }}%">
                                    <span class="mision-hito-punto" aria-hidden="true"></span>
                                    <span class="mision-hito-n">{{ $f['nombre'] }}</span>
                                    <span class="mision-hito-d mono">D{{ $f['dia'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <div class="mision-cuerpo">
                <section class="mision-panel" aria-labelledby="mision-dosis-t">
                    <h3 class="mision-kicker mono" id="mision-dosis-t">DOSIS ACUMULADA VS. LÍMITE DE MISIÓN</h3>
                    @if ($d['dosis'])
                        <div class="mision-dosis-cifras mono"><span>{{ $d['dosis']['txt'] }} mSv</span><span>{{ rtrim(rtrim(number_format($d['limite'], 1, '.', ''), '0'), '.') }} mSv</span></div>
                        <div class="mision-barra" data-estado="{{ $d['dosis']['estado'] }}" role="progressbar" aria-label="Dosis acumulada frente al límite de misión" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($d['dosis']['pct']) }}">
                            <span style="width: {{ $d['dosis']['pct'] }}%"></span>
                        </div>
                        <p class="mision-nota">Llevas el {{ number_format($d['dosis']['pct'], 0) }} % del límite de referencia de la misión. Es un dato de contexto: tu equipo y el centro de control revisan la dosis contigo.</p>
                    @else
                        <p class="mision-nota">Aún no hay una medición de dosis acumulada{{ $d['nombre_corto'] ? ' para '.ucfirst(mb_strtolower($d['nombre_corto'])) : '' }}.</p>
                    @endif
                </section>

                <section class="mision-panel" aria-labelledby="mision-ev-t">
                    <h3 class="mision-kicker mono" id="mision-ev-t">PRÓXIMOS EVENTOS</h3>
                    @if ($d['eventos'])
                        <ul class="mision-eventos">
                            @foreach ($d['eventos'] as $e)
                                <li><span class="icono" aria-hidden="true">{{ $e['icono'] }}</span><span class="mision-ev-d mono">D{{ $e['dia'] }}</span><span>{{ $e['titulo'] }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mision-nota">No hay eventos programados.</p>
                    @endif
                </section>
            </div>
        @endif
    </main>
</div>
