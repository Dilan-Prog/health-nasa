{{--
    Asistente "Nuevo tripulante" (5 pasos): marco del diseño final (cabecera, indicador de pasos, resumen de errores,
    tarjeta del paso, resumen del expediente y barra de acciones). La lógica está en App\Livewire\NuevoTripulante;
    los 5 pasos son parciales livewire/nuevo-tripulante/paso1..5 que definen su propio contenido.
--}}
@php
    $cfg = fn ($k, $d = null) => config('expediente.'.$k, $d);
    $lim = fn ($c) => config('expediente.limites.'.$c, [null, null]);
    $id = fn (string $k) => 'nt-'.str_replace('.', '-', $k);
    $maxItems = config('expediente.max_items');
    $marcadores = config('indicadores.marcadores', []);
    $nombrePaso = $this->pasos[$paso][0];

    // Campos de texto/número simples: [propiedad, etiqueta, tipo, atributos, ayuda]
    $campos1 = [
        ['nombre', 'Nombre completo', 'text', 'autocomplete="off" maxlength="120" required', null],
        ['fecha_nacimiento', 'Fecha de nacimiento', 'date', 'required', null],
    ];
    $campos1b = [
        ['nacionalidad', 'Nacionalidad', 'text', 'maxlength="80"', null],
        ['agencia', 'Agencia', 'text', 'maxlength="80"', null],
    ];
    $num = function (string $c, string $et, string $unidad, string $paso = '1') use ($lim) {
        [$mn, $mx] = $lim($c);
        return [$c, $et, 'number', 'inputmode="decimal" step="'.$paso.'" min="'.$mn.'" max="'.$mx.'"', $unidad.' · entre '.$mn.' y '.$mx];
    };
    $bio = [
        $num('altura_m', 'Altura', 'm', '0.01'), $num('peso_kg', 'Peso', 'kg', '0.1'), $num('evas', 'EVAs realizadas', 'n.º'),
    ];
    $basales = [
        $num('fc_reposo', 'Frecuencia cardíaca en reposo', 'lpm'), $num('fc_maxima', 'Frecuencia cardíaca máxima', 'lpm'),
        $num('presion_basal_sis', 'Presión sistólica basal', 'mmHg'), $num('presion_basal_dia', 'Presión diastólica basal', 'mmHg'),
        $num('spo2_basal', 'Saturación de O₂ basal', '%'), $num('temperatura_basal', 'Temperatura basal', '°C', '0.1'),
        $num('glucosa_basal', 'Glucosa basal', 'mg/dL'), $num('vo2max', 'VO₂máx', 'ml/kg/min', '0.1'),
        $num('grasa_corporal_pct', 'Grasa corporal', '%', '0.1'), $num('densidad_osea_tscore', 'T-score de densidad ósea', 'T-score', '0.1'),
    ];
    $habitos = [
        $num('horas_sueno_habituales', 'Horas de sueño habituales', 'h', '0.1'),
        $num('actividad_fisica_semana_min', 'Actividad física semanal', 'min', '1'),
        $num('dosis_radiacion_previa_msv', 'Dosis de radiación previa acumulada', 'mSv', '0.1'),
    ];

    /** Mensaje de error + ayuda enlazados al campo por aria-describedby. */
    $describe = fn (string $k, bool $ayuda = false) => trim(($ayuda ? $id($k).'-ayuda ' : '').($errors->has($k) ? $id($k).'-error' : ''));

    $siNo = ['1' => 'Sí', '0' => 'No'];
    $placeholderSel = '— Selecciona —';
@endphp

<div class="nt">
    <x-encabezado titulo-a="Nuevo tripulante" :titulo-b="$this->mision?->nombre ?? 'Sin misión'" />

    @if (! $this->mision || $this->cupoLleno)
        {{-- ===== Bloqueos: sin misión activa / tripulación completa ===== --}}
        @php $sinMision = ! $this->mision; @endphp
        <section class="nt-bloqueo" role="alert" aria-labelledby="nt-bloqueo-titulo">
            <span class="nt-bloqueo-ic icono" aria-hidden="true">{{ $sinMision ? 'warning' : 'groups' }}</span>
            <span class="nt-pastilla">{{ $sinMision ? 'Sin misión activa' : 'Tripulación completa' }}</span>
            @if ($sinMision)
                <h2 id="nt-bloqueo-titulo">No hay una misión activa</h2>
                <p>Cada expediente se guarda dentro de una misión. Crea o selecciona una misión con tripulación y, cuando haya una activa, podrás continuar aquí.</p>
            @else
                <h2 id="nt-bloqueo-titulo">Tripulación completa</h2>
                <p>La misión ya tiene el máximo de {{ \App\Services\Tripulacion\AltaTripulante::maximo() }} tripulantes. No se pueden añadir más.</p>
            @endif
            <div class="nt-bloqueo-acc">
                <button type="button" class="nt-btn" wire:click="cancelar"><span class="icono" aria-hidden="true">arrow_back</span> Volver a Tripulación</button>
                @if ($sinMision)
                    <a class="nt-btn nt-btn--primario" href="{{ route('mision') }}"><span class="icono" aria-hidden="true">rocket_launch</span> Ir a Misión</a>
                @endif
            </div>
        </section>
    @else
    @php
        $estados = $this->estadosPasos;
        $etiquetaEstado = ['actual' => 'Actual', 'actual-error' => 'Actual · error', 'completo' => 'Completado', 'pendiente' => 'Pendiente'];
        $conError = $this->pasosConError;
        $errores = $this->erroresResumen;
        $rev = $this->revision['completitud'] ?? null;
        $pct = (int) ($rev['porcentaje'] ?? 0);
        $faltan = count($rev['faltantes'] ?? []);
        $textoFaltan = $faltan ? 'Faltan '.$faltan.' datos recomendados.' : 'Expediente completo.';
        $tono = $this->tonoImc;
        $nombreVisible = trim($nombre) !== '' ? $nombre : 'Sin nombre';
        $imcTxt = $this->imc !== null ? number_format($this->imc, 1) : '—';
        $edadTxt = $this->edad !== null ? $this->edad.' años' : '—';
        $alcanzable = fn (int $n) => $n === 1 || collect(range(1, $n - 1))->every(fn ($p) => in_array($p, $pasosValidos, true));
    @endphp
    <form class="nt-form" wire:submit.prevent="{{ $paso === 5 ? 'guardar' : 'siguiente' }}" novalidate aria-labelledby="paso-titulo">

        {{-- ===== Indicador de pasos: completo (escritorio) ===== --}}
        <nav class="nt-pasos nt-pasos--completo" aria-label="Pasos del alta de tripulante">
            <ol>
                @foreach ($this->pasos as $n => [$titulo, $icono])
                    @php $e = $estados[$n]; @endphp
                    <li class="nt-paso" data-estado="{{ $e }}" wire:key="nt-paso-{{ $n }}">
                        <button type="button" class="nt-paso-btn" wire:click="irAPaso({{ $n }})" title="{{ $titulo }} · {{ $etiquetaEstado[$e] }}"
                                aria-label="Paso {{ $n }} de 5: {{ $titulo }}. {{ $etiquetaEstado[$e] }}"
                                @if ($n === $paso) aria-current="step" @endif
                                @if (! $alcanzable($n)) disabled @endif>
                            <span class="nt-paso-marca" aria-hidden="true">
                                @if ($e === 'completo') <span class="icono">check</span>
                                @elseif ($e === 'actual-error') <span class="icono">priority_high</span>
                                @else {{ $n }} @endif
                            </span>
                            <span class="nt-paso-txt">
                                <span class="nt-paso-t">{{ $titulo }}</span>
                                <span class="nt-paso-n">{{ $etiquetaEstado[$e] }}</span>
                            </span>
                        </button>
                        @if ($n < 5) <span class="nt-paso-linea" aria-hidden="true" data-hecho="{{ $e === 'completo' ? '1' : '0' }}"></span> @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        {{-- ===== Indicador de pasos: compacto (pantallas estrechas) ===== --}}
        <nav class="nt-pasos nt-pasos--compacto" aria-label="Pasos del alta de tripulante (compacto)">
            <div class="nt-compacto-cab">
                <span class="nt-compacto-tit"><span class="nt-ojo">Paso {{ $paso }} de 5</span><span class="nt-compacto-nom">{{ $nombrePaso }}</span></span>
                <span class="nt-pastilla nt-pastilla--paso" data-estado="{{ $estados[$paso] }}"><span class="icono" aria-hidden="true">{{ $estados[$paso] === 'actual-error' ? 'priority_high' : 'edit' }}</span>{{ $etiquetaEstado[$estados[$paso]] }}</span>
            </div>
            <ol class="nt-segmentos">
                @foreach ($this->pasos as $n => [$titulo, $icono])
                    @php $e = $estados[$n]; @endphp
                    <li wire:key="nt-seg-{{ $n }}" data-estado="{{ $e }}">
                        <button type="button" wire:click="irAPaso({{ $n }})" aria-label="Paso {{ $n }} de 5: {{ $titulo }}. {{ $etiquetaEstado[$e] }}"
                                @if ($n === $paso) aria-current="step" @endif @if (! $alcanzable($n)) disabled @endif><span></span></button>
                    </li>
                @endforeach
            </ol>
            @if ($conError)
                <span class="nt-nota-error"><span class="icono" aria-hidden="true">error</span>{{ collect($conError)->map(fn ($i) => 'Paso '.$i)->implode(', ') }} {{ count($conError) > 1 ? 'tienen' : 'tiene' }} errores.</span>
            @endif
        </nav>

        {{-- ===== Resumen en línea (pantallas medias y estrechas) ===== --}}
        <details class="nt-resumen nt-resumen--linea" wire:key="nt-resumen-linea">
            <summary>
                <span class="nt-avatar nt-avatar--s" aria-hidden="true">{{ $this->iniciales }}</span>
                <span class="nt-resumen-tit"><strong>{{ $nombreVisible }}</strong><span class="nt-ojo">Resumen · {{ $pct }} % completo</span></span>
                <span class="icono nt-resumen-flecha" aria-hidden="true">expand_more</span>
            </summary>
            <div class="nt-resumen-cuerpo">
                <dl class="nt-resumen-dl">
                    <div><dt>Edad</dt><dd>{{ $edadTxt }}</dd></div>
                    <div><dt>IMC</dt><dd><span>{{ $imcTxt }}</span>
                        @if ($tono) <span class="nt-tono" data-estado="{{ $tono['estado'] }}"><span class="icono" aria-hidden="true">{{ $tono['icono'] }}</span>{{ $tono['etiqueta'] }}</span> @endif</dd></div>
                </dl>
                <div class="nt-barra-pct" role="progressbar" aria-label="Expediente completo" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                <span class="nt-resumen-falta">{{ $textoFaltan }}</span>
            </div>
        </details>

        <div class="nt-cuerpo">
            <div class="nt-principal">
                <div class="nt-paso-cab">
                    <span class="nt-ojo">Paso {{ $paso }} de 5</span>
                    <h2 id="paso-titulo" class="nt-titulo" tabindex="-1">{{ $nombrePaso }}</h2>
                    <p>{{ $this->descripcionPaso }}</p>
                </div>

                @if ($errores)
                    <div id="resumen-errores" class="nt-resumen-errores" role="alert" tabindex="-1">
                        <span class="icono" aria-hidden="true">error</span>
                        <div>
                            <strong>Revisa {{ count($errores) === 1 ? 'el campo marcado' : 'los '.count($errores).' campos marcados' }} antes de continuar</strong>
                            <ul class="nt-lista-errores">
                                @foreach ($errores as $err)
                                    <li wire:key="nt-err-{{ $err['clave'] }}"><a href="#{{ $err['uid'] }}" data-ir="{{ $err['uid'] }}">{{ $err['etiqueta'] }}</a>: {{ $err['mensaje'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <section class="nt-tarjeta" aria-labelledby="paso-titulo">
                    @include('livewire.nuevo-tripulante.paso1')
                    @include('livewire.nuevo-tripulante.paso2')
                    @include('livewire.nuevo-tripulante.paso3')
                    @include('livewire.nuevo-tripulante.paso4')
                    @include('livewire.nuevo-tripulante.paso5')
                </section>

                {{-- ===== Acciones ===== --}}
                <div class="nt-barra">
                    <button type="button" class="nt-btn nt-btn--suave" wire:click="cancelar" wire:loading.attr="disabled" wire:target="guardar">Cancelar</button>
                    <span class="nt-barra-sep"></span>
                    @if ($paso > 1)
                        <button type="button" class="nt-btn" wire:click="anterior" aria-label="Paso anterior" wire:loading.attr="disabled" wire:target="guardar"><span class="icono" aria-hidden="true">arrow_back</span><span class="nt-btn-txt">Anterior</span></button>
                    @endif
                    @if ($paso < 5)
                        <button type="submit" class="nt-btn nt-btn--primario">Siguiente <span class="icono" aria-hidden="true">arrow_forward</span></button>
                    @else
                        <button type="submit" class="nt-btn nt-btn--primario nt-btn--guardar" wire:loading.attr="disabled" wire:loading.class="is-guardando" wire:target="guardar">
                            <span class="nt-guardar-normal"><span class="icono" aria-hidden="true">person_add</span> Guardar tripulante</span>
                            <span class="nt-guardar-curso"><span class="nt-giro" aria-hidden="true"></span> Guardando…</span>
                        </button>
                    @endif
                </div>
                <span class="nt-sr" role="status" aria-live="polite"><span wire:loading wire:target="guardar">Guardando tripulante…</span></span>
                <p class="nt-seguridad">Esta herramienta orienta, no diagnostica ni receta.</p>
            </div>

            {{-- ===== Resumen lateral (pantallas anchas) ===== --}}
            <aside class="nt-resumen nt-resumen--lateral" aria-label="Resumen del expediente" wire:key="nt-resumen-lateral">
                <span class="nt-ojo">Resumen</span>
                <div class="nt-resumen-id">
                    <span class="nt-avatar" aria-hidden="true">{{ $this->iniciales }}</span>
                    <span class="nt-resumen-tit"><strong>{{ $nombreVisible }}</strong><span>{{ $this->rolResumen }}</span></span>
                </div>
                <dl class="nt-resumen-dl">
                    <div><dt>Edad</dt><dd>{{ $edadTxt }}</dd></div>
                    <div><dt>IMC</dt><dd><span>{{ $imcTxt }}</span>
                        @if ($tono) <span class="nt-tono" data-estado="{{ $tono['estado'] }}"><span class="icono" aria-hidden="true">{{ $tono['icono'] }}</span>{{ $tono['etiqueta'] }}</span> @endif</dd></div>
                </dl>
                <div class="nt-resumen-pct">
                    <div class="nt-resumen-pct-cab"><span id="nt-sum-pct" class="nt-ojo">Expediente</span><span>{{ $pct }} %</span></div>
                    <div class="nt-barra-pct" role="progressbar" aria-labelledby="nt-sum-pct" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                    <span class="nt-resumen-falta">{{ $textoFaltan }}</span>
                </div>
                <p class="nt-resumen-nota">La app orienta, no diagnostica ni receta. Los datos se guardan en este equipo y se envían a Tierra cuando hay enlace.</p>
            </aside>
        </div>
    </form>
    @endif

    @script
    <script>
        // Gestión del foco: al cambiar de paso, al título; si hay errores, al resumen de errores.
        $wire.on('nt-foco', (e) => {
            const destino = (e && (e.destino ?? (e[0] && e[0].destino))) || 'titulo';
            setTimeout(() => {
                const el = document.getElementById(destino === 'errores' ? 'resumen-errores' : 'paso-titulo')
                    || document.getElementById('paso-titulo');
                if (el) { el.focus(); }
            }, 60);
        });
        // Enlaces del resumen de errores: enfocan el campo (o el primero de una lista) sin cambiar la URL.
        if (!window.__ntIrCampo) {
            window.__ntIrCampo = true;
            document.addEventListener('click', (ev) => {
                const a = ev.target.closest && ev.target.closest('a[data-ir]');
                if (!a) { return; }
                ev.preventDefault();
                const uid = a.getAttribute('data-ir');
                const el = document.getElementById(uid) || document.querySelector('[id^="' + uid + '-"]');
                if (el) { el.scrollIntoView({ block: 'center' }); el.focus({ preventScroll: true }); }
            });
        }
    </script>
    @endscript
</div>
