import test from 'node:test';
import assert from 'node:assert/strict';
import { crearModelo } from './modelo.js';

const DIAS = 30;
const serie = (ini, fin, dosis = false) => Array.from({ length: DIAS }, (_, i) => {
  const t = i / (DIAS - 1);
  return dosis ? +(fin * t).toFixed(2) : +(ini + (fin - ini) * t + Math.sin(i) * 0.5).toFixed(2);
});
const hora = v => Array.from({ length: 24 }, (_, i) => (i === 23 ? v : +(v + Math.sin(i / 4)).toFixed(2)));

function payload() {
  const d = {
    presion_arterial: serie(116, 128), perdida_osea: serie(-0.6, -1.6), dosis_acumulada: serie(0, 76.7, true),
  };
  d.presion_arterial[DIAS - 1] = 128; d.perdida_osea[DIAS - 1] = -1.6; d.dosis_acumulada[DIAS - 1] = 76.7;
  return {
    hoy: '2026-11-15', dias: DIAS,
    sistemas: [['Cardiovascular', 'cardiology'], ['Metabolism', 'nutrition'], ['Musculoskeletal', 'skeleton'], ['Immune & radiation', 'shield'], ['Mental health', 'psychology']],
    marcadores: [
      { id: 'presion_arterial', label: 'Blood pressure', ic: 'monitor_heart', unit: 'mmHg', sys: 0, val: 128, disp: '128/84', lo: 90, hi: 120, base: 116, dec: 0, rec: 'Rest.', estado: 'warn', loss: false, cum: false, signo: false },
      { id: 'perdida_osea', label: 'Bone loss (hip)', ic: 'skeleton', unit: '%/month', sys: 2, val: -1.6, disp: '−1.6', lo: -1, hi: 0, base: -0.6, dec: 1, rec: 'ARED session.', estado: 'crit', loss: true, cum: false, signo: false },
      { id: 'dosis_acumulada', label: 'Cumulative dose', ic: 'data_usage', unit: 'mSv', sys: 3, val: 76.7, disp: '76.7', lo: 0, hi: 100, base: 0, dec: 1, rec: 'Review EVAs.', estado: 'warn', loss: false, cum: true, signo: false },
    ],
    diaria: d,
    horaria: { presion_arterial: hora(128), perdida_osea: hora(-1.6), dosis_acumulada: hora(76.7) },
  };
}

const URL = s => new URLSearchParams(s);
function harness(query = '', ancho = 1280, extra = {}) {
  const modelo = crearModelo(payload());
  let state = { ...modelo.estadoInicial(URL(query), ancho), ...extra };
  const llamadas = [];
  const acciones = {
    set: patch => { llamadas.push(['set', patch]); state = { ...state, ...patch }; },
    fade: patch => { llamadas.push(['fade', patch]); state = { ...state, ...patch, hover: null }; },
    setView: v => { llamadas.push(['setView', v]); state = { ...state, view: v, hover: null }; },
  };
  const vals = () => modelo.build(state, acciones, { w: ancho, reduce: false });
  return { modelo, get state() { return state; }, acciones, llamadas, vals };
}

// Minimal check that an SVG fragment is well formed (balanced tags, quoted attributes).
function bienFormado(s) {
  const pila = [];
  const re = /<(\/?)([a-z]+)((?:\s+[a-z0-9-]+="[^"<]*")*)\s*(\/?)>/g;
  let resto = s.replace(re, (_, cierre, tag, _a, auto) => {
    if (cierre) { assert.equal(pila.pop(), tag, 'unbalanced tag ' + tag); } else if (!auto) pila.push(tag);
    return '';
  });
  assert.equal(pila.length, 0, 'unclosed tags: ' + pila);
  assert.equal(resto.trim(), '', 'invalid remainder: ' + resto.slice(0, 80));
}

test('default initial state', () => {
  const h = harness();
  const s = h.state;
  assert.equal(s.id, 'perdida_osea');
  assert.equal(s.mode, 'ind');
  assert.equal(s.view, 'lines');
  assert.equal(s.pre, '10');
  assert.equal(s.to, '2026-11-15');
  assert.equal(s.from, '2026-10-17'); // D1 = 2026-10-17 (30 days ending Nov 15)
  assert.deepEqual(s.cmp, ['presion_arterial', 'perdida_osea']); // the default ids that exist
  assert.equal(s.norm, true);
  assert.equal(s.estado, 'ok');
  assert.equal(s.w, 1280);
});

test('initial state reads parameters and validates inputs', () => {
  const m = crearModelo(payload());
  const s = m.estadoInicial(URL('id=dosis_acumulada&mode=cmp&m=presion_arterial,zzz,dosis_acumulada&view=heat&pre=7&norm=0&drawer=1&estado=vacio'), 600);
  assert.equal(s.id, 'dosis_acumulada'); assert.equal(s.mode, 'cmp');
  assert.deepEqual(s.cmp, ['presion_arterial', 'dosis_acumulada']);
  assert.equal(s.view, 'heat'); assert.equal(s.pre, '7'); assert.equal(s.norm, false); assert.equal(s.drawer, true); assert.equal(s.estado, 'vacio');
  const mal = m.estadoInicial(URL('id=nope&mode=x&view=y&pre=z&estado=q&m=a,b'), 1000);
  assert.equal(mal.id, 'perdida_osea'); assert.equal(mal.mode, 'ind'); assert.equal(mal.view, 'lines'); assert.equal(mal.pre, '10'); assert.equal(mal.estado, 'ok');
  assert.deepEqual(mal.cmp, ['presion_arterial', 'perdida_osea']);
  assert.doesNotThrow(() => m.estadoInicial(null, undefined));
});

test('falls back to the first marker when perdida_osea is missing', () => {
  const p = payload(); p.marcadores = p.marcadores.filter(x => x.id !== 'perdida_osea');
  assert.equal(crearModelo(p).estadoInicial(URL(''), 1000).id, 'presion_arterial');
});

test('ranges and presets', () => {
  const h = harness();
  assert.equal(h.vals().ff.text, 'D21 – D30 · 10 days');
  h.acciones.fade({ pre: '7' }); assert.equal(h.vals().ff.text, 'D24 – D30 · 7 days');
  h.acciones.fade({ pre: 'all' }); assert.equal(h.vals().ff.text, 'D1 – D30 · 30 days');
  h.acciones.fade({ pre: '24h', group: 'hora' }); assert.equal(h.vals().ff.text, 'D30 · last 24 h');
  h.acciones.fade({ pre: 'custom', group: 'dia', from: '2026-11-01', to: '2026-11-10' });
  let v = h.vals();
  assert.equal(v.ff.invalid, 'false'); assert.equal(v.ff.text, 'D16 – D25 · 10 days');
  h.acciones.fade({ from: '2026-11-10', to: '2026-11-01' });
  v = h.vals(); assert.equal(v.ff.invalid, 'true'); assert.match(v.ff.text, /Invalid range/);
  assert.ok(v.ch.showSvg); // falls back to the default range, does not throw
  h.acciones.fade({ from: '2020-01-01', to: '2026-11-10' });
  assert.equal(h.vals().ff.invalid, 'true');
  assert.equal(v.ff.presets.length, 6);
  assert.equal(v.ff.min, '2026-10-17'); assert.equal(v.ff.max, '2026-11-15');
});

test('preset handler sets hourly grouping and restores it', () => {
  const h = harness();
  const v = h.vals();
  v.ff.presets.find(p => p.label === '24 h').onClick();
  assert.deepEqual(h.llamadas.at(-1), ['fade', { pre: '24h', group: 'hora' }]);
  assert.equal(h.state.group, 'hora');
  h.vals().ff.presets.find(p => p.label === '7 d').onClick();
  assert.equal(h.state.group, 'dia');
});

test('weekly grouping', () => {
  const h = harness('pre=all&view=bars');
  h.acciones.fade({ group: 'semana' });
  const v = h.vals();
  assert.equal(v.tbl.rows.length, Math.ceil(30 / 7)); // 5 weeks
  assert.equal(v.tbl.rows.at(-1).date, 'D1 · 17 Oct');
  // the first week average matches the table
  const d = payload().diaria.perdida_osea.slice(0, 7);
  const media = d.reduce((a, x) => a + x, 0) / 7;
  assert.equal(v.tbl.rows.at(-1).cells[0].v, '−' + Math.abs(media).toFixed(1));
  assert.equal(v.ff.groups.find(g => g.label === 'Week').disabled, false);
  // with 7 days the Week option is disabled
  h.acciones.fade({ pre: '7' });
  assert.equal(h.vals().ff.groups.find(g => g.label === 'Week').disabled, true);
  assert.equal(h.vals().ff.groups.find(g => g.label === 'Hour').disabled, true);
});

test('forecast: length, shape and uncertainty', () => {
  const h = harness('view=forecast&pre=10');
  let v = h.vals();
  assert.equal(v.ff.showHorizon, true);
  assert.equal(v.ch.forecastNote, true);
  assert.equal(v.ch.polys.length, 1);
  const lineas = v.ch.lines;
  const proy = lineas.find(l => l.dash === '6 5');
  assert.ok(proy);
  assert.equal(proy.pts.split(' ').length, 8); // h + 1 points (the first is the last data point)
  const base = lineas.find(l => l.dash === '0');
  assert.equal(base.pts.split(' ').length, 10);
  // the uncertainty area closes: (h+1)*2 vertices
  assert.equal(v.ch.polys[0].d.split(/ L|M/).filter(Boolean).length, 16);
  assert.match(v.ch.polys[0].d, /Z$/);
  // the projection starts at the last point of the series
  assert.equal(proy.pts.split(' ')[0], base.pts.split(' ').at(-1));
  // X grows to the right edge (720)
  assert.equal(proy.pts.split(' ').at(-1).split(',')[0], '720');
  h.acciones.fade({ horizon: 14 });
  v = h.vals();
  assert.equal(v.ch.lines.find(l => l.dash === '6 5').pts.split(' ').length, 15);
  assert.ok(v.ch.keys.some(k => k.t === 'Projection 14 days'));
  assert.equal(v.ch.xLabels.at(-1).t, 'D44');
  // tooltip over the forecast
  h.acciones.set({ hover: 100 });
  v = h.vals();
  assert.match(v.tip.date, /^Estimate · D44/);
  assert.equal(v.tip.rows[0].st, 'ESTIMATE');
});

test('forecast with a constant series uses a minimal residual', () => {
  const p = payload(); p.diaria.presion_arterial = Array(DIAS).fill(120);
  const m = crearModelo(p);
  const st = { ...m.estadoInicial(URL('id=presion_arterial&view=forecast'), 1280) };
  const v = m.build(st, {}, { w: 1280, reduce: true });
  assert.equal(v.ch.polys.length, 1);
  assert.equal(v.ch.trans, 'none');
});

test('normalization and compare mode', () => {
  const h = harness('mode=cmp&m=presion_arterial,perdida_osea,dosis_acumulada&view=lines');
  let v = h.vals();
  assert.equal(v.pm.count, '3 selected');
  assert.equal(v.ch.title, 'Comparison · 3 indicators');
  assert.equal(v.ch.hasLegend, true);
  assert.equal(v.ch.legend.length, 3);
  assert.equal(v.ch.showSvg, true); assert.equal(v.ch.showLanes, false);
  assert.ok(v.ch.keys.some(k => k.t.startsWith('100 %')));
  assert.ok(v.ch.yLabels.every(l => l.t.endsWith('%')));
  assert.equal(v.ch.lines.length, 3);
  assert.equal(v.ch.dots.length, 3);
  // without normalization: one lane per unit
  h.acciones.fade({ norm: false });
  v = h.vals();
  assert.equal(v.ch.showSvg, false); assert.equal(v.ch.showLanes, true);
  assert.equal(v.ch.lanes.length, 3);
  v.ch.lanes.forEach(l => { assert.ok(l.svg.includes('<polyline')); bienFormado(l.svg); });
  // hide a series from the legend
  v.ch.legend[0].onClick();
  assert.deepEqual(h.state.hidden, ['presion_arterial']);
  assert.equal(h.vals().ch.lanes.length, 2);
  // toggle selection from the panel
  const grupo = v.pm.groups.find(g => g.name === 'Cardiovascular');
  grupo.items[0].onToggle();
  assert.ok(!h.state.cmp.includes('presion_arterial'));
  assert.equal(grupo.showAll, true);
  h.vals().pm.groups.find(g => g.name === 'Cardiovascular').onAll();
  assert.ok(h.state.cmp.includes('presion_arterial'));
});

test('single mode: marker panel, filters and selection', () => {
  const h = harness();
  let v = h.vals();
  assert.equal(v.narrow, false); assert.equal(v.showSidePanel, true); assert.equal(v.drawerOpen, false);
  assert.equal(v.pm.groups.length, 3);
  assert.equal(v.pm.groups.flatMap(g => g.items).find(i => i.checked === 'true').label, 'Bone loss (hip)');
  v.pm.filters.find(f => f.label === 'Critical only').onClick();
  assert.equal(h.state.filter, 'crit');
  v = h.vals();
  assert.deepEqual(v.pm.groups.flatMap(g => g.items.map(i => i.id)), ['perdida_osea']);
  h.acciones.set({ filter: 'all', query: 'zzz' });
  assert.equal(h.vals().pm.none, true);
  h.acciones.set({ query: 'blood' });
  v = h.vals(); assert.equal(v.pm.groups[0].items.length, 1);
  v.pm.groups[0].items[0].onToggle();
  assert.equal(h.state.id, 'presion_arterial'); assert.equal(h.state.mode, 'ind'); assert.equal(h.state.drawer, false);
  v.pm.onQuery({ target: { value: 'x' } });
  assert.equal(h.state.query, 'x');
  v.sel.views.find(x => x.key === '3').onClick();
  assert.deepEqual(h.llamadas.at(-1), ['setView', 'forecast']);
});

test('narrow viewport: side drawer', () => {
  const h = harness('drawer=1', 600);
  let v = h.vals();
  assert.equal(v.narrow, true); assert.equal(v.drawerOpen, true); assert.equal(v.showSidePanel, true);
  assert.equal(v.pm.role, 'dialog'); assert.equal(v.ch.h, 220);
  v.closeDrawer(); assert.equal(h.state.drawer, false);
  v = h.vals(); assert.equal(v.showSidePanel, false);
  v.openDrawer(); assert.equal(h.state.drawer, true);
});

test('heat map: cells (weekly calendar, hourly and compare)', () => {
  // calendar: D21..D30 (Oct 21...) with gaps at the start of the week
  const h = harness('view=heat&pre=10');
  let v = h.vals();
  assert.equal(v.heat.cols, 7);
  assert.deepEqual(v.heat.colLabels, ['M', 'T', 'W', 'T', 'F', 'S', 'S']);
  const celdas = v.heat.cells.filter(c => c.role === 'gridcell');
  assert.equal(celdas.length % 7, 0);
  assert.equal(celdas.filter(c => c.t !== '').length, 10);
  assert.equal(celdas.filter(c => c.title === 'No data').length, celdas.length - 10);
  assert.ok(v.heat.cells.filter(c => c.role === 'rowheader').every(c => /^Wk \d+$/.test(c.t)));
  assert.equal(v.heat.legend.length, 3);
  assert.equal(v.ch.showHeat, true); assert.equal(v.ch.showSvg, false);
  assert.equal(celdas.filter(c => c.t !== '').at(-1).t, '−1.6');
  assert.match(celdas.filter(c => c.t !== '').at(-1).title, /^D30 · 15 Nov: −1\.6 %\/month · CRITICAL$/);
  // hourly: 24 cells
  h.acciones.fade({ pre: '24h', group: 'hora' });
  v = h.vals();
  assert.equal(v.heat.cols, 24);
  assert.equal(v.heat.cells.filter(c => c.role === 'gridcell').length, 24);
  assert.equal(v.heat.cells[0].t, 'Today');
  // compare: one row per marker
  const c = harness('mode=cmp&view=heat&pre=7&m=presion_arterial,perdida_osea');
  const vc = c.vals();
  assert.equal(vc.heat.cols, 7);
  assert.equal(vc.heat.cells.filter(x => x.role === 'rowheader').length, 2);
  assert.equal(vc.heat.cells.filter(x => x.role === 'gridcell').length, 14);
});

test('alternative table', () => {
  const h = harness('pre=7', 1280, { table: true });
  const v = h.vals();
  assert.equal(v.ch.showTable, true); assert.equal(v.ch.showSvg, false);
  assert.deepEqual(v.tbl.heads, ['Bone loss (hip) (%/month)']);
  assert.equal(v.tbl.rows.length, 7);
  assert.equal(v.tbl.rows[0].date, 'D30 · 15 Nov'); // most recent first
  assert.equal(v.tbl.rows[0].cells[0].v, '−1.6');
  assert.equal(v.tbl.rows[0].cells[0].st, 'CRITICAL'); // the current value takes the server status
  assert.equal(v.ch.tableLabel, 'View chart');
  v.ch.toggleTable(); assert.equal(h.state.table, false);
});

test('tooltip on hover', () => {
  const h = harness('view=lines&pre=10');
  h.acciones.set({ hover: 9 });
  let v = h.vals();
  assert.equal(v.tip.show, true);
  assert.equal(v.tip.date, 'D30 · 15 Nov');
  assert.equal(v.tip.rows.length, 1);
  assert.equal(v.tip.rows[0].v, '−1.6'); assert.equal(v.tip.rows[0].st, 'CRITICAL'); assert.equal(v.tip.rows[0].unit, '%/month');
  assert.ok(['trending_up', 'trending_down', 'trending_flat'].includes(v.tip.rows[0].trIc));
  assert.ok(v.ch.hoverX > 700);
  assert.match(v.ch.svg, /<line x1="720" x2="720" y1="0" y2="280"/);
  // out-of-range values are clamped
  h.acciones.set({ hover: 99 }); assert.equal(h.vals().tip.date, 'D30 · 15 Nov');
  h.acciones.set({ hover: null }); v = h.vals(); assert.equal(v.tip.show, false); assert.equal(v.ch.hoverX, -10);
  // previous period
  h.acciones.fade({ compare: true }); h.acciones.set({ hover: 0 });
  v = h.vals(); assert.equal(v.tip.rows.at(-1).label, 'Previous period');
  // mouse and keyboard handlers
  const el = { getBoundingClientRect: () => ({ left: 0, width: 720 }) };
  v.ch.onMove({ clientX: 360, currentTarget: el }); assert.equal(h.state.hover, 5);
  h.vals().ch.onKey({ key: 'ArrowLeft', preventDefault() {} }); assert.equal(h.state.hover, 4);
  h.vals().ch.onKey({ key: 'Escape' }); assert.equal(h.state.hover, null);
  v.ch.onTouch({ touches: [{ clientX: 720 }], currentTarget: el }); assert.equal(h.state.hover, 9);
  v.ch.onLeave(); assert.equal(h.state.hover, null);
});

test('main SVG, lanes and thumbnails: non-empty and well formed', () => {
  for (const vista of ['bars', 'lines', 'forecast']) {
    for (const modo of ['ind', 'cmp']) {
      const h = harness(`mode=${modo}&view=${vista}&pre=all&m=presion_arterial,perdida_osea,dosis_acumulada`);
      const v = h.vals();
      assert.ok(v.ch.svg.length > 100, `${modo}/${vista} empty svg`);
      bienFormado(v.ch.svg);
      if (vista === 'bars') assert.ok(v.ch.svg.includes('<rect') && v.ch.bars.length > 0);
      if (vista === 'forecast') assert.ok(v.ch.svg.includes('<path'));
    }
  }
  const m = harness('mode=mini').vals();
  assert.equal(m.minis.length, 3);
  assert.equal(m.ch.showMini, true); assert.equal(m.ch.showSvg, false); assert.equal(m.pm.count, 3);
  m.minis.forEach(x => { assert.ok(x.svg.includes('polyline')); bienFormado(x.svg); assert.equal(x.pts.split(' ').length, 30); });
  const ind = harness().vals().ch.svg;
  assert.ok(ind.includes('stroke-dasharray="5 4"')); // personal baseline line
  assert.ok(ind.includes('vector-effect="non-scaling-stroke"'));
  // escaping
  const p = payload(); p.marcadores[0].label = 'A "<b>" & C';
  const mod = crearModelo(p);
  const vv = mod.build(mod.estadoInicial(URL('id=presion_arterial'), 1280), {}, { w: 1280 });
  assert.equal(vv.ch.title, 'A "<b>" & C · mmHg'); // the text is raw (x-text escapes it); the SVG only carries numbers/colors
  bienFormado(vv.ch.svg);
});

test('statistics and summary', () => {
  const h = harness('id=presion_arterial&pre=10');
  const s = h.vals().sum;
  const d = payload().diaria.presion_arterial.slice(-10);
  assert.equal(s.show, true);
  assert.equal(s.label, 'BLOOD PRESSURE'); assert.equal(s.value, '128/84'); assert.equal(s.st, 'CAUTION');
  assert.equal(s.range, '90 – 120 mmHg');
  assert.equal(s.stats[0].v, Math.min(...d).toFixed(0));
  assert.equal(s.stats[1].v, Math.max(...d).toFixed(0));
  assert.equal(s.stats[2].v, (d.reduce((a, x) => a + x, 0) / 10).toFixed(0));
  assert.equal(s.tr, 'Rising');
  const dosis = harness('id=dosis_acumulada').vals().sum;
  assert.equal(dosis.range, 'mission limit 100 mSv');
  // the cumulative dose series has no baseline
  assert.ok(!harness('id=dosis_acumulada').vals().ch.refs.length);
  // loading/error/empty state
  const e = harness('estado=error'); assert.equal(e.vals().ch.error, true); assert.equal(e.vals().sum.show, false);
  assert.equal(harness('estado=carga').vals().ch.loading, true);
  assert.equal(harness('estado=vacio').vals().ch.empty, true);
  const c = harness('estado=carga'); c.vals().ch.retry();
  assert.equal(c.state.estado, 'carga');
});

test('log URL and handlers', () => {
  const h = harness('id=glucosa'); // unknown id: falls back to perdida_osea
  let v = h.vals();
  assert.equal(v.sum.regUrl, '/registrar?indicador=perdida_osea');
  h.acciones.fade({ id: 'dosis_acumulada' });
  assert.equal(h.vals().sum.regUrl, '/registrar?indicador=dosis_acumulada');
  const destinos = [];
  globalThis.window = { location: { assign: u => destinos.push(u) } };
  try {
    let prevented = 0;
    v = h.vals();
    v.sum.onReg({ preventDefault: () => prevented++ });
    assert.deepEqual(destinos, ['/registrar?indicador=dosis_acumulada']);
    assert.equal(prevented, 1);
    assert.doesNotThrow(() => v.sum.onGuide({ preventDefault() {} }));
    assert.deepEqual(destinos.length, 1);
  } finally { delete globalThis.window; }
  assert.doesNotThrow(() => v.sum.onReg());
});

test('empty or invalid payload does not throw', () => {
  for (const p of [undefined, null, {}, { dias: 0, marcadores: [] }, { dias: 5, hoy: 'x', marcadores: [{ id: 'a' }, null, { nada: 1 }] }]) {
    const m = crearModelo(p);
    const s = m.estadoInicial(URL('mode=cmp&view=heat'), 800);
    for (const vista of ['bars', 'lines', 'forecast', 'heat']) {
      for (const modo of ['ind', 'cmp', 'mini']) {
        for (const pre of ['24h', '10', 'all', 'custom']) {
          const v = m.build({ ...s, view: vista, mode: modo, pre, table: pre === 'all' }, {}, { w: 800, reduce: false });
          assert.equal(typeof v.ch.svg, 'string');
        }
      }
    }
  }
});

test('incomplete payload series are normalized', () => {
  const p = payload();
  p.diaria.presion_arterial = [120, 'x', 125]; // short and with junk
  delete p.horaria.presion_arterial;
  delete p.diaria.perdida_osea;
  const m = crearModelo(p);
  for (const id of ['presion_arterial', 'perdida_osea']) {
    for (const pre of ['10', '24h', 'all']) {
      const v = m.build({ ...m.estadoInicial(URL('id=' + id), 1280), pre, group: pre === '24h' ? 'hora' : 'dia' }, {}, { w: 1280 });
      assert.equal(typeof v.ch.svg, 'string');
      if (id === 'perdida_osea' && pre !== '24h') assert.equal(v.ch.showSvg, false); // no daily series: no chart
    }
  }
});
