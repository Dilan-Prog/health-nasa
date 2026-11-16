            {{-- ================= PASO 5: REVISIÓN ================= --}}
            @if ($paso === 5)
                @php
                    $rev = $this->revision;
                    $d = $rev['datos'];
                    $comp = $rev['completitud'];
                    $v = fn ($x, $suf = '') => ($x === null || $x === '') ? '—' : $x.$suf;
                    $etq = fn (string $cat, $clave) => $clave === null ? '—' : ($cfg($cat, [])[$clave] ?? $clave);
                    // Paso al que corresponde cada criterio de completitud (AltaTripulante::completitud).
                    $pasoDe = [
                        'Fecha de nacimiento' => 1, 'Nacionalidad y agencia' => 1,
                        'Altura y peso' => 2, 'Grupo sanguíneo' => 2, 'Línea base personal' => 2,
                        'Signos basales (frecuencia cardíaca y presión)' => 2, 'Hábitos (sueño y actividad física)' => 2,
                        'Aptitud de vuelo evaluada' => 3, 'Fecha del último examen médico' => 3,
                        'Contacto de emergencia principal' => 4,
                    ];
                    $pct = (int) ($comp['porcentaje'] ?? 0);
                    $tono = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'crit');
                    $tonoIc = ['ok' => 'check_circle', 'warn' => 'schedule', 'crit' => 'error'][$tono];
                    $tonoTxt = ['ok' => 'Casi completo', 'warn' => 'En progreso', 'crit' => 'Faltan datos importantes'][$tono];
                    $conError = [];
                    foreach ([1, 2, 3, 4] as $n) {
                        $conError[$n] = collect(\App\Services\Tripulacion\ReglasExpediente::camposPaso($n))
                            ->contains(fn ($c) => $errors->has($c) || $errors->has($c.'.*'));
                    }
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

                @if ($comp)
                    <section class="nt-rev-comp" data-estado="{{ $tono }}" aria-labelledby="nt-rev-comp-t">
                        <div class="nt-rev-comp-cab">
                            <h3 id="nt-rev-comp-t" class="nt-rev-eyebrow">EXPEDIENTE COMPLETO</h3>
                            <span class="nt-rev-pct" role="status" aria-live="polite"><span class="nt-sr">Expediente completo al </span>{{ $pct }} %</span>
                        </div>
                        <div class="nt-rev-barra" role="progressbar" aria-labelledby="nt-rev-comp-t" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                        <p class="nt-rev-estado"><span class="icono" aria-hidden="true">{{ $tonoIc }}</span> {{ $tonoTxt }}</p>
                        @if (! empty($comp['faltantes']))
                            <p class="nt-rev-texto">Puedes guardar ahora y completar después. Faltan {{ count($comp['faltantes']) }} datos.</p>
                            <ul class="nt-rev-chips">
                                @foreach ($comp['faltantes'] as $f)
                                    <li>
                                        @if (isset($pasoDe[$f]))
                                            <button type="button" class="nt-rev-chip" wire:click="irAPaso({{ $pasoDe[$f] }})">{{ $f }} <span class="nt-rev-chip-paso">PASO {{ $pasoDe[$f] }}</span></button>
                                        @else
                                            <span class="nt-rev-chip">{{ $f }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="nt-rev-texto">El expediente tiene todos los datos clave.</p>
                        @endif
                    </section>

                    @if (! empty($comp['faltantes']))
                        <section class="nt-rev-pend" aria-labelledby="nt-rev-pend-t">
                            <h3 id="nt-rev-pend-t"><span class="icono" aria-hidden="true">info</span> Datos pendientes (no bloquean el guardado)</h3>
                            <ul>
                                @foreach ($comp['faltantes'] as $f)
                                    <li>
                                        <span class="nt-rev-pend-t"><strong>{{ $f }}</strong>: sin registrar.</span>
                                        @if (isset($pasoDe[$f]))
                                            <button type="button" class="nt-rev-ir" wire:click="irAPaso({{ $pasoDe[$f] }})">Ir al paso {{ $pasoDe[$f] }} <span class="icono" aria-hidden="true">arrow_forward</span></button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endif

                @if ($errors->any())
                    <section class="nt-rev-errores" role="alert" aria-labelledby="nt-rev-err-t">
                        <h3 id="nt-rev-err-t"><span class="icono" aria-hidden="true">error</span> No se pudo guardar: revisa estos datos</h3>
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
                                    <span class="nt-rev-err-tag"><span class="icono" aria-hidden="true">error</span> CON ERROR</span>
                                @endif
                                <button type="button" class="nt-rev-editar" aria-label="Editar {{ $tit }}" wire:click="irAPaso({{ $n }})"><span class="icono" aria-hidden="true">edit</span> Editar</button>
                            </div>
                            <dl class="nt-rev-dl">
                                @foreach ($filas as [$k, $val])
                                    <div class="{{ ($n >= 3 || mb_strlen((string) $val) > 28) ? 'nt-rev-ancho' : '' }}">
                                        <dt>{{ $k }}</dt>
                                        <dd class="{{ in_array($val, ['—', 'Ninguna', 'Ninguno'], true) ? 'nt-rev-vacio' : '' }}">{{ $val }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>
                    @endforeach
                </div>

                {{-- PENDIENTE: para tomar mediciones precisas hacen falta dispositivos de medición reales (reloj, tensiómetro,
                     glucómetro, etc.). Por ahora las mediciones se simulan (SimuladorMediciones) o se capturan a mano.
                     Este bloque se activará cuando se integren dispositivos reales. --}}
                <div class="nt-dispositivo nt-rev-disp" aria-disabled="true">
                    <span class="icono" aria-hidden="true">watch</span>
                    <div class="nt-dispositivo-t">
                        <strong>Vincular dispositivo <span class="nt-proximamente">PRÓXIMAMENTE</span></strong>
                        <span class="nt-ayuda">Podrás vincular el reloj del tripulante cuando esta función esté disponible.</span>
                    </div>
                </div>
            @endif
