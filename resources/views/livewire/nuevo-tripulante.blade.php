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
        ['nombre', 'Full name', 'text', 'autocomplete="off" maxlength="120" required', null],
        ['fecha_nacimiento', 'Date of birth', 'date', 'required', null],
    ];
    $campos1b = [
        ['nacionalidad', 'Nationality', 'text', 'maxlength="80"', null],
        ['agencia', 'Agency', 'text', 'maxlength="80"', null],
    ];
    $num = function (string $c, string $et, string $unidad, string $paso = '1') use ($lim) {
        [$mn, $mx] = $lim($c);
        return [$c, $et, 'number', 'inputmode="decimal" step="'.$paso.'" min="'.$mn.'" max="'.$mx.'"', $unidad.' · between '.$mn.' and '.$mx];
    };
    $bio = [
        $num('altura_m', 'Altura', 'm', '0.01'), $num('peso_kg', 'Peso', 'kg', '0.1'), $num('evas', 'EVAs completed', 'no.'),
    ];
    $basales = [
        $num('fc_reposo', 'Resting heart rate', 'bpm'), $num('fc_maxima', 'Maximum heart rate', 'bpm'),
        $num('presion_basal_sis', 'Baseline systolic pressure', 'mmHg'), $num('presion_basal_dia', 'Baseline diastolic pressure', 'mmHg'),
        $num('spo2_basal', 'Baseline SpO₂', '%'), $num('temperatura_basal', 'Baseline temperature', '°C', '0.1'),
        $num('glucosa_basal', 'Baseline glucose', 'mg/dL'), $num('vo2max', 'VO₂max', 'ml/kg/min', '0.1'),
        $num('grasa_corporal_pct', 'Body fat', '%', '0.1'), $num('densidad_osea_tscore', 'Bone density T-score', 'T-score', '0.1'),
    ];
    $habitos = [
        $num('horas_sueno_habituales', 'Usual sleep hours', 'h', '0.1'),
        $num('actividad_fisica_semana_min', 'Weekly physical activity', 'min', '1'),
        $num('dosis_radiacion_previa_msv', 'Prior cumulative radiation dose', 'mSv', '0.1'),
    ];

    /** Mensaje de error + ayuda enlazados al campo por aria-describedby. */
    $describe = fn (string $k, bool $ayuda = false) => trim(($ayuda ? $id($k).'-ayuda ' : '').($errors->has($k) ? $id($k).'-error' : ''));

    $siNo = ['1' => 'Yes', '0' => 'No'];
    $placeholderSel = '— Select —';
@endphp

<div class="nt">
    <x-encabezado titulo-a="New crew member" :titulo-b="$this->mision?->nombre ?? 'No mission'" />

    @if (! $this->mision || $this->cupoLleno)
        {{-- ===== Bloqueos: sin misión activa / tripulación completa ===== --}}
        @php $sinMision = ! $this->mision; @endphp
        <section class="nt-bloqueo" role="alert" aria-labelledby="nt-bloqueo-titulo">
            <span class="nt-bloqueo-ic icono" aria-hidden="true">{{ $sinMision ? 'warning' : 'groups' }}</span>
            <span class="nt-pastilla">{{ $sinMision ? 'No active mission' : 'Crew full' }}</span>
            @if ($sinMision)
                <h2 id="nt-bloqueo-titulo">There is no active mission</h2>
                <p>Each record is saved within a mission. Create or select a mission with a crew; once one is active, you can continue here.</p>
            @else
                <h2 id="nt-bloqueo-titulo">Crew full</h2>
                <p>The mission already has the maximum of {{ \App\Services\Tripulacion\AltaTripulante::maximo() }} crew members. No more can be added.</p>
            @endif
            <div class="nt-bloqueo-acc">
                <button type="button" class="nt-btn" wire:click="cancelar"><span class="icono" aria-hidden="true">arrow_back</span> Back to Crew</button>
                @if ($sinMision)
                    <a class="nt-btn nt-btn--primario" href="{{ route('mision') }}"><span class="icono" aria-hidden="true">rocket_launch</span> Go to Mission</a>
                @endif
            </div>
        </section>
    @else
    @php
        $estados = $this->estadosPasos;
        $etiquetaEstado = ['actual' => 'Current', 'actual-error' => 'Current · error', 'completo' => 'Completed', 'pendiente' => 'Pending'];
        $conError = $this->pasosConError;
        $errores = $this->erroresResumen;
        $rev = $this->revision['completitud'] ?? null;
        $pct = (int) ($rev['porcentaje'] ?? 0);
        $faltan = count($rev['faltantes'] ?? []);
        $textoFaltan = $faltan ? $faltan.' recommended '.($faltan === 1 ? 'item' : 'items').' missing.' : 'Record complete.';
        $tono = $this->tonoImc;
        $nombreVisible = trim($nombre) !== '' ? $nombre : 'No name';
        $imcTxt = $this->imc !== null ? number_format($this->imc, 1) : '—';
        $edadTxt = $this->edad !== null ? $this->edad.' yr' : '—';
        $alcanzable = fn (int $n) => $n === 1 || collect(range(1, $n - 1))->every(fn ($p) => in_array($p, $pasosValidos, true));
    @endphp
    <form class="nt-form" wire:submit.prevent="{{ $paso === 5 ? 'guardar' : 'siguiente' }}" novalidate aria-labelledby="paso-titulo">

        {{-- ===== Indicador de pasos: completo (escritorio) ===== --}}
        <nav class="nt-pasos nt-pasos--completo" aria-label="New crew member steps">
            <ol>
                @foreach ($this->pasos as $n => [$titulo, $icono])
                    @php $e = $estados[$n]; @endphp
                    <li class="nt-paso" data-estado="{{ $e }}" wire:key="nt-paso-{{ $n }}">
                        <button type="button" class="nt-paso-btn" wire:click="irAPaso({{ $n }})" title="{{ $titulo }} · {{ $etiquetaEstado[$e] }}"
                                aria-label="Step {{ $n }} of 5: {{ $titulo }}. {{ $etiquetaEstado[$e] }}"
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
        <nav class="nt-pasos nt-pasos--compacto" aria-label="New crew member steps (compact)">
            <div class="nt-compacto-cab">
                <span class="nt-compacto-tit"><span class="nt-ojo">Step {{ $paso }} of 5</span><span class="nt-compacto-nom">{{ $nombrePaso }}</span></span>
                <span class="nt-pastilla nt-pastilla--paso" data-estado="{{ $estados[$paso] }}"><span class="icono" aria-hidden="true">{{ $estados[$paso] === 'actual-error' ? 'priority_high' : 'edit' }}</span>{{ $etiquetaEstado[$estados[$paso]] }}</span>
            </div>
            <ol class="nt-segmentos">
                @foreach ($this->pasos as $n => [$titulo, $icono])
                    @php $e = $estados[$n]; @endphp
                    <li wire:key="nt-seg-{{ $n }}" data-estado="{{ $e }}">
                        <button type="button" wire:click="irAPaso({{ $n }})" aria-label="Step {{ $n }} of 5: {{ $titulo }}. {{ $etiquetaEstado[$e] }}"
                                @if ($n === $paso) aria-current="step" @endif @if (! $alcanzable($n)) disabled @endif><span></span></button>
                    </li>
                @endforeach
            </ol>
            @if ($conError)
                <span class="nt-nota-error"><span class="icono" aria-hidden="true">error</span>{{ collect($conError)->map(fn ($i) => 'Step '.$i)->implode(', ') }} {{ count($conError) > 1 ? 'have' : 'has' }} errors.</span>
            @endif
        </nav>

        {{-- ===== Resumen en línea (pantallas medias y estrechas) ===== --}}
        <details class="nt-resumen nt-resumen--linea" wire:key="nt-resumen-linea">
            <summary>
                <span class="nt-avatar nt-avatar--s" aria-hidden="true">{{ $this->iniciales }}</span>
                <span class="nt-resumen-tit"><strong>{{ $nombreVisible }}</strong><span class="nt-ojo">Summary · {{ $pct }} % complete</span></span>
                <span class="icono nt-resumen-flecha" aria-hidden="true">expand_more</span>
            </summary>
            <div class="nt-resumen-cuerpo">
                <dl class="nt-resumen-dl">
                    <div><dt>Age</dt><dd>{{ $edadTxt }}</dd></div>
                    <div><dt>BMI</dt><dd><span>{{ $imcTxt }}</span>
                        @if ($tono) <span class="nt-tono" data-estado="{{ $tono['estado'] }}"><span class="icono" aria-hidden="true">{{ $tono['icono'] }}</span>{{ $tono['etiqueta'] }}</span> @endif</dd></div>
                </dl>
                <div class="nt-barra-pct" role="progressbar" aria-label="Record complete" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                <span class="nt-resumen-falta">{{ $textoFaltan }}</span>
            </div>
        </details>

        <div class="nt-cuerpo">
            <div class="nt-principal">
                <div class="nt-paso-cab">
                    <span class="nt-ojo">Step {{ $paso }} of 5</span>
                    <h2 id="paso-titulo" class="nt-titulo" tabindex="-1">{{ $nombrePaso }}</h2>
                    <p>{{ $this->descripcionPaso }}</p>
                </div>

                @if ($errores)
                    <div id="resumen-errores" class="nt-resumen-errores" role="alert" tabindex="-1">
                        <span class="icono" aria-hidden="true">error</span>
                        <div>
                            <strong>Review {{ count($errores) === 1 ? 'the marked field' : 'the '.count($errores).' marked fields' }} before continuing</strong>
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
                    <button type="button" class="nt-btn nt-btn--suave" wire:click="cancelar" wire:loading.attr="disabled" wire:target="guardar">Cancel</button>
                    <span class="nt-barra-sep"></span>
                    @if ($paso > 1)
                        <button type="button" class="nt-btn" wire:click="anterior" aria-label="Previous step" wire:loading.attr="disabled" wire:target="guardar"><span class="icono" aria-hidden="true">arrow_back</span><span class="nt-btn-txt">Previous</span></button>
                    @endif
                    @if ($paso < 5)
                        <button type="submit" class="nt-btn nt-btn--primario">Next <span class="icono" aria-hidden="true">arrow_forward</span></button>
                    @else
                        <button type="submit" class="nt-btn nt-btn--primario nt-btn--guardar" wire:loading.attr="disabled" wire:loading.class="is-guardando" wire:target="guardar">
                            <span class="nt-guardar-normal"><span class="icono" aria-hidden="true">person_add</span> Save crew member</span>
                            <span class="nt-guardar-curso"><span class="nt-giro" aria-hidden="true"></span> Saving…</span>
                        </button>
                    @endif
                </div>
                <span class="nt-sr" role="status" aria-live="polite"><span wire:loading wire:target="guardar">Saving crew member…</span></span>
                <p class="nt-seguridad">This tool provides guidance only; it does not diagnose or prescribe.</p>
            </div>

            {{-- ===== Resumen lateral (pantallas anchas) ===== --}}
            <aside class="nt-resumen nt-resumen--lateral" aria-label="Record summary" wire:key="nt-resumen-lateral">
                <span class="nt-ojo">Summary</span>
                <div class="nt-resumen-id">
                    <span class="nt-avatar" aria-hidden="true">{{ $this->iniciales }}</span>
                    <span class="nt-resumen-tit"><strong>{{ $nombreVisible }}</strong><span>{{ $this->rolResumen }}</span></span>
                </div>
                <dl class="nt-resumen-dl">
                    <div><dt>Age</dt><dd>{{ $edadTxt }}</dd></div>
                    <div><dt>BMI</dt><dd><span>{{ $imcTxt }}</span>
                        @if ($tono) <span class="nt-tono" data-estado="{{ $tono['estado'] }}"><span class="icono" aria-hidden="true">{{ $tono['icono'] }}</span>{{ $tono['etiqueta'] }}</span> @endif</dd></div>
                </dl>
                <div class="nt-resumen-pct">
                    <div class="nt-resumen-pct-cab"><span id="nt-sum-pct" class="nt-ojo">Record</span><span>{{ $pct }} %</span></div>
                    <div class="nt-barra-pct" role="progressbar" aria-labelledby="nt-sum-pct" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                    <span class="nt-resumen-falta">{{ $textoFaltan }}</span>
                </div>
                <p class="nt-resumen-nota">The app provides guidance only; it does not diagnose or prescribe. Data is stored on this device and sent to the ground when a link is available.</p>
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
