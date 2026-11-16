{{--
    Asistente "Nuevo tripulante" (5 pasos). MARCADO PROVISIONAL: la lógica está en App\Livewire\NuevoTripulante;
    el diseñador puede reorganizar el HTML siempre que conserve los wire:model / wire:click.
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

    @if ($this->mision && $this->cupoLleno)
        <div class="nt-bloqueo" role="alert">
            <span class="icono">groups</span>
            <div>
                <strong>Tripulación completa</strong>
                <p>La misión ya tiene el máximo de {{ \App\Services\Tripulacion\AltaTripulante::maximo() }} tripulantes. No se pueden añadir más.</p>
                <button type="button" class="nt-btn" wire:click="cancelar">Volver a Tripulación</button>
            </div>
        </div>
    @elseif (! $this->mision)
        <div class="nt-bloqueo" role="alert">
            <span class="icono">block</span>
            <div>
                <strong>No hay una misión activa</strong>
                <p>Para dar de alta un tripulante primero debe existir una misión con tripulación. Crea o selecciona una misión y vuelve a intentarlo.</p>
                <button type="button" class="nt-btn" wire:click="cancelar">Volver a Tripulación</button>
            </div>
        </div>
    @else
    <form class="nt-form" wire:submit.prevent="{{ $paso === 5 ? 'guardar' : 'siguiente' }}" novalidate>

        {{-- ===== Indicador de pasos ===== --}}
        <nav class="nt-pasos" aria-label="Pasos del alta de tripulante">
            <ol>
                @foreach ($this->pasos as $n => [$titulo, $icono])
                    @php
                        $estado = $n === $paso ? 'actual' : (in_array($n, $pasosValidos, true) ? 'completo' : 'pendiente');
                        $alcanzable = $n === 1 || collect(range(1, $n - 1))->every(fn ($p) => in_array($p, $pasosValidos, true));
                    @endphp
                    <li class="nt-paso" data-estado="{{ $estado }}" wire:key="nt-paso-{{ $n }}">
                        <button type="button" class="nt-paso-btn" wire:click="irAPaso({{ $n }})"
                                @if ($n === $paso) aria-current="step" @endif
                                @if (! $alcanzable) disabled @endif>
                            <span class="icono nt-paso-ic">{{ $estado === 'completo' ? 'check_circle' : $icono }}</span>
                            <span class="nt-paso-txt">
                                <span class="nt-paso-n">Paso {{ $n }}</span>
                                <span class="nt-paso-t">{{ $titulo }}</span>
                                <span class="nt-sr">{{ $estado === 'completo' ? '(completado)' : ($estado === 'actual' ? '(paso actual)' : '(pendiente)') }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($errors->any())
            <div class="nt-resumen-errores" role="alert">
                <span class="icono">error</span>
                Revisa {{ $errors->count() === 1 ? 'el campo marcado' : 'los '.$errors->count().' campos marcados' }} antes de continuar.
            </div>
        @endif

        <section class="nt-tarjeta" aria-labelledby="nt-titulo-paso">
            <h2 id="nt-titulo-paso" class="nt-titulo">Paso {{ $paso }} de 5 · {{ $nombrePaso }}</h2>

            {{-- ================= PASO 1: IDENTIDAD ================= --}}
            @if ($paso === 1)
                <div class="nt-rejilla">
                    @foreach ($campos1 as [$k, $et, $tipo, $attrs, $ayuda])
                        <div class="nt-campo" wire:key="c1-{{ $k }}">
                            <label for="{{ $id($k) }}">{{ $et }}</label>
                            <input id="{{ $id($k) }}" type="{{ $tipo }}" wire:model="{{ $k }}" {!! $attrs !!}
                                   @if ($k === 'fecha_nacimiento') max="{{ now()->subYears($lim('edad')[0])->toDateString() }}" @endif
                                   aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" @if ($describe($k, $k === 'fecha_nacimiento')) aria-describedby="{{ $describe($k, $k === 'fecha_nacimiento') }}" @endif>
                            @if ($k === 'fecha_nacimiento')
                                <p class="nt-ayuda" id="{{ $id($k) }}-ayuda">{{ $this->edad !== null ? 'Edad: '.$this->edad.' años' : 'La edad se calcula con la fecha.' }}</p>
                            @endif
                            @error($k) <p class="nt-error" id="{{ $id($k) }}-error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div class="nt-campo">
                        <label for="{{ $id('sexo') }}">Sexo</label>
                        <select id="{{ $id('sexo') }}" wire:model="sexo" required aria-invalid="{{ $errors->has('sexo') ? 'true' : 'false' }}" @if ($describe('sexo')) aria-describedby="{{ $describe('sexo') }}" @endif>
                            <option value="">{{ $placeholderSel }}</option>
                            @foreach ($cfg('sexos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                        </select>
                        @error('sexo') <p class="nt-error" id="{{ $id('sexo') }}-error">{{ $message }}</p> @enderror
                    </div>

                    @foreach ($campos1b as [$k, $et, $tipo, $attrs, $ayuda])
                        <div class="nt-campo" wire:key="c1b-{{ $k }}">
                            <label for="{{ $id($k) }}">{{ $et }}</label>
                            <input id="{{ $id($k) }}" type="{{ $tipo }}" wire:model="{{ $k }}" {!! $attrs !!}
                                   aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" @if ($describe($k)) aria-describedby="{{ $describe($k) }}" @endif>
                            @error($k) <p class="nt-error" id="{{ $id($k) }}-error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div class="nt-campo">
                        <label for="{{ $id('rol') }}">Rol en la misión</label>
                        <input id="{{ $id('rol') }}" type="text" wire:model="rol" list="nt-roles" maxlength="80"
                               aria-invalid="{{ $errors->has('rol') ? 'true' : 'false' }}" @if ($describe('rol')) aria-describedby="{{ $describe('rol') }}" @endif>
                        <datalist id="nt-roles">@foreach ($cfg('roles', []) as $r) <option value="{{ $r }}"></option> @endforeach</datalist>
                        @error('rol') <p class="nt-error" id="{{ $id('rol') }}-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="nt-campo">
                        <label for="{{ $id('fecha_ingreso') }}">Fecha de ingreso al cuerpo de astronautas</label>
                        <input id="{{ $id('fecha_ingreso') }}" type="date" wire:model="fecha_ingreso" max="{{ now()->toDateString() }}"
                               aria-invalid="{{ $errors->has('fecha_ingreso') ? 'true' : 'false' }}" @if ($describe('fecha_ingreso')) aria-describedby="{{ $describe('fecha_ingreso') }}" @endif>
                        @error('fecha_ingreso') <p class="nt-error" id="{{ $id('fecha_ingreso') }}-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="nt-campo">
                        <label for="{{ $id('estado_servicio') }}">Estado de servicio</label>
                        <select id="{{ $id('estado_servicio') }}" wire:model="estado_servicio" aria-invalid="{{ $errors->has('estado_servicio') ? 'true' : 'false' }}" @if ($describe('estado_servicio')) aria-describedby="{{ $describe('estado_servicio') }}" @endif>
                            @foreach ($cfg('estados_servicio', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                        </select>
                        @error('estado_servicio') <p class="nt-error" id="{{ $id('estado_servicio') }}-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif

            {{-- ================= PASO 2: BIOMETRÍA Y LÍNEA BASE ================= --}}
            @if ($paso === 2)
                <fieldset class="nt-grupo">
                    <legend>Biometría</legend>
                    <div class="nt-rejilla">
                        @foreach ($bio as [$k, $et, $tipo, $attrs, $ayuda])
                            <div class="nt-campo" wire:key="c2-{{ $k }}">
                                <label for="{{ $id($k) }}">{{ $et }}</label>
                                <input id="{{ $id($k) }}" type="{{ $tipo }}" wire:model="{{ $k }}" {!! $attrs !!}
                                       aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" aria-describedby="{{ $describe($k, true) }}">
                                <p class="nt-ayuda" id="{{ $id($k) }}-ayuda">{{ $ayuda }}</p>
                                @error($k) <p class="nt-error" id="{{ $id($k) }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div class="nt-campo">
                            <label for="{{ $id('grupo_sanguineo') }}">Grupo sanguíneo</label>
                            <select id="{{ $id('grupo_sanguineo') }}" wire:model="grupo_sanguineo" aria-invalid="{{ $errors->has('grupo_sanguineo') ? 'true' : 'false' }}" @if ($describe('grupo_sanguineo')) aria-describedby="{{ $describe('grupo_sanguineo') }}" @endif>
                                <option value="">{{ $placeholderSel }}</option>
                                @foreach ($cfg('grupos_sanguineos', []) as $g) <option value="{{ $g }}">{{ $g }}</option> @endforeach
                            </select>
                            @error('grupo_sanguineo') <p class="nt-error" id="{{ $id('grupo_sanguineo') }}-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="nt-campo nt-calculado" aria-live="polite">
                            <span class="nt-etiqueta">IMC calculado</span>
                            <strong>{{ $this->imc !== null ? number_format($this->imc, 1, '.', '') : '—' }}</strong>
                            <p class="nt-ayuda">Se calcula con altura y peso; es solo orientativo.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="nt-grupo">
                    <legend>Signos basales (valores en reposo)</legend>
                    <div class="nt-rejilla">
                        @foreach ($basales as [$k, $et, $tipo, $attrs, $ayuda])
                            <div class="nt-campo" wire:key="c2b-{{ $k }}">
                                <label for="{{ $id($k) }}">{{ $et }}</label>
                                <input id="{{ $id($k) }}" type="{{ $tipo }}" wire:model="{{ $k }}" {!! $attrs !!}
                                       aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" aria-describedby="{{ $describe($k, true) }}">
                                <p class="nt-ayuda" id="{{ $id($k) }}-ayuda">{{ $ayuda }}</p>
                                @error($k) <p class="nt-error" id="{{ $id($k) }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="nt-grupo">
                    <legend>Hábitos</legend>
                    <div class="nt-rejilla">
                        @foreach ($habitos as [$k, $et, $tipo, $attrs, $ayuda])
                            <div class="nt-campo" wire:key="c2c-{{ $k }}">
                                <label for="{{ $id($k) }}">{{ $et }}</label>
                                <input id="{{ $id($k) }}" type="{{ $tipo }}" wire:model="{{ $k }}" {!! $attrs !!}
                                       aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" aria-describedby="{{ $describe($k, true) }}">
                                <p class="nt-ayuda" id="{{ $id($k) }}-ayuda">{{ $ayuda }}</p>
                                @error($k) <p class="nt-error" id="{{ $id($k) }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div class="nt-campo">
                            <label for="{{ $id('alcohol') }}">Consumo de alcohol</label>
                            <select id="{{ $id('alcohol') }}" wire:model="alcohol" aria-invalid="{{ $errors->has('alcohol') ? 'true' : 'false' }}" @if ($describe('alcohol')) aria-describedby="{{ $describe('alcohol') }}" @endif>
                                <option value="">{{ $placeholderSel }}</option>
                                @foreach ($cfg('alcohol', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                            </select>
                            @error('alcohol') <p class="nt-error" id="{{ $id('alcohol') }}-error">{{ $message }}</p> @enderror
                        </div>
                        <fieldset class="nt-campo nt-radios">
                            <legend>¿Fuma?</legend>
                            @foreach ($siNo as $v => $et)
                                <label class="nt-radio"><input type="radio" name="nt-fumador" value="{{ $v }}" wire:model="fumador"> {{ $et }}</label>
                            @endforeach
                            @error('fumador') <p class="nt-error" id="{{ $id('fumador') }}-error">{{ $message }}</p> @enderror
                        </fieldset>
                    </div>
                </fieldset>

                <fieldset class="nt-grupo">
                    <legend>Línea base personal por marcador</legend>
                    <p class="nt-ayuda">Valor habitual de esta persona para cada marcador. Sirve de referencia para comparar sus mediciones. Todos son opcionales.</p>
                    <div class="nt-acciones-fila">
                        <button type="button" class="nt-btn" wire:click="precargarLineaBase"><span class="icono">auto_fix_high</span> Usar valores de ejemplo</button>
                        <button type="button" class="nt-btn nt-btn--suave" wire:click="vaciarLineaBase"><span class="icono">backspace</span> Vaciar</button>
                    </div>
                    <p class="nt-ayuda">Los valores de ejemplo salen de la configuración del sistema; puedes editarlos uno a uno.</p>
                    <div class="nt-rejilla">
                        @foreach ($marcadores as $clave => $m)
                            <div class="nt-campo" wire:key="lb-{{ $clave }}">
                                <label for="{{ $id('lb-'.$clave) }}">{{ $m['label'] }} <span class="nt-unidad">({{ $m['unit'] }})</span></label>
                                <input id="{{ $id('lb-'.$clave) }}" type="text" inputmode="decimal" wire:model="linea_base.{{ $clave }}"
                                       aria-invalid="{{ $errors->has('linea_base.'.$clave) ? 'true' : 'false' }}"
                                       @if ($errors->has('linea_base.'.$clave)) aria-describedby="{{ $id('linea_base.'.$clave) }}-error" @endif>
                                @error('linea_base.'.$clave) <p class="nt-error" id="{{ $id('linea_base.'.$clave) }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                    @error('linea_base') <p class="nt-error" id="{{ $id('linea_base') }}-error">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            {{-- ================= PASO 3: HISTORIA CLÍNICA ================= --}}
            @if ($paso === 3)
                <fieldset class="nt-grupo">
                    <legend>Aptitud de vuelo y último examen</legend>
                    <div class="nt-rejilla">
                        <div class="nt-campo">
                            <label for="{{ $id('aptitud_vuelo') }}">Aptitud de vuelo</label>
                            <select id="{{ $id('aptitud_vuelo') }}" wire:model="aptitud_vuelo" aria-invalid="{{ $errors->has('aptitud_vuelo') ? 'true' : 'false' }}" @if ($describe('aptitud_vuelo')) aria-describedby="{{ $describe('aptitud_vuelo') }}" @endif>
                                @foreach ($cfg('aptitudes', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                            </select>
                            @error('aptitud_vuelo') <p class="nt-error" id="{{ $id('aptitud_vuelo') }}-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="nt-campo">
                            <label for="{{ $id('ultimo_examen_en') }}">Fecha del último examen médico</label>
                            <input id="{{ $id('ultimo_examen_en') }}" type="date" wire:model="ultimo_examen_en" max="{{ now()->toDateString() }}"
                                   aria-invalid="{{ $errors->has('ultimo_examen_en') ? 'true' : 'false' }}" @if ($describe('ultimo_examen_en')) aria-describedby="{{ $describe('ultimo_examen_en') }}" @endif>
                            @error('ultimo_examen_en') <p class="nt-error" id="{{ $id('ultimo_examen_en') }}-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="nt-campo nt-ancho">
                            <label for="{{ $id('restricciones') }}">Restricciones</label>
                            <textarea id="{{ $id('restricciones') }}" rows="2" maxlength="1000" wire:model="restricciones"
                                      aria-invalid="{{ $errors->has('restricciones') ? 'true' : 'false' }}" @if ($describe('restricciones')) aria-describedby="{{ $describe('restricciones') }}" @endif></textarea>
                            @error('restricciones') <p class="nt-error" id="{{ $id('restricciones') }}-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>

                {{-- Alergias --}}
                <fieldset class="nt-grupo">
                    <legend>Alergias</legend>
                    @forelse ($alergias as $i => $f)
                        <div class="nt-fila" wire:key="alergia-{{ $i }}">
                            <div class="nt-campo">
                                <label for="{{ $id("alergias.$i.sustancia") }}">Sustancia</label>
                                <input id="{{ $id("alergias.$i.sustancia") }}" type="text" maxlength="120" wire:model="alergias.{{ $i }}.sustancia"
                                       aria-invalid="{{ $errors->has("alergias.$i.sustancia") ? 'true' : 'false' }}" @if ($describe("alergias.$i.sustancia")) aria-describedby="{{ $describe("alergias.$i.sustancia") }}" @endif>
                                @error("alergias.$i.sustancia") <p class="nt-error" id="{{ $id("alergias.$i.sustancia") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("alergias.$i.reaccion") }}">Reacción</label>
                                <input id="{{ $id("alergias.$i.reaccion") }}" type="text" maxlength="160" wire:model="alergias.{{ $i }}.reaccion"
                                       aria-invalid="{{ $errors->has("alergias.$i.reaccion") ? 'true' : 'false' }}" @if ($describe("alergias.$i.reaccion")) aria-describedby="{{ $describe("alergias.$i.reaccion") }}" @endif>
                                @error("alergias.$i.reaccion") <p class="nt-error" id="{{ $id("alergias.$i.reaccion") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("alergias.$i.gravedad") }}">Gravedad</label>
                                <select id="{{ $id("alergias.$i.gravedad") }}" wire:model="alergias.{{ $i }}.gravedad"
                                        aria-invalid="{{ $errors->has("alergias.$i.gravedad") ? 'true' : 'false' }}" @if ($describe("alergias.$i.gravedad")) aria-describedby="{{ $describe("alergias.$i.gravedad") }}" @endif>
                                    @foreach ($cfg('gravedades', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                </select>
                                @error("alergias.$i.gravedad") <p class="nt-error" id="{{ $id("alergias.$i.gravedad") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <button type="button" class="nt-btn nt-btn--quitar" wire:click="quitarAlergia({{ $i }})" aria-label="Quitar alergia {{ $i + 1 }}"><span class="icono">delete</span> Quitar</button>
                        </div>
                    @empty
                        <p class="nt-vacio">Sin alergias registradas.</p>
                    @endforelse
                    @error('alergias') <p class="nt-error" id="{{ $id('alergias') }}-error">{{ $message }}</p> @enderror
                    <button type="button" class="nt-btn" wire:click="agregarAlergia" @if (count($alergias) >= $maxItems['alergias']) disabled @endif><span class="icono">add</span> Añadir alergia</button>
                </fieldset>

                {{-- Condiciones médicas --}}
                <fieldset class="nt-grupo">
                    <legend>Condiciones médicas</legend>
                    @forelse ($condiciones as $i => $f)
                        <div class="nt-fila" wire:key="condicion-{{ $i }}">
                            <div class="nt-campo">
                                <label for="{{ $id("condiciones.$i.nombre") }}">Condición</label>
                                <input id="{{ $id("condiciones.$i.nombre") }}" type="text" maxlength="120" wire:model="condiciones.{{ $i }}.nombre"
                                       aria-invalid="{{ $errors->has("condiciones.$i.nombre") ? 'true' : 'false' }}" @if ($describe("condiciones.$i.nombre")) aria-describedby="{{ $describe("condiciones.$i.nombre") }}" @endif>
                                @error("condiciones.$i.nombre") <p class="nt-error" id="{{ $id("condiciones.$i.nombre") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("condiciones.$i.estado") }}">Estado</label>
                                <select id="{{ $id("condiciones.$i.estado") }}" wire:model="condiciones.{{ $i }}.estado"
                                        aria-invalid="{{ $errors->has("condiciones.$i.estado") ? 'true' : 'false' }}" @if ($describe("condiciones.$i.estado")) aria-describedby="{{ $describe("condiciones.$i.estado") }}" @endif>
                                    @foreach ($cfg('estados_condicion', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                </select>
                                @error("condiciones.$i.estado") <p class="nt-error" id="{{ $id("condiciones.$i.estado") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("condiciones.$i.desde") }}">Desde</label>
                                <input id="{{ $id("condiciones.$i.desde") }}" type="date" max="{{ now()->toDateString() }}" wire:model="condiciones.{{ $i }}.desde"
                                       aria-invalid="{{ $errors->has("condiciones.$i.desde") ? 'true' : 'false' }}" @if ($describe("condiciones.$i.desde")) aria-describedby="{{ $describe("condiciones.$i.desde") }}" @endif>
                                @error("condiciones.$i.desde") <p class="nt-error" id="{{ $id("condiciones.$i.desde") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo nt-ancho">
                                <label for="{{ $id("condiciones.$i.notas") }}">Notas</label>
                                <input id="{{ $id("condiciones.$i.notas") }}" type="text" maxlength="500" wire:model="condiciones.{{ $i }}.notas"
                                       aria-invalid="{{ $errors->has("condiciones.$i.notas") ? 'true' : 'false' }}" @if ($describe("condiciones.$i.notas")) aria-describedby="{{ $describe("condiciones.$i.notas") }}" @endif>
                                @error("condiciones.$i.notas") <p class="nt-error" id="{{ $id("condiciones.$i.notas") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <button type="button" class="nt-btn nt-btn--quitar" wire:click="quitarCondicion({{ $i }})" aria-label="Quitar condición {{ $i + 1 }}"><span class="icono">delete</span> Quitar</button>
                        </div>
                    @empty
                        <p class="nt-vacio">Sin condiciones médicas registradas.</p>
                    @endforelse
                    @error('condiciones') <p class="nt-error" id="{{ $id('condiciones') }}-error">{{ $message }}</p> @enderror
                    <button type="button" class="nt-btn" wire:click="agregarCondicion" @if (count($condiciones) >= $maxItems['condiciones']) disabled @endif><span class="icono">add</span> Añadir condición</button>
                </fieldset>

                {{-- Medicamentos --}}
                <fieldset class="nt-grupo">
                    <legend>Medicamentos</legend>
                    <p class="nt-aviso"><span class="icono">info</span> Es un registro de lo que la persona toma o ha tomado; no es una receta ni una indicación médica.</p>
                    @forelse ($medicamentos as $i => $f)
                        <div class="nt-fila" wire:key="medicamento-{{ $i }}">
                            <div class="nt-campo">
                                <label for="{{ $id("medicamentos.$i.nombre") }}">Medicamento</label>
                                <input id="{{ $id("medicamentos.$i.nombre") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.nombre"
                                       aria-invalid="{{ $errors->has("medicamentos.$i.nombre") ? 'true' : 'false' }}" @if ($describe("medicamentos.$i.nombre")) aria-describedby="{{ $describe("medicamentos.$i.nombre") }}" @endif>
                                @error("medicamentos.$i.nombre") <p class="nt-error" id="{{ $id("medicamentos.$i.nombre") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("medicamentos.$i.dosis_texto") }}">Dosis (texto libre)</label>
                                <input id="{{ $id("medicamentos.$i.dosis_texto") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.dosis_texto"
                                       aria-invalid="{{ $errors->has("medicamentos.$i.dosis_texto") ? 'true' : 'false' }}" @if ($describe("medicamentos.$i.dosis_texto")) aria-describedby="{{ $describe("medicamentos.$i.dosis_texto") }}" @endif>
                                @error("medicamentos.$i.dosis_texto") <p class="nt-error" id="{{ $id("medicamentos.$i.dosis_texto") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("medicamentos.$i.frecuencia") }}">Frecuencia</label>
                                <input id="{{ $id("medicamentos.$i.frecuencia") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.frecuencia"
                                       aria-invalid="{{ $errors->has("medicamentos.$i.frecuencia") ? 'true' : 'false' }}" @if ($describe("medicamentos.$i.frecuencia")) aria-describedby="{{ $describe("medicamentos.$i.frecuencia") }}" @endif>
                                @error("medicamentos.$i.frecuencia") <p class="nt-error" id="{{ $id("medicamentos.$i.frecuencia") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("medicamentos.$i.motivo") }}">Motivo</label>
                                <input id="{{ $id("medicamentos.$i.motivo") }}" type="text" maxlength="160" wire:model="medicamentos.{{ $i }}.motivo"
                                       aria-invalid="{{ $errors->has("medicamentos.$i.motivo") ? 'true' : 'false' }}" @if ($describe("medicamentos.$i.motivo")) aria-describedby="{{ $describe("medicamentos.$i.motivo") }}" @endif>
                                @error("medicamentos.$i.motivo") <p class="nt-error" id="{{ $id("medicamentos.$i.motivo") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("medicamentos.$i.desde") }}">Desde</label>
                                <input id="{{ $id("medicamentos.$i.desde") }}" type="date" max="{{ now()->toDateString() }}" wire:model="medicamentos.{{ $i }}.desde"
                                       aria-invalid="{{ $errors->has("medicamentos.$i.desde") ? 'true' : 'false' }}" @if ($describe("medicamentos.$i.desde")) aria-describedby="{{ $describe("medicamentos.$i.desde") }}" @endif>
                                @error("medicamentos.$i.desde") <p class="nt-error" id="{{ $id("medicamentos.$i.desde") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo nt-check">
                                <label><input type="checkbox" wire:model="medicamentos.{{ $i }}.activo"> Lo toma actualmente</label>
                            </div>
                            <button type="button" class="nt-btn nt-btn--quitar" wire:click="quitarMedicamento({{ $i }})" aria-label="Quitar medicamento {{ $i + 1 }}"><span class="icono">delete</span> Quitar</button>
                        </div>
                    @empty
                        <p class="nt-vacio">Sin medicamentos registrados.</p>
                    @endforelse
                    @error('medicamentos') <p class="nt-error" id="{{ $id('medicamentos') }}-error">{{ $message }}</p> @enderror
                    <button type="button" class="nt-btn" wire:click="agregarMedicamento" @if (count($medicamentos) >= $maxItems['medicamentos']) disabled @endif><span class="icono">add</span> Añadir medicamento</button>
                </fieldset>

                {{-- Antecedentes familiares --}}
                <fieldset class="nt-grupo">
                    <legend>Antecedentes familiares</legend>
                    @forelse ($antecedentes as $i => $f)
                        <div class="nt-fila" wire:key="antecedente-{{ $i }}">
                            <div class="nt-campo">
                                <label for="{{ $id("antecedentes.$i.parentesco") }}">Parentesco</label>
                                <select id="{{ $id("antecedentes.$i.parentesco") }}" wire:model="antecedentes.{{ $i }}.parentesco"
                                        aria-invalid="{{ $errors->has("antecedentes.$i.parentesco") ? 'true' : 'false' }}" @if ($describe("antecedentes.$i.parentesco")) aria-describedby="{{ $describe("antecedentes.$i.parentesco") }}" @endif>
                                    <option value="">{{ $placeholderSel }}</option>
                                    @foreach ($cfg('parentescos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                </select>
                                @error("antecedentes.$i.parentesco") <p class="nt-error" id="{{ $id("antecedentes.$i.parentesco") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("antecedentes.$i.condicion") }}">Condición</label>
                                <input id="{{ $id("antecedentes.$i.condicion") }}" type="text" maxlength="160" wire:model="antecedentes.{{ $i }}.condicion"
                                       aria-invalid="{{ $errors->has("antecedentes.$i.condicion") ? 'true' : 'false' }}" @if ($describe("antecedentes.$i.condicion")) aria-describedby="{{ $describe("antecedentes.$i.condicion") }}" @endif>
                                @error("antecedentes.$i.condicion") <p class="nt-error" id="{{ $id("antecedentes.$i.condicion") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("antecedentes.$i.edad_aparicion") }}">Edad de aparición</label>
                                <input id="{{ $id("antecedentes.$i.edad_aparicion") }}" type="number" inputmode="numeric" min="{{ $lim('edad_aparicion')[0] }}" max="{{ $lim('edad_aparicion')[1] }}" wire:model="antecedentes.{{ $i }}.edad_aparicion"
                                       aria-invalid="{{ $errors->has("antecedentes.$i.edad_aparicion") ? 'true' : 'false' }}" @if ($describe("antecedentes.$i.edad_aparicion")) aria-describedby="{{ $describe("antecedentes.$i.edad_aparicion") }}" @endif>
                                @error("antecedentes.$i.edad_aparicion") <p class="nt-error" id="{{ $id("antecedentes.$i.edad_aparicion") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <button type="button" class="nt-btn nt-btn--quitar" wire:click="quitarAntecedente({{ $i }})" aria-label="Quitar antecedente {{ $i + 1 }}"><span class="icono">delete</span> Quitar</button>
                        </div>
                    @empty
                        <p class="nt-vacio">Sin antecedentes familiares registrados.</p>
                    @endforelse
                    @error('antecedentes') <p class="nt-error" id="{{ $id('antecedentes') }}-error">{{ $message }}</p> @enderror
                    <button type="button" class="nt-btn" wire:click="agregarAntecedente" @if (count($antecedentes) >= $maxItems['antecedentes']) disabled @endif><span class="icono">add</span> Añadir antecedente</button>
                </fieldset>

                <div class="nt-campo nt-ancho">
                    <label for="{{ $id('notas') }}">Notas médicas</label>
                    <textarea id="{{ $id('notas') }}" rows="3" maxlength="2000" wire:model="notas"
                              aria-invalid="{{ $errors->has('notas') ? 'true' : 'false' }}" @if ($describe('notas')) aria-describedby="{{ $describe('notas') }}" @endif></textarea>
                    @error('notas') <p class="nt-error" id="{{ $id('notas') }}-error">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- ================= PASO 4: CONTACTOS ================= --}}
            @if ($paso === 4)
                <fieldset class="nt-grupo">
                    <legend>Contactos de emergencia</legend>
                    <p class="nt-ayuda">Hasta {{ $maxItems['contactos'] }} contactos. Si añades alguno, uno debe quedar marcado como principal.</p>
                    @forelse ($contactos as $i => $f)
                        <div class="nt-fila" wire:key="contacto-{{ $i }}">
                            <div class="nt-campo">
                                <label for="{{ $id("contactos.$i.nombre") }}">Nombre</label>
                                <input id="{{ $id("contactos.$i.nombre") }}" type="text" maxlength="120" wire:model="contactos.{{ $i }}.nombre"
                                       aria-invalid="{{ $errors->has("contactos.$i.nombre") ? 'true' : 'false' }}" @if ($describe("contactos.$i.nombre")) aria-describedby="{{ $describe("contactos.$i.nombre") }}" @endif>
                                @error("contactos.$i.nombre") <p class="nt-error" id="{{ $id("contactos.$i.nombre") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("contactos.$i.parentesco") }}">Parentesco</label>
                                <select id="{{ $id("contactos.$i.parentesco") }}" wire:model="contactos.{{ $i }}.parentesco"
                                        aria-invalid="{{ $errors->has("contactos.$i.parentesco") ? 'true' : 'false' }}" @if ($describe("contactos.$i.parentesco")) aria-describedby="{{ $describe("contactos.$i.parentesco") }}" @endif>
                                    <option value="">{{ $placeholderSel }}</option>
                                    @foreach ($cfg('parentescos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                </select>
                                @error("contactos.$i.parentesco") <p class="nt-error" id="{{ $id("contactos.$i.parentesco") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo">
                                <label for="{{ $id("contactos.$i.telefono") }}">Teléfono</label>
                                <input id="{{ $id("contactos.$i.telefono") }}" type="tel" maxlength="32" wire:model="contactos.{{ $i }}.telefono"
                                       aria-invalid="{{ $errors->has("contactos.$i.telefono") ? 'true' : 'false' }}" @if ($describe("contactos.$i.telefono")) aria-describedby="{{ $describe("contactos.$i.telefono") }}" @endif>
                                @error("contactos.$i.telefono") <p class="nt-error" id="{{ $id("contactos.$i.telefono") }}-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="nt-campo nt-check">
                                <label><input type="radio" name="nt-contacto-principal" @checked($f['principal'] ?? false) wire:click="marcarPrincipal({{ $i }})"> Contacto principal</label>
                            </div>
                            <button type="button" class="nt-btn nt-btn--quitar" wire:click="quitarContacto({{ $i }})" aria-label="Quitar contacto {{ $i + 1 }}"><span class="icono">delete</span> Quitar</button>
                        </div>
                    @empty
                        <p class="nt-vacio">Sin contactos de emergencia.</p>
                    @endforelse
                    @error('contactos') <p class="nt-error" id="{{ $id('contactos') }}-error">{{ $message }}</p> @enderror
                    <button type="button" class="nt-btn" wire:click="agregarContacto" @if (count($contactos) >= $maxItems['contactos']) disabled @endif><span class="icono">add</span> Añadir contacto</button>
                </fieldset>
            @endif

            {{-- ================= PASO 5: REVISIÓN ================= --}}
            @if ($paso === 5)
                @php
                    $rev = $this->revision;
                    $d = $rev['datos'];
                    $comp = $rev['completitud'];
                    $v = fn ($x, $suf = '') => ($x === null || $x === '') ? '—' : $x.$suf;
                    $etq = fn (string $cat, $clave) => $clave === null ? '—' : ($cfg($cat, [])[$clave] ?? $clave);
                    $bloques = [
                        1 => ['Identidad', [
                            ['Nombre', $v($d['nombre'])], ['Fecha de nacimiento', $v($d['fecha_nacimiento']).($this->edad !== null ? ' ('.$this->edad.' años)' : '')],
                            ['Sexo', $etq('sexos', $d['sexo'])], ['Nacionalidad', $v($d['nacionalidad'])], ['Agencia', $v($d['agencia'])],
                            ['Rol', $v($d['rol'])], ['Fecha de ingreso', $v($d['fecha_ingreso'])], ['Estado de servicio', $etq('estados_servicio', $d['estado_servicio'])],
                        ]],
                        2 => ['Biometría y hábitos', [
                            ['Altura', $v($d['altura_m'], ' m')], ['Peso', $v($d['peso_kg'], ' kg')], ['IMC', $this->imc !== null ? number_format($this->imc, 1, '.', '') : '—'],
                            ['Grupo sanguíneo', $v($d['grupo_sanguineo'])], ['EVAs', $v($d['evas'])],
                            ['FC reposo / máxima', $v($d['fc_reposo']).' / '.$v($d['fc_maxima']).' lpm'],
                            ['Presión basal', $v($d['presion_basal_sis']).' / '.$v($d['presion_basal_dia']).' mmHg'],
                            ['SpO₂ / temperatura', $v($d['spo2_basal'], ' %').' / '.$v($d['temperatura_basal'], ' °C')],
                            ['Glucosa basal', $v($d['glucosa_basal'], ' mg/dL')], ['VO₂máx', $v($d['vo2max'])],
                            ['Grasa corporal', $v($d['grasa_corporal_pct'], ' %')], ['T-score óseo', $v($d['densidad_osea_tscore'])],
                            ['Sueño habitual', $v($d['horas_sueno_habituales'], ' h')], ['Actividad semanal', $v($d['actividad_fisica_semana_min'], ' min')],
                            ['Fuma', $d['fumador'] ? 'Sí' : 'No'], ['Alcohol', $etq('alcohol', $d['alcohol'])],
                            ['Radiación previa', $v($d['dosis_radiacion_previa_msv'], ' mSv')],
                            ['Línea base', count($d['linea_base']).' de '.count($marcadores).' marcadores'],
                        ]],
                        3 => ['Historia clínica', [
                            ['Aptitud de vuelo', $etq('aptitudes', $d['aptitud_vuelo'])], ['Último examen', $v($d['ultimo_examen_en'])],
                            ['Restricciones', $v($d['restricciones'])],
                            ['Alergias', count($d['alergias']) ? collect($d['alergias'])->map(fn ($a) => $a['sustancia'].' ('.$etq('gravedades', $a['gravedad']).')')->implode(', ') : 'Ninguna'],
                            ['Condiciones', count($d['condiciones']) ? collect($d['condiciones'])->map(fn ($a) => $a['nombre'].' ('.$etq('estados_condicion', $a['estado']).')')->implode(', ') : 'Ninguna'],
                            ['Medicamentos (registro)', count($d['medicamentos']) ? collect($d['medicamentos'])->map(fn ($a) => $a['nombre'].($a['dosis_texto'] ? ' '.$a['dosis_texto'] : ''))->implode(', ') : 'Ninguno'],
                            ['Antecedentes familiares', count($d['antecedentes']) ? collect($d['antecedentes'])->map(fn ($a) => $etq('parentescos', $a['parentesco']).': '.$a['condicion'])->implode(', ') : 'Ninguno'],
                            ['Notas', $v($d['notas'])],
                        ]],
                        4 => ['Contactos de emergencia', [
                            ['Contactos', count($d['contactos']) ? collect($d['contactos'])->map(fn ($c) => $c['nombre'].' ('.$etq('parentescos', $c['parentesco']).') '.$c['telefono'].($c['principal'] ? ' · principal' : ''))->implode('; ') : 'Ninguno'],
                        ]],
                    ];
                @endphp

                <p class="nt-ayuda">Revisa los datos. Con "Editar" vuelves al paso correspondiente sin perder nada.</p>
                @foreach ($bloques as $n => [$tit, $filas])
                    <section class="nt-revision" aria-labelledby="nt-rev-{{ $n }}" wire:key="rev-{{ $n }}">
                        <div class="nt-revision-cab">
                            <h3 id="nt-rev-{{ $n }}">{{ $tit }}</h3>
                            <button type="button" class="nt-btn nt-btn--suave" wire:click="irAPaso({{ $n }})"><span class="icono">edit</span> Editar<span class="nt-sr"> {{ $tit }}</span></button>
                        </div>
                        <dl class="nt-dl">
                            @foreach ($filas as [$k, $val]) <div><dt>{{ $k }}</dt><dd>{{ $val }}</dd></div> @endforeach
                        </dl>
                    </section>
                @endforeach

                @if ($comp)
                    <div class="nt-completitud" role="status">
                        <strong>Expediente completo al {{ $comp['porcentaje'] }} %</strong>
                        <progress max="100" value="{{ $comp['porcentaje'] }}" aria-label="Completitud del expediente"></progress>
                        @if (! empty($comp['faltantes']))
                            <p class="nt-ayuda">Puedes guardar ahora y completar después. Falta: {{ implode(', ', $comp['faltantes']) }}.</p>
                        @endif
                    </div>
                @endif

                {{-- PENDIENTE: para tomar mediciones precisas hacen falta dispositivos de medición reales (reloj, tensiómetro,
                     glucómetro, etc.). Por ahora las mediciones se simulan (SimuladorMediciones) o se capturan a mano.
                     Este bloque se activará cuando se integren dispositivos reales. --}}
                <div class="nt-dispositivo" aria-disabled="true">
                    <span class="icono" aria-hidden="true">watch</span>
                    <div class="nt-dispositivo-t">
                        <strong>Vincular dispositivo <span class="nt-proximamente">PRÓXIMAMENTE</span></strong>
                        <span class="nt-ayuda">Podrás vincular el reloj del tripulante cuando esta función esté disponible.</span>
                    </div>
                </div>
                @if ($errors->any())
                    <ul class="nt-lista-errores">
                        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                    </ul>
                @endif
            @endif
        </section>

        {{-- ===== Acciones ===== --}}
        <div class="nt-barra">
            <button type="button" class="nt-btn nt-btn--suave" wire:click="cancelar">Cancelar</button>
            <span class="nt-barra-sep"></span>
            @if ($paso > 1)
                <button type="button" class="nt-btn" wire:click="anterior"><span class="icono">arrow_back</span> Anterior</button>
            @endif
            @if ($paso < 5)
                <button type="submit" class="nt-btn nt-btn--primario">Siguiente <span class="icono">arrow_forward</span></button>
            @else
                <button type="submit" class="nt-btn nt-btn--primario" wire:loading.attr="disabled" wire:target="guardar"><span class="icono">person_add</span> Guardar tripulante</button>
            @endif
        </div>
        <p class="nt-seguridad">Esta herramienta orienta, no diagnostica ni receta.</p>
    </form>
    @endif
</div>
