            {{-- ================= PASO 4: CONTACTOS ================= --}}
            @if ($paso === 4)
                @php $maxK = $maxItems['contactos']; @endphp
                <section class="nt-lista" aria-labelledby="nt-contactos-titulo" aria-describedby="nt-contactos-ayuda" data-error="{{ $errors->has('contactos') ? 1 : 0 }}">
                    <div class="nt-lista-cab">
                        <span class="nt-lista-ic" aria-hidden="true"><span class="icono">contacts</span></span>
                        <h3 class="nt-lista-titulo" id="nt-contactos-titulo">Emergency contacts <span class="nt-lista-cuenta">{{ count($contactos) }}</span></h3>
                        @if (count($contactos))
                            <button type="button" class="nt-lista-anadir" wire:click="agregarContacto" aria-label="Add emergency contact" @if (count($contactos) >= $maxK) disabled @endif><span class="icono" aria-hidden="true">add_circle</span> Add contact</button>
                        @endif
                    </div>
                    <p class="nt-lista-desc" id="nt-contactos-ayuda">Whom to notify if something goes wrong. Up to {{ $maxK }} contacts; if you add any, one must be marked as primary.</p>
                    <p class="nt-lista-sr" role="status" aria-live="polite">{{ count($contactos) ? 'Emergency contacts: '.count($contactos).' in the list.' : 'Emergency contacts: list empty.' }}</p>
                    @error('contactos') <p class="nt-lista-error" id="{{ $id('contactos') }}-error" role="alert"><span class="icono" aria-hidden="true">error</span> {{ $message }}</p> @enderror
                    @if (! count($contactos))
                        <div class="nt-lista-vacio">
                            <span class="nt-lista-vacio-ic" aria-hidden="true"><span class="icono">contacts</span></span>
                            <strong>No emergency contacts</strong>
                            <span class="nt-lista-vacio-x">Add at least one and mark it as primary.</span>
                            <button type="button" class="nt-lista-anadir" wire:click="agregarContacto" aria-label="Add emergency contact"><span class="icono" aria-hidden="true">add_circle</span> Add contact</button>
                        </div>
                    @endif
                    @foreach ($contactos as $i => $f)
                        <div class="nt-lista-fila" wire:key="contacto-{{ $i }}" role="group" aria-label="Contact {{ $i + 1 }}" data-tono="{{ ($f['principal'] ?? false) ? 'principal' : '' }}">
                            <div class="nt-lista-fila-cab">
                                <span class="nt-lista-fila-t">Contact {{ $i + 1 }}</span>
                                @if ($f['principal'] ?? false)
                                    <span class="nt-lista-pill" data-tono="principal"><span class="icono" aria-hidden="true">star</span> Primary</span>
                                @endif
                                <span class="nt-lista-fila-esp"></span>
                                <button type="button" class="nt-lista-quitar" wire:click="quitarContacto({{ $i }})" aria-label="Remove contact {{ $i + 1 }}"><span class="icono" aria-hidden="true">delete</span> Remove</button>
                            </div>
                            <div class="nt-lista-campos">
                                <div class="nt-campo">
                                    <label for="{{ $id("contactos.$i.nombre") }}">Name <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("contactos.$i.nombre") }}" type="text" maxlength="120" wire:model="contactos.{{ $i }}.nombre" autocomplete="off"
                                           aria-invalid="{{ $errors->has("contactos.$i.nombre") ? 'true' : 'false' }}" @if ($describe("contactos.$i.nombre")) aria-describedby="{{ $describe("contactos.$i.nombre") }}" @endif>
                                    @error("contactos.$i.nombre") <p class="nt-error" id="{{ $id("contactos.$i.nombre") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("contactos.$i.parentesco") }}">Relationship</label>
                                    <select id="{{ $id("contactos.$i.parentesco") }}" wire:model="contactos.{{ $i }}.parentesco"
                                            aria-invalid="{{ $errors->has("contactos.$i.parentesco") ? 'true' : 'false' }}" @if ($describe("contactos.$i.parentesco")) aria-describedby="{{ $describe("contactos.$i.parentesco") }}" @endif>
                                        <option value="">{{ $placeholderSel }}</option>
                                        @foreach ($cfg('parentescos', []) as $v => $et) <option value="{{ $v }}">{{ $et }}</option> @endforeach
                                    </select>
                                    @error("contactos.$i.parentesco") <p class="nt-error" id="{{ $id("contactos.$i.parentesco") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <label for="{{ $id("contactos.$i.telefono") }}">Phone <span class="nt-lista-req">Required</span></label>
                                    <input id="{{ $id("contactos.$i.telefono") }}" type="tel" maxlength="32" wire:model="contactos.{{ $i }}.telefono" placeholder="+52 55 1234 5678" autocomplete="off"
                                           aria-invalid="{{ $errors->has("contactos.$i.telefono") ? 'true' : 'false' }}" @if ($describe("contactos.$i.telefono")) aria-describedby="{{ $describe("contactos.$i.telefono") }}" @endif>
                                    @error("contactos.$i.telefono") <p class="nt-error" id="{{ $id("contactos.$i.telefono") }}-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="nt-campo">
                                    <span class="nt-etiqueta">Priority</span>
                                    <label class="nt-lista-opcion"><input type="radio" name="nt-contacto-principal" @checked($f['principal'] ?? false) wire:click="marcarPrincipal({{ $i }})"> <span>Primary contact</span></label>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

