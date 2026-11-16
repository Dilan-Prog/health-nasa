{{-- Tarjeta de respuesta del orientador conversacional (simulado). Todo texto del usuario o del análisis se escapa con {{ }}. --}}
@php
    $r = $consulta->respuesta ?? [];
    $e = $consulta->entidades ?? [];
    $urg = $r['urgencia'] ?? $consulta->urgencia ?? 'observacion';
    $emerg = $urg === 'emergencia' || ! empty($r['escalar_ahora']);
    $sintomas = array_values($e['sintomas'] ?? []);
    $alcance = ($r['alcance'] ?? true) !== false;
    $parcial = $sintomas && (($e['entendido'] ?? true) === false);
    $sinEntender = (! $sintomas || ! $alcance) && ! $emerg;
    $sintomasCfg = (array) config('orientador.sintomas', []);
    $zonasCfg = (array) config('orientador.zonas', []);
    // etiqueta, icono: el color nunca es la única señal (siempre hay icono y texto)
    $U = [
        'emergencia' => ['EMERGENCY', 'error'],
        'urgente' => ['URGENT', 'warning'],
        'autocuidado' => ['SELF-CARE', 'self_improvement'],
        'observacion' => ['OBSERVATION', 'visibility'],
    ];
    $origen = fn ($o) => $o === 'documento' ? 'Document' : 'Simulated example';
    $conf = fn ($n) => number_format(max(0, min(100, (int) $n)) / 100, 2, '.', '');
    $hipotesis = $r['hipotesis'] ?? [];
    $acciones = $r['acciones'] ?? [];
    $botiquin = $emerg ? [] : ($r['botiquin_opciones'] ?? []);
    $seguimiento = $r['seguimiento'] ?? [];
    $escalar = $r['escalar_si'] ?? [];
    $usados = $r['datos_usados'] ?? [];
    $fuentes = $r['fuentes'] ?? [];
@endphp
<article id="mf-consulta" class="mf-msg mf-resp" data-urg="{{ $urg }}" data-estado-ui="{{ $emerg ? 'emergencia' : ($sinEntender ? 'sin-entender' : 'respuesta') }}" aria-labelledby="mf-resp-titulo" wire:key="mf-consulta-{{ $consulta->id }}">
    <div class="mf-msg-cab">
        <span class="icono mf-msg-ic" aria-hidden="true">{{ $U[$urg][1] ?? 'stethoscope' }}</span>
        <h3 id="mf-resp-titulo" class="mf-msg-t" tabindex="-1">Guidance on what you describe</h3>
        <button type="button" class="mf-cerrar mf-cerrar--sm" wire:click="cerrarConsulta" aria-label="Close the response"><span class="icono" aria-hidden="true">close</span></button>
    </div>

    @if ($emerg)
        @include('livewire.mf.urgencia')
    @endif

    @include('livewire.mf.entendi')

    @if ($sinEntender)
        <p class="mf-sinent" role="note">
            @if (! $alcance)
                I can only guide you on symptoms and your health indicators. Tell me how you feel or what discomfort you have.
            @else
                I could not interpret your message. Rephrase it in other words (for example "my chest hurts" or "I am dizzy"; Spanish input is understood), add a symptom or an area above, or use a pain area you already logged.
            @endif
        </p>
    @else
        @unless ($emerg)
            @include('livewire.mf.urgencia')
        @endunless

        @if ($hipotesis)
            <x-mf-seccion id="mf-s-hip" titulo="3 · HYPOTHESES TO RULE OUT" icono="rule" :abierta="true">
                <p class="mf-nota">Possibilities to rule out with the flight surgeon; they are not a diagnosis.</p>
                <ul class="mf-hip">
                    @foreach ($hipotesis as $h)
                        <li class="mf-hip-i">
                            <span class="mf-hip-t">{{ $h['titulo'] ?? '' }}</span>
                            <div class="mf-conf-f mono"><span>SIMULATED CONFIDENCE</span><strong>{{ $conf($h['confianza'] ?? 0) }}</strong></div>
                            <div class="mf-barra" aria-hidden="true"><div class="mf-barra-v" style="width: {{ max(0, min(100, (int) ($h['confianza'] ?? 0))) }}%"></div></div>
                            @if (! empty($h['por_que']))
                                <span class="mf-it-e mono">WHY</span>
                                <ul class="mf-lista-pl">@foreach ($h['por_que'] as $p)<li>{{ $p }}</li>@endforeach</ul>
                            @endif
                            <span class="mf-fuente-t mono">SOURCE · {{ $origen($h['origen'] ?? '') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-mf-seccion>
        @endif

        <x-mf-seccion id="mf-s-hacer" titulo="4 · WHAT TO DO NOW" icono="checklist" :abierta="true">
            <p class="mf-nota">{{ ! empty($r['panel_vigente']) ? 'What the Panel shows still applies. What is here is in addition to what you already see in the Panel.' : 'In addition to what you already see in the Panel.' }}</p>
            @if ($acciones)
                <ol class="mf-pasos">
                    @foreach ($acciones as $ac)
                        <li>
                            <span class="mf-n mono" aria-hidden="true">{{ $loop->iteration }}</span>
                            <span class="mf-paso">
                                <span>{{ $ac['texto'] ?? '' }}</span>
                                @if (! empty($ac['fuente']['etiqueta']))
                                    <span class="mf-fuente-t mono" title="{{ $ac['fuente']['texto'] ?? '' }}">SOURCE · {{ $origen($ac['fuente']['origen'] ?? '') }} · {{ $ac['fuente']['etiqueta'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mf-it-t">No new actions: confirm what you already see in the Panel and consult the flight surgeon if it does not improve.</p>
            @endif
        </x-mf-seccion>

        @if ($botiquin)
            <x-mf-seccion id="mf-s-botiquin" titulo="5 · FROM THE MEDICAL KIT" icono="medication" :abierta="false">
                <p class="mf-nota mf-nota--fija">Medical kit options to discuss with the flight surgeon. Contraindications not verified. No doses.</p>
                <ul class="mf-bot">
                    @foreach ($botiquin as $b)
                        <li class="mf-bot-i">
                            <span class="mf-it-t"><strong>{{ $b['nombre'] ?? '' }}</strong>@if (! empty($b['grupo'])) <span class="mf-it-e mono">· {{ $b['grupo'] }}</span>@endif</span>
                            @if (! empty($b['nota']))<span class="mf-it-n">{{ $b['nota'] }}</span>@endif
                            <span class="mf-it-n">{{ ! empty($b['contraindicaciones']) ? 'Contraindications: '.$b['contraindicaciones'] : 'Contraindications not verified.' }}</span>
                            @if (! empty($b['aviso_medicacion']))<span class="mf-it-n mf-it-n--aviso">{{ $b['aviso_medicacion'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </x-mf-seccion>
        @endif

        @if ($seguimiento || ! empty($r['eva_proxima']))
            <x-mf-seccion id="mf-s-seg" titulo="6 · FOLLOW-UP" icono="monitor_heart" :abierta="false">
                <ul class="mf-seg">
                    @foreach ($seguimiento as $s)
                        <li class="mf-seg-i">
                            <span class="mf-it-t">{{ $s['texto'] ?? '' }}</span>
                            @if (! empty($s['indicador']))
                                <a class="mf-btn mf-btn--txt" href="{{ route('registrar', ['indicador' => $s['indicador']]) }}"><span class="icono" aria-hidden="true">add_chart</span>Log a measurement</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if (! empty($r['eva_proxima']))<p class="mf-matiz">{{ $r['eva_proxima'] }}</p>@endif
            </x-mf-seccion>
        @endif
    @endif

    @if ($escalar)
        <x-mf-seccion id="mf-s-esc" titulo="7 · WHEN TO ESCALATE" icono="priority_high" :abierta="true">
            <ul class="mf-lista-pl">@foreach ($escalar as $x)<li>{{ $x }}</li>@endforeach</ul>
        </x-mf-seccion>
    @endif

    @if ($usados)
        <x-mf-seccion id="mf-s-datos" titulo="8 · DATA I USED" icono="database" :abierta="false">
            <div class="mf-it-lista">
                @foreach ($usados as $d)
                    <div class="mf-it"><span class="mf-it-e mono">{{ $d['etiqueta'] ?? '' }}</span><span class="mf-it-t">{{ $d['detalle'] ?? '' }}</span></div>
                @endforeach
            </div>
        </x-mf-seccion>
    @endif

    @if ($fuentes)
        <x-mf-seccion id="mf-s-fuentes" titulo="9 · SOURCES" icono="menu_book" :abierta="false">
            <div class="mf-chips">
                @foreach ($fuentes as $f)
                    <x-fuente-chip :tipo="$f['origen'] ?? 'ejemplo_simulado'" :validado="($f['origen'] ?? '') === 'documento'" :label="$origen($f['origen'] ?? '').' · '.($f['etiqueta'] ?? '')" />
                @endforeach
            </div>
        </x-mf-seccion>
    @endif

    <div class="mf-botones mf-botones--resp">
        <div class="mf-util" role="group" aria-label="Did this guidance help?">
            <button type="button" class="mf-btn" wire:click="marcarUtil(true)" aria-pressed="{{ $consulta->util === true ? 'true' : 'false' }}"><span class="icono" aria-hidden="true">thumb_up</span>It helped</button>
            <button type="button" class="mf-btn" wire:click="marcarUtil(false)" aria-pressed="{{ $consulta->util === false ? 'true' : 'false' }}"><span class="icono" aria-hidden="true">thumb_down</span>It did not help</button>
        </div>
        @unless ($emerg)
            <button type="button" class="mf-btn mf-btn--aviso" wire:click="avisarConsulta" @disabled($avisoConsultaEnviado) data-enviado="{{ $avisoConsultaEnviado ? '1' : '0' }}">
                <span class="icono" aria-hidden="true">{{ $avisoConsultaEnviado ? 'check' : 'campaign' }}</span>{{ $avisoConsultaEnviado ? 'Alert sent to ground' : 'Alert ground' }}
            </button>
        @endunless
        <button type="button" class="mf-btn" wire:click="guardarEnRegistro" @disabled($registroGuardado)>
            <span class="icono" aria-hidden="true">{{ $registroGuardado ? 'check' : 'bookmark_add' }}</span>{{ $registroGuardado ? 'Saved to your log' : 'Save to my log' }}
        </button>
    </div>
    @unless ($sinEntender)
        <div class="mf-botones mf-botones--evol" role="group" aria-label="How are you doing?">
            <span class="mf-it-e mono">HOW ARE YOU DOING?</span>
            <button type="button" class="mf-btn" wire:click="reevaluar('igual')"><span class="icono" aria-hidden="true">drag_handle</span>Unchanged</button>
            <button type="button" class="mf-btn" wire:click="reevaluar('empeoro')"><span class="icono" aria-hidden="true">trending_up</span>Worsened</button>
        </div>
    @endunless
</article>
