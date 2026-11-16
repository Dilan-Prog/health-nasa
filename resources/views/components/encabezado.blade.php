@props(['tituloA' => '', 'tituloB' => ''])
@php
    $mision = \App\Support\TripulanteActual::mision();
    $dia = $total = null;
    if ($mision?->inicio_en) {
        $dia = max(1, (int) $mision->inicio_en->copy()->startOfDay()->diffInDays(now('UTC')->startOfDay(), false) + 1);
        $total = $mision->duracion_dias ?: null;
        if ($total) {
            $dia = min($dia, (int) $total);
        }
    }
    $orbita = config('panel.orbita_km', 408);
    $ids = $mision ? \App\Models\Astronauta::where('mision_id', $mision->id)->pluck('id') : collect();
    $ultima = $ids->isNotEmpty() ? \App\Models\Medicion::whereIn('astronauta_id', $ids)->max('medido_en') : null;
    $ultima = $ultima ? \Illuminate\Support\Carbon::parse($ultima, 'UTC')->utc()->format('H:i') : null;
@endphp
<header class="encabezado">
    <h1 class="encabezado-titulo">{{ $tituloA }} <span class="muted">—</span> {{ $tituloB }}</h1>
    <div class="encabezado-datos mono">
        <div><span class="dato-et">MISSION DAY</span><span class="dato-val">{{ $dia ?? '—' }} / {{ $total ?? '—' }}</span></div>
        <div><span class="dato-et">ORBIT</span><span class="dato-val">{{ $orbita ? $orbita.' km' : '—' }}</span></div>
        <div><span class="dato-et">LAST READING</span><span class="dato-val">{{ $ultima ? $ultima.' UTC' : '—' }}</span></div>
    </div>
</header>
