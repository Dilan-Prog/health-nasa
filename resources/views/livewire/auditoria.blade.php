<div class="al">
    <x-encabezado titulo-a="Auditoría" titulo-b="Registro de accesos" />

    <main class="al-main" id="auditoria-contenido">
        <section class="al-card" aria-label="Filtros">
            <p class="al-h2">Solo se registra quién accedió a qué; nunca valores médicos.</p>
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <label>Rol
                    <select wire:model.live="rol"><option value="">Todos</option>@foreach ($roles as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Acción
                    <select wire:model.live="accion"><option value="">Todas</option>@foreach ($acciones as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Recurso
                    <select wire:model.live="recurso"><option value="">Todos</option>@foreach ($recursos as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Tripulante
                    <select wire:model.live="tripulante"><option value="">Todos</option>@foreach ($tripulantes as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                </label>
                <label>Desde <input type="date" wire:model.live="desde"></label>
                <label>Hasta <input type="date" wire:model.live="hasta"></label>
                <button type="button" wire:click="limpiar">Limpiar</button>
            </div>
        </section>

        <section class="al-card" aria-label="Accesos">
            <table style="width:100%">
                <thead>
                    <tr><th scope="col">Fecha (UTC)</th><th scope="col">Rol</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Recurso</th><th scope="col">Tripulante</th><th scope="col">IP</th></tr>
                </thead>
                <tbody>
                    @forelse ($registros as $r)
                        <tr>
                            <td class="mono">{{ $r->ocurrido_en?->copy()->utc()->format('Y-m-d H:i') }}</td>
                            <td>{{ $r->rol }}</td>
                            <td>{{ $r->user_id ?? '—' }}</td>
                            <td>{{ $r->accion }}</td>
                            <td>{{ $r->recurso }}{{ $r->recurso_id ? ' #'.$r->recurso_id : '' }}</td>
                            <td>{{ $r->astronauta_id ? ($nombres[$r->astronauta_id] ?? '#'.$r->astronauta_id) : '—' }}</td>
                            <td class="mono">{{ $r->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Sin registros con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $registros->links() }}
        </section>
    </main>
</div>
