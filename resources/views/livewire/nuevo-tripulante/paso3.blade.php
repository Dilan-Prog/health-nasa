            {{-- ================= PASO 3: HISTORIA CLÍNICA ================= --}}
            @if ($paso === 3)
                @php
                    $inv = fn (string $k) => $errors->has($k) ? 'true' : 'false';
                    $hoy = now()->toDateString();
                    $maxA = $maxItems['alergias']; $maxC = $maxItems['condiciones']; $maxM = $maxItems['medicamentos']; $maxF = $maxItems['antecedentes'];
                @endphp

                {{-- Aptitud de vuelo y último examen --}}
                <section class="nt-lista-apto" aria-labelledby="nt-apto-titulo">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">flight</span></span>
                        <h3 class="nt-lista-titulo" id="nt-apto-titulo">Aptitud de vuelo</h3>
                    </div>
                    <p class="nt-lista-desc">Según el último examen médico.</p>
                    <div class="nt-lista-apto-campos">
                        <fieldset class="nt-campo nt-lista-ancho" id="{{ $id('aptitud_vuelo') }}" @if ($describe('aptitud_vuelo')) aria-describedby="{{ $describe('aptitud_vuelo') }}" @endif>
                            <legend>Aptitud de vuelo <span class="nt-lista-req">Obligatorio</span></legend>
                            <div class="nt-lista-segs" role="radiogroup" aria-label="Aptitud de vuelo">
                                @foreach ($cfg('aptitudes', []) as $v => $et)
                                    <label class="nt-lista-seg">
                                        <input type="radio" name="nt-aptitud-vuelo" value="{{ $v }}" wire:model.live="aptitud_vuelo">
                                        <span>{{ $et }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('aptitud_vuelo') <p class="nt-error" id="{{ $id('aptitud_vuelo') }}-error">{{ $message }}</p> @enderror
                        </fieldset>

                        @if ($aptitud_vuelo === 'apto_restricciones')
                            <div class="nt-campo nt-lista-ancho">
                                <label for="{{ $id('restricciones') }}">Restricciones <span class="nt-lista-req">Obligatorio</span></label>
                                <textarea id="{{ $id('restricciones') }}" rows="2" maxlength="1000" wire:model="restricciones" placeholder="Qué no puede hacer y hasta cuándo"
                                          aria-invalid="{{ $inv('restricciones') }}" @if ($describe('restricciones')) aria-describedby="{{ $describe('restricciones') }}" @endif></textarea>
                                @error('restricciones') <p class="nt-error" id="{{ $id('restricciones') }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="nt-campo">
                            <label for="{{ $id('ultimo_examen_en') }}">Último examen médico</label>
                            <input id="{{ $id('ultimo_examen_en') }}" type="date" wire:model="ultimo_examen_en" max="{{ $hoy }}"
                                   aria-invalid="{{ $inv('ultimo_examen_en') }}" @if ($describe('ultimo_examen_en')) aria-describedby="{{ $describe('ultimo_examen_en') }}" @endif>
                            @error('ultimo_examen_en') <p class="nt-error" id="{{ $id('ultimo_examen_en') }}-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="nt-campo nt-lista-ancho">
                            <label for="{{ $id('notas') }}">Notas clínicas</label>
                            <textarea id="{{ $id('notas') }}" rows="3" maxlength="2000" wire:model="notas" placeholder="Observaciones relevantes para el equipo médico"
                                      aria-invalid="{{ $inv('notas') }}" @if ($describe('notas')) aria-describedby="{{ $describe('notas') }}" @endif></textarea>
                            @error('notas') <p class="nt-error" id="{{ $id('notas') }}-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                {{-- ===== Alergias ===== --}}
                <section class="nt-lista" aria-labelledby="nt-alergias-titulo" data-error="{{ $errors->has('alergias') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">allergy</span></span>
                        <h3 class="nt-lista-titulo" id="nt-alergias-titulo">Alergias <span class="nt-lista-cuenta">{{ count($alergias) }}</span></h3>
                        @if (count($alergias))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAlergia" aria-label="Añadir alergia" @if (count($alergias) >= $maxA) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Añadir alergia</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($alergias) ? 'Alergias: '.count($alergias).' en la lista.' : 'Alergias: lista vacía.' }}</p>
                    @error('alergias') <p class="nt-lista-error" id="{{ $id('alergias') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($alergias))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">allergy</span></span>
                            <strong>Sin alergias registradas</strong>
                            <span class="nt-lista-vacio-x">Si no tiene ninguna conocida, deja la lista vacía.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAlergia" aria-label="Añadir alergia"><span class="icono" aria-hidden="true">add_circle</span> Añadir alergia</button>
                        </div>
                    @endif
                    @foreach ($alergias as $i => $f)
                        <div class="nt-lista-fila" wire:key="alergia-{{ $i }}" role="group" aria-label="Alergia {{ $i + 1 }}" data-tono="{{ ($f['gravedad'] ?? '') === 'grave' ? 'crit' : '' }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Alergia {{ $i + 1 }}</span>
                                @if (($f['gravedad'] ?? '') !== '')
                                    <span class="nt-lista-pill" data-tono="{{ ['leve' => 'ok', 'moderada' => 'warn', 'grave' => 'crit'][$f['gravedad']] ?? '' }}">{{ $cfg('gravedades', [])[$f['gravedad']] ?? $f['gravedad'] }}</span>
                                @endif
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarAlergia({{ $i }})" aria-label="Quitar alergia {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Quitar</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("alergias.$i.sustancia") }}">Sustancia <span class="nt-lista-req">Obligatorio</span></label>
                                    <input id="{{ $id("alergias.$i.sustancia") }}" type="text" maxlength="120" wire:model="alergias.{{ $i }}.sustancia" placeholder="Ej.: Penicilina" autocomplete="off"
                                           aria-invalid="{{ $inv("alergias.$i.sustancia") }}" @if ($describe("alergias.$i.sustancia")) aria-describedby="{{ $describe("alergias.$i.sustancia") }}" @endif>
                                    @error("alergias.$i.sustancia") <p class="nt-error" id="{{ $id("alergias.$i.sustancia") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("alergias.$i.reaccion") }}">Reacción</label>
                                    <input id="{{ $id("alergias.$i.reaccion") }}" type="text" maxlength="160" wire:model="alergias.{{ $i }}.reaccion" placeholder="Ej.: Urticaria" autocomplete="off"
                                           aria-invalid="{{ $inv("alergias.$i.reaccion") }}" @if ($describe("alergias.$i.reaccion")) aria-describedby="{{ $describe("alergias.$i.reaccion") }}" @endif>
                                    @error("alergias.$i.reaccion") <p class="nt-error" id="{{ $id("alergias.$i.reaccion") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <fieldset class="nt-campo nt-lista-ancho" id="{{ $id("alergias.$i.gravedad") }}" @if ($describe("alergias.$i.gravedad")) aria-describedby="{{ $describe("alergias.$i.gravedad") }}" @endif>
                                    <legend>Gravedad</legend>
                                    <div class="nt-lista-segs" role="radiogroup" aria-label="Gravedad de la alergia {{ $i + 1 }}">
                                        @foreach ($cfg('gravedades', []) as $v => $et)
                                            <label class="nt-lista-seg">
                                                <input type="radio" name="nt-alergias-{{ $i }}-gravedad" value="{{ $v }}" wire:model.live="alergias.{{ $i }}.gravedad">
                                                <span>{{ $et }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error("alergias.$i.gravedad") <p class="nt-error" id="{{ $id("alergias.$i.gravedad") }}-error">{{ $message }}</p> @enderror
                                </fieldset>
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- ===== Condiciones médicas ===== --}}
                <section class="nt-lista" aria-labelledby="nt-condiciones-titulo" data-error="{{ $errors->has('condiciones') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">medical_information</span></span>
                        <h3 class="nt-lista-titulo" id="nt-condiciones-titulo">Condiciones médicas <span class="nt-lista-cuenta">{{ count($condiciones) }}</span></h3>
                        @if (count($condiciones))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarCondicion" aria-label="Añadir condición médica" @if (count($condiciones) >= $maxC) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Añadir condición</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($condiciones) ? 'Condiciones médicas: '.count($condiciones).' en la lista.' : 'Condiciones médicas: lista vacía.' }}</p>
                    @error('condiciones') <p class="nt-lista-error" id="{{ $id('condiciones') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($condiciones))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">medical_information</span></span>
                            <strong>Sin condiciones registradas</strong>
                            <span class="nt-lista-vacio-x">Enfermedades crónicas, lesiones o cirugías relevantes.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarCondicion" aria-label="Añadir condición médica"><span class="icono" aria-hidden="true">add_circle</span> Añadir condición</button>
                        </div>
                    @endif
                    @foreach ($condiciones as $i => $f)
                        <div class="nt-lista-fila" wire:key="condicion-{{ $i }}" role="group" aria-label="Condición {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Condición {{ $i + 1 }}</span>
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarCondicion({{ $i }})" aria-label="Quitar condición {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Quitar</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.nombre") }}">Condición <span class="nt-lista-req">Obligatorio</span></label>
                                    <input id="{{ $id("condiciones.$i.nombre") }}" type="text" maxlength="120" wire:model="condiciones.{{ $i }}.nombre" autocomplete="off"
                                           aria-invalid="{{ $inv("condiciones.$i.nombre") }}" @if ($describe("condiciones.$i.nombre")) aria-describedby="{{ $describe("condiciones.$i.nombre") }}" @endif>
                                    @error("condiciones.$i.nombre") <p class="nt-error" id="{{ $id("condiciones.$i.nombre") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.estado") }}">Estado</label>
                                    <select id="{{ $id("condiciones.$i.estado") }}" wire:model="condiciones.{{ $i }}.estado"
                                            aria-invalid="{{ $inv("condiciones.$i.estado") }}" @if ($describe("condiciones.$i.estado")) aria-describedby="{{ $describe("condiciones.$i.estado") }}" @endif>
                                        @foreach ($cfg('estados_condicion', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                    </select>
                                    @error("condiciones.$i.estado") <p class="nt-error" id="{{ $id("condiciones.$i.estado") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.desde") }}">Desde</label>
                                    <input id="{{ $id("condiciones.$i.desde") }}" type="date" max="{{ $hoy }}" wire:model="condiciones.{{ $i }}.desde"
                                           aria-invalid="{{ $inv("condiciones.$i.desde") }}" @if ($describe("condiciones.$i.desde")) aria-describedby="{{ $describe("condiciones.$i.desde") }}" @endif>
                                    @error("condiciones.$i.desde") <p class="nt-error" id="{{ $id("condiciones.$i.desde") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo nt-lista-ancho">
                                    <label for="{{ $id("condiciones.$i.notas") }}">Notas</label>
                                    <input id="{{ $id("condiciones.$i.notas") }}" type="text" maxlength="500" wire:model="condiciones.{{ $i }}.notas" autocomplete="off"
                                           aria-invalid="{{ $inv("condiciones.$i.notas") }}" @if ($describe("condiciones.$i.notas")) aria-describedby="{{ $describe("condiciones.$i.notas") }}" @endif>
                                    @error("condiciones.$i.notas") <p class="nt-error" id="{{ $id("condiciones.$i.notas") }}-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- ===== Medicamentos ===== --}}
                <section class="nt-lista" aria-labelledby="nt-medicamentos-titulo" aria-describedby="nt-medicamentos-aviso" data-error="{{ $errors->has('medicamentos') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">medication</span></span>
                        <h3 class="nt-lista-titulo" id="nt-medicamentos-titulo">Medicamentos actuales <span class="nt-lista-cuenta">{{ count($medicamentos) }}</span></h3>
                        @if (count($medicamentos))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarMedicamento" aria-label="Añadir medicamento" @if (count($medicamentos) >= $maxM) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Añadir medicamento</button>
                        @endif
                    </div>
                    <p class="nt-lista-aviso" id="nt-medicamentos-aviso"><span class="icono" aria-hidden="true">info</span><span><strong>Registro de lo que ya toma. La app no receta ni cambia dosis.</strong></span></p>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($medicamentos) ? 'Medicamentos: '.count($medicamentos).' en la lista.' : 'Medicamentos: lista vacía.' }}</p>
                    @error('medicamentos') <p class="nt-lista-error" id="{{ $id('medicamentos') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($medicamentos))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">medication</span></span>
                            <strong>Sin medicamentos registrados</strong>
                            <span class="nt-lista-vacio-x">Anota solo lo que ya toma por indicación de su médico.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarMedicamento" aria-label="Añadir medicamento"><span class="icono" aria-hidden="true">add_circle</span> Añadir medicamento</button>
                        </div>
                    @endif
                    @foreach ($medicamentos as $i => $f)
                        <div class="nt-lista-fila" wire:key="medicamento-{{ $i }}" role="group" aria-label="Medicamento {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Medicamento {{ $i + 1 }}</span>
                                @if (! ($f['activo'] ?? false))
                                    <span class="nt-lista-pill"><span class="icono" aria-hidden="true">pause_circle</span> Ya no lo toma</span>
                                @endif
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarMedicamento({{ $i }})" aria-label="Quitar medicamento {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Quitar</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.nombre") }}">Medicamento <span class="nt-lista-req">Obligatorio</span></label>
                                    <input id="{{ $id("medicamentos.$i.nombre") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.nombre" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.nombre") }}" @if ($describe("medicamentos.$i.nombre")) aria-describedby="{{ $describe("medicamentos.$i.nombre") }}" @endif>
                                    @error("medicamentos.$i.nombre") <p class="nt-error" id="{{ $id("medicamentos.$i.nombre") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.dosis_texto") }}">Dosis</label>
                                    <input id="{{ $id("medicamentos.$i.dosis_texto") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.dosis_texto" placeholder="Ej.: 50 mg" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.dosis_texto") }}" @if ($describe("medicamentos.$i.dosis_texto")) aria-describedby="{{ $describe("medicamentos.$i.dosis_texto") }}" @endif>
                                    @error("medicamentos.$i.dosis_texto") <p class="nt-error" id="{{ $id("medicamentos.$i.dosis_texto") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.frecuencia") }}">Frecuencia</label>
                                    <input id="{{ $id("medicamentos.$i.frecuencia") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.frecuencia" placeholder="Ej.: cada 12 h" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.frecuencia") }}" @if ($describe("medicamentos.$i.frecuencia")) aria-describedby="{{ $describe("medicamentos.$i.frecuencia") }}" @endif>
                                    @error("medicamentos.$i.frecuencia") <p class="nt-error" id="{{ $id("medicamentos.$i.frecuencia") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.motivo") }}">Motivo</label>
                                    <input id="{{ $id("medicamentos.$i.motivo") }}" type="text" maxlength="160" wire:model="medicamentos.{{ $i }}.motivo" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.motivo") }}" @if ($describe("medicamentos.$i.motivo")) aria-describedby="{{ $describe("medicamentos.$i.motivo") }}" @endif>
                                    @error("medicamentos.$i.motivo") <p class="nt-error" id="{{ $id("medicamentos.$i.motivo") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.desde") }}">Desde</label>
                                    <input id="{{ $id("medicamentos.$i.desde") }}" type="date" max="{{ $hoy }}" wire:model="medicamentos.{{ $i }}.desde"
                                           aria-invalid="{{ $inv("medicamentos.$i.desde") }}" @if ($describe("medicamentos.$i.desde")) aria-describedby="{{ $describe("medicamentos.$i.desde") }}" @endif>
                                    @error("medicamentos.$i.desde") <p class="nt-error" id="{{ $id("medicamentos.$i.desde") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <span class="nt-etiqueta" id="{{ $id("medicamentos.$i.activo") }}-etiqueta">Estado</span>
                                    <label class="nt-lista-opcion"><input type="checkbox" id="{{ $id("medicamentos.$i.activo") }}" wire:model.live="medicamentos.{{ $i }}.activo" aria-describedby="{{ $id("medicamentos.$i.activo") }}-etiqueta"> <span>Lo toma actualmente</span></label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- ===== Antecedentes familiares ===== --}}
                <section class="nt-lista" aria-labelledby="nt-antecedentes-titulo" data-error="{{ $errors->has('antecedentes') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">family_restroom</span></span>
                        <h3 class="nt-lista-titulo" id="nt-antecedentes-titulo">Antecedentes familiares <span class="nt-lista-cuenta">{{ count($antecedentes) }}</span></h3>
                        @if (count($antecedentes))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAntecedente" aria-label="Añadir antecedente familiar" @if (count($antecedentes) >= $maxF) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Añadir antecedente</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($antecedentes) ? 'Antecedentes familiares: '.count($antecedentes).' en la lista.' : 'Antecedentes familiares: lista vacía.' }}</p>
                    @error('antecedentes') <p class="nt-lista-error" id="{{ $id('antecedentes') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($antecedentes))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">family_restroom</span></span>
                            <strong>Sin antecedentes registrados</strong>
                            <span class="nt-lista-vacio-x">Enfermedades de padres, hermanos o abuelos.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAntecedente" aria-label="Añadir antecedente familiar"><span class="icono" aria-hidden="true">add_circle</span> Añadir antecedente</button>
                        </div>
                    @endif
                    @foreach ($antecedentes as $i => $f)
                        <div class="nt-lista-fila" wire:key="antecedente-{{ $i }}" role="group" aria-label="Antecedente {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Antecedente {{ $i + 1 }}</span>
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarAntecedente({{ $i }})" aria-label="Quitar antecedente {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Quitar</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.parentesco") }}">Parentesco</label>
                                    <select id="{{ $id("antecedentes.$i.parentesco") }}" wire:model="antecedentes.{{ $i }}.parentesco"
                                            aria-invalid="{{ $inv("antecedentes.$i.parentesco") }}" @if ($describe("antecedentes.$i.parentesco")) aria-describedby="{{ $describe("antecedentes.$i.parentesco") }}" @endif>
                                        <option value="">{{ $placeholderSel }}</option>
                                        @foreach ($cfg('parentescos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                    </select>
                                    @error("antecedentes.$i.parentesco") <p class="nt-error" id="{{ $id("antecedentes.$i.parentesco") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.condicion") }}">Condición <span class="nt-lista-req">Obligatorio</span></label>
                                    <input id="{{ $id("antecedentes.$i.condicion") }}" type="text" maxlength="160" wire:model="antecedentes.{{ $i }}.condicion" autocomplete="off"
                                           aria-invalid="{{ $inv("antecedentes.$i.condicion") }}" @if ($describe("antecedentes.$i.condicion")) aria-describedby="{{ $describe("antecedentes.$i.condicion") }}" @endif>
                                    @error("antecedentes.$i.condicion") <p class="nt-error" id="{{ $id("antecedentes.$i.condicion") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.edad_aparicion") }}">Edad de aparición <span class="nt-lista-unidad">(años)</span></label>
                                    <input id="{{ $id("antecedentes.$i.edad_aparicion") }}" type="number" inputmode="numeric" min="{{ $lim('edad_aparicion')[0] }}" max="{{ $lim('edad_aparicion')[1] }}" wire:model="antecedentes.{{ $i }}.edad_aparicion"
                                           aria-invalid="{{ $inv("antecedentes.$i.edad_aparicion") }}" @if ($describe("antecedentes.$i.edad_aparicion")) aria-describedby="{{ $describe("antecedentes.$i.edad_aparicion") }}" @endif>
                                    @error("antecedentes.$i.edad_aparicion") <p class="nt-error" id="{{ $id("antecedentes.$i.edad_aparicion") }}-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

