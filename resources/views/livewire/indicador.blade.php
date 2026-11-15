@php
    $nombreTripulante = $astronauta?->nombre;
    $extra = ['consulta' => $consulta, 'urls' => $urls];
@endphp
<div class="ind">
    <x-encabezado titulo-a="Indicador" :titulo-b="$nombreTripulante ? 'Tripulante '.$nombreTripulante : 'Sin tripulante'" />

    @if (! $astronauta)
        <main class="ind-main" id="indicador-contenido">
            <div class="ind-aviso" role="status">
                <span class="icono" aria-hidden="true">groups</span>
                <strong>No hay un tripulante seleccionado</strong>
                <span>Elige un tripulante en el selector para ver sus indicadores.</span>
            </div>
        </main>
    @elseif ($error || ! $payload)
        <main class="ind-main" id="indicador-contenido">
            <div class="ind-aviso ind-aviso--error" role="alert">
                <span class="icono" aria-hidden="true">error</span>
                <strong>No se pudieron leer las mediciones</strong>
                <span>Ha fallado la lectura de los indicadores. Tus datos no se han perdido.</span>
                <button type="button" class="ind-btn" wire:click="reintentar"><span class="icono" aria-hidden="true">refresh</span>Reintentar</button>
            </div>
        </main>
    @else
        {{-- wire:key re-crea el bloque (y su Alpine) al cambiar de tripulante; wire:ignore evita que un re-render toque el DOM de Alpine. --}}
        <main class="ind-main" id="indicador-contenido"
              wire:key="indicador-{{ $astronauta->id }}" wire:ignore
              x-data="indicador(@js($payload), @js($extra))" :class="{ 'is-narrow': vals.narrow }">

            <template x-if="vals.narrow">
                <button type="button" class="ind-abrir" data-ind-abrir aria-haspopup="dialog" x-on:click="vals.openDrawer()">
                    <span class="icono" aria-hidden="true">tune</span>Marcadores · <span x-text="vals.pm.count"></span>
                </button>
            </template>

            <div class="ind-cuerpo">
                {{-- Panel de marcadores (cajón con rol dialog en móvil) --}}
                <template x-if="vals.showSidePanel">
                    <aside class="ind-panel" :class="{ 'ind-panel--cajon': vals.narrow }" data-component="PanelMarcadores"
                           aria-label="Marcadores" :role="vals.pm.role" :aria-modal="vals.pm.modal">
                        <div class="ind-panel-cab">
                            <h2 class="ind-h2">Marcadores</h2>
                            <template x-if="vals.narrow">
                                <button type="button" class="ind-cerrar" data-ind-cerrar aria-label="Cerrar marcadores" x-on:click="vals.closeDrawer()">
                                    <span class="icono" aria-hidden="true">close</span>
                                </button>
                            </template>
                        </div>

                        <div class="ind-modos" role="radiogroup" aria-label="Modo">
                            <template x-for="m in vals.pm.modes" :key="m.label">
                                <button type="button" role="radio" class="ind-seg ind-seg--modo" :aria-checked="m.checked" x-on:click="m.onClick()">
                                    <span class="icono" aria-hidden="true" x-text="m.ic"></span><span x-text="m.label"></span>
                                </button>
                            </template>
                        </div>

                        <label class="ind-buscar">
                            <span class="icono" aria-hidden="true">search</span>
                            <span class="ind-sr">Buscar marcador</span>
                            <input type="search" placeholder="Buscar marcador" autocomplete="off" :value="vals.pm.query" x-on:input="vals.pm.onQuery($event)">
                        </label>

                        <div class="ind-filtros" role="radiogroup" aria-label="Filtrar por estado">
                            <template x-for="f in vals.pm.filters" :key="f.label">
                                <button type="button" role="radio" class="ind-chip" :aria-checked="f.checked" x-on:click="f.onClick()">
                                    <span class="ind-punto" :style="{ background: f.dot }"></span><span x-text="f.label"></span>
                                </button>
                            </template>
                        </div>

                        <div class="ind-lista" data-component="ListaMarcadoresPorSistema">
                            <template x-for="g in vals.pm.groups" :key="g.name">
                                <div class="ind-grupo" role="group" :aria-label="g.name">
                                    <div class="ind-grupo-cab">
                                        <span class="icono ind-grupo-ic" aria-hidden="true" x-text="g.ic"></span>
                                        <span class="ind-grupo-t mono" x-text="g.name"></span>
                                        <template x-if="g.showAll">
                                            <button type="button" class="ind-enlace" x-on:click="g.onAll()" x-text="g.allLabel"></button>
                                        </template>
                                    </div>
                                    <template x-for="it in g.items" :key="it.label">
                                        <button type="button" class="ind-item" :role="it.role" :aria-checked="it.checked"
                                                :class="{ 'is-sel': it.checked === 'true' && it.ctl !== 'open_in_new' }" x-on:click="it.onToggle()">
                                            <span class="icono ind-item-ctl" aria-hidden="true" :class="{ 'is-on': it.checked === 'true' }" x-text="it.ctl"></span>
                                            <span class="ind-item-t">
                                                <span class="ind-item-n" x-text="it.label"></span>
                                                <span class="ind-item-v mono"><span x-text="it.value"></span> <span x-text="it.unit"></span></span>
                                            </span>
                                            <span class="ind-pildora mono" :style="{ background: it.soft, color: it.text }">
                                                <span class="icono" aria-hidden="true" x-text="it.stIc"></span><span x-text="it.st"></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="vals.pm.none">
                                <span class="ind-nada">Ningún marcador coincide con la búsqueda.</span>
                            </template>
                        </div>
                    </aside>
                </template>

                <div class="ind-col">
                    {{-- Filtro de fechas --}}
                    <section class="ind-tarjeta ind-fechas" data-component="FiltroFechas" aria-label="Filtro de fechas">
                        <div class="ind-fila">
                            <div class="ind-presets" role="radiogroup" aria-label="Periodo">
                                <template x-for="p in vals.ff.presets" :key="p.label">
                                    <button type="button" role="radio" class="ind-chip ind-chip--grande" :aria-checked="p.checked" x-on:click="p.onClick()" x-text="p.label"></button>
                                </template>
                            </div>
                            <span class="ind-rango mono" role="status" :class="{ 'is-mal': vals.ff.invalid === 'true' }">
                                <span class="icono" aria-hidden="true" x-text="vals.ff.textIc"></span><span x-text="vals.ff.text"></span>
                            </span>
                        </div>

                        <template x-if="vals.ff.isCustom">
                            <div class="ind-fila ind-fechas-custom">
                                <label for="ind-from" class="ind-campo">Desde
                                    <input id="ind-from" type="date" class="mono" :min="vals.ff.min" :max="vals.ff.max" :value="vals.ff.from"
                                           :aria-invalid="vals.ff.invalid" :class="{ 'is-mal': vals.ff.invalid === 'true' }" x-on:change="vals.ff.onFrom($event)">
                                </label>
                                <label for="ind-to" class="ind-campo">Hasta
                                    <input id="ind-to" type="date" class="mono" :min="vals.ff.min" :max="vals.ff.max" :value="vals.ff.to"
                                           :aria-invalid="vals.ff.invalid" :class="{ 'is-mal': vals.ff.invalid === 'true' }" x-on:change="vals.ff.onTo($event)">
                                </label>
                            </div>
                        </template>

                        <div class="ind-fila ind-fila--opciones">
                            <label class="ind-check">
                                <input type="checkbox" :checked="vals.ff.compare" :disabled="vals.ff.compareDisabled" x-on:change="vals.ff.onCompare($event)">
                                Comparar con el periodo anterior
                            </label>
                            <div class="ind-grupo-chips" role="radiogroup" aria-label="Agrupar por">
                                <span>Agrupar por</span>
                                <template x-for="g in vals.ff.groups" :key="g.label">
                                    <button type="button" role="radio" class="ind-chip" :aria-checked="g.checked" :disabled="g.disabled" x-on:click="g.onClick()" x-text="g.label"></button>
                                </template>
                            </div>
                            <template x-if="vals.ff.showHorizon">
                                <div class="ind-grupo-chips" role="radiogroup" aria-label="Horizonte del pronóstico">
                                    <span>Proyectar</span>
                                    <template x-for="g in vals.ff.horizons" :key="g.label">
                                        <button type="button" role="radio" class="ind-chip" :aria-checked="g.checked" x-on:click="g.onClick()" x-text="g.label"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </section>

                    {{-- Gráfico --}}
                    <section class="ind-tarjeta ind-grafico" aria-label="Gráfico">
                        <div class="ind-fila ind-grafico-cab">
                            <h2 class="ind-h2 ind-grafico-t" x-text="vals.ch.title"></h2>
                            <template x-if="vals.ch.showSelector">
                                <div class="ind-modos ind-modos--vista" data-component="SelectorVistaGrafico" role="radiogroup" aria-label="Tipo de gráfico (teclas 1 a 4)">
                                    <template x-for="v in vals.sel.views" :key="v.key">
                                        <button type="button" role="radio" class="ind-seg ind-seg--vista" :aria-checked="v.checked" :aria-label="v.aria" :title="v.aria"
                                                :aria-keyshortcuts="v.key" x-on:click="v.onClick()">
                                            <span class="icono" aria-hidden="true" x-text="v.ic"></span>
                                            <span class="ind-vista-txt" x-text="v.label"></span>
                                            <span class="ind-vista-txt ind-tecla mono" x-text="v.key"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <button type="button" class="ind-btn" :aria-pressed="vals.ch.tablePressed" :class="{ 'is-on': vals.ch.tablePressed === 'true' }" x-on:click="vals.ch.toggleTable()">
                                <span class="icono" aria-hidden="true" x-text="vals.ch.tableIc"></span><span x-text="vals.ch.tableLabel"></span>
                            </button>
                        </div>

                        <template x-if="vals.ch.hasLegend">
                            <div class="ind-fila ind-leyenda">
                                <template x-for="l in vals.ch.legend" :key="l.label">
                                    <button type="button" class="ind-ley" :aria-pressed="l.pressed" :class="{ 'is-off': l.pressed === 'false' }" x-on:click="l.onClick()">
                                        <span class="ind-ley-raya" :style="{ background: l.color }"></span><span x-text="l.label"></span>
                                        <span class="icono" aria-hidden="true" x-text="l.ic"></span>
                                    </button>
                                </template>
                                <label class="ind-check ind-norm">
                                    <input type="checkbox" role="switch" :checked="vals.ch.norm" x-on:change="vals.ch.onNorm($event)">
                                    Normalizar (0–100 % del rango)
                                </label>
                            </div>
                        </template>

                        <template x-if="vals.ch.forecastNote">
                            <span class="ind-estimacion mono"><span class="icono" aria-hidden="true">online_prediction</span>ESTIMACIÓN, NO DIAGNÓSTICO</span>
                        </template>

                        <template x-if="vals.ch.loading">
                            <div class="ind-carga mono" role="status" aria-live="polite">CARGANDO MEDICIONES…</div>
                        </template>
                        <template x-if="vals.ch.error">
                            <div class="ind-vacio ind-vacio--error" role="alert">
                                <span class="icono" aria-hidden="true">error</span>
                                <strong>No se pudieron leer las mediciones</strong>
                                <span>El almacenamiento local no respondió. Tus datos no se han perdido.</span>
                                <button type="button" class="ind-btn" x-on:click="vals.ch.retry()">Reintentar</button>
                            </div>
                        </template>
                        <template x-if="vals.ch.empty">
                            <div class="ind-vacio ind-vacio--punteado">
                                <span class="icono" aria-hidden="true">query_stats</span>
                                <strong>Sin mediciones en este periodo</strong>
                                <span>Prueba con un rango más amplio o registra una nueva lectura.</span>
                            </div>
                        </template>

                        {{-- Gráfico principal (barras, líneas, pronóstico) --}}
                        <template x-if="vals.ch.showSvg">
                            <div class="ind-xy">
                                <div class="ind-xy-graf" :style="{ opacity: vals.ch.op, transition: vals.ch.trans }">
                                    <div class="ind-ejey mono" :style="{ width: vals.ch.axisW, height: vals.ch.h + 'px' }">
                                        <template x-for="(y, i) in vals.ch.yLabels" :key="i">
                                            <span :style="{ top: y.top }" x-text="y.t"></span>
                                        </template>
                                    </div>
                                    <div class="ind-scroll">
                                        <div class="ind-caja" tabindex="0" role="group" :aria-label="vals.ch.aria" aria-keyshortcuts="ArrowLeft ArrowRight Escape"
                                             :style="{ minWidth: vals.ch.minW }"
                                             x-on:mousemove="vals.ch.onMove($event)" x-on:mouseleave="vals.ch.onLeave()"
                                             x-on:keydown="vals.ch.onKey($event)" x-on:touchstart.passive="vals.ch.onTouch($event)">
                                            <svg role="img" :aria-label="vals.ch.aria" :view-box.camel="'0 0 720 ' + vals.ch.h" preserveAspectRatio="none"
                                                 :style="{ height: vals.ch.h + 'px' }" x-html="vals.ch.svg"></svg>
                                            <template x-if="vals.tip.show">
                                                <div class="ind-tip" data-component="TooltipMedicion" role="status"
                                                     :style="{ left: vals.tip.left, transform: 'translateX(' + vals.tip.shift + ')' }">
                                                    <span class="ind-tip-f mono" x-text="vals.tip.date"></span>
                                                    <template x-for="(r, i) in vals.tip.rows" :key="i">
                                                        <div class="ind-tip-r">
                                                            <span class="ind-tip-n"><span class="ind-ley-raya ind-ley-raya--s" :style="{ background: r.color }"></span><span x-text="r.label"></span></span>
                                                            <span class="ind-tip-v">
                                                                <span class="ind-tip-num mono" :style="{ color: r.text }" x-text="r.v"></span>
                                                                <span class="ind-tip-u" x-text="r.unit"></span>
                                                                <span class="ind-tip-e mono" :style="{ color: r.text }"><span class="icono" aria-hidden="true" x-text="r.stIc"></span><span x-text="r.st"></span></span>
                                                                <span class="ind-tip-t"><span class="icono" aria-hidden="true" x-text="r.trIc"></span><span x-text="r.tr"></span></span>
                                                            </span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <span></span>
                                    <div class="ind-ejex mono">
                                        <template x-for="(x, i) in vals.ch.xLabels" :key="i">
                                            <span :style="{ left: x.left, transform: 'translateX(' + x.shift + ')' }" x-text="x.t"></span>
                                        </template>
                                    </div>
                                </div>
                                <template x-if="vals.ch.lossNote">
                                    <span class="ind-nota-eje"><span class="icono" aria-hidden="true">south</span>Eje de pérdida: más abajo = peor</span>
                                </template>
                                <div class="ind-claves">
                                    <template x-for="(k, i) in vals.ch.keys" :key="i">
                                        <span class="ind-clave"><span class="ind-clave-m" :style="{ height: k.h, background: k.bg, borderTop: k.bt }"></span><span x-text="k.t"></span></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Carriles por unidad (comparar sin normalizar) --}}
                        <template x-if="vals.ch.showLanes">
                            <div class="ind-carriles">
                                <span class="ind-carriles-n">Unidades distintas: cada marcador con su propia escala. Activa "Normalizar" para superponerlos.</span>
                                <template x-for="(l, i) in vals.ch.lanes" :key="i">
                                    <div class="ind-carril">
                                        <span class="ind-carril-t">
                                            <span class="ind-carril-n"><span class="ind-ley-raya ind-ley-raya--s" :style="{ background: l.color }"></span><span x-text="l.label"></span></span>
                                            <span class="ind-carril-v mono" :style="{ color: l.text }"><span x-text="l.value"></span> <span x-text="l.unit"></span></span>
                                        </span>
                                        <svg role="img" :aria-label="l.aria" viewBox="0 0 720 64" preserveAspectRatio="none" x-html="l.svg"></svg>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Mapa de calor --}}
                        <template x-if="vals.ch.showHeat">
                            <div class="ind-calor" data-component="GraficoCalor" :style="{ opacity: vals.ch.op, transition: vals.ch.trans }">
                                <div class="ind-calor-rej" role="grid" :aria-label="vals.ch.aria"
                                     :style="{ gridTemplateColumns: '64px repeat(' + vals.heat.cols + ',minmax(18px,1fr))', minWidth: vals.heat.minW }">
                                    <span></span>
                                    <template x-for="(c, i) in vals.heat.colLabels" :key="'c' + i">
                                        <span class="ind-calor-col mono" x-text="c"></span>
                                    </template>
                                    <template x-for="(c, i) in vals.heat.cells" :key="'k' + i">
                                        <span class="ind-celda mono" :role="c.role" :title="c.title" :aria-label="c.title"
                                              :style="{ background: c.bg, color: c.fg, justifyContent: c.align }" x-text="c.t"></span>
                                    </template>
                                </div>
                                <div class="ind-claves ind-claves--calor">
                                    <template x-for="k in vals.heat.legend" :key="k.t">
                                        <span class="ind-clave"><span class="ind-clave-c" :style="{ background: k.bg }"></span>
                                            <span class="icono" aria-hidden="true" :style="{ color: k.text }" x-text="k.ic"></span><span x-text="k.t"></span></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Tabla alternativa --}}
                        <template x-if="vals.ch.showTable">
                            <div class="ind-tabla-caja">
                                <table class="ind-tabla">
                                    <caption class="ind-sr" x-text="vals.ch.aria"></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="mono ind-tabla-f">FECHA</th>
                                            <template x-for="(h, i) in vals.tbl.heads" :key="i">
                                                <th scope="col" class="mono ind-tabla-n" x-text="h"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(r, i) in vals.tbl.rows" :key="i">
                                            <tr>
                                                <th scope="row" class="mono ind-tabla-d" x-text="r.date"></th>
                                                <template x-for="(c, j) in r.cells" :key="j">
                                                    <td class="mono ind-tabla-n" :style="{ color: c.text }"><span x-text="c.v"></span> <span class="ind-tabla-st" x-text="c.st"></span></td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        {{-- Cuadrícula de miniaturas --}}
                        <template x-if="vals.ch.showMini">
                            <div class="ind-minis" data-component="CuadriculaMiniaturas">
                                <template x-for="m in vals.minis" :key="m.label">
                                    <button type="button" class="ind-mini" :aria-label="m.aria" :style="{ boxShadow: 'inset 0 3px 0 ' + m.color }" x-on:click="m.onOpen()">
                                        <span class="ind-mini-cab">
                                            <span class="icono" aria-hidden="true" :style="{ color: m.color }" x-text="m.ic"></span>
                                            <span class="ind-mini-n" x-text="m.label"></span>
                                        </span>
                                        <span class="ind-mini-val">
                                            <span class="ind-mini-num mono" :style="{ color: m.text }"><span x-text="m.value"></span> <span class="ind-mini-u" x-text="m.unit"></span></span>
                                            <span class="ind-pildora mono" :style="{ background: m.soft, color: m.text }"><span class="icono" aria-hidden="true" x-text="m.stIc"></span><span x-text="m.st"></span></span>
                                        </span>
                                        <svg aria-hidden="true" viewBox="0 0 200 40" preserveAspectRatio="none" x-html="m.svg"></svg>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </section>

                    {{-- Resumen del indicador --}}
                    <template x-if="vals.sum.show">
                        <section class="ind-tarjeta ind-resumen" data-component="TarjetaResumenIndicador" aria-label="Resumen del indicador">
                            <div class="ind-res-val">
                                <span class="ind-res-et mono">VALOR ACTUAL · <span x-text="vals.sum.label"></span></span>
                                <span class="ind-res-num">
                                    <span class="ind-res-v mono" :style="{ color: vals.sum.text }" x-text="vals.sum.value"></span>
                                    <span class="ind-res-u" x-text="vals.sum.unit"></span>
                                </span>
                                <div class="ind-res-pills">
                                    <span class="ind-pildora ind-pildora--g mono" :style="{ background: vals.sum.soft, color: vals.sum.text }">
                                        <span class="icono" aria-hidden="true" x-text="vals.sum.stIc"></span><span x-text="vals.sum.st"></span>
                                    </span>
                                    <span class="ind-pildora ind-pildora--g ind-pildora--neutra mono">
                                        <span class="icono" aria-hidden="true" x-text="vals.sum.trIc"></span><span x-text="vals.sum.tr"></span>
                                    </span>
                                </div>
                                <span class="ind-res-ref mono">REFERENCIA <span x-text="vals.sum.range"></span></span>
                            </div>
                            <div class="ind-res-stats">
                                <template x-for="s in vals.sum.stats" :key="s.k">
                                    <div class="ind-stat">
                                        <span class="ind-stat-k mono" x-text="s.k"></span>
                                        <span class="ind-stat-v mono" x-text="s.v"></span>
                                        <span class="ind-stat-u" x-text="s.u"></span>
                                    </div>
                                </template>
                            </div>
                            <div class="ind-res-rec">
                                <div class="ind-rec" :style="{ borderLeftColor: vals.sum.color }" x-text="vals.sum.rec"></div>
                                <div class="ind-fila">
                                    <a class="ind-btn ind-btn--primario" :href="registrarHref()"><span class="icono" aria-hidden="true">add_circle</span>Registrar nueva lectura</a>
                                    <button type="button" class="ind-btn" disabled aria-disabled="true" title="Próximamente"><span class="icono" aria-hidden="true">menu_book</span>Ver en guía</button>
                                </div>
                            </div>
                        </section>
                    </template>
                </div>
            </div>

            <p class="ind-seguridad" role="note"><span class="icono" aria-hidden="true">info</span>Orienta, no diagnostica. Consulta al flight surgeon ante cualquier duda.</p>

            <template x-if="vals.drawerOpen">
                <div class="ind-velo" aria-hidden="true" x-on:click="vals.closeDrawer()"></div>
            </template>
        </main>
    @endif
</div>
