            {{-- ================= PASO 1: IDENTIDAD ================= --}}
            @if ($paso === 1)
                @php
                    $edadTxt = $this->edad !== null ? $this->edad.' años' : null;
                    $maxNac = now()->subYears($lim('edad')[0])->toDateString();
                    // [propiedad, etiqueta, tipo, obligatorio, ancho completo, placeholder, atributos extra]
                    $camposId = [
                        ['nombre', 'Nombre completo', 'text', true, true, 'Nombre y apellidos', 'autocomplete="off" maxlength="120"'],
                        ['fecha_nacimiento', 'Fecha de nacimiento', 'date', true, false, '', 'max="'.$maxNac.'"'],
                        ['sexo', 'Sexo', 'seg', true, false, '', ''],
                        ['nacionalidad', 'Nacionalidad', 'text', false, false, 'Ej.: Mexicana', 'maxlength="80" autocomplete="off"'],
                        ['agencia', 'Agencia', 'lista', false, false, 'Escribe o elige', 'maxlength="80" list="nt-agencias" autocomplete="off"'],
                        ['rol', 'Rol en la misión', 'lista', false, false, 'Escribe o elige', 'maxlength="80" list="nt-roles" autocomplete="off"'],
                        ['fecha_ingreso', 'Ingreso al cuerpo de astronautas', 'date', false, false, '', 'max="'.now()->toDateString().'"'],
                        ['estado_servicio', 'Estado de servicio', 'seg', false, true, '', ''],
                    ];
                    $opcionesSeg = ['sexo' => $cfg('sexos', []), 'estado_servicio' => $cfg('estados_servicio', [])];
                    $ayudasId = ['fecha_nacimiento' => 'La edad se calcula con la fecha. Mínimo '.$lim('edad')[0].' años.'];
                @endphp

                <section class="nt-seccion" aria-labelledby="nt-sec-identidad">
                    <div class="nt-seccion-cab">
                        <h3 id="nt-sec-identidad" class="nt-seccion-t">Identidad</h3>
                        <p class="nt-seccion-d">Como figura en su documentación oficial.</p>
                    </div>

                    <div class="nt-rejilla">
                        @foreach ($camposId as [$k, $et, $tipo, $req, $ancho, $ph, $extra])
                            @php
                                $uid = $id($k);
                                $tieneAyuda = isset($ayudasId[$k]);
                                $desc = trim(($tieneAyuda ? $uid.'-ayuda ' : '').($errors->has($k) ? $uid.'-error' : ''));
                                $inv = $errors->has($k) ? 'true' : 'false';
                            @endphp
                            <div class="nt-campo {{ $ancho ? 'nt-ancho' : '' }}" wire:key="c1-{{ $k }}">
                                <label for="{{ $uid }}" id="{{ $uid }}-etq">{{ $et }} @if ($req)<span class="nt-req">obligatorio</span>@endif</label>

                                @if ($tipo === 'seg')
                                    <div id="{{ $uid }}" class="nt-seg" role="radiogroup" aria-labelledby="{{ $uid }}-etq"
                                         aria-invalid="{{ $inv }}" @if ($req) aria-required="true" @endif @if ($desc) aria-describedby="{{ $desc }}" @endif>
                                        @foreach ($opcionesSeg[$k] as $v => $l)
                                            <label class="nt-seg-op" wire:key="c1-{{ $k }}-{{ $v }}">
                                                <input type="radio" name="{{ $uid }}" value="{{ $v }}" wire:model="{{ $k }}">
                                                <span><span class="icono" aria-hidden="true">check</span>{{ $l }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="nt-control">
                                        <input id="{{ $uid }}" type="{{ $tipo === 'lista' ? 'text' : $tipo }}"
                                               wire:model{{ $k === 'fecha_nacimiento' ? '.blur' : '' }}="{{ $k }}"
                                               @if ($ph) placeholder="{{ $ph }}" @endif {!! $extra !!}
                                               @if ($req) required aria-required="true" @endif
                                               aria-invalid="{{ $inv }}" @if ($desc) aria-describedby="{{ $desc }}" @endif>
                                    </span>
                                    @if ($k === 'rol')
                                        <datalist id="nt-roles">@foreach ($cfg('roles', []) as $r) <option value="{{ $r }}"></option> @endforeach</datalist>
                                    @elseif ($k === 'agencia')
                                        <datalist id="nt-agencias">@foreach (['NASA', 'ESA', 'JAXA', 'CSA', 'Roscosmos', 'AEM'] as $a) <option value="{{ $a }}"></option> @endforeach</datalist>
                                    @endif
                                @endif

                                @if ($k === 'fecha_nacimiento')
                                    <p class="nt-ayuda {{ $edadTxt ? 'nt-ayuda--ok' : '' }}" id="{{ $uid }}-ayuda" aria-live="polite">{{ $edadTxt ? 'Edad: '.$edadTxt : $ayudasId[$k] }}</p>
                                @endif
                                @error($k) <p class="nt-error" id="{{ $uid }}-error"><span class="icono" aria-hidden="true">error</span>{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
