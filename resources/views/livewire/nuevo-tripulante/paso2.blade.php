            {{-- ================= PASO 2: BIOMETRÍA Y LÍNEA BASE ================= --}}
            @if ($paso === 2)
                @php
                    $imc = $this->imc;
                    $imcTono = $imc === null ? 'neu' : ($imc >= 30 ? 'crit' : (($imc < 18.5 || $imc >= 25) ? 'warn' : 'ok'));
                    $imcEt = ['neu' => 'No data', 'ok' => 'Normal', 'warn' => ($imc !== null && $imc < 18.5) ? 'Underweight' : 'Overweight', 'crit' => 'Obese'][$imcTono];
                    $imcIc = ['neu' => 'remove', 'ok' => 'check_circle', 'warn' => 'warning', 'crit' => 'error'][$imcTono];
                    $phNum = ['altura_m' => '1.70', 'peso_kg' => '65.0'];

                    // Campo numérico: unit inside the box y rango como ayuda (se parte el texto "unidad · entre a y b").
                    $numero = function (array $def) use ($phNum) {
                        [$k, $et, , $attrs, $ayuda] = $def;
                        $rango = \Illuminate\Support\Str::after($ayuda, ' · ');

                        return compact('k', 'et', 'attrs') + [
                            'unidad' => \Illuminate\Support\Str::before($ayuda, ' · '),
                            'rango' => ucfirst($rango),
                            'ph' => $phNum[$k] ?? '',
                        ];
                    };
                @endphp

                {{-- ---------- Biometría ---------- --}}
                <section class="nt-seccion" aria-labelledby="nt-sec-biometria">
                    <div class="nt-seccion-cab">
                        <h3 id="nt-sec-biometria" class="nt-seccion-t">Biometrics</h3>
                        <p class="nt-seccion-d">Body measurements and habits.</p>
                    </div>
                    <div class="nt-rejilla">
                        @foreach ($bio as $def)
                            @php
                                $n = $numero($def); $k = $n['k']; $uid = $id($k);
                                $desc = trim($uid.'-ayuda '.($errors->has($k) ? $uid.'-error' : ''));
                            @endphp
                            <div class="nt-campo" wire:key="c2-{{ $k }}">
                                <label for="{{ $uid }}">{{ $n['et'] }}</label>
                                <span class="nt-control">
                                    <input id="{{ $uid }}" type="number" wire:model{{ in_array($k, ['altura_m', 'peso_kg'], true) ? '.blur' : '' }}="{{ $k }}" {!! $n['attrs'] !!}
                                           @if ($n['ph']) placeholder="{{ $n['ph'] }}" @endif
                                           aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" aria-describedby="{{ $desc }}">
                                    <span class="nt-unidad-fija" aria-hidden="true">{{ $n['unidad'] }}</span>
                                </span>
                                <p class="nt-ayuda" id="{{ $uid }}-ayuda">{{ $n['rango'] }}</p>
                                @error($k) <p class="nt-error" id="{{ $uid }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        @endforeach

                        <div class="nt-campo nt-calculado" aria-live="polite">
                            <span class="nt-etq">Calculated BMI</span>
                            <div class="nt-calculado-v">
                                <strong>{{ $imc !== null ? number_format($imc, 1, '.', '') : '—' }}</strong>
                                <span class="nt-pildora" data-estado="{{ $imcTono }}"><span class="icono" aria-hidden="true">{{ $imcIc }}</span>{{ $imcEt }}</span>
                            </div>
                            <p class="nt-ayuda">Calculated from height and weight (kg/m²). For guidance only.</p>
                        </div>

                        <div class="nt-campo" wire:key="c2-grupo_sanguineo">
                            @php $uid = $id('grupo_sanguineo'); $desc = $errors->has('grupo_sanguineo') ? $uid.'-error' : ''; @endphp
                            <label for="{{ $uid }}">Blood type</label>
                            <span class="nt-control">
                                <select id="{{ $uid }}" wire:model="grupo_sanguineo" aria-invalid="{{ $errors->has('grupo_sanguineo') ? 'true' : 'false' }}" @if ($desc) aria-describedby="{{ $desc }}" @endif>
                                    <option value="">{{ $placeholderSel }}</option>
                                    @foreach ($cfg('grupos_sanguineos', []) as $g) <option value="{{ $g }}">{{ str_replace('-', '−', $g) }}</option> @endforeach
                                </select>
                                <span class="icono nt-select-ic" aria-hidden="true">expand_more</span>
                            </span>
                            @error('grupo_sanguineo') <p class="nt-error" id="{{ $uid }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                        </div>

                        @foreach ([['fumador', 'Smoker', $siNo, false], ['alcohol', 'Alcohol use', $cfg('alcohol', []), true]] as [$k, $et, $ops, $ancho])
                            @php $uid = $id($k); $desc = $errors->has($k) ? $uid.'-error' : ''; @endphp
                            <div class="nt-campo {{ $ancho ? 'nt-ancho' : '' }}" wire:key="c2-{{ $k }}">
                                <span class="nt-etq" id="{{ $uid }}-etq">{{ $et }}</span>
                                <div id="{{ $uid }}" class="nt-seg" role="radiogroup" aria-labelledby="{{ $uid }}-etq"
                                     aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" @if ($desc) aria-describedby="{{ $desc }}" @endif>
                                    @foreach ($ops as $v => $l)
                                        <label class="nt-seg-op" wire:key="c2-{{ $k }}-{{ $v }}">
                                            <input type="radio" name="{{ $uid }}" value="{{ $v }}" wire:model="{{ $k }}">
                                            <span><span class="icono" aria-hidden="true">check</span>{{ $l }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error($k) <p class="nt-error" id="{{ $uid }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ---------- Valores basales y hábitos ---------- --}}
                <section class="nt-seccion" aria-labelledby="nt-sec-basales">
                    <div class="nt-seccion-cab">
                        <h3 id="nt-sec-basales" class="nt-seccion-t">Baseline values</h3>
                        <p class="nt-seccion-d">At rest, on Earth, before the mission. All are optional.</p>
                    </div>
                    <div class="nt-rejilla">
                        @foreach (array_merge($basales, $habitos) as $def)
                            @php $n = $numero($def); $k = $n['k']; $uid = $id($k); $desc = trim($uid.'-ayuda '.($errors->has($k) ? $uid.'-error' : '')); @endphp
                            <div class="nt-campo" wire:key="c2b-{{ $k }}">
                                <label for="{{ $uid }}">{{ $n['et'] }}</label>
                                <span class="nt-control">
                                    <input id="{{ $uid }}" type="number" wire:model="{{ $k }}" {!! $n['attrs'] !!}
                                           aria-invalid="{{ $errors->has($k) ? 'true' : 'false' }}" aria-describedby="{{ $desc }}">
                                    <span class="nt-unidad-fija" aria-hidden="true">{{ $n['unidad'] }}</span>
                                </span>
                                <p class="nt-ayuda" id="{{ $uid }}-ayuda">{{ $n['rango'] }}</p>
                                @error($k) <p class="nt-error" id="{{ $uid }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ---------- Línea base personal ---------- --}}
                @php $lbRellenos = collect($linea_base)->filter(fn ($v) => trim((string) $v) !== '')->count(); @endphp
                <section class="nt-seccion" aria-labelledby="nt-lb-titulo" aria-describedby="nt-lb-desc">
                    <div class="nt-lb-cab">
                        <div class="nt-lb-txt">
                            <h3 id="nt-lb-titulo" class="nt-seccion-t">Personal baseline · {{ $lbRellenos }}/{{ count($marcadores) }}</h3>
                            <p id="nt-lb-desc">With a personal baseline, the app compares against the individual, not only against general ranges. All values are optional and can be edited one by one.</p>
                        </div>
                        <div class="nt-lb-acciones">
                            <button type="button" class="nt-btn" wire:click="precargarLineaBase"><span class="icono" aria-hidden="true">auto_fix_high</span> Fill with reference values</button>
                            <button type="button" class="nt-btn nt-btn--suave" wire:click="vaciarLineaBase"><span class="icono" aria-hidden="true">backspace</span> Clear</button>
                        </div>
                    </div>
                    @error('linea_base') <p class="nt-error" id="{{ $id('linea_base') }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror

                    <div class="nt-lb-tabla" role="table" aria-labelledby="nt-lb-titulo">
                        <div class="nt-lb-cols" role="row">
                            <span role="columnheader">INDICATOR</span><span role="columnheader">BASELINE VALUE</span><span role="columnheader">NORMAL RANGE</span><span role="columnheader">STATUS</span>
                        </div>
                        @foreach ($marcadores as $clave => $m)
                            @php
                                $uid = $id('lb-'.$clave); $campoLb = 'linea_base.'.$clave; $errId = $id($campoLb).'-error';
                                $dec = (int) ($m['dec'] ?? 0);
                                $fmt = fn ($x) => str_replace('-', '−', number_format((float) $x, $dec, '.', ''));
                                $rango = $fmt($m['lo']).' – '.$fmt($m['hi']).(($m['ic'] ?? '') === 'monitor_heart' ? ' (systolic)' : '');
                                $txt = str_replace(',', '.', trim((string) ($linea_base[$clave] ?? '')));
                                $val = $txt === '' ? null : (is_numeric($txt) ? (float) $txt : false);
                                $tono = $val === null ? 'neu' : (($val !== false && $val >= $m['lo'] && $val <= $m['hi']) ? 'ok' : 'warn');
                                $tonoEt = ['neu' => 'No data', 'ok' => 'In range', 'warn' => 'Out of range'][$tono];
                                $tonoIc = ['neu' => 'remove', 'ok' => 'check_circle', 'warn' => 'warning'][$tono];
                                $invalido = $errors->has($campoLb);
                            @endphp
                            <div class="nt-lb-fila nt-campo" role="row" wire:key="lb-{{ $clave }}">
                                <label class="nt-lb-nombre" role="rowheader" for="{{ $uid }}"><span class="icono" aria-hidden="true">{{ $m['ic'] }}</span>{{ $m['label'] }}</label>
                                <span class="nt-control" role="cell">
                                    <input id="{{ $uid }}" class="nt-mono" type="text" inputmode="decimal" autocomplete="off" placeholder="—"
                                           wire:model.blur="linea_base.{{ $clave }}"
                                           aria-invalid="{{ $invalido ? 'true' : 'false' }}"
                                           aria-describedby="{{ $uid }}-ref{{ $invalido ? ' '.$errId : '' }}">
                                    <span class="nt-unidad-fija" aria-hidden="true">{{ $m['unit'] }}</span>
                                </span>
                                <span class="nt-lb-rango" role="cell" id="{{ $uid }}-ref">{{ $rango }} {{ $m['unit'] }}</span>
                                <span role="cell"><span class="nt-pildora" data-estado="{{ $tono }}"><span class="icono" aria-hidden="true">{{ $tonoIc }}</span>{{ $tonoEt }}</span></span>
                                @error($campoLb) <p class="nt-error" id="{{ $errId }}"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
