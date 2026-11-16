<div class="sm" wire:poll.3s>
    <x-encabezado titulo-a="Simulación" titulo-b="Motor de mediciones en vivo" />

    <main class="sm-main" id="simulacion-contenido">
        {{-- Estado y controles del motor --}}
        <section class="sm-card sm-motor" aria-label="Motor de simulación">
            <div class="sm-fila-cab">
                <h2 class="sm-h2">Motor</h2>
                <span class="sm-chip mono" data-estado="{{ $chip[2] }}" data-chip="motor"><span class="icono" aria-hidden="true">{{ $chip[1] }}</span>{{ $chip[0] }}</span>
                <span class="sm-meta mono">
                    @if ($hace !== null) último tick hace {{ $hace }} s @else sin latido todavía @endif
                </span>
            </div>

            <div class="sm-controles">
                <div class="sm-ctl">
                    <span class="sm-et" id="sm-et-activa">Simulación activa</span>
                    <button type="button" role="switch" class="sm-sw" aria-checked="{{ $motor->activa ? 'true' : 'false' }}" aria-labelledby="sm-et-activa" wire:click="alternarMotor">
                        <span class="sm-sw-pista" aria-hidden="true"><span class="sm-sw-bola"></span></span>
                        <span class="sm-sw-t mono">{{ $motor->activa ? 'ACTIVA' : 'APAGADA' }}</span>
                    </button>
                </div>
                <div class="sm-ctl">
                    <span class="sm-et" id="sm-et-vel">Velocidad</span>
                    <div class="sm-grupo" role="radiogroup" aria-labelledby="sm-et-vel">
                        @foreach ($velocidades as $v)
                            <button type="button" role="radio" class="sm-opt mono" aria-checked="{{ $motor->velocidad === $v ? 'true' : 'false' }}" wire:click="fijarVelocidad({{ $v }})">{{ $v }}x</button>
                        @endforeach
                    </div>
                </div>
                <div class="sm-ctl">
                    <span class="sm-et" id="sm-et-int">Intervalo</span>
                    <div class="sm-grupo" role="radiogroup" aria-labelledby="sm-et-int">
                        @foreach ($intervalos as $s)
                            <button type="button" role="radio" class="sm-opt mono" aria-checked="{{ $motor->intervalo === $s ? 'true' : 'false' }}" wire:click="fijarIntervalo({{ $s }})">{{ $s }} s</button>
                        @endforeach
                    </div>
                </div>
            </div>

            @unless ($corriendo)
                <div class="sm-aviso" role="status" data-aviso="comando" x-data="{ ok: false }">
                    <span class="icono" aria-hidden="true">terminal</span>
                    <div class="sm-aviso-t">
                        <strong>El simulador no está corriendo.</strong> Los interruptores solo guardan la configuración; las mediciones las genera este comando, que debes ejecutar en una terminal:
                        <code class="mono sm-cmd">{{ $comando }}</code>
                    </div>
                    <button type="button" class="sm-btn"
                            x-on:click="navigator.clipboard.writeText(@js($comando)).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">
                        <span class="icono" aria-hidden="true" x-text="ok ? 'check' : 'content_copy'">content_copy</span>
                        <span x-text="ok ? 'Copiado' : 'Copiar'">Copiar</span>
                    </button>
                </div>
            @endunless
        </section>

        {{-- Tripulantes --}}
        <section class="sm-card sm-tripulantes" aria-label="Tripulantes simulados">
            <h2 class="sm-h2">Tripulantes</h2>
            @forelse ($lista as $f)
                @php($t = $f['t'])
                <article class="sm-trip" wire:key="trip-{{ $f['id'] }}" @if ($f['estado']) data-estado="{{ $f['estado'] }}" @endif>
                    <div class="sm-trip-cab">
                        <span class="sm-trip-nombre">{{ $f['nombre'] }}</span>
                        @if ($f['reloj'])
                            <span class="sm-chip mono"><span class="icono" aria-hidden="true">watch</span>RELOJ</span>
                        @endif
                        @if ($f['estado'])
                            <span class="sm-chip mono" data-estado="{{ $f['estado'] }}"><span class="icono" aria-hidden="true">{{ $estados[$f['estado']][1] }}</span>{{ $estados[$f['estado']][0] }}</span>
                        @endif
                        <button type="button" role="switch" class="sm-sw sm-sw--sm" aria-checked="{{ $t->activa ? 'true' : 'false' }}"
                                aria-label="Simular a {{ $f['nombre'] }}" @disabled($f['reloj']) wire:click="alternarTripulante({{ $f['id'] }})">
                            <span class="sm-sw-pista" aria-hidden="true"><span class="sm-sw-bola"></span></span>
                            <span class="sm-sw-t mono">{{ $t->activa ? 'SIMULA' : 'NO SIMULA' }}</span>
                        </button>
                    </div>

                    <div class="sm-trip-datos mono">
                        <span>
                            <span class="sm-dato-et">ÚLTIMA MEDICIÓN</span>
                            @if ($f['ultima']) {{ $f['ultima']['hora'] }} UTC · {{ $f['ultima']['ind'] }} {{ $f['ultima']['valor'] }} @else sin mediciones @endif
                        </span>
                        <span>
                            <span class="sm-dato-et">ESCENARIO</span>
                            @if ($f['escenario'])
                                {{ $f['escenario'] }}@if ($f['restante'] !== null) · quedan {{ $f['restante'] }} s @endif
                            @else ninguno @endif
                        </span>
                    </div>

                    <div class="sm-trip-acc">
                        <label class="sm-campo">
                            <span class="sm-et">Fuente</span>
                            <select class="sm-sel" wire:change="fijarFuente({{ $f['id'] }}, $event.target.value)" aria-label="Fuente de {{ $f['nombre'] }}">
                                <option value="simulado" @selected(! $f['reloj'])>Simulado</option>
                                <option value="reloj" @selected($f['reloj'])>Reloj{{ $f['token'] ? ' (token)' : '' }}</option>
                            </select>
                        </label>
                        <label class="sm-campo sm-campo--g">
                            <span class="sm-et">Escenario</span>
                            <select class="sm-sel" wire:model="escenarioSel.{{ $f['id'] }}" aria-label="Escenario para {{ $f['nombre'] }}">
                                <option value="">Elige un escenario…</option>
                                @foreach ($escenarios as $k => $e)
                                    <option value="{{ $k }}">{{ $e['nombre'] }} · {{ $e['duracion_min'] }} min</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="button" class="sm-btn sm-btn--pri" wire:click="lanzar({{ $f['id'] }})" @disabled($f['reloj'])><span class="icono" aria-hidden="true">play_arrow</span>Lanzar ahora</button>
                        <button type="button" class="sm-btn" wire:click="cancelar({{ $f['id'] }})" @disabled(! $f['escenario'])><span class="icono" aria-hidden="true">stop</span>Cancelar</button>
                    </div>

                    @if (! empty($errores[$f['id']]))
                        <p class="sm-error" role="alert" data-estado="crit"><span class="icono" aria-hidden="true">error</span>{{ $errores[$f['id']] }}</p>
                    @endif
                    @if ($t->ultimo_error)
                        <p class="sm-error" data-estado="crit"><span class="icono" aria-hidden="true">bug_report</span>Último error del motor: {{ $t->ultimo_error }}</p>
                    @endif
                </article>
            @empty
                <div class="sm-vacio">No hay tripulantes en la misión actual. Crea uno desde Tripulación para poder simular.</div>
            @endforelse
        </section>

        {{-- Vista en vivo --}}
        <div class="sm-dos">
            <section class="sm-card sm-vivo" aria-label="Últimas mediciones simuladas">
                <h2 class="sm-h2">Últimas mediciones simuladas</h2>
                @forelse ($recientes as $r)
                    <div class="sm-lin" wire:key="med-{{ $loop->index }}">
                        <span class="mono sm-meta">{{ $r['hora'] }}</span>
                        <span class="sm-lin-t"><strong>{{ $r['quien'] }}</strong> · {{ $r['ind'] }} <span class="mono">{{ $r['valor'] }}</span></span>
                        @if ($r['estado'])
                            <span class="sm-chip mono" data-estado="{{ $r['estado'] }}"><span class="icono" aria-hidden="true">{{ $estados[$r['estado']][1] }}</span>{{ $estados[$r['estado']][0] }}</span>
                        @endif
                    </div>
                @empty
                    <div class="sm-vacio">Aún no hay mediciones simuladas.</div>
                @endforelse
            </section>

            <section class="sm-card sm-vivo" aria-label="Últimas alertas a tierra">
                <h2 class="sm-h2">Últimas alertas a tierra</h2>
                @forelse ($alertas as $x)
                    <div class="sm-lin" wire:key="ale-{{ $loop->index }}">
                        <span class="mono sm-meta">{{ $x['hora'] }}</span>
                        <span class="sm-lin-t"><strong>{{ $x['quien'] }}</strong> · {{ $x['ind'] }}</span>
                        @if ($x['estado'])
                            <span class="sm-chip mono" data-estado="{{ $x['estado'] }}"><span class="icono" aria-hidden="true">{{ $estados[$x['estado']][1] }}</span>{{ $estados[$x['estado']][0] }}</span>
                        @endif
                    </div>
                @empty
                    <div class="sm-vacio">Sin alertas a tierra.</div>
                @endforelse
            </section>
        </div>

        <p class="sm-nota" role="note"><span class="icono" aria-hidden="true">info</span>Herramienta de desarrollo: solo disponible fuera de producción. Orienta, no diagnostica.</p>
    </main>
</div>
