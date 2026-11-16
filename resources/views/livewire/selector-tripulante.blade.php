@php
    $iniciales = fn ($nombre) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $nombre)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    $tag = function (array $e) {
        return $e['medido']
            ? ['ic' => 'task_alt', 'txt' => 'MEASURED'.($e['hora'] ? ' '.$e['hora'] : ''), 'est' => 'ok']
            : ['ic' => 'schedule', 'txt' => 'PENDING', 'est' => 'warn'];
    };
@endphp
<div class="selector selector--{{ $variante }}" x-data x-on:keydown.escape.window="$wire.abierto && $wire.cerrar()" x-on:click.outside="$wire.abierto && $wire.cerrar()">
    @if ($actual)
        @if ($variante === 'compacta')
            <button type="button" class="selector-pildora" title="Switch crew member" wire:click="siguiente">
                <span class="avatar avatar--34">{{ $iniciales($actual->nombre) }}</span>
                <span class="mono selector-pos">{{ $pos }}/{{ $total }}</span>
                <span class="icono muted">unfold_more</span>
            </button>
        @else
            <button type="button" class="selector-boton" title="Switch crew member" aria-haspopup="listbox" aria-expanded="{{ $abierto ? 'true' : 'false' }}" wire:click="alternar">
                <span class="avatar avatar--36">{{ $iniciales($actual->nombre) }}</span>
                <span class="selector-textos">
                    <span class="selector-nombre">{{ $actual->nombre }}</span>
                    <span class="mono selector-sub">CREW MEMBER {{ $pos }}/{{ $total }}</span>
                </span>
                <span class="icono muted">unfold_more</span>
            </button>
            @if ($abierto)
                <div class="selector-menu" role="listbox" aria-label="Crew members">
                    @foreach ($tripulacion as $t)
                        @php($e = $estados[$t->id] ?? ['medido' => false, 'hora' => null, 'peor' => null])
                        @php($m = $tag($e))
                        <button type="button" role="option" aria-selected="{{ $t->id === $actual->id ? 'true' : 'false' }}" class="selector-opcion {{ $t->id === $actual->id ? 'is-sel' : '' }}" wire:click="elegir({{ $t->id }})" wire:key="opc-{{ $t->id }}">
                            <span class="avatar avatar--30">{{ $iniciales($t->nombre) }}</span>
                            <span class="selector-textos">
                                <span class="selector-nombre">{{ $t->nombre }}</span>
                                <span class="mono selector-med" data-estado="{{ $m['est'] }}"><span class="icono">{{ $m['ic'] }}</span>{{ $m['txt'] }}</span>
                            </span>
                            @if ($e['peor'])
                                <span class="selector-punto" data-estado="{{ $e['peor'] }}" title="{{ ['ok' => 'Normal', 'warn' => 'Caution', 'crit' => 'Critical'][$e['peor']] }}"></span>
                            @else
                                <span class="selector-punto selector-punto--sin" title="No data"></span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    @else
        <span class="mono selector-sub">NO CREW</span>
    @endif
</div>
