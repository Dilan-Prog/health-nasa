            {{-- ================= PASO 5: REVISIÓN ================= --}}
            @if ($paso === 5)
                @php
                    $rev = $this->revision;
                    $d = $rev['datos'];
                    $comp = $rev['completitud'];
                    // $v: null when empty (the row renders its own empty text); $vt: '—' when empty, for composed strings.
                    $v = fn ($x, $suf = '') => ($x === null || $x === '') ? null : $x.$suf;
                    $vt = fn ($x, $suf = '') => $v($x, $suf) ?? '—';
                    $etq = fn (string $cat, $clave) => $clave === null ? null : ($cfg($cat, [])[$clave] ?? $clave);
                    // Paso al que corresponde cada criterio de completitud (AltaTripulante::completitud).
                    $pasoDe = [
                        'Fecha de nacimiento' => 1, 'Nacionalidad y agencia' => 1,
                        'Altura y peso' => 2, 'Grupo sanguíneo' => 2, 'Línea base personal' => 2,
                        'Signos basales (frecuencia cardíaca y presión)' => 2, 'Hábitos (sueño y actividad física)' => 2,
                        'Aptitud de vuelo evaluada' => 3, 'Fecha del último examen médico' => 3,
                        'Contacto de emergencia principal' => 4,
                        // English labels (must match App\Services\Tripulacion\AltaTripulante::completitud 'faltantes').
                        'Date of birth' => 1, 'Nationality and agency' => 1,
                        'Height and weight' => 2, 'Blood type' => 2, 'Personal baseline' => 2,
                        'Baseline vitals (heart rate and blood pressure)' => 2, 'Habits (sleep and physical activity)' => 2,
                        'Flight fitness assessed' => 3, 'Date of last medical exam' => 3,
                        'Primary emergency contact' => 4,
                    ];
                    $pct = (int) ($comp['porcentaje'] ?? 0);
                    $tono = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'crit');
                    $tonoIc = ['ok' => 'check_circle', 'warn' => 'schedule', 'crit' => 'error'][$tono];
                    $tonoTxt = ['ok' => 'Almost complete', 'warn' => 'In progress', 'crit' => 'Important data missing'][$tono];
                    $conError = [];
                    foreach ([1, 2, 3, 4] as $n) {
                        $conError[$n] = collect(\App\Services\Tripulacion\ReglasExpediente::camposPaso($n))
                            ->contains(fn ($c) => $errors->has($c) || $errors->has($c.'.*'));
                    }
                    $bloques = [
                        1 => ['Identity', [
                            ['Name', $v($d['nombre'])], ['Date of birth', $vt($d['fecha_nacimiento']).($this->edad !== null ? ' ('.$this->edad.' yr)' : '')],
                            ['Sex', $etq('sexos', $d['sexo'])], ['Nationality', $v($d['nacionalidad'])], ['Agency', $v($d['agencia'])],
                            ['Role', $v($d['rol'])], ['Entry date', $v($d['fecha_ingreso'])], ['Service status', $etq('estados_servicio', $d['estado_servicio'])],
                        ]],
                        2 => ['Biometrics and habits', [
                            ['Height', $v($d['altura_m'], ' m')], ['Weight', $v($d['peso_kg'], ' kg')], ['BMI', $this->imc !== null ? number_format($this->imc, 1, '.', '') : '—'],
                            ['Blood type', $v($d['grupo_sanguineo'])], ['EVAs', $v($d['evas'])],
                            ['Resting / max HR', $vt($d['fc_reposo']).' / '.$vt($d['fc_maxima']).' bpm'],
                            ['Baseline BP', $vt($d['presion_basal_sis']).' / '.$vt($d['presion_basal_dia']).' mmHg'],
                            ['SpO₂ / temperature', $vt($d['spo2_basal'], ' %').' / '.$vt($d['temperatura_basal'], ' °C')],
                            ['Baseline glucose', $v($d['glucosa_basal'], ' mg/dL')], ['VO₂max', $v($d['vo2max'])],
                            ['Body fat', $v($d['grasa_corporal_pct'], ' %')], ['Bone T-score', $v($d['densidad_osea_tscore'])],
                            ['Usual sleep', $v($d['horas_sueno_habituales'], ' h')], ['Weekly activity', $v($d['actividad_fisica_semana_min'], ' min')],
                            ['Smoker', $d['fumador'] ? 'Yes' : 'No'], ['Alcohol', $etq('alcohol', $d['alcohol'])],
                            ['Prior radiation', $v($d['dosis_radiacion_previa_msv'], ' mSv')],
                            ['Baseline', count($d['linea_base']).' of '.count($marcadores).' markers'],
                        ]],
                        3 => ['Clinical history', [
                            ['Flight fitness', $etq('aptitudes', $d['aptitud_vuelo'])], ['Last exam', $v($d['ultimo_examen_en'])],
                            ['Restrictions', $v($d['restricciones'])],
                            ['Allergies', count($d['alergias']) ? collect($d['alergias'])->map(fn ($a) => $a['sustancia'].' ('.$etq('gravedades', $a['gravedad']).')')->implode(', ') : null, 'None'],
                            ['Conditions', count($d['condiciones']) ? collect($d['condiciones'])->map(fn ($a) => $a['nombre'].' ('.$etq('estados_condicion', $a['estado']).')')->implode(', ') : null, 'None'],
                            ['Medications (record)', count($d['medicamentos']) ? collect($d['medicamentos'])->map(fn ($a) => $a['nombre'].($a['dosis_texto'] ? ' '.$a['dosis_texto'] : ''))->implode(', ') : null, 'None'],
                            ['Family history', count($d['antecedentes']) ? collect($d['antecedentes'])->map(fn ($a) => $etq('parentescos', $a['parentesco']).': '.$a['condicion'])->implode(', ') : null, 'None'],
                            ['Notes', $v($d['notas'])],
                        ]],
                        4 => ['Emergency contacts', [
                            ['Contacts', count($d['contactos']) ? collect($d['contactos'])->map(fn ($c) => $c['nombre'].' ('.$etq('parentescos', $c['parentesco']).') '.$c['telefono'].($c['principal'] ? ' · primary' : ''))->implode('; ') : null, 'None'],
                        ]],
                    ];
                @endphp

                <p class="nt-ayuda">Review the data. "Edit" takes you back to the corresponding step without losing anything.</p>

                @if ($comp)
                    <section class="nt-rev-comp" data-estado="{{ $tono }}" aria-labelledby="nt-rev-comp-t">
                        <div class="nt-rev-comp-cab">
                            <h3 id="nt-rev-comp-t" class="nt-rev-eyebrow">RECORD COMPLETE</h3>
                            <span class="nt-rev-pct" role="status" aria-live="polite"><span class="nt-sr">Record complete at </span>{{ $pct }} %</span>
                        </div>
                        <div class="nt-rev-barra" role="progressbar" aria-labelledby="nt-rev-comp-t" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                        <p class="nt-rev-estado"><span class="icono" aria-hidden="true">{{ $tonoIc }}</span> {{ $tonoTxt }}</p>
                        @if (! empty($comp['faltantes']))
                            <p class="nt-rev-texto">You can save now and complete it later. {{ count($comp['faltantes']) }} items missing.</p>
                            <ul class="nt-rev-chips">
                                @foreach ($comp['faltantes'] as $f)
                                    <li>
                                        @if (isset($pasoDe[$f]))
                                            <button type="button" class="nt-rev-chip" wire:click="irAPaso({{ $pasoDe[$f] }})">{{ $f }} <span class="nt-rev-chip-paso">STEP {{ $pasoDe[$f] }}</span></button>
                                        @else
                                            <span class="nt-rev-chip">{{ $f }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="nt-rev-texto">The record has all key data.</p>
                        @endif
                    </section>

                    @if (! empty($comp['faltantes']))
                        <section class="nt-rev-pend" aria-labelledby="nt-rev-pend-t">
                            <h3 id="nt-rev-pend-t"><span class="icono" aria-hidden="true">info</span> Pending data (does not block saving)</h3>
                            <ul>
                                @foreach ($comp['faltantes'] as $f)
                                    <li>
                                        <span class="nt-rev-pend-t"><strong>{{ $f }}</strong>: not recorded.</span>
                                        @if (isset($pasoDe[$f]))
                                            <button type="button" class="nt-rev-ir" wire:click="irAPaso({{ $pasoDe[$f] }})">Go to step {{ $pasoDe[$f] }} <span class="icono" aria-hidden="true">arrow_forward</span></button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endif

                @if ($errors->any())
                    <section class="nt-rev-errores" role="alert" aria-labelledby="nt-rev-err-t">
                        <h3 id="nt-rev-err-t"><span class="icono" aria-hidden="true">error</span> Could not save: review this data</h3>
                        <ul class="nt-lista-errores">
                            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </section>
                @endif

                <div class="nt-rev-grid">
                    @foreach ($bloques as $n => [$tit, $filas])
                        <section class="nt-rev-sec {{ $conError[$n] ? 'nt-rev-sec--error' : '' }}" aria-labelledby="nt-rev-{{ $n }}" wire:key="rev-{{ $n }}">
                            <div class="nt-rev-sec-cab">
                                <h3 id="nt-rev-{{ $n }}">{{ $tit }}</h3>
                                @if ($conError[$n])
                                    <span class="nt-rev-err-tag"><span class="icono" aria-hidden="true">error</span> HAS ERRORS</span>
                                @endif
                                <button type="button" class="nt-rev-editar" aria-label="Edit {{ $tit }}" wire:click="irAPaso({{ $n }})"><span class="icono" aria-hidden="true">edit</span> Edit</button>
                            </div>
                            <dl class="nt-rev-dl">
                                @foreach ($filas as $fila)
                                    @php [$k, $val] = $fila; $sinValor = $val === null; $textoVacio = $fila[2] ?? '—'; @endphp
                                    <div class="{{ ($n >= 3 || mb_strlen((string) $val) > 28) ? 'nt-rev-ancho' : '' }}">
                                        <dt>{{ $k }}</dt>
                                        <dd class="{{ $sinValor ? 'nt-rev-vacio' : '' }}">{{ $sinValor ? $textoVacio : $val }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endforeach
                </div>

                {{-- PENDING: accurate measurements require real measurement devices (watch, blood pressure monitor,
                     glucometer, etc.). For now measurements are simulated (SimuladorMediciones) or entered by hand.
                     This block will be enabled when real devices are integrated. --}}
                <div class="nt-dispositivo nt-rev-disp" aria-disabled="true">
                    <span class="icono" aria-hidden="true">watch</span>
                    <div class="nt-dispositivo-t">
                        <strong>Link device <span class="nt-proximamente">COMING SOON</span></strong>
                        <span class="nt-ayuda">You will be able to link the crew member's watch when this feature is available.</span>
                    </div>
                </div>
            @endif
