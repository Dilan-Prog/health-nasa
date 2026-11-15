@php
    $estados = \App\Livewire\Rutinas::ESTADOS;
    $kinds = config('rutinas.kinds');
    $nombreTripulante = $astronauta?->nombre;
    $dow = config('rutinas.dias');
    $tiposRutina = array_keys(config('rutinas.tipos'));
    $unidadA = config('rutinas.tipos.'.$aTipo.'.unidad', 'min');
    $unidadR = config('rutinas.tipos.'.$rTipo.'.unidad', 'min');
    $conIntensidad = ! in_array($aTipo, ['Hidratación', 'Sueño'], true);
@endphp
<div class="rut">
    <x-encabezado titulo-a="Rutinas" :titulo-b="$nombreTripulante ? 'Tripulante '.$nombreTripulante : 'Sin tripulante'" />

    <main class="rut-main" id="rutinas-contenido">
        @if (! $astronauta)
            <div class="rut-vacio rut-vacio--pagina" role="status">
                <span class="icono" aria-hidden="true">groups</span>
                <strong>No hay un tripulante seleccionado</strong>
                <span>Elige un tripulante en el selector para ver y registrar sus rutinas.</span>
            </div>
        @else
            <div class="rut-cabecera">
                <div class="rut-cab-texto">
                    <span class="rut-fecha mono">{{ $d['etiqueta_fecha'] ?? '' }}</span>
                    <span class="rut-sub">Actividades de salud de <strong>{{ $nombreTripulante }}</strong></span>
                </div>
                <button type="button" class="rut-btn rut-btn--primario" wire:click="abrirActividad">
                    <span class="icono" aria-hidden="true">add_circle</span>Registrar actividad
                </button>
            </div>

            <div class="rut-esqueleto" wire:loading.flex wire:target="reintentar" role="status" aria-live="polite">
                <span class="mono rut-esq-et">CARGANDO RUTINAS…</span>
                <div class="rut-esq-rejilla">@foreach ([1, 2, 3, 4] as $k)<div class="rut-esq rut-esq--alta"></div>@endforeach</div>
                <div class="rut-esq rut-esq--cal"></div>
                @foreach ([1, 2, 3, 4] as $k)<div class="rut-esq rut-esq--fila"></div>@endforeach
            </div>

            @if ($error || ! $d)
                <div class="rut-error" role="alert">
                    <span class="icono" aria-hidden="true">error</span>
                    <div class="rut-error-t">
                        <strong>No se pudieron cargar tus rutinas</strong>
                        <span>Ha fallado la lectura de tu plan. Tus registros no se han perdido.</span>
                    </div>
                    <button type="button" class="rut-btn" wire:click="reintentar"><span class="icono" aria-hidden="true">refresh</span>Reintentar</button>
                </div>
            @else
                <div class="rut-contenido" wire:loading.class="is-ocupado" wire:target="seleccionarDia,marcar,omitir,deshacer" aria-busy="false">
                    <div class="rut-tarjetas" role="group" aria-label="Progreso de hoy">
                        @foreach ($d['tarjetas'] as $c)
                            <div class="rut-tarjeta rut-k--{{ $c['clase'] }}">
                                <div class="rut-anillo" role="progressbar" aria-label="{{ $c['aria'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $c['pct'] }}" style="--deg: {{ $c['deg'] }}">
                                    <div class="rut-anillo-in"><span class="icono" aria-hidden="true">{{ $c['ic'] }}</span></div>
                                </div>
                                <div class="rut-tarjeta-t">
                                    <span class="rut-tarjeta-et mono">{{ $c['label'] }}</span>
                                    <span class="rut-tarjeta-v"><span class="mono rut-tarjeta-num">{{ $c['value'] }}</span><span class="rut-tarjeta-meta">{{ $c['goal'] }}</span></span>
                                    <span class="rut-tarjeta-nota">{{ $c['note'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="rut-cuerpo">
                        <div class="rut-col rut-col--principal">
                            <section class="rut-panel" aria-labelledby="rut-sem-t">
                                <div class="rut-panel-cab">
                                    <h2 id="rut-sem-t">Semana · {{ $d['semana_txt'] }}</h2>
                                    <div class="rut-leyenda" aria-label="Leyenda">
                                        @foreach ($kinds as $k => $l)
                                            <span class="rut-k--{{ $k }}"><span class="rut-marca" aria-hidden="true"></span>{{ $l }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="rut-semana" role="tablist" aria-label="Día de la semana">
                                    @foreach ($d['dias'] as $dd)
                                        <button type="button" role="tab" class="rut-dia {{ $dd['sel'] ? 'is-sel' : '' }} {{ $dd['es_hoy'] ? 'is-hoy' : '' }}"
                                                aria-selected="{{ $dd['sel'] ? 'true' : 'false' }}" @if ($dd['es_hoy']) aria-current="date" @endif
                                                aria-label="{{ $dd['dia'] }} {{ $dd['n'] }} de {{ $dd['mes'] }}, {{ $dd['hechas'] }} de {{ $dd['total'] }} hechas"
                                                wire:key="dia-{{ $dd['fecha'] }}" wire:click="seleccionarDia('{{ $dd['fecha'] }}')">
                                            <span class="mono rut-dia-ab">{{ $dd['abbr'] }}</span>
                                            <span class="rut-dia-n">{{ $dd['n'] }}</span>
                                            <span class="rut-dia-puntos" aria-hidden="true">
                                                @foreach ($dd['kinds'] as $k)<span class="rut-punto rut-k--{{ $k }}"></span>@endforeach
                                            </span>
                                            <span class="mono rut-dia-frac">{{ $dd['frac'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="rut-semana-prog">
                                    <div class="rut-barra" role="progressbar" aria-label="Cumplimiento de la semana" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $d['semana_pct'] }}">
                                        <span style="width: {{ $d['semana_pct'] }}%"></span>
                                    </div>
                                    <span class="mono rut-semana-txt">{{ $d['semana_hechas'] }} de {{ $d['semana_total'] }} hechas · {{ $d['semana_pct'] }}%</span>
                                </div>
                            </section>

                            <section class="rut-panel" aria-labelledby="rut-ag-t">
                                <div class="rut-panel-cab">
                                    <h2 id="rut-ag-t">{{ $d['agenda_titulo'] }}</h2>
                                    <span class="mono rut-cuenta" aria-live="polite">{{ $d['agenda_cuenta'] }}</span>
                                </div>
                                @if (count($d['tareas']) === 0)
                                    <div class="rut-vacio">
                                        <span class="icono" aria-hidden="true">event_available</span>
                                        <strong>{{ $d['sin_rutinas'] ? 'Aún no tienes rutinas' : ($d['sel_es_hoy'] ? 'Aún no hay actividades hoy' : 'No hay actividades este día') }}</strong>
                                        <span>{{ $d['sin_rutinas'] ? 'Añade tu primera rutina para recibir recordatorios.' : 'Registra tu primera actividad o espera los recordatorios de tu plan.' }}</span>
                                        <button type="button" class="rut-btn rut-btn--sec" wire:click="{{ $d['sin_rutinas'] ? 'abrirRutina' : 'abrirActividad' }}">{{ $d['sin_rutinas'] ? 'Añadir rutina' : 'Registrar actividad' }}</button>
                                    </div>
                                @else
                                    <ul class="rut-lista">
                                        @foreach ($d['tareas'] as $t)
                                            @php
                                                [$etq, $icoEst, $sk] = $estados[$t['estado']];
                                                $hecha = $t['estado'] === 'done';
                                                $omitida = $t['estado'] === 'skipped';
                                                $cant = $t['cantidad'] > 0 ? $t['cantidad'].' '.$t['unidad'] : null;
                                            @endphp
                                            <li class="rut-tarea rut-k--{{ $t['kind'] }} est-{{ $t['estado'] }}" @if ($sk) data-estado="{{ $sk }}" @endif wire:key="tarea-{{ $d['sel_fecha'] }}-{{ $t['id'] }}">
                                                <span class="mono rut-tarea-hora">{{ $t['hora'] }}</span>
                                                <span class="icono rut-tarea-ic" aria-hidden="true">{{ $t['icono'] }}</span>
                                                <span class="rut-tarea-t">
                                                    <span class="rut-tarea-nombre">{{ $t['nombre'] }}</span>
                                                    <span class="mono rut-tarea-estado"><span class="icono" aria-hidden="true">{{ $icoEst }}</span>{{ $etq }}@if ($cant) <span class="rut-tarea-cant">· {{ $cant }}</span>@endif</span>
                                                </span>
                                                <span class="rut-tarea-acc">
                                                    @if ($hecha || $omitida)
                                                        <button type="button" class="rut-btn rut-btn--sec" wire:click="deshacer({{ $t['id'] }})" aria-label="{{ $hecha ? 'Deshacer' : 'Retomar' }} {{ $t['nombre'] }}">
                                                            <span class="icono" aria-hidden="true">{{ $hecha ? 'undo' : 'replay' }}</span>{{ $hecha ? 'Deshacer' : 'Retomar' }}
                                                        </button>
                                                    @else
                                                        <button type="button" class="rut-btn rut-btn--primario" wire:click="marcar({{ $t['id'] }})" aria-label="Marcar hecha {{ $t['nombre'] }}" @disabled($d['sel_futuro']) @if ($d['sel_futuro']) title="Aún no es este día" @endif>
                                                            <span class="icono" aria-hidden="true">check</span><span class="rut-largo">Marcar hecha</span><span class="rut-corto">Hecha</span>
                                                        </button>
                                                        <button type="button" class="rut-btn rut-btn--sec rut-btn--icono" wire:click="omitir({{ $t['id'] }})" aria-label="Omitir {{ $t['nombre'] }}" title="Omitir" @disabled($d['sel_futuro'])>
                                                            <span class="icono" aria-hidden="true">do_not_disturb_on</span>
                                                        </button>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>

                            <section class="rut-panel" aria-labelledby="rut-plan-t">
                                <div class="rut-panel-cab">
                                    <h2 id="rut-plan-t">Mi plan de rutinas</h2>
                                    <button type="button" class="rut-btn rut-btn--sec" wire:click="abrirRutina"><span class="icono" aria-hidden="true">add</span>Añadir rutina</button>
                                </div>
                                @if ($d['plan']->isEmpty())
                                    <p class="rut-nota-vacia">Todavía no hay rutinas en tu plan.</p>
                                @else
                                    <ul class="rut-plan">
                                        @foreach ($d['plan'] as $r)
                                            <li class="rut-plan-fila rut-k--{{ $r->kind }}" wire:key="plan-{{ $r->id }}">
                                                <span class="mono rut-tarea-hora">{{ $r->hora }}</span>
                                                <span class="icono rut-tarea-ic" aria-hidden="true">{{ $r->icono }}</span>
                                                <span class="rut-tarea-t">
                                                    <span class="rut-tarea-nombre">{{ $r->nombre }}</span>
                                                    <span class="rut-plan-dias">
                                                        @if (count($r->dias) === 7) Todos los días
                                                        @else {{ collect($r->dias)->sort()->map(fn ($i) => ucfirst(mb_strtolower($dow[(int) $i] ?? '')))->implode(' · ') }}
                                                        @endif
                                                    </span>
                                                </span>
                                                <span class="rut-tarea-acc">
                                                    <button type="button" class="rut-btn rut-btn--sec rut-btn--icono" wire:click="abrirRutina({{ $r->id }})" aria-label="Editar {{ $r->nombre }}" title="Editar"><span class="icono" aria-hidden="true">edit</span></button>
                                                    <button type="button" class="rut-btn rut-btn--sec rut-btn--icono" wire:click="eliminarRutina({{ $r->id }})" wire:confirm="¿Eliminar la rutina «{{ $r->nombre }}»? Se perderá su historial de cumplimiento." aria-label="Eliminar {{ $r->nombre }}" title="Eliminar"><span class="icono" aria-hidden="true">delete</span></button>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        </div>

                        <div class="rut-col rut-col--lateral">
                            <section class="rut-panel" aria-labelledby="rut-why-t">
                                <h2 id="rut-why-t" class="rut-h-ic"><span class="icono" aria-hidden="true">lightbulb</span>Por qué importa</h2>
                                @forelse ($d['why'] as $w)
                                    <div class="rut-why" data-estado="{{ $w['estado'] }}" wire:key="why-{{ $w['clave'] }}">
                                        <div class="rut-why-cab">
                                            <span class="icono rut-why-ic" aria-hidden="true">{{ $w['ic'] }}</span>
                                            <span class="rut-why-nombre">{{ $w['indicador'] }} <span class="mono rut-why-valor">{{ $w['valor'] }} {{ $w['unidad'] }}</span></span>
                                            <span class="mono rut-pildora"><span class="icono" aria-hidden="true">{{ $w['estado_txt'][1] }}</span>{{ $w['estado_txt'][0] }}</span>
                                        </div>
                                        @if ($w['razon'])<span class="rut-why-razon">{{ $w['razon'] }}</span>@endif
                                        <span class="rut-why-accion"><span class="icono" aria-hidden="true">arrow_forward</span>{{ $w['accion'] }}</span>
                                        <span class="rut-why-porque">{{ $w['porque'] }}</span>
                                        @php
                                            $tt = $w['tarea'];
                                            $te = $tt ? $estados[$tt['estado']] : null;
                                        @endphp
                                        <span class="mono rut-why-tarea" @if ($te && $te[2]) data-estado="{{ $te[2] }}" @endif>
                                            <span class="icono" aria-hidden="true">{{ $te ? $te[1] : 'event_busy' }}</span>{{ $tt ? $tt['nombre'].' · '.$te[0] : 'Sin tarea programada hoy' }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="rut-nota-vacia">Aún no hay mediciones para relacionar con tus rutinas. Registra una medición para ver aquí por qué importan.</p>
                                @endforelse
                            </section>

                            <section class="rut-panel" aria-labelledby="rut-his-t">
                                <h2 id="rut-his-t">Historial reciente</h2>
                                @forelse ($d['historial'] as $h)
                                    <div class="rut-hist rut-k--{{ $h['kind'] }}">
                                        <span class="icono rut-hist-ic" aria-hidden="true">{{ $h['ic'] }}</span>
                                        <span class="rut-hist-t"><span class="rut-hist-nombre">{{ $h['nombre'] }}</span><span class="mono rut-hist-cuando">{{ $h['cuando'] }}</span></span>
                                        <span class="rut-hist-d"><span class="mono rut-hist-cant">{{ $h['cantidad'] }}</span><span class="rut-hist-origen"><span class="icono" aria-hidden="true">{{ $h['origen_ic'] }}</span>{{ $h['origen'] }}</span></span>
                                    </div>
                                @empty
                                    <p class="rut-nota-vacia">Todavía no hay actividades registradas.</p>
                                @endforelse
                            </section>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <p class="rut-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>
    </main>

    @if ($modal === 'actividad')
        <div class="rut-fondo"
             x-data="{
                 previo: null,
                 init() { this.previo = document.activeElement; this.$nextTick(() => { const c = this.$el.querySelector('select, input'); c && c.focus(); }); },
                 destroy() { this.previo && this.previo.focus && this.previo.focus(); },
                 trampa(e) {
                     const f = [...this.$refs.dialogo.querySelectorAll('button, input, select, textarea')].filter(el => !el.disabled && el.offsetParent !== null);
                     if (!f.length) return;
                     const a = f[0], z = f[f.length - 1];
                     if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
                     else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
                 },
             }"
             @keydown.escape.window="$wire.cerrar()" @keydown.tab="trampa($event)" @click.self="$wire.cerrar()">
            <form class="rut-dialogo" x-ref="dialogo" role="dialog" aria-modal="true" aria-labelledby="ra-title" wire:submit="guardarActividad" novalidate>
                <div class="rut-dialogo-cab">
                    <h2 id="ra-title">Registrar actividad</h2>
                    <button type="button" class="rut-cerrar" aria-label="Cerrar" wire:click="cerrar"><span class="icono" aria-hidden="true">close</span></button>
                </div>
                <label class="rut-campo" for="ra-tipo">Tipo de actividad
                    <select id="ra-tipo" class="rut-entrada" wire:model.live="aTipo">
                        @foreach (config('rutinas.tipos_actividad') as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                    </select>
                </label>
                <div class="rut-fila-campos">
                    <div class="rut-campo rut-campo--flex">
                        <label for="ra-dur">{{ $unidadA === 'ml' ? 'Cantidad' : 'Duración' }}</label>
                        <span class="rut-entrada rut-entrada--unidad @error('aCantidad') es-invalido @enderror">
                            <input id="ra-dur" class="mono" inputmode="numeric" autocomplete="off" wire:model="aCantidad" @error('aCantidad') aria-invalid="true" @enderror aria-describedby="ra-dur-msg">
                            <span class="rut-unidad">{{ $unidadA }}</span>
                        </span>
                        <span id="ra-dur-msg" class="rut-msg" @error('aCantidad') role="alert" @enderror>@error('aCantidad')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                    </div>
                    <div class="rut-campo rut-campo--flex">
                        <label for="ra-hora">Hora (UTC)</label>
                        <input id="ra-hora" type="time" class="rut-entrada mono @error('aHora') es-invalido @enderror" wire:model="aHora" @error('aHora') aria-invalid="true" @enderror aria-describedby="ra-hora-msg">
                        <span id="ra-hora-msg" class="rut-msg" @error('aHora') role="alert" @enderror>@error('aHora')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                    </div>
                </div>
                @if ($conIntensidad)
                    <div class="rut-campo" role="radiogroup" aria-label="Intensidad">Intensidad
                        <div class="rut-segmentos">
                            @foreach (config('rutinas.intensidades') as $i)
                                <button type="button" role="radio" aria-checked="{{ $aInt === $i ? 'true' : 'false' }}" class="rut-seg {{ $aInt === $i ? 'is-on' : '' }}" wire:click="$set('aInt', '{{ $i }}')">{{ $i }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                <label class="rut-campo" for="ra-notas">Notas <span class="rut-opc">(opcional)</span>
                    <textarea id="ra-notas" class="rut-entrada rut-entrada--area" rows="3" wire:model="aNotas" maxlength="200"></textarea>
                </label>
                <div class="rut-dialogo-pie">
                    <button type="button" class="rut-btn rut-btn--sec" wire:click="cerrar">Cancelar</button>
                    <button type="submit" class="rut-btn rut-btn--primario"><span class="icono" aria-hidden="true">save</span>Guardar</button>
                </div>
            </form>
        </div>
    @endif

    @if ($modal === 'rutina')
        <div class="rut-fondo"
             x-data="{
                 previo: null,
                 init() { this.previo = document.activeElement; this.$nextTick(() => { const c = this.$el.querySelector('input'); c && c.focus(); }); },
                 destroy() { this.previo && this.previo.focus && this.previo.focus(); },
                 trampa(e) {
                     const f = [...this.$refs.dialogo.querySelectorAll('button, input, select, textarea')].filter(el => !el.disabled && el.offsetParent !== null);
                     if (!f.length) return;
                     const a = f[0], z = f[f.length - 1];
                     if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
                     else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
                 },
             }"
             @keydown.escape.window="$wire.cerrar()" @keydown.tab="trampa($event)" @click.self="$wire.cerrar()">
            <form class="rut-dialogo" x-ref="dialogo" role="dialog" aria-modal="true" aria-labelledby="rr-title" wire:submit="guardarRutina" novalidate>
                <div class="rut-dialogo-cab">
                    <h2 id="rr-title">{{ $rId ? 'Editar rutina' : 'Añadir rutina' }}</h2>
                    <button type="button" class="rut-cerrar" aria-label="Cerrar" wire:click="cerrar"><span class="icono" aria-hidden="true">close</span></button>
                </div>
                <div class="rut-campo">
                    <label for="rr-nombre">Nombre</label>
                    <input id="rr-nombre" class="rut-entrada @error('rNombre') es-invalido @enderror" wire:model="rNombre" maxlength="120" autocomplete="off" placeholder="Ej.: Estiramientos 10 min" @error('rNombre') aria-invalid="true" @enderror aria-describedby="rr-nombre-msg">
                    <span id="rr-nombre-msg" class="rut-msg" @error('rNombre') role="alert" @enderror>@error('rNombre')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                </div>
                <div class="rut-fila-campos">
                    <label class="rut-campo rut-campo--flex" for="rr-tipo">Tipo
                        <select id="rr-tipo" class="rut-entrada" wire:model.live="rTipo">
                            @foreach ($tiposRutina as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                        </select>
                    </label>
                    <div class="rut-campo rut-campo--flex">
                        <label for="rr-hora">Hora (UTC)</label>
                        <input id="rr-hora" type="time" class="rut-entrada mono @error('rHora') es-invalido @enderror" wire:model="rHora" @error('rHora') aria-invalid="true" @enderror aria-describedby="rr-hora-msg">
                        <span id="rr-hora-msg" class="rut-msg" @error('rHora') role="alert" @enderror>@error('rHora')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                    </div>
                </div>
                <div class="rut-campo" role="radiogroup" aria-label="Icono">Icono
                    <div class="rut-iconos">
                        @foreach (config('rutinas.iconos') as $ic)
                            <button type="button" role="radio" aria-checked="{{ $rIcono === $ic ? 'true' : 'false' }}" aria-label="{{ $ic }}" class="rut-icono-op {{ $rIcono === $ic ? 'is-on' : '' }}" wire:click="$set('rIcono', '{{ $ic }}')"><span class="icono" aria-hidden="true">{{ $ic }}</span></button>
                        @endforeach
                    </div>
                </div>
                <div class="rut-campo">
                    <label for="rr-cant">{{ $unidadR === 'ml' ? 'Cantidad' : 'Duración' }} <span class="rut-opc">(opcional en mediciones y bienestar)</span></label>
                    <span class="rut-entrada rut-entrada--unidad @error('rCantidad') es-invalido @enderror">
                        <input id="rr-cant" class="mono" inputmode="numeric" autocomplete="off" wire:model="rCantidad" @error('rCantidad') aria-invalid="true" @enderror aria-describedby="rr-cant-msg">
                        <span class="rut-unidad">{{ $unidadR }}</span>
                    </span>
                    <span id="rr-cant-msg" class="rut-msg" @error('rCantidad') role="alert" @enderror>@error('rCantidad')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                </div>
                <fieldset class="rut-campo rut-dias">
                    <legend>Días</legend>
                    <div class="rut-dias-fila">
                        @foreach ($dow as $n => $ab)
                            <label class="rut-dia-chk"><input type="checkbox" value="{{ $n }}" wire:model="rDias" @error('rDias') aria-invalid="true" @enderror><span>{{ $ab }}</span></label>
                        @endforeach
                    </div>
                    <span class="rut-msg" @error('rDias') role="alert" @enderror>@error('rDias')<span class="icono" aria-hidden="true">error</span>{{ $message }}@enderror</span>
                </fieldset>
                <label class="rut-campo" for="rr-ind">Indicador que la motiva <span class="rut-opc">(opcional)</span>
                    <select id="rr-ind" class="rut-entrada" wire:model="rIndicador">
                        <option value="">Ninguno</option>
                        @foreach (config('rutinas.why') as $clave => $w)<option value="{{ $clave }}">{{ $w['etiqueta'] }}</option>@endforeach
                    </select>
                </label>
                <div class="rut-dialogo-pie">
                    <button type="button" class="rut-btn rut-btn--sec" wire:click="cerrar">Cancelar</button>
                    <button type="submit" class="rut-btn rut-btn--primario"><span class="icono" aria-hidden="true">save</span>Guardar</button>
                </div>
            </form>
        </div>
    @endif
</div>
