<div class="bq">
    <x-encabezado titulo-a="Medical kit" titulo-b="Informational inventory" />

    <main class="bq-main" id="botiquin-contenido">
        <div class="bq-cabecera">
            <span class="mono bq-kicker">MEDICAL KIT</span>
            <h2 class="bq-titulo">Informational medical kit</h2>
        </div>

        <p class="bq-aviso" role="note"><span class="icono" aria-hidden="true">info</span>Informational inventory. This is not a prescription; consult the Flight Surgeon</p>

        <div class="bq-inventario">
            @foreach ($grupos as $grupo => $items)
                <section class="bq-grupo" wire:key="bq-g-{{ $loop->index }}" aria-labelledby="bq-g-{{ $loop->index }}-t">
                    <h3 class="bq-grupo-t" id="bq-g-{{ $loop->index }}-t">{{ $etqGrupo[$grupo] ?? $grupo }}</h3>
                    <ul class="bq-lista">
                        @forelse ($items as $item)
                            @php([$ek, $et, $ei] = $item->estadoCaducidad())
                            <li class="bq-item" data-caducidad="{{ $ek }}" wire:key="bq-i-{{ $item->id }}">
                                <div class="bq-item-cab">
                                    <strong class="bq-nombre">{{ $etqNombre[$item->nombre] ?? $item->nombre }}</strong>
                                    <span class="mono bq-pildora"><span class="icono" aria-hidden="true">{{ $ei }}</span>{{ $etqCad[$ek] ?? $et }}</span>
                                    <span class="mono bq-ejemplo">{{ $item->valor_ejemplo ? 'example value' : 'edited value' }}</span>
                                </div>

                                @if ($editando === $item->id)
                                    <form class="bq-form" wire:submit="guardar">
                                        <label class="bq-campo">
                                            <span>Stock ({{ $etqUnidad[$item->unidad] ?? $item->unidad }})</span>
                                            <input type="number" inputmode="numeric" min="0" step="1" wire:model="stock" aria-invalid="{{ $errors->has('stock') ? 'true' : 'false' }}">
                                            @error('stock') <span class="bq-error" role="alert">{{ $message }}</span> @enderror
                                        </label>
                                        <label class="bq-campo">
                                            <span>Expiry date</span>
                                            <input type="date" wire:model="caducidad" aria-invalid="{{ $errors->has('caducidad') ? 'true' : 'false' }}">
                                            @error('caducidad') <span class="bq-error" role="alert">{{ $message }}</span> @enderror
                                        </label>
                                        <div class="bq-acciones">
                                            <button type="submit" class="bq-btn bq-btn-prim">Save</button>
                                            <button type="button" class="bq-btn" wire:click="cancelar">Cancel</button>
                                        </div>
                                    </form>
                                @else
                                    <dl class="bq-datos">
                                        <div><dt>Stock</dt><dd class="mono">{{ $item->stock }} {{ $etqUnidad[$item->unidad] ?? $item->unidad }}</dd></div>
                                        <div><dt>Expiry date</dt><dd class="mono">{{ $item->caducidad ? $item->caducidad->format('d M Y') : 'no date' }}</dd></div>
                                        <div><dt>Contraindications</dt><dd>{{ \App\Models\BotiquinItem::etiquetaPendiente($item->contraindicaciones) }}</dd></div>
                                    </dl>
                                    @if ($item->nota)<p class="bq-nota">{{ $item->nota }}</p>@endif
                                    <button type="button" class="bq-btn" wire:click="editar({{ $item->id }})"><span class="icono" aria-hidden="true">edit</span>Edit stock and expiry date</button>
                                @endif
                            </li>
                        @empty
                            <li class="bq-vacio">No items recorded.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </main>
</div>
