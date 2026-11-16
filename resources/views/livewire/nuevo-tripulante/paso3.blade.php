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
                        <h3 class="nt-lista-titulo" id="nt-apto-titulo">Flight fitness</h3>
                    </div>
                    <p class="nt-lista-desc">Based on the latest medical exam.</p>
                    <div class="nt-lista-apto-campos">
                        <fieldset class="nt-campo nt-lista-ancho" id="{{ $id('aptitud_vuelo') }}" @if ($describe('aptitud_vuelo')) aria-describedby="{{ $describe('aptitud_vuelo') }}" @endif>
                            <legend>Flight fitness <span class="nt-lista-req">Required</span></legend>
                            <div class="nt-lista-segs" role="radiogroup" aria-label="Flight fitness">
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
                                <label for="{{ $id('restricciones') }}">Restrictions <span class="nt-lista-req">Required</span></label>
                                <textarea id="{{ $id('restricciones') }}" rows="2" maxlength="1000" wire:model="restricciones" placeholder="What they cannot do and until when"
                                          aria-invalid="{{ $inv('restricciones') }}" @if ($describe('restricciones')) aria-describedby="{{ $describe('restricciones') }}" @endif></textarea>
                                @error('restricciones') <p class="nt-error" id="{{ $id('restricciones') }}-error">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="nt-campo">
                            <label for="{{ $id('ultimo_examen_en') }}">Last medical exam</label>
                            <input id="{{ $id('ultimo_examen_en') }}" type="date" wire:model="ultimo_examen_en" max="{{ $hoy }}"
                                   aria-invalid="{{ $inv('ultimo_examen_en') }}" @if ($describe('ultimo_examen_en')) aria-describedby="{{ $describe('ultimo_examen_en') }}" @endif>
                            @error('ultimo_examen_en') <p class="nt-error" id="{{ $id('ultimo_examen_en') }}-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="nt-campo nt-lista-ancho">
                            <label for="{{ $id('notas') }}">Clinical notes</label>
                            <textarea id="{{ $id('notas') }}" rows="3" maxlength="2000" wire:model="notas" placeholder="Observations relevant to the medical team"
                                      aria-invalid="{{ $inv('notas') }}" @if ($describe('notas')) aria-describedby="{{ $describe('notas') }}" @endif></textarea>
                            @error('notas') <p class="nt-error" id="{{ $id('notas') }}-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                {{-- ===== Alergias ===== --}}
                <section class="nt-lista" aria-labelledby="nt-alergias-titulo" data-error="{{ $errors->has('alergias') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">allergy</span></span>
                        <h3 class="nt-lista-titulo" id="nt-alergias-titulo">Allergies <span class="nt-lista-cuenta">{{ count($alergias) }}</span></h3>
                        @if (count($alergias))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAlergia" aria-label="Add allergy" @if (count($alergias) >= $maxA) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Add allergy</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($alergias) ? 'Allergies: '.count($alergias).' in the list.' : 'Allergies: list empty.' }}</p>
                    @error('alergias') <p class="nt-lista-error" id="{{ $id('alergias') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($alergias))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">allergy</span></span>
                            <strong>No allergies recorded</strong>
                            <span class="nt-lista-vacio-x">If none are known, leave the list empty.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAlergia" aria-label="Add allergy"><span class="icono" aria-hidden="true">add_circle</span> Add allergy</button>
                        </div>
                    @endif
                    @foreach ($alergias as $i => $f)
                        <div class="nt-lista-fila" wire:key="alergia-{{ $i }}" role="group" aria-label="Allergy {{ $i + 1 }}" data-tono="{{ ($f['gravedad'] ?? '') === 'grave' ? 'crit' : '' }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Allergy {{ $i + 1 }}</span>
                                @if (($f['gravedad'] ?? '') !== '')
                                    <span class="nt-lista-pill" data-tono="{{ ['leve' => 'ok', 'moderada' => 'warn', 'grave' => 'crit'][$f['gravedad']] ?? '' }}">{{ $cfg('gravedades', [])[$f['gravedad']] ?? $f['gravedad'] }}</span>
                                @endif
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarAlergia({{ $i }})" aria-label="Remove allergy {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Remove</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("alergias.$i.sustancia") }}">Substance <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("alergias.$i.sustancia") }}" type="text" maxlength="120" wire:model="alergias.{{ $i }}.sustancia" placeholder="E.g., Penicillin" autocomplete="off"
                                           aria-invalid="{{ $inv("alergias.$i.sustancia") }}" @if ($describe("alergias.$i.sustancia")) aria-describedby="{{ $describe("alergias.$i.sustancia") }}" @endif>
                                    @error("alergias.$i.sustancia") <p class="nt-error" id="{{ $id("alergias.$i.sustancia") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("alergias.$i.reaccion") }}">Reaction</label>
                                    <input id="{{ $id("alergias.$i.reaccion") }}" type="text" maxlength="160" wire:model="alergias.{{ $i }}.reaccion" placeholder="E.g., Hives" autocomplete="off"
                                           aria-invalid="{{ $inv("alergias.$i.reaccion") }}" @if ($describe("alergias.$i.reaccion")) aria-describedby="{{ $describe("alergias.$i.reaccion") }}" @endif>
                                    @error("alergias.$i.reaccion") <p class="nt-error" id="{{ $id("alergias.$i.reaccion") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <fieldset class="nt-campo nt-lista-ancho" id="{{ $id("alergias.$i.gravedad") }}" @if ($describe("alergias.$i.gravedad")) aria-describedby="{{ $describe("alergias.$i.gravedad") }}" @endif>
                                    <legend>Severity</legend>
                                    <div class="nt-lista-segs" role="radiogroup" aria-label="Allergy {{ $i + 1 }} severity">
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
                        <h3 class="nt-lista-titulo" id="nt-condiciones-titulo">Medical conditions <span class="nt-lista-cuenta">{{ count($condiciones) }}</span></h3>
                        @if (count($condiciones))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarCondicion" aria-label="Add medical condition" @if (count($condiciones) >= $maxC) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Add condition</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($condiciones) ? 'Medical conditions: '.count($condiciones).' in the list.' : 'Medical conditions: list empty.' }}</p>
                    @error('condiciones') <p class="nt-lista-error" id="{{ $id('condiciones') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($condiciones))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">medical_information</span></span>
                            <strong>No conditions recorded</strong>
                            <span class="nt-lista-vacio-x">Chronic illnesses, injuries, or relevant surgeries.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarCondicion" aria-label="Add medical condition"><span class="icono" aria-hidden="true">add_circle</span> Add condition</button>
                        </div>
                    @endif
                    @foreach ($condiciones as $i => $f)
                        <div class="nt-lista-fila" wire:key="condicion-{{ $i }}" role="group" aria-label="Condition {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Condition {{ $i + 1 }}</span>
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarCondicion({{ $i }})" aria-label="Remove condition {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Remove</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.nombre") }}">Condition <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("condiciones.$i.nombre") }}" type="text" maxlength="120" wire:model="condiciones.{{ $i }}.nombre" autocomplete="off"
                                           aria-invalid="{{ $inv("condiciones.$i.nombre") }}" @if ($describe("condiciones.$i.nombre")) aria-describedby="{{ $describe("condiciones.$i.nombre") }}" @endif>
                                    @error("condiciones.$i.nombre") <p class="nt-error" id="{{ $id("condiciones.$i.nombre") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.estado") }}">Status</label>
                                    <select id="{{ $id("condiciones.$i.estado") }}" wire:model="condiciones.{{ $i }}.estado"
                                            aria-invalid="{{ $inv("condiciones.$i.estado") }}" @if ($describe("condiciones.$i.estado")) aria-describedby="{{ $describe("condiciones.$i.estado") }}" @endif>
                                        @foreach ($cfg('estados_condicion', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                    </select>
                                    @error("condiciones.$i.estado") <p class="nt-error" id="{{ $id("condiciones.$i.estado") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("condiciones.$i.desde") }}">Since</label>
                                    <input id="{{ $id("condiciones.$i.desde") }}" type="date" max="{{ $hoy }}" wire:model="condiciones.{{ $i }}.desde"
                                           aria-invalid="{{ $inv("condiciones.$i.desde") }}" @if ($describe("condiciones.$i.desde")) aria-describedby="{{ $describe("condiciones.$i.desde") }}" @endif>
                                    @error("condiciones.$i.desde") <p class="nt-error" id="{{ $id("condiciones.$i.desde") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo nt-lista-ancho">
                                    <label for="{{ $id("condiciones.$i.notas") }}">Notes</label>
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
                        <h3 class="nt-lista-titulo" id="nt-medicamentos-titulo">Current medications <span class="nt-lista-cuenta">{{ count($medicamentos) }}</span></h3>
                        @if (count($medicamentos))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarMedicamento" aria-label="Add medication" @if (count($medicamentos) >= $maxM) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Add medication</button>
                        @endif
                    </div>
                    <p class="nt-lista-aviso" id="nt-medicamentos-aviso"><span class="icono" aria-hidden="true">info</span><span><strong>Record of what is already being taken. The app does not prescribe or change doses.</strong></span></p>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($medicamentos) ? 'Medications: '.count($medicamentos).' in the list.' : 'Medications: list empty.' }}</p>
                    @error('medicamentos') <p class="nt-lista-error" id="{{ $id('medicamentos') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($medicamentos))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">medication</span></span>
                            <strong>No medications recorded</strong>
                            <span class="nt-lista-vacio-x">Enter only what is already taken as directed by their physician.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarMedicamento" aria-label="Add medication"><span class="icono" aria-hidden="true">add_circle</span> Add medication</button>
                        </div>
                    @endif
                    @foreach ($medicamentos as $i => $f)
                        <div class="nt-lista-fila" wire:key="medicamento-{{ $i }}" role="group" aria-label="Medication {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Medication {{ $i + 1 }}</span>
                                @if (! ($f['activo'] ?? false))
                                    <span class="nt-lista-pill"><span class="icono" aria-hidden="true">pause_circle</span> No longer taking</span>
                                @endif
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarMedicamento({{ $i }})" aria-label="Remove medication {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Remove</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.nombre") }}">Medication <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("medicamentos.$i.nombre") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.nombre" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.nombre") }}" @if ($describe("medicamentos.$i.nombre")) aria-describedby="{{ $describe("medicamentos.$i.nombre") }}" @endif>
                                    @error("medicamentos.$i.nombre") <p class="nt-error" id="{{ $id("medicamentos.$i.nombre") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.dosis_texto") }}">Dose</label>
                                    <input id="{{ $id("medicamentos.$i.dosis_texto") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.dosis_texto" placeholder="E.g., 50 mg" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.dosis_texto") }}" @if ($describe("medicamentos.$i.dosis_texto")) aria-describedby="{{ $describe("medicamentos.$i.dosis_texto") }}" @endif>
                                    @error("medicamentos.$i.dosis_texto") <p class="nt-error" id="{{ $id("medicamentos.$i.dosis_texto") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.frecuencia") }}">Frequency</label>
                                    <input id="{{ $id("medicamentos.$i.frecuencia") }}" type="text" maxlength="120" wire:model="medicamentos.{{ $i }}.frecuencia" placeholder="E.g., every 12 h" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.frecuencia") }}" @if ($describe("medicamentos.$i.frecuencia")) aria-describedby="{{ $describe("medicamentos.$i.frecuencia") }}" @endif>
                                    @error("medicamentos.$i.frecuencia") <p class="nt-error" id="{{ $id("medicamentos.$i.frecuencia") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.motivo") }}">Reason</label>
                                    <input id="{{ $id("medicamentos.$i.motivo") }}" type="text" maxlength="160" wire:model="medicamentos.{{ $i }}.motivo" autocomplete="off"
                                           aria-invalid="{{ $inv("medicamentos.$i.motivo") }}" @if ($describe("medicamentos.$i.motivo")) aria-describedby="{{ $describe("medicamentos.$i.motivo") }}" @endif>
                                    @error("medicamentos.$i.motivo") <p class="nt-error" id="{{ $id("medicamentos.$i.motivo") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("medicamentos.$i.desde") }}">Since</label>
                                    <input id="{{ $id("medicamentos.$i.desde") }}" type="date" max="{{ $hoy }}" wire:model="medicamentos.{{ $i }}.desde"
                                           aria-invalid="{{ $inv("medicamentos.$i.desde") }}" @if ($describe("medicamentos.$i.desde")) aria-describedby="{{ $describe("medicamentos.$i.desde") }}" @endif>
                                    @error("medicamentos.$i.desde") <p class="nt-error" id="{{ $id("medicamentos.$i.desde") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <span class="nt-etiqueta" id="{{ $id("medicamentos.$i.activo") }}-etiqueta">Status</span>
                                    <label class="nt-lista-opcion"><input type="checkbox" id="{{ $id("medicamentos.$i.activo") }}" wire:model.live="medicamentos.{{ $i }}.activo" aria-describedby="{{ $id("medicamentos.$i.activo") }}-etiqueta"> <span>Currently taking</span></label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>

                {{-- ===== Antecedentes familiares ===== --}}
                <section class="nt-lista" aria-labelledby="nt-antecedentes-titulo" data-error="{{ $errors->has('antecedentes') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">family_restroom</span></span>
                        <h3 class="nt-lista-titulo" id="nt-antecedentes-titulo">Family history <span class="nt-lista-cuenta">{{ count($antecedentes) }}</span></h3>
                        @if (count($antecedentes))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAntecedente" aria-label="Add family history entry" @if (count($antecedentes) >= $maxF) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Add entry</button>
                        @endif
                    </div>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($antecedentes) ? 'Family history: '.count($antecedentes).' in the list.' : 'Family history: list empty.' }}</p>
                    @error('antecedentes') <p class="nt-lista-error" id="{{ $id('antecedentes') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($antecedentes))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">family_restroom</span></span>
                            <strong>No family history recorded</strong>
                            <span class="nt-lista-vacio-x">Illnesses of parents, siblings, or grandparents.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarAntecedente" aria-label="Add family history entry"><span class="icono" aria-hidden="true">add_circle</span> Add entry</button>
                        </div>
                    @endif
                    @foreach ($antecedentes as $i => $f)
                        <div class="nt-lista-fila" wire:key="antecedente-{{ $i }}" role="group" aria-label="Entry {{ $i + 1 }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Entry {{ $i + 1 }}</span>
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarAntecedente({{ $i }})" aria-label="Remove entry {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Remove</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.parentesco") }}">Relationship</label>
                                    <select id="{{ $id("antecedentes.$i.parentesco") }}" wire:model="antecedentes.{{ $i }}.parentesco"
                                            aria-invalid="{{ $inv("antecedentes.$i.parentesco") }}" @if ($describe("antecedentes.$i.parentesco")) aria-describedby="{{ $describe("antecedentes.$i.parentesco") }}" @endif>
                                        <option value="">{{ $placeholderSel }}</option>
                                        @foreach ($cfg('parentescos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                    </select>
                                    @error("antecedentes.$i.parentesco") <p class="nt-error" id="{{ $id("antecedentes.$i.parentesco") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.condicion") }}">Condition <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("antecedentes.$i.condicion") }}" type="text" maxlength="160" wire:model="antecedentes.{{ $i }}.condicion" autocomplete="off"
                                           aria-invalid="{{ $inv("antecedentes.$i.condicion") }}" @if ($describe("antecedentes.$i.condicion")) aria-describedby="{{ $describe("antecedentes.$i.condicion") }}" @endif>
                                    @error("antecedentes.$i.condicion") <p class="nt-error" id="{{ $id("antecedentes.$i.condicion") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("antecedentes.$i.edad_aparicion") }}">Age at onset <span class="nt-lista-unidad">(years)</span></label>
                                    <input id="{{ $id("antecedentes.$i.edad_aparicion") }}" type="number" inputmode="numeric" min="{{ $lim('edad_aparicion')[0] }}" max="{{ $lim('edad_aparicion')[1] }}" wire:model="antecedentes.{{ $i }}.edad_aparicion"
                                           aria-invalid="{{ $inv("antecedentes.$i.edad_aparicion") }}" @if ($describe("antecedentes.$i.edad_aparicion")) aria-describedby="{{ $describe("antecedentes.$i.edad_aparicion") }}" @endif>
                                    @error("antecedentes.$i.edad_aparicion") <p class="nt-error" id="{{ $id("antecedentes.$i.edad_aparicion") }}-error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

