<div class="al">
    <x-encabezado titulo-a="Audit" titulo-b="log" />

    <main class="al-main" id="auditoria-contenido">
        <section class="al-card" aria-label="Filters">
            <p class="al-h2">Only who accessed what is recorded; never medical values.</p>
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <label>Role
                    <select wire:model.live="rol"><option value="">All</option>@foreach ($roles as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Action
                    <select wire:model.live="accion"><option value="">All</option>@foreach ($acciones as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Resource
                    <select wire:model.live="recurso"><option value="">All</option>@foreach ($recursos as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select>
                </label>
                <label>Crew member
                    <select wire:model.live="tripulante"><option value="">All</option>@foreach ($tripulantes as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                </label>
                <label>From <input type="date" wire:model.live="desde"></label>
                <label>To <input type="date" wire:model.live="hasta"></label>
                <button type="button" wire:click="limpiar">Clear</button>
            </div>
        </section>

        <section class="al-card" aria-label="Access events">
            <table style="width:100%">
                <thead>
                    <tr><th scope="col">Date (UTC)</th><th scope="col">Role</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Resource</th><th scope="col">Crew member</th><th scope="col">IP</th></tr>
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
                        <tr><td colspan="7">No records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $registros->links() }}
        </section>
    </main>
</div>
