<div class="bq">
    <x-encabezado titulo-a="Botiquín" titulo-b="Inventario informativo" />

    <main class="bq-main" id="botiquin-contenido">
        <div class="bq-cabecera">
            <span class="mono bq-kicker">BOTIQUÍN</span>
            <h2 class="bq-titulo">Botiquín informativo</h2>
        </div>

        <p class="bq-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Inventario informativo. No es una prescripción; consulta al médico de vuelo</p>

        <div class="bq-inventario">
            @foreach ($grupos as $grupo => $items)
                <section class="bq-grupo" wire:key="bq-g-{{ $loop->index }}" aria-labelledby="bq-g-{{ $loop->index }}-t">
                    <h3 class="bq-grupo-t" id="bq-g-{{ $loop->index }}-t">{{ $grupo }}</h3>
                    <ul class="bq-lista">
                        @forelse ($items as $item)
                            @php([$ek, $et, $ei] = $item->estadoCaducidad())
                            <li class="bq-item" data-caducidad="{{ $ek }}" wire:key="bq-i-{{ $item->id }}">
                                <div class="bq-item-cab">
                                    <strong class="bq-nombre">{{ $item->nombre }}</strong>
                                    <span class="mono bq-pildora"><span class="icono" aria-hidden="true">{{ $ei }}</span>{{ $et }}</span>
                                    <span class="mono bq-ejemplo">{{ $item->valor_ejemplo ? 'valor de ejemplo' : 'valor editado' }}</span>
                                </div>

                                @if ($editando === $item->id)
                                    <form class="bq-form" wire:submit="guardar">
                                        <label class="bq-campo">
                                            <span>Stock ({{ $item->unidad }})</span>
                                            <input type="number" inputmode="numeric" min="0" step="1" wire:model="stock" aria-invalid="{{ $errors->has('stock') ? 'true' : 'false' }}">
                                            @error('stock') <span class="bq-error" role="alert">{{ $message }}</span> @enderror
                                        </label>
                                        <label class="bq-campo">
                                            <span>Caducidad</span>
                                            <input type="date" wire:model="caducidad" aria-invalid="{{ $errors->has('caducidad') ? 'true' : 'false' }}">
                                            @error('caducidad') <span class="bq-error" role="alert">{{ $message }}</span> @enderror
                                        </label>
                                        <div class="bq-acciones">
                                            <button type="submit" class="bq-btn bq-btn-prim">Guardar</button>
                                            <button type="button" class="bq-btn" wire:click="cancelar">Cancelar</button>
                                        </div>
                                    </form>
                                @else
                                    <dl class="bq-datos">
                                        <div><dt>Stock</dt><dd class="mono">{{ $item->stock }} {{ $item->unidad }}</dd></div>
                                        <div><dt>Caducidad</dt><dd class="mono">{{ $item->caducidad ? $item->caducidad->format('d/m/Y') : 'sin fecha' }}</dd></div>
                                        <div><dt>Contraindicaciones</dt><dd>{{ $item->contraindicaciones }}</dd></div>
                                    </dl>
                                    @if ($item->nota)<p class="bq-nota">{{ $item->nota }}</p>@endif
                                    <button type="button" class="bq-btn" wire:click="editar({{ $item->id }})"><span class="icono" aria-hidden="true">edit</span>Editar stock y caducidad</button>
                                @endif
                            </li>
                        @empty
                            <li class="bq-vacio">Sin elementos registrados.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </main>
</div>
