// Modelo de la pantalla "Indicador": lógica pura (sin DOM, sin dependencias).
// Port de la maqueta (Component.build/chartData/svgParts) alimentado por el payload del servidor.
// API: crearModelo(payload) -> { estadoInicial(params, ancho), build(state, acciones, ctx) }

const ST = {
  ok:   { c: 'var(--ok)', t: 'var(--ok-text)', soft: 'var(--ok-soft)', l: 'NORMAL', ic: 'check_circle' },
  warn: { c: 'var(--warn)',  t: 'var(--warn-text)', soft: 'var(--warn-soft)',  l: 'ATENCIÓN', ic: 'warning' },
  crit: { c: 'var(--crit)', t: 'var(--crit-text)',  soft: 'var(--crit-soft)', l: 'GRAVE', ic: 'error' },
};
const SERIES_COLORS = ['var(--serie-1)', 'var(--serie-2)', 'var(--serie-3)', 'var(--serie-4)', 'var(--serie-5)', 'var(--serie-6)'];
const MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const PRESETS = [['24h', '24 h'], ['7', '7 d'], ['10', '10 d'], ['30', '30 d'], ['all', 'Misión completa'], ['custom', 'Personalizado']];
const VIEWS = [['bars', 'Barras', 'bar_chart', '1'], ['lines', 'Líneas', 'show_chart', '2'], ['forecast', 'Pronóstico', 'online_prediction', '3'], ['heat', 'Mapa de calor', 'grid_on', '4']];
const MODOS = ['ind', 'cmp', 'mini'];
const ESTADOS = ['ok', 'carga', 'error', 'vacio'];
const CMP_DEFECTO = ['presion_arterial', 'glucosa', 'perdida_osea', 'sueno'];
const SISTEMAS_DEFECTO = [['Cardiovascular', 'cardiology'], ['Metabólico', 'nutrition'], ['Musculoesquelético', 'skeleton'], ['Inmune y radiación', 'shield'], ['Mental', 'psychology']];

const num = (x, d = 0) => (Number.isFinite(+x) && x !== null && x !== '' ? +x : d);
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const pad2 = n => String(n).padStart(2, '0');

// Marcador vacío para pantallas sin datos (evita excepciones).
const VACIO = Object.freeze({ id: '', label: '—', ic: 'help', unit: '', sys: 0, val: 0, disp: '—', lo: 0, hi: 1, base: 0, dec: 0, rec: '', estado: 'ok', loss: false, cum: false, signo: false });

function normalizarMarcador(raw) {
  if (!raw || typeof raw.id !== 'string' || !raw.id) return null;
  const lo = num(raw.lo), hi = num(raw.hi, lo + 1), dec = Math.max(0, Math.min(6, Math.round(num(raw.dec))));
  const val = num(raw.val);
  return {
    id: raw.id, label: String(raw.label ?? raw.id), ic: String(raw.ic ?? 'monitor_heart'), unit: String(raw.unit ?? ''),
    sys: Math.max(0, Math.round(num(raw.sys))), val, disp: raw.disp != null ? String(raw.disp) : val.toFixed(dec),
    lo, hi, base: num(raw.base, val), dec, rec: String(raw.rec ?? ''),
    estado: ST[raw.estado] ? raw.estado : 'ok', loss: !!raw.loss, cum: !!raw.cum, signo: !!raw.signo,
    sospechoso: raw.sospechoso ? String(raw.sospechoso) : '',
    desv: raw.desv && Number.isFinite(+raw.desv.pct) && Number.isFinite(+raw.desv.base) ? { base: +raw.desv.base, pct: +raw.desv.pct } : null,
  };
}

// Deja la serie con exactamente `len` valores finitos (recorta por delante o rellena con el primer valor).
function ajustarSerie(arr, len, fallback) {
  if (!Array.isArray(arr) || !arr.length || len < 1) return [];
  let prev = null;
  const limpio = arr.map(x => { if (Number.isFinite(+x) && x !== null && x !== '') prev = +x; return prev; });
  const first = limpio.find(x => x !== null);
  const base = first == null ? fallback : first;
  const fin = limpio.map(x => (x == null ? base : x));
  if (fin.length >= len) return fin.slice(fin.length - len);
  return [...Array(len - fin.length).fill(fin[0]), ...fin];
}

export function crearModelo(payload) {
  const p = payload && typeof payload === 'object' ? payload : {};
  const DAYS = Math.max(1, Math.round(num(p.dias, 1)));
  const MK = (Array.isArray(p.marcadores) ? p.marcadores : []).map(normalizarMarcador).filter(Boolean);
  const MKI = Object.fromEntries(MK.map(m => [m.id, m]));
  const SYS = Array.isArray(p.sistemas) && p.sistemas.length ? p.sistemas.map(s => [String(s?.[0] ?? ''), String(s?.[1] ?? '')]) : SISTEMAS_DEFECTO;

  // Fecha de D<dias> = payload.hoy (UTC).
  let hoyMs = Date.parse(String(p.hoy ?? '') + 'T00:00:00Z');
  if (!Number.isFinite(hoyMs)) { const n = new Date(); hoyMs = Date.UTC(n.getUTCFullYear(), n.getUTCMonth(), n.getUTCDate()); }
  const dayDate = n => new Date(hoyMs - (DAYS - n) * 864e5);
  const iso = n => dayDate(n).toISOString().slice(0, 10);
  const isoToDay = s => { const t = Date.parse(String(s) + 'T00:00:00Z'); return Number.isFinite(t) ? Math.round((t - hoyMs) / 864e5) + DAYS : NaN; };
  const fdate = n => { const d = dayDate(n); return d.getUTCDate() + ' ' + MES[d.getUTCMonth()]; };

  const DAILY = {}, HOURLY = {};
  MK.forEach(m => {
    DAILY[m.id] = ajustarSerie(p.diaria?.[m.id], DAYS, m.val);
    HOURLY[m.id] = ajustarSerie(p.horaria?.[m.id], 24, m.val);
  });
  const daily = m => DAILY[m.id] || [];
  const hourly = m => HOURLY[m.id] || [];

  const S0 = id => (MKI[id] ? MKI[id].estado : 'ok');
  const margin = m => m.loss ? Math.abs(m.lo) * 0.3 : Math.max((m.hi - m.lo) * 0.3, Math.abs(m.hi) * 0.15);
  const status = (m, v) => {
    if (v === m.val) return m.estado;
    if (m.cum) return v < 75 ? 'ok' : v < 100 ? 'warn' : 'crit';
    if (v >= m.lo && v <= m.hi) return 'ok';
    return (v < m.lo ? m.lo - v : v - m.hi) <= margin(m) ? 'warn' : 'crit';
  };
  const fmt = (m, v) => (v < 0 ? '−' : m.signo && v > 0 ? '+' : '') + Math.abs(v).toFixed(m.dec);
  const xLabel = (r, x) => r.hourly ? pad2(x) + ':00' : 'D' + x + ' · ' + fdate(x);
  const actual = st => MKI[st.id] || MK[0] || VACIO;

  // ---------- Estado inicial ----------
  function estadoInicial(params, ancho) {
    const g = k => { try { return params && typeof params.get === 'function' ? params.get(k) : null; } catch (e) { return null; } };
    const idParam = g('id');
    const id = MKI[idParam] ? idParam : MKI.perdida_osea ? 'perdida_osea' : (MK[0] ? MK[0].id : '');
    let cmp = (g('m') ? g('m').split(',') : CMP_DEFECTO).map(s => s.trim()).filter((x, i, a) => MKI[x] && a.indexOf(x) === i);
    if (!cmp.length) cmp = CMP_DEFECTO.filter(x => MKI[x]);
    if (!cmp.length) cmp = MK.slice(0, 4).map(m => m.id);
    const view = g('view');
    const pre = g('pre');
    const mode = g('mode'), estado = g('estado');
    return {
      w: num(ancho, 1280), mode: MODOS.includes(mode) ? mode : 'ind', id, cmp, hidden: [],
      view: VIEWS.some(v => v[0] === view) ? view : 'lines', pre: PRESETS.some(x => x[0] === pre) ? pre : '10',
      from: iso(Math.max(1, DAYS - 29)), to: iso(DAYS), group: 'dia', compare: false, horizon: 7,
      norm: g('norm') !== '0', query: '', filter: 'all', table: false, hover: null, drawer: g('drawer') === '1',
      estado: ESTADOS.includes(estado) ? estado : 'ok', op: 1,
    };
  }

  // ---------- Rango y series ----------
  function range(st) {
    const { pre, from, to } = st;
    if (pre === '24h') return { hourly: true, a: DAYS, b: DAYS, bad: false, text: `D${DAYS} · últimas 24 h` };
    let a, b, bad = false;
    if (pre === 'all') [a, b] = [1, DAYS];
    else if (pre === 'custom') {
      a = isoToDay(from); b = isoToDay(to);
      if (!(a >= 1 && b <= DAYS && a < b)) { bad = true; [a, b] = [Math.max(1, DAYS - 9), DAYS]; }
    } else {
      const k = Math.round(+pre);
      [a, b] = [Math.max(1, DAYS - (Number.isFinite(k) && k >= 1 ? k : 10) + 1), DAYS];
    }
    return { a, b, bad, text: bad ? 'Rango no válido: la fecha inicial debe ser anterior a la final' : `D${a} – D${b} · ${b - a + 1} días` };
  }

  function seriesFor(st, m, r, offset = 0) {
    if (r.hourly) { if (offset) return null; const v = hourly(m); return v.length ? { xs: v.map((_, i) => i), v } : null; }
    const len = r.b - r.a + 1, a = r.a - offset * len, b = r.b - offset * len;
    if (a < 1) return null;
    let v = daily(m).slice(a - 1, b);
    if (!v.length) return null;
    let xs = v.map((_, i) => a + i);
    if (st.group === 'semana' && len >= 14) {
      const gx = [], gv = [];
      for (let i = 0; i < v.length; i += 7) { const c = v.slice(i, i + 7); gx.push(xs[i]); gv.push(c.reduce((s, x) => s + x, 0) / c.length); }
      xs = gx; v = gv;
    }
    return { xs, v };
  }

  function chartData(st, r, H) {
    const cmp = st.mode === 'cmp', view = st.view;
    const m1 = actual(st);
    const ids = cmp ? st.cmp.filter(id => MKI[id] && !st.hidden.includes(id)) : (m1.id ? [m1.id] : []);
    const norm = cmp && st.norm;
    const nv = (m, v) => norm ? (m.cum ? v : (v - m.lo) / (m.hi - m.lo) * 100) : v;
    const ser = ids.map(id => {
      const m = MKI[id], s = seriesFor(st, m, r);
      if (!s) return null;
      return { m, color: cmp ? SERIES_COLORS[st.cmp.indexOf(id) % SERIES_COLORS.length] : SERIES_COLORS[0], xs: s.xs, raw: s.v, v: s.v.map(x => nv(m, x)) };
    }).filter(Boolean);
    if (!ser.length) return { ser, n: 0 };
    const n = ser[0].v.length, xs = ser[0].xs;
    const h = view === 'forecast' ? st.horizon : 0, N = n + h;
    const prev = !cmp && st.compare ? seriesFor(st, ser[0].m, r, 1) : null;
    const fc = h ? ser.map(s => {
      const L = Math.min(14, n), ys = s.v.slice(-L), mx = (L - 1) / 2, my = ys.reduce((a, x) => a + x, 0) / L;
      let numr = 0, den = 0; ys.forEach((y, i) => { numr += (i - mx) * (y - my); den += (i - mx) ** 2; });
      const slope = den ? numr / den : 0, res = Math.sqrt(ys.reduce((a, y, i) => a + (y - (my + slope * (i - mx))) ** 2, 0) / L) || Math.abs(my) * 0.02 || 0.1;
      const last = s.v[n - 1];
      return Array.from({ length: h + 1 }, (_, j) => ({ y: last + slope * j, e: j === 0 ? 0 : res * 1.96 * (1 + j / h) }));
    }) : [];
    const m0 = ser[0].m, mg = norm ? 0 : margin(m0);
    const lo = norm ? 0 : m0.cum ? 0 : m0.lo, hi = norm ? 100 : m0.hi;
    const all = [...ser.flatMap(s => s.v), ...(prev ? prev.v : []), ...fc.flatMap(f => f.flatMap(q => [q.y - q.e, q.y + q.e])), lo - (cmp ? 0 : mg), hi + (cmp || m0.loss ? 0 : mg)];
    if (view === 'bars' && !norm) all.push(m0.loss ? 0 : Math.min(...all));
    let yMin = Math.min(...all), yMax = Math.max(...all); const pad = (yMax - yMin || 1) * 0.06; yMin -= pad; yMax += pad;
    const Y = v => +(H - 8 - (v - yMin) / (yMax - yMin) * (H - 16)).toFixed(1);
    const bars = view === 'bars';
    const X = j => +(bars ? (j + 0.5) * 720 / N : N > 1 ? j * 720 / (N - 1) : 360).toFixed(1);
    return { ser, n, N, h, xs, prev, fc, norm, cmp, lo, hi, mg, yMin, yMax, Y, X, m0 };
  }

  function svgParts(st, cd, r, H) {
    const view = st.view, { ser, n, N, h, xs, prev, fc, norm, cmp, lo, hi, mg, Y, X, m0 } = cd;
    const bands = [], refs = [], polys = [], bars = [], lines = [], dots = [], keys = [];
    if (norm) { bands.push({ y: Y(100), h: Y(0) - Y(100), fill: 'var(--ok-faint)' }); refs.push({ y: Y(100), stroke: 'var(--crit-text)', dash: '6 4' }, { y: Y(0), stroke: 'var(--muted)', dash: '3 4' }); keys.push({ t: '100 % = límite del rango normal', bg: 'transparent', h: '0', bt: '2px dashed var(--crit-text)' }); }
    else if (!cmp) {
      if (m0.cum) { bands.push({ y: Y(75), h: Y(0) - Y(75), fill: 'var(--ok-faint)' }, { y: Y(100), h: Y(75) - Y(100), fill: 'var(--warn-faint)' }); }
      else {
        bands.push({ y: Y(hi), h: Y(lo) - Y(hi), fill: 'var(--ok-faint)' }, { y: Y(lo), h: Y(lo - mg) - Y(lo), fill: 'var(--warn-faint)' });
        if (!m0.loss) bands.push({ y: Y(hi + mg), h: Y(hi) - Y(hi + mg), fill: 'var(--warn-faint)' });
      }
      if (!m0.cum) refs.push({ y: Y(m0.base), stroke: 'var(--muted)', dash: '5 4' });
      keys.push({ t: 'Rango normal', bg: 'var(--ok-soft)', h: '10px', bt: '0' }, { t: 'Atención', bg: 'var(--warn-soft)', h: '10px', bt: '0' });
      if (!m0.cum) keys.push({ t: 'Tu base', bg: 'transparent', h: '0', bt: '2px dashed var(--muted)' });
    }
    if (prev && view !== 'heat') { lines.push({ pts: prev.v.map((v, j) => X(j) + ',' + Y(v)).join(' '), stroke: 'var(--ch-prev)', w: 2, dash: '0' }); keys.push({ t: 'Periodo anterior', bg: 'var(--ch-prev)', h: '3px', bt: '0' }); }
    if (view === 'bars') {
      const k = ser.length, slot = 720 / N, bw = slot * 0.78 / k;
      ser.forEach((s, si) => s.v.forEach((v, j) => {
        const base = norm ? Y(0) : m0.loss ? Y(0) : H - 8, y = Y(v), top = Math.min(base, y), hh = Math.max(1, Math.abs(base - y));
        const sk = status(s.m, s.raw[j]), last = j === n - 1;
        bars.push({ x: (j * slot + slot * 0.11 + si * bw).toFixed(1), y: top, w: Math.max(0.8, bw - 0.6).toFixed(1), h: hh, fill: cmp ? s.color : last ? ST[sk].c : 'var(--ch-bar)' });
      }));
      if (!cmp) keys.push({ t: 'Último día con color de su estado', bg: ST[status(m0, ser[0].raw[n - 1])].c, h: '10px', bt: '0' });
    } else if (view !== 'heat') {
      ser.forEach((s, si) => {
        lines.push({ pts: s.v.map((v, j) => X(j) + ',' + Y(v)).join(' '), stroke: s.color, w: 2.5, dash: '0' });
        if (view === 'lines' && n <= 60) {
          if (cmp) dots.push({ d: s.v.map((v, j) => `M${X(j)} ${Y(v)} l0 0`).join(' '), stroke: s.color, w: 6 });
          else ['ok', 'warn', 'crit'].forEach(sk => { const d = s.v.map((v, j) => status(s.m, s.raw[j]) === sk ? `M${X(j)} ${Y(v)} l0 0` : '').join(' ').trim(); if (d) dots.push({ d, stroke: ST[sk].c, w: 8 }); });
        }
        if (h) {
          const f = fc[si], up = f.map((q, j) => X(n - 1 + j) + ',' + Y(q.y + q.e)), dn = f.map((q, j) => X(n - 1 + j) + ',' + Y(q.y - q.e)).reverse();
          polys.push({ d: 'M' + up.join(' L') + ' L' + dn.join(' L') + ' Z', fill: cmp ? 'var(--primary-faint)' : 'var(--primary-soft)' });
          lines.push({ pts: f.map((q, j) => X(n - 1 + j) + ',' + Y(q.y)).join(' '), stroke: s.color, w: 2.5, dash: '6 5' });
        }
      });
      if (h) keys.push({ t: `Proyección ${h} ${r.hourly ? 'h' : 'días'}`, bg: 'transparent', h: '0', bt: '2px dashed var(--primary)' }, { t: 'Incertidumbre', bg: 'var(--ch-incert)', h: '10px', bt: '0' });
    }
    const ticks = 4, yLabels = Array.from({ length: ticks + 1 }, (_, i) => { const v = cd.yMin + (cd.yMax - cd.yMin) * (i / ticks); return { top: (Y(v) / H * 100).toFixed(1) + '%', t: norm ? Math.round(v) + '%' : fmt(m0, v) }; });
    const paso = st.group === 'semana' && n > 1 ? xs[1] - xs[0] : 1;
    const lastX = r.hourly ? 23 + h : xs[n - 1] + h * (!r.hourly ? paso : 1);
    const xAt = j => j < n ? xs[j] : (r.hourly ? 23 + (j - n + 1) : xs[n - 1] + (j - n + 1) * paso);
    const tk = st.wEff < 900 ? [0, 0.5, 1] : [0, 0.33, 0.66, 1], seen = new Set();
    const xLabels = tk.map(f => Math.round(f * (N - 1))).filter(j => !seen.has(j) && seen.add(j)).map((j, i, arr) => ({ left: (X(j) / 720 * 100).toFixed(1) + '%', shift: i === 0 ? '0' : i === arr.length - 1 ? '-100%' : '-50%', t: r.hourly ? pad2(xAt(j) % 24) + ':00' : 'D' + xAt(j) }));
    return { bands, refs, polys, bars, lines, dots, keys, yLabels, xLabels, xAt, lastX };
  }

  // ---------- Cadenas SVG ----------
  const nse = 'vector-effect="non-scaling-stroke"';
  function svgPrincipal(sp, H, hx) {
    let s = '';
    sp.bands.forEach(b => { s += `<rect x="0" width="720" y="${esc(b.y)}" height="${esc(b.h)}" style="fill:${esc(b.fill)}"></rect>`; });
    sp.refs.forEach(r => { s += `<line x1="0" x2="720" y1="${esc(r.y)}" y2="${esc(r.y)}" style="stroke:${esc(r.stroke)}" stroke-width="1" stroke-dasharray="${esc(r.dash)}" ${nse}></line>`; });
    sp.polys.forEach(q => { s += `<path d="${esc(q.d)}" style="fill:${esc(q.fill)}" stroke="none"></path>`; });
    sp.bars.forEach(b => { s += `<rect x="${esc(b.x)}" y="${esc(b.y)}" width="${esc(b.w)}" height="${esc(b.h)}" style="fill:${esc(b.fill)}" rx="1"></rect>`; });
    sp.lines.forEach(l => { s += `<polyline points="${esc(l.pts)}" style="fill:none;stroke:${esc(l.stroke)}" stroke-width="${esc(l.w)}" stroke-dasharray="${esc(l.dash)}" stroke-linejoin="round" ${nse}></polyline>`; });
    sp.dots.forEach(d => { s += `<path d="${esc(d.d)}" style="fill:none;stroke:${esc(d.stroke)}" stroke-width="${esc(d.w)}" stroke-linecap="round" ${nse}></path>`; });
    s += `<line x1="${esc(hx)}" x2="${esc(hx)}" y1="0" y2="${esc(H)}" style="stroke:var(--primary-line)" stroke-width="1" ${nse}></line>`;
    return s;
  }
  const svgCarril = (bandY, bandH, pts, color) =>
    `<rect x="0" width="720" y="${esc(bandY)}" height="${esc(bandH)}" style="fill:var(--ok-faint)"></rect><polyline points="${esc(pts)}" style="fill:none;stroke:${esc(color)}" stroke-width="2" ${nse}></polyline>`;
  const svgMini = (bandY, bandH, pts, color) =>
    `<rect x="0" width="200" y="${esc(bandY)}" height="${esc(bandH)}" style="fill:var(--ok-faint)"></rect><polyline points="${esc(pts)}" style="fill:none;stroke:${esc(color)}" stroke-width="2" ${nse}></polyline>`;

  // ---------- build ----------
  function build(st, acciones, ctx) {
    const ac = acciones || {};
    const set = patch => ac.set && ac.set(patch), fade = patch => ac.fade && ac.fade(patch), setView = v => ac.setView && ac.setView(v);
    const w = num(ctx && ctx.w, num(st.w, 1280)), reduce = !!(ctx && ctx.reduce);
    const stx = { ...st, wEff: w };
    const narrow = w < 900, r = range(st), H = narrow ? 220 : 280;
    const cmp = st.mode === 'cmp', mini = st.mode === 'mini', m = actual(st), view = st.view;
    const loading = st.estado === 'carga', error = st.estado === 'error', empty = st.estado === 'vacio' && !loading && !error, ok = !loading && !error && !empty;
    const btn = (on, extra = {}) => ({ checked: on ? 'true' : 'false', bg: on ? 'var(--primary)' : 'var(--surface)', fg: on ? 'var(--on-primary)' : 'var(--text-2)', border: on ? 'var(--primary)' : 'var(--border)', ...extra });
    const seg = on => ({ checked: on ? 'true' : 'false', bg: on ? 'var(--surface)' : 'transparent', fg: on ? 'var(--primary)' : 'var(--muted-3)' });
    const cd = chartData(st, r, H);
    const sp = cd.n ? svgParts(stx, cd, r, H) : { bands: [], refs: [], polys: [], bars: [], lines: [], dots: [], keys: [], yLabels: [], xLabels: [], xAt: () => 0, lastX: 0 };
    const q = String(st.query || '').trim().toLowerCase();
    const vis = MK.filter(x => (!q || x.label.toLowerCase().includes(q)) && (st.filter === 'all' || x.estado === st.filter));
    const toggleCmp = id => fade({ cmp: st.cmp.includes(id) ? st.cmp.filter(x => x !== id) : [...st.cmp, id], hidden: st.hidden.filter(x => x !== id) });
    const hv = st.hover != null && cd.n ? Math.max(0, Math.min(cd.N - 1, st.hover)) : null;
    const idxFromEvent = (clientX, el) => { const b = el.getBoundingClientRect(), f = (clientX - b.left) / (b.width || 1); return cd.N <= 1 ? 0 : Math.round(view === 'bars' ? f * cd.N - 0.5 : f * (cd.N - 1)); };
    const tipRows = hv == null ? [] : cd.ser.map(s => {
      const isF = hv >= cd.n, raw = isF ? null : s.raw[hv], fv = isF ? cd.fc[cd.ser.indexOf(s)][hv - cd.n + 1].y : null;
      const val = isF ? fv : raw, sk = isF ? null : status(s.m, raw), pv = !isF && hv > 0 ? s.raw[hv - 1] : null, d = pv == null ? 0 : raw - pv;
      return { label: s.m.label + (isF ? ' · estimado' : ''), color: s.color, v: cd.norm && !isF ? Math.round(s.v[hv]) + '%' : cd.norm ? Math.round(fv) + '%' : fmt(s.m, val), unit: cd.norm ? 'del rango' : s.m.unit,
        st: sk ? ST[sk].l : 'ESTIMACIÓN', stIc: sk ? ST[sk].ic : 'online_prediction', text: sk ? ST[sk].t : 'var(--primary)',
        trIc: pv == null ? 'remove' : Math.abs(d) < (s.m.hi - s.m.lo) * 0.01 ? 'trending_flat' : d > 0 ? 'trending_up' : 'trending_down', tr: pv == null ? '' : (d >= 0 ? '+' : '−') + Math.abs(d).toFixed(s.m.dec) };
    }).concat(hv != null && hv < cd.n && cd.prev && cd.prev.v[hv] !== undefined ? [{ label: 'Periodo anterior', color: 'var(--ch-prev)', v: fmt(m, cd.prev.v[hv]), unit: m.unit, st: ST[status(m, cd.prev.v[hv])].l, stIc: ST[status(m, cd.prev.v[hv])].ic, text: 'var(--muted-3)', trIc: 'history', tr: '' }] : []);
    const hx = hv == null ? -10 : cd.X(hv), hpct = hx / 720 * 100;

    // Mapa de calor
    let heat = { cols: 7, minW: '0', colLabels: [], cells: [], legend: [] };
    if (view === 'heat' && cd.n) {
      const bgOf = sk => sk === 'ok' ? 'var(--heat-ok)' : sk === 'warn' ? 'var(--heat-warn)' : 'var(--heat-crit)';
      const cell = (mm, v, label) => { const sk = status(mm, v); return { role: 'gridcell', t: fmt(mm, v), title: `${label}: ${fmt(mm, v)} ${mm.unit} · ${ST[sk].l}`, bg: bgOf(sk), fg: 'var(--text)', align: 'center' }; };
      const rowH = t => ({ role: 'rowheader', t, title: t, bg: 'transparent', fg: 'var(--text-2)', align: 'start' });
      if (cmp) {
        heat.cols = cd.n; heat.minW = (64 + cd.n * 22) + 'px'; heat.colLabels = cd.xs.map((x, j) => j % Math.ceil(cd.n / 10) === 0 ? (r.hourly ? x + 'h' : 'D' + x) : '');
        cd.ser.forEach(s => { heat.cells.push(rowH(s.m.label.split(' ')[0])); s.raw.forEach((v, j) => heat.cells.push(cell(s.m, v, xLabel(r, cd.xs[j])))); });
      } else if (r.hourly) {
        heat.cols = 24; heat.minW = (64 + 24 * 22) + 'px'; heat.colLabels = Array.from({ length: 24 }, (_, i) => i % 3 ? '' : pad2(i));
        heat.cells.push(rowH('Hoy')); cd.ser[0].raw.forEach((v, j) => heat.cells.push(cell(m, v, pad2(j) + ':00')));
      } else {
        heat.cols = 7; heat.colLabels = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
        const first = cd.xs[0], dow = (dayDate(first).getUTCDay() + 6) % 7;
        const all = [...Array(dow).fill(null), ...cd.xs.map((d, j) => ({ d, v: cd.ser[0].raw[j] }))];
        for (let i = 0; i < all.length; i += 7) {
          const wk = all.slice(i, i + 7); heat.cells.push(rowH('Sem ' + (Math.floor((first - dow + i - 1) / 7) + 1)));
          for (let k = 0; k < 7; k++) { const e = wk[k]; heat.cells.push(e ? cell(m, e.v, 'D' + e.d + ' · ' + fdate(e.d)) : { role: 'gridcell', t: '', title: 'Sin dato', bg: 'var(--bg)', fg: 'transparent', align: 'center' }); }
        }
      }
      heat.legend = ['ok', 'warn', 'crit'].map(k => ({ t: ST[k].l[0] + ST[k].l.slice(1).toLowerCase(), ic: ST[k].ic, text: ST[k].t, bg: bgOf(k) }));
    }

    const sv = cd.n ? cd.ser[0].raw : [], avg = sv.length ? sv.reduce((a, x) => a + x, 0) / sv.length : 0, diff = sv.length ? sv[sv.length - 1] - sv[0] : 0;
    const flat = Math.abs(diff) < (m.hi - m.lo) * 0.03;
    const sk0 = m.estado;
    const regUrl = '/registrar?indicador=' + encodeURIComponent(m.id);
    const lanes = cd.ser.map(s => {
      const lo2 = s.m.cum ? 0 : s.m.lo, hi2 = s.m.cum ? 75 : s.m.hi, mn = Math.min(...s.raw, lo2), mx = Math.max(...s.raw, hi2), Yl = v => +(60 - (v - mn) / (mx - mn || 1) * 56).toFixed(1), sk = s.m.estado;
      const bandY = Yl(hi2), bandH = Math.max(0, Yl(lo2) - Yl(hi2)), pts = s.raw.map((v, j) => (cd.n > 1 ? j * 720 / (cd.n - 1) : 360).toFixed(1) + ',' + Yl(v)).join(' ');
      return { label: s.m.label, color: s.color, value: s.m.disp, unit: s.m.unit, text: ST[sk].t, aria: s.m.label + ', ' + r.text, bandY, bandH, pts, svg: svgCarril(bandY, bandH, pts, s.color) };
    });
    const minis = vis.map(x => {
      const v = daily(x).slice(-30), lo2 = x.cum ? 0 : x.lo, hi2 = x.cum ? 75 : x.hi, mn = Math.min(...v, lo2), mx = Math.max(...v, hi2), Yy = y => +(38 - (y - mn) / (mx - mn || 1) * 36).toFixed(1), sk = x.estado;
      const bandY = Yy(hi2), bandH = Math.max(0, Yy(lo2) - Yy(hi2)), pts = v.map((y, j) => (j * 200 / Math.max(1, v.length - 1)).toFixed(1) + ',' + Yy(y)).join(' ');
      return { id: x.id, label: x.label, ic: x.ic, value: x.disp, unit: x.unit, st: ST[sk].l, stIc: ST[sk].ic, soft: ST[sk].soft, text: ST[sk].t, color: ST[sk].c, aria: `${x.label}: ${x.disp} ${x.unit}, ${ST[sk].l}. Abrir detalle`,
        bandY, bandH, pts, svg: svgMini(bandY, bandH, pts, ST[sk].c), onOpen: () => fade({ id: x.id, mode: 'ind' }) };
    });

    return {
      narrow, pagePad: narrow ? '12px' : '16px 20px',
      showSidePanel: !narrow || !!st.drawer, drawerOpen: narrow && !!st.drawer,
      openDrawer: () => set({ drawer: true }), closeDrawer: () => set({ drawer: false }),
      pm: {
        role: narrow ? 'dialog' : 'region', modal: narrow ? 'true' : 'false', pos: narrow ? 'fixed' : 'sticky', top: narrow ? 'auto' : '12px', side: narrow ? '0' : 'auto', bottom: narrow ? '0' : 'auto',
        maxH: narrow ? '82vh' : 'calc(100vh - 24px)', radius: narrow ? '16px 16px 0 0' : '14px', shadow: narrow ? '0 -12px 40px ' + 'color-mix(in srgb, var(--shadow-c) 20%, transparent)' : 'none',
        count: cmp ? st.cmp.length + ' seleccionados' : mini ? MK.length : m.label,
        modes: [['ind', 'Individual', 'looks_one'], ['cmp', 'Comparar', 'stacked_line_chart'], ['mini', 'Miniaturas', 'grid_view']].map(([id, label, ic]) => ({ label, ic, ...seg(st.mode === id), onClick: () => fade({ mode: id }) })),
        query: st.query, onQuery: e => set({ query: e && e.target ? e.target.value : '' }),
        filters: [['all', 'Todos', 'var(--muted)'], ['warn', 'Solo atención', ST.warn.c], ['crit', 'Solo graves', ST.crit.c]].map(([id, label, dot]) => ({ label, dot, ...btn(st.filter === id), onClick: () => set({ filter: id }) })),
        none: !vis.length,
        groups: SYS.map(([name, ic], si) => {
          const items = vis.filter(x => x.sys === si); if (!items.length) return null;
          const allOn = items.every(x => st.cmp.includes(x.id));
          return { name, ic, showAll: cmp, allLabel: allOn ? 'Quitar sistema' : 'Todo el sistema',
            onAll: () => fade({ cmp: allOn ? st.cmp.filter(id => !items.some(x => x.id === id)) : [...new Set([...st.cmp, ...items.map(x => x.id)])] }),
            items: items.map(x => { const on = cmp ? st.cmp.includes(x.id) : x.id === m.id, sk = x.estado;
              return { id: x.id, label: x.label, value: x.disp, unit: x.unit, st: ST[sk].l, stIc: ST[sk].ic, soft: ST[sk].soft, text: ST[sk].t,
                role: cmp ? 'checkbox' : 'radio', checked: on ? 'true' : 'false', bg: on && !mini ? 'var(--primary-soft)' : 'transparent',
                ctl: mini ? 'open_in_new' : cmp ? (on ? 'check_box' : 'check_box_outline_blank') : (on ? 'radio_button_checked' : 'radio_button_unchecked'), ctlColor: on ? 'var(--primary)' : 'var(--muted)',
                onToggle: () => cmp ? toggleCmp(x.id) : fade({ id: x.id, mode: 'ind', drawer: false }) }; }) };
        }).filter(Boolean),
      },
      ff: {
        presets: PRESETS.map(([id, label]) => ({ label, ...btn(st.pre === id), onClick: () => fade({ pre: id, group: id === '24h' ? 'hora' : st.group === 'hora' ? 'dia' : st.group }) })),
        text: r.text, textIc: r.bad ? 'error' : 'date_range', textColor: r.bad ? ST.crit.t : 'var(--text)',
        isCustom: st.pre === 'custom', from: st.from, to: st.to, min: iso(1), max: iso(DAYS), invalid: r.bad ? 'true' : 'false', inBorder: r.bad ? ST.crit.c : 'var(--border-strong)',
        onFrom: e => fade({ from: e && e.target ? e.target.value : '' }), onTo: e => fade({ to: e && e.target ? e.target.value : '' }),
        compare: !!st.compare && !cmp, compareDisabled: cmp || !!r.hourly, onCompare: e => fade({ compare: !!(e && e.target && e.target.checked) }),
        groups: [['hora', 'Hora'], ['dia', 'Día'], ['semana', 'Semana']].map(([id, label]) => { const dis = r.hourly ? id !== 'hora' : id === 'hora' || (id === 'semana' && r.b - r.a < 13); const on = r.hourly ? id === 'hora' : st.group === id; return { label, ...btn(on), disabled: dis, op: dis ? '0.45' : '1', onClick: () => { if (!dis) fade({ group: id }); } }; }),
        showHorizon: view === 'forecast' && !mini,
        horizons: [3, 7, 14].map(hz => ({ label: hz + (r.hourly ? ' h' : ' d'), ...btn(st.horizon === hz), onClick: () => fade({ horizon: hz }) })),
      },
      sel: { pad: narrow ? '0' : '10px', labelDisplay: narrow ? 'none' : 'inline', views: VIEWS.map(([id, label, ic, key]) => ({ label, ic, key, aria: label + ' (tecla ' + key + ')', ...seg(view === id), onClick: () => setView(id) })) },
      ch: {
        title: mini ? 'Todos los marcadores' : cmp ? `Comparación · ${cd.ser.length} marcadores` : `${m.label} · ${m.unit}`,
        showSelector: !mini, aria: `${mini ? 'Miniaturas' : cmp ? 'Comparación' : m.label}, ${r.text}, vista ${VIEWS.find(v => v[0] === view)[1]}`,
        toggleTable: () => set({ table: !st.table }), tablePressed: st.table ? 'true' : 'false', tableBg: st.table ? 'var(--hover)' : 'var(--surface)', tableIc: st.table ? 'monitoring' : 'table', tableLabel: st.table ? 'Ver gráfico' : 'Ver como tabla',
        hasLegend: cmp && !mini, norm: !!st.norm, onNorm: e => fade({ norm: !!(e && e.target && e.target.checked) }),
        legend: st.cmp.filter(id => MKI[id]).map(id => { const off = st.hidden.includes(id); return { label: MKI[id].label, color: SERIES_COLORS[st.cmp.indexOf(id) % SERIES_COLORS.length], pressed: off ? 'false' : 'true', op: off ? '0.5' : '1', bg: off ? 'var(--surface)' : 'var(--bg)', border: 'var(--border)', ic: off ? 'visibility_off' : 'visibility', onClick: () => fade({ hidden: off ? st.hidden.filter(x => x !== id) : [...st.hidden, id] }) }; }),
        forecastNote: view === 'forecast' && !mini, loading, error, empty: empty && !mini,
        retry: () => { set({ estado: 'carga' }); setTimeout(() => set({ estado: 'ok' }), 900); },
        showSvg: ok && !st.table && !mini && view !== 'heat' && !(cmp && !st.norm) && cd.n > 0,
        showLanes: ok && !st.table && cmp && !st.norm && view !== 'heat' && !mini,
        showHeat: ok && !st.table && view === 'heat' && !mini && cd.n > 0, showTable: ok && st.table && !mini, showMini: mini && !loading && !error,
        lossNote: !cmp && !!m.loss, h: H, axisW: narrow ? '40px' : '52px', minW: narrow ? '640px' : '0', op: String(st.op), trans: reduce ? 'none' : 'opacity 200ms ease',
        ...sp, hoverX: hx,
        svg: svgPrincipal(sp, H, hx),
        boxRef: () => {},
        onMove: e => set({ hover: idxFromEvent(e.clientX, e.currentTarget) }), onLeave: () => set({ hover: null }),
        onTouch: e => { if (e.touches && e.touches[0]) set({ hover: idxFromEvent(e.touches[0].clientX, e.currentTarget) }); },
        onKey: e => {
          if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); const c = st.hover == null ? cd.n - 1 : st.hover; set({ hover: Math.max(0, Math.min(cd.N - 1, c + (e.key === 'ArrowRight' ? 1 : -1))) }); }
          else if (e.key === 'Escape') set({ hover: null });
        },
        lanes,
      },
      tip: { show: hv != null && !st.table, left: hpct.toFixed(1) + '%', shift: hpct > 55 ? 'calc(-100% - 10px)' : '10px', date: hv == null ? '' : (hv >= cd.n ? 'Estimación · ' : '') + xLabel(r, sp.xAt(hv)), rows: tipRows },
      heat,
      tbl: { heads: cd.n ? cd.ser.map(s => s.m.label + ' (' + s.m.unit + ')') : [], rows: cd.n ? cd.xs.map((x, j) => ({ date: xLabel(r, x), cells: cd.ser.map(s => { const sk = status(s.m, s.raw[j]); return { v: fmt(s.m, s.raw[j]), st: ST[sk].l, text: ST[sk].t }; }) })).reverse() : [] },
      minis,
      sum: {
        show: !cmp && !mini && ok && !!m.id, label: m.label.toUpperCase(), value: m.disp, unit: m.unit, st: ST[sk0].l, stIc: ST[sk0].ic, soft: ST[sk0].soft, text: ST[sk0].t, color: ST[sk0].c,
        trIc: flat ? 'trending_flat' : diff > 0 ? 'trending_up' : 'trending_down', tr: flat ? 'Estable' : diff > 0 ? 'Subiendo' : 'Bajando',
        range: m.cum ? 'límite de misión 100 mSv' : `${fmt(m, m.lo)} – ${fmt(m, m.hi)} ${m.unit}`,
        stats: [['MÍN', sv.length ? Math.min(...sv) : 0], ['MÁX', sv.length ? Math.max(...sv) : 0], ['PROMEDIO', avg]].map(([k, v]) => ({ k, v: sv.length ? fmt(m, v) : '—', u: m.unit })),
        // Desviación frente a la línea base pre-vuelo (solo si el servidor la envió).
        desv: m.desv ? `${m.desv.pct > 0 ? '+' : m.desv.pct < 0 ? '−' : ''}${Math.abs(m.desv.pct).toFixed(1)} % vs base pre-vuelo (${fmt(m, m.desv.base)} ${m.unit})` : '',
        sospechoso: m.sospechoso || '',
        rec: m.rec,
        regUrl,
        onReg: e => { if (e && e.preventDefault) e.preventDefault(); if (typeof window !== 'undefined' && window.location && typeof window.location.assign === 'function') window.location.assign(regUrl); },
        onGuide: e => { if (e && e.preventDefault) e.preventDefault(); },
      },
    };
  }

  return { estadoInicial, build, constantes: { PRESETS, VIEWS, dias: DAYS } };
}

export default crearModelo;
